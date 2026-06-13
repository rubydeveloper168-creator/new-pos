<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateQuoteItems extends Command
{
    protected $signature = 'migrate:quote-items';
    protected $description = 'Migrate quote items from old POS to transaction_sell_lines';

    public function handle(): int
    {
        $this->info('========================================');
        $this->info('Migrating Quote Items');
        $this->info('========================================');

        try {
            // Get all quote mappings
            $mappings = DB::table('migration_mappings')
                ->where('old_table', 'quotes')
                ->get();

            $this->info("Found {$mappings->count()} quotes to process.");

            $bar = $this->output->createProgressBar($mappings->count());
            $bar->start();

            $totalItems = 0;
            $skipped = 0;

            foreach ($mappings as $mapping) {
                // Check if items already exist
                $existingLines = DB::table('transaction_sell_lines')
                    ->where('transaction_id', $mapping->new_id)
                    ->count();

                if ($existingLines > 0) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                // Get old quote items
                $oldItems = DB::connection('old_pos')
                    ->table('quote_items')
                    ->where('quote_id', $mapping->old_id)
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
                        'transaction_id' => $mapping->new_id,
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

                    $totalItems++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();

            $this->info("✓ Migrated {$totalItems} quote items.");
            if ($skipped > 0) {
                $this->warn("Skipped {$skipped} quotes that already had items.");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Migration failed: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
