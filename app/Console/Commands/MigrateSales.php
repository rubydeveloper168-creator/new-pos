<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateSales extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'migrate:sales 
                            {--dry-run : Run without making changes}
                            {--batch= : Batch size for processing (default: 500)}';

    /**
     * The console command description.
     */
    protected $description = 'Migrate sales transactions from old POS to new POS (uses raw SQL for performance)';

    protected $businessId = 1;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $batchSize = (int) ($this->option('batch') ?? 500);
        
        $this->info('========================================');
        $this->info('Migrating Sales Transactions (Raw SQL)');
        $this->info('========================================');
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }
        
        try {
            // Count total sales
            $totalSales = DB::connection('old_pos')
                ->table('sales')
                ->count();
                
            $this->info("Found {$totalSales} sales to migrate.");
            
            if ($totalSales == 0) {
                $this->info('No sales to migrate.');
                return Command::SUCCESS;
            }
            
            $bar = $this->output->createProgressBar($totalSales);
            $bar->start();
            
            $migrated = 0;
            $skipped = 0;
            $offset = 0;
            
            // Get default location
            $defaultLocation = DB::table('business_locations')
                ->where('business_id', $this->businessId)
                ->first();
                
            $locationId = $defaultLocation->id ?? 1;
            
            while ($offset < $totalSales) {
                $sales = DB::connection('old_pos')
                    ->table('sales')
                    ->orderBy('id')
                    ->offset($offset)
                    ->limit($batchSize)
                    ->get();
                    
                if ($sales->isEmpty()) break;
                
                DB::beginTransaction();
                
                foreach ($sales as $oldSale) {
                    // Check if already migrated
                    $existing = DB::table('migration_mappings')
                        ->where('old_table', 'sales')
                        ->where('old_id', $oldSale->id)
                        ->first();
                        
                    if ($existing) {
                        $skipped++;
                        $bar->advance();
                        continue;
                    }
                    
                    // Get customer mapping
                    $customerMapping = null;
                    if ($oldSale->customer_id) {
                        $customerMapping = DB::table('migration_mappings')
                            ->where('old_table', 'companies')
                            ->where('old_id', $oldSale->customer_id)
                            ->first();
                    }
                    
                    // Get location mapping
                    $locMapping = null;
                    if (isset($oldSale->warehouse_id) && $oldSale->warehouse_id) {
                        $locMapping = DB::table('migration_mappings')
                            ->where('old_table', 'warehouses')
                            ->where('old_id', $oldSale->warehouse_id)
                            ->first();
                    }
                    
                    // Determine payment status
                    $paymentStatus = 'due';
                    $paid = $oldSale->paid ?? 0;
                    $total = $oldSale->grand_total ?? 0;
                    if ($paid >= $total && $total > 0) {
                        $paymentStatus = 'paid';
                    } elseif ($paid > 0) {
                        $paymentStatus = 'partial';
                    }
                    
                    if (!$dryRun) {
                        // Insert transaction using raw SQL for performance
                        // Only include core columns that exist in the transactions table
                        $transactionId = DB::table('transactions')->insertGetId([
                            'business_id' => $this->businessId,
                            'location_id' => $locMapping->new_id ?? $locationId,
                            'type' => 'sell',
                            'sub_type' => null,
                            'status' => $oldSale->sale_status == 'completed' ? 'final' : ($oldSale->sale_status ?? 'final'),
                            'is_quotation' => 0,
                            'payment_status' => $paymentStatus,
                            'adjustment_type' => null,
                            'contact_id' => $customerMapping->new_id ?? 1,
                            'customer_group_id' => null,
                            'invoice_no' => $oldSale->reference_no,
                            'ref_no' => $oldSale->reference_no,
                            'transaction_date' => $oldSale->date,
                            'total_before_tax' => $oldSale->total ?? 0,
                            'tax_id' => ($oldSale->total_tax > 0 || (isset($oldSale->grand_total, $oldSale->total) && $oldSale->grand_total > $oldSale->total)) ? 4 : null,
                            'tax_amount' => $oldSale->total_tax > 0 ? $oldSale->total_tax : (($oldSale->grand_total ?? 0) - ($oldSale->total ?? 0)),
                            'discount_type' => 'fixed',
                            'discount_amount' => $oldSale->order_discount ?? 0,
                            'shipping_charges' => $oldSale->shipping ?? 0,
                            'additional_notes' => $oldSale->note ?? null,
                            'staff_note' => $oldSale->staff_note ?? null,
                            'round_off_amount' => $oldSale->rounding ?? 0,
                            'final_total' => $oldSale->grand_total ?? 0,
                            'document' => $oldSale->attachment ?? null,
                            'is_direct_sale' => 0,
                            'is_suspend' => $oldSale->sale_status == 'pending' ? 1 : 0,
                            'exchange_rate' => 1,
                            'created_by' => 1,
                            'rp_earned' => 0,
                            'rp_redeemed' => 0,
                            'rp_redeemed_amount' => 0,
                            'is_recurring' => 0,
                            'recur_repetitions' => 0,
                            'created_at' => $oldSale->date,
                            'updated_at' => now(),
                        ]);
                        
                        // Record mapping
                        DB::table('migration_mappings')->insert([
                            'old_table' => 'sales',
                            'old_id' => $oldSale->id,
                            'new_table' => 'transactions',
                            'new_id' => $transactionId,
                            'migrated_at' => now(),
                            'notes' => 'type: sell',
                        ]);
                        
                        // Migrate sale items
                        $this->migrateSaleItems($oldSale->id, $transactionId);
                    }
                    
                    $migrated++;
                    $bar->advance();
                }
                
                if (!$dryRun) {
                    DB::commit();
                } else {
                    DB::rollBack();
                }
                
                $offset += $batchSize;
            }
            
            $bar->finish();
            $this->newLine();
            
            if (!$dryRun) {
                $this->info("✓ Migrated {$migrated} sales transactions.");
            } else {
                $this->info("Would migrate {$migrated} sales transactions.");
            }
            
            if ($skipped > 0) {
                $this->warn("Skipped {$skipped} already migrated sales.");
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Migration failed: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
    
    /**
     * Migrate sale items for a specific sale.
     */
    protected function migrateSaleItems(int $oldSaleId, int $newTransactionId): void
    {
        $oldItems = DB::connection('old_pos')
            ->table('sale_items')
            ->where('sale_id', $oldSaleId)
            ->get();
            
        foreach ($oldItems as $oldItem) {
            // Get product mapping
            $productMapping = DB::table('migration_mappings')
                ->where('old_table', 'products')
                ->where('old_id', $oldItem->product_id)
                ->first();
                
            if (!$productMapping) continue;
            
            // Get variation mapping
            $variationMapping = DB::table('migration_mappings')
                ->where('old_table', 'products_variation')
                ->where('old_id', $oldItem->product_id)
                ->first();
                
            if (!$variationMapping) continue;
            
            DB::table('transaction_sell_lines')->insert([
                'transaction_id' => $newTransactionId,
                'product_id' => $productMapping->new_id,
                'variation_id' => $variationMapping->new_id,
                'quantity' => $oldItem->quantity ?? 0,
                'mfg_waste_percent' => 0,
                'quantity_returned' => 0,
                'unit_price_before_discount' => $oldItem->unit_price ?? 0,
                'unit_price' => $oldItem->unit_price ?? 0,
                'line_discount_type' => $oldItem->discount ? 'fixed' : null,
                'line_discount_amount' => $oldItem->discount ?? 0,
                'unit_price_inc_tax' => $oldItem->net_unit_price > 0 ? $oldItem->net_unit_price : (($oldItem->unit_price ?? 0) * 1.07),
                'item_tax' => $oldItem->item_tax > 0 ? $oldItem->item_tax : (($oldItem->unit_price ?? 0) * 0.07),
                'tax_id' => ($oldItem->item_tax > 0 || ($oldItem->net_unit_price > $oldItem->unit_price)) ? 4 : null,
                'discount_id' => null,
                'lot_no_line_id' => null,
                'sell_line_note' => null,
                'res_service_staff_id' => null,
                'res_line_order_status' => null,
                'woocommerce_line_items_id' => null,
                'parent_sell_line_id' => null,
                'children_type' => null,
                'sub_unit_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
