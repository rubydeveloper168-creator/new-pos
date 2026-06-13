<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigratePurchases extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'migrate:purchases 
                            {--dry-run : Run without making changes}
                            {--batch= : Batch size for processing (default: 500)}';

    /**
     * The console command description.
     */
    protected $description = 'Migrate purchase transactions from old POS to new POS (uses raw SQL for performance)';

    protected $businessId = 1;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $batchSize = (int) ($this->option('batch') ?? 500);
        
        $this->info('========================================');
        $this->info('Migrating Purchase Transactions (Raw SQL)');
        $this->info('========================================');
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }
        
        try {
            // Count total purchases
            $totalPurchases = DB::connection('old_pos')
                ->table('purchases')
                ->count();
                
            $this->info("Found {$totalPurchases} purchases to migrate.");
            
            if ($totalPurchases == 0) {
                $this->info('No purchases to migrate.');
                return Command::SUCCESS;
            }
            
            $bar = $this->output->createProgressBar($totalPurchases);
            $bar->start();
            
            $migrated = 0;
            $skipped = 0;
            $offset = 0;
            
            // Get default location
            $defaultLocation = DB::table('business_locations')
                ->where('business_id', $this->businessId)
                ->first();
                
            $locationId = $defaultLocation->id ?? 1;
            
            while ($offset < $totalPurchases) {
                $purchases = DB::connection('old_pos')
                    ->table('purchases')
                    ->orderBy('id')
                    ->offset($offset)
                    ->limit($batchSize)
                    ->get();
                    
                if ($purchases->isEmpty()) break;
                
                DB::beginTransaction();
                
                foreach ($purchases as $oldPurchase) {
                    // Check if already migrated
                    $existing = DB::table('migration_mappings')
                        ->where('old_table', 'purchases')
                        ->where('old_id', $oldPurchase->id)
                        ->first();
                        
                    if ($existing) {
                        $skipped++;
                        $bar->advance();
                        continue;
                    }
                    
                    // Get supplier mapping
                    $supplierMapping = null;
                    if ($oldPurchase->supplier_id) {
                        $supplierMapping = DB::table('migration_mappings')
                            ->where('old_table', 'companies')
                            ->where('old_id', $oldPurchase->supplier_id)
                            ->first();
                    }
                    
                    // Get location mapping
                    $locMapping = null;
                    if (isset($oldPurchase->warehouse_id) && $oldPurchase->warehouse_id) {
                        $locMapping = DB::table('migration_mappings')
                            ->where('old_table', 'warehouses')
                            ->where('old_id', $oldPurchase->warehouse_id)
                            ->first();
                    }
                    
                    // Determine payment status
                    $paymentStatus = 'due';
                    $paid = $oldPurchase->paid ?? 0;
                    $total = $oldPurchase->grand_total ?? 0;
                    if ($paid >= $total && $total > 0) {
                        $paymentStatus = 'paid';
                    } elseif ($paid > 0) {
                        $paymentStatus = 'partial';
                    }
                    
                    if (!$dryRun) {
                        // Insert transaction using raw SQL for performance
                        $transactionId = DB::table('transactions')->insertGetId([
                            'business_id' => $this->businessId,
                            'location_id' => $locMapping->new_id ?? $locationId,
                            'type' => 'purchase',
                            'sub_type' => null,
                            'status' => $oldPurchase->status ?? 'received',
                            'is_quotation' => 0,
                            'payment_status' => $paymentStatus,
                            'adjustment_type' => null,
                            'contact_id' => $supplierMapping->new_id ?? 1,
                            'customer_group_id' => null,
                            'invoice_no' => null,
                            'ref_no' => $oldPurchase->reference_no,
                            'transaction_date' => $oldPurchase->date,
                            'total_before_tax' => $oldPurchase->total ?? 0,
                            'tax_id' => null,
                            'tax_amount' => $oldPurchase->total_tax ?? 0,
                            'discount_type' => $oldPurchase->order_discount_id ? 'percentage' : 'fixed',
                            'discount_amount' => $oldPurchase->order_discount ?? 0,
                            'shipping_details' => null,
                            'shipping_charges' => $oldPurchase->shipping ?? 0,
                            'additional_notes' => $oldPurchase->note ?? null,
                            'staff_note' => $oldPurchase->staff_note ?? null,
                            'final_total' => $oldPurchase->grand_total ?? 0,
                            'document' => $oldPurchase->attachment ?? null,
                            'exchange_rate' => 1,
                            'created_by' => 1,
                            'pay_term_number' => $oldPurchase->payment_term ?? null,
                            'pay_term_type' => $oldPurchase->payment_term ? 'days' : null,
                            'created_at' => $oldPurchase->date,
                            'updated_at' => now(),
                        ]);
                        
                        // Record mapping
                        DB::table('migration_mappings')->insert([
                            'old_table' => 'purchases',
                            'old_id' => $oldPurchase->id,
                            'new_table' => 'transactions',
                            'new_id' => $transactionId,
                            'migrated_at' => now(),
                            'notes' => 'type: purchase',
                        ]);
                        
                        // Migrate purchase items
                        $this->migratePurchaseItems($oldPurchase->id, $transactionId);
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
                $this->info("✓ Migrated {$migrated} purchase transactions.");
            } else {
                $this->info("Would migrate {$migrated} purchase transactions.");
            }
            
            if ($skipped > 0) {
                $this->warn("Skipped {$skipped} already migrated purchases.");
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
     * Migrate purchase items for a specific purchase.
     */
    protected function migratePurchaseItems(int $oldPurchaseId, int $newTransactionId): void
    {
        $oldItems = DB::connection('old_pos')
            ->table('purchase_items')
            ->where('purchase_id', $oldPurchaseId)
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
            
            DB::table('purchase_lines')->insert([
                'transaction_id' => $newTransactionId,
                'product_id' => $productMapping->new_id,
                'variation_id' => $variationMapping->new_id,
                'quantity' => $oldItem->quantity ?? 0,
                'pp_without_discount' => $oldItem->net_unit_cost ?? 0,
                'discount_percent' => 0,
                'purchase_price' => $oldItem->unit_cost ?? 0,
                'purchase_price_inc_tax' => $oldItem->net_unit_cost ?? 0,
                'item_tax' => $oldItem->item_tax ?? 0,
                'tax_id' => null,
                'quantity_sold' => 0,
                'quantity_adjusted' => 0,
                'quantity_returned' => 0,
                'mfg_quantity_used' => 0,
                'exp_date' => $oldItem->expiry ?? null,
                'lot_number' => null,
                'sub_unit_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
