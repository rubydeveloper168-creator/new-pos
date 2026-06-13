<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateQuotations extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'migrate:quotations 
                            {--dry-run : Run without making changes}
                            {--batch= : Batch size for processing (default: 500)}';

    /**
     * The console command description.
     */
    protected $description = 'Migrate quotations from old POS to new POS';

    protected $businessId = 1;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $batchSize = (int) ($this->option('batch') ?? 500);
        
        $this->info('========================================');
        $this->info('Migrating Quotations');
        $this->info('========================================');
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }
        
        try {
            // Count total quotes
            $totalQuotes = DB::connection('old_pos')
                ->table('quotes')
                ->count();
                
            $this->info("Found {$totalQuotes} quotations to migrate.");
            
            if ($totalQuotes == 0) {
                $this->info('No quotations to migrate.');
                return Command::SUCCESS;
            }
            
            $bar = $this->output->createProgressBar($totalQuotes);
            $bar->start();
            
            $migrated = 0;
            $skipped = 0;
            $offset = 0;
            
            // Get default location
            $defaultLocation = DB::table('business_locations')
                ->where('business_id', $this->businessId)
                ->first();
                
            $locationId = $defaultLocation->id ?? 1;
            
            while ($offset < $totalQuotes) {
                $quotes = DB::connection('old_pos')
                    ->table('quotes')
                    ->orderBy('id')
                    ->offset($offset)
                    ->limit($batchSize)
                    ->get();
                    
                if ($quotes->isEmpty()) break;
                
                DB::beginTransaction();
                
                foreach ($quotes as $oldQuote) {
                    // Check if already migrated
                    $existing = DB::table('migration_mappings')
                        ->where('old_table', 'quotes')
                        ->where('old_id', $oldQuote->id)
                        ->first();
                        
                    if ($existing) {
                        $skipped++;
                        $bar->advance();
                        continue;
                    }
                    
                    // Get customer mapping
                    $customerMapping = null;
                    if ($oldQuote->customer_id) {
                        $customerMapping = DB::table('migration_mappings')
                            ->where('old_table', 'companies')
                            ->where('old_id', $oldQuote->customer_id)
                            ->first();
                    }
                    
                    // Get location mapping
                    $locMapping = null;
                    if (isset($oldQuote->warehouse_id) && $oldQuote->warehouse_id) {
                        $locMapping = DB::table('migration_mappings')
                            ->where('old_table', 'warehouses')
                            ->where('old_id', $oldQuote->warehouse_id)
                            ->first();
                    }
                    
                    if (!$dryRun) {
                        // Insert transaction as quotation
                        $transactionId = DB::table('transactions')->insertGetId([
                            'business_id' => $this->businessId,
                            'location_id' => $locMapping->new_id ?? $locationId,
                            'type' => 'sell',
                            'sub_type' => null,
                            'status' => 'draft',
                            'sub_status' => 'quotation',
                            'document_type' => 'quotation',
                            'is_quotation' => 1,
                            'payment_status' => 'due',
                            'adjustment_type' => null,
                            'contact_id' => $customerMapping->new_id ?? 1,
                            'customer_group_id' => null,
                            'invoice_no' => $oldQuote->reference_no,
                            'ref_no' => $oldQuote->reference_no,
                            'transaction_date' => $oldQuote->date,
                            'total_before_tax' => $oldQuote->total ?? 0,
                            'tax_id' => null,
                            'tax_amount' => $oldQuote->total_tax ?? 0,
                            'discount_type' => 'fixed',
                            'discount_amount' => $oldQuote->order_discount ?? 0,
                            'shipping_charges' => $oldQuote->shipping ?? 0,
                            'additional_notes' => $oldQuote->note ?? null,
                            'staff_note' => $oldQuote->internal_note ?? null,
                            'round_off_amount' => 0,
                            'final_total' => $oldQuote->grand_total ?? 0,
                            'document' => $oldQuote->attachment ?? null,
                            'is_direct_sale' => 0,
                            'is_suspend' => 0,
                            'exchange_rate' => 1,
                            'created_by' => $oldQuote->created_by ?? 1,
                            'rp_earned' => 0,
                            'rp_redeemed' => 0,
                            'rp_redeemed_amount' => 0,
                            'is_recurring' => 0,
                            'recur_repetitions' => 0,
                            'created_at' => $oldQuote->date,
                            'updated_at' => now(),
                        ]);
                        
                        // Record mapping
                        DB::table('migration_mappings')->insert([
                            'old_table' => 'quotes',
                            'old_id' => $oldQuote->id,
                            'new_table' => 'transactions',
                            'new_id' => $transactionId,
                            'migrated_at' => now(),
                            'notes' => 'type: sell, is_quotation: 1',
                        ]);
                        
                        // Migrate quote items
                        $this->migrateQuoteItems($oldQuote->id, $transactionId);
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
                $this->info("✓ Migrated {$migrated} quotations.");
            } else {
                $this->info("Would migrate {$migrated} quotations.");
            }
            
            if ($skipped > 0) {
                $this->warn("Skipped {$skipped} already migrated quotations.");
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
     * Migrate quote items for a specific quote.
     */
    protected function migrateQuoteItems(int $oldQuoteId, int $newTransactionId): void
    {
        $oldItems = DB::connection('old_pos')
            ->table('quote_items')
            ->where('quote_id', $oldQuoteId)
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
                'unit_price_inc_tax' => $oldItem->net_unit_price ?? 0,
                'item_tax' => $oldItem->item_tax ?? 0,
                'tax_id' => null,
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
