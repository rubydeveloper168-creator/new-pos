<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrateSellLinesSeeder extends Seeder
{
    /**
     * Migrate sell lines (product data) from old CodeIgniter POS to new Laravel POS.
     *
     * This seeder:
     * 1. Maps old sma_sales.reference_no to new transactions.invoice_no
     * 2. Maps old sma_sale_items.product_code to new products.sku
     * 3. Creates transaction_sell_lines records for each product
     */
    public function run()
    {
        $this->command->info('Starting sell lines migration...');
        
        $batchSize = 500;
        $offset = 0;
        $totalMigrated = 0;
        $totalSkipped = 0;
        $errors = [];

        // Build product mapping cache (product_code => [product_id, variation_id])
        $this->command->info('Building product mapping cache...');
        $productMap = $this->buildProductMap();
        $this->command->info('Product map built: ' . count($productMap) . ' products');

        // Build transaction mapping cache (old_sale_id => new_transaction_id)
        $this->command->info('Building transaction mapping cache...');
        $transactionMap = $this->buildTransactionMap();
        $this->command->info('Transaction map built: ' . count($transactionMap) . ' transactions');

        // Get total count
        $totalOldItems = DB::connection('old_pos')
            ->selectOne('SELECT COUNT(*) as cnt FROM sma_sale_items')->cnt;
        $this->command->info("Total old sale items to migrate: {$totalOldItems}");

        // Process in batches
        do {
            $oldItems = DB::connection('old_pos')
                ->select("SELECT si.*, s.reference_no 
                          FROM sma_sale_items si 
                          JOIN sma_sales s ON si.sale_id = s.id 
                          ORDER BY si.id 
                          LIMIT {$batchSize} OFFSET {$offset}");

            if (empty($oldItems)) {
                break;
            }

            $insertBatch = [];

            foreach ($oldItems as $oldItem) {
                // Get new transaction_id from map
                $newTransactionId = $transactionMap[$oldItem->reference_no] ?? null;
                
                if (!$newTransactionId) {
                    $totalSkipped++;
                    continue;
                }

                // Check if already migrated
                $exists = DB::table('transaction_sell_lines')
                    ->where('transaction_id', $newTransactionId)
                    ->where('sell_line_note', 'LIKE', '%OLD_ID:' . $oldItem->id . '%')
                    ->exists();
                
                if ($exists) {
                    $totalSkipped++;
                    continue;
                }

                // Get product mapping
                $productInfo = $productMap[$oldItem->product_code] ?? null;
                
                if (!$productInfo) {
                    // Try to find by product name (fallback)
                    $productInfo = $this->findProductByName($oldItem->product_name);
                }

                if (!$productInfo) {
                    $errors[] = "Product not found: code={$oldItem->product_code}, name={$oldItem->product_name}";
                    $totalSkipped++;
                    continue;
                }

                // Determine discount type
                $discountType = 'fixed';
                $discountAmount = $oldItem->item_discount ?? 0;
                if (!empty($oldItem->discount) && strpos($oldItem->discount, '%') !== false) {
                    $discountType = 'percentage';
                    $discountAmount = floatval(str_replace('%', '', $oldItem->discount));
                }

                // Build sell line record
                $insertBatch[] = [
                    'transaction_id' => $newTransactionId,
                    'product_id' => $productInfo['product_id'],
                    'variation_id' => $productInfo['variation_id'],
                    'quantity' => $oldItem->quantity ?? 1,
                    'quantity_returned' => 0,
                    'unit_price_before_discount' => $oldItem->real_unit_price ?? $oldItem->unit_price ?? 0,
                    'unit_price' => $oldItem->net_unit_price ?? $oldItem->unit_price ?? 0,
                    'line_discount_type' => $discountType,
                    'line_discount_amount' => $discountAmount,
                    'unit_price_inc_tax' => $oldItem->unit_price ?? 0,
                    'item_tax' => $oldItem->item_tax ?? 0,
                    'tax_id' => null, // Could map if needed
                    'sell_line_note' => ($oldItem->comment ?? '') . ' [OLD_ID:' . $oldItem->id . ']',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $totalMigrated++;
            }

            // Insert batch
            if (!empty($insertBatch)) {
                DB::table('transaction_sell_lines')->insert($insertBatch);
            }

            $offset += $batchSize;
            $progress = min(100, round(($offset / $totalOldItems) * 100));
            $this->command->info("Progress: {$progress}% ({$offset}/{$totalOldItems}) - Migrated: {$totalMigrated}, Skipped: {$totalSkipped}");

        } while (count($oldItems) === $batchSize);

        $this->command->info('');
        $this->command->info('=== Migration Complete ===');
        $this->command->info("Total migrated: {$totalMigrated}");
        $this->command->info("Total skipped: {$totalSkipped}");
        
        if (!empty($errors)) {
            $this->command->warn('Errors (first 10):');
            foreach (array_slice($errors, 0, 10) as $error) {
                $this->command->warn("  - {$error}");
            }
        }
    }

    /**
     * Build mapping of product_code => [product_id, variation_id]
     */
    private function buildProductMap(): array
    {
        $map = [];
        
        // Get all products with their variations
        // Note: variation_id foreign key references the 'variations' table
        $products = DB::table('products')
            ->join('variations', 'products.id', '=', 'variations.product_id')
            ->select('products.id as product_id', 'products.sku', 'variations.id as variation_id')
            ->get();

        foreach ($products as $p) {
            if (!empty($p->sku)) {
                $map[$p->sku] = [
                    'product_id' => $p->product_id,
                    'variation_id' => $p->variation_id,
                ];
            }
        }

        return $map;
    }

    /**
     * Build mapping of reference_no => new transaction_id
     */
    private function buildTransactionMap(): array
    {
        $map = [];
        
        $transactions = DB::table('transactions')
            ->whereNotNull('invoice_no')
            ->where('invoice_no', '!=', '')
            ->select('id', 'invoice_no')
            ->get();

        foreach ($transactions as $t) {
            // Normalize the reference number (handle backslash vs forward slash differences)
            $normalizedRef = str_replace('\\', '/', $t->invoice_no);
            $map[$t->invoice_no] = $t->id;
            $map[$normalizedRef] = $t->id;
            // Also try with backslash
            $backslashRef = str_replace('/', '\\', $t->invoice_no);
            $map[$backslashRef] = $t->id;
        }

        return $map;
    }

    /**
     * Try to find product by name (fallback)
     */
    private function findProductByName(string $productName): ?array
    {
        $product = DB::table('products')
            ->join('variations', 'products.id', '=', 'variations.product_id')
            ->where('products.name', 'LIKE', '%' . $productName . '%')
            ->select('products.id as product_id', 'variations.id as variation_id')
            ->first();

        if ($product) {
            return [
                'product_id' => $product->product_id,
                'variation_id' => $product->variation_id,
            ];
        }

        return null;
    }
}
