<?php

namespace App\Http\Controllers;

use App\Services\BidirectionalSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Exception;

class MigrationUpdateController extends Controller
{
    /**
     * Old POS database configuration
     * Local MAMP: rubyshop_co_th_sale_pos on port 8889
     */
    private $oldDbConfig = [
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'rubyshop_co_th_sale_pos',
        'username' => 'root',
        'password' => 'root258369',
    ];

    /**
     * User ID mapping from old POS to new POS
     */
    private $userIdMapping = [
        4 => 3,  // nui.rubyshop (old) -> rungarun.ruby@gmail.com (new)
        5 => 18, // lek-rubyshop (old) -> arocha598@gmail.com (new)
    ];

    /**
     * Map old user ID to new user ID
     * Returns mapped ID or fallback to 1 if not found
     */
    private function mapUserId($oldUserId)
    {
        return $this->userIdMapping[$oldUserId] ?? 1;
    }

    /**
     * Map old POS payment status to new POS payment status.
     */
    private function mapOldPaymentStatusToNew($oldStatus)
    {
        return match (strtolower((string) $oldStatus)) {
            'paid' => 'paid',
            'partial' => 'partial',
            default => 'due',
        };
    }

    /**
     * Mark old sale row as synced (if sync columns exist).
     */
    private function markOldSaleAsSynced($oldSaleId, $newTransactionId): void
    {
        try {
            DB::connection('old_pos')
                ->table('sma_sales')
                ->where('id', $oldSaleId)
                ->update([
                    'synced_to_new_pos' => 1,
                    'new_pos_transaction_id' => $newTransactionId,
                ]);
        } catch (Exception $e) {
            // Ignore if sync columns are not set up yet.
        }
    }

    /**
     * Normalize product code keys for mapping.
     */
    private function normalizeProductCode($code): string
    {
        return trim((string) $code);
    }

    /**
     * Build product map by multiple code fields (sku, second_name, sub_sku).
     */
    private function buildProductMapByCode($business_id): array
    {
        $map = [];

        $rows = DB::table('products')
            ->leftJoin('variations', 'variations.product_id', '=', 'products.id')
            ->where('products.business_id', $business_id)
            ->select(
                'products.id as product_id',
                'products.sku',
                'products.second_name',
                'variations.id as variation_id',
                'variations.sub_sku'
            )
            ->orderBy('products.id')
            ->orderBy('variations.id')
            ->get();

        foreach ($rows as $row) {
            $entry = [
                'product_id' => $row->product_id,
                'variation_id' => $row->variation_id,
            ];

            foreach ([$row->sku, $row->second_name, $row->sub_sku] as $candidateCode) {
                $key = $this->normalizeProductCode($candidateCode);
                if ($key === '' || isset($map[$key])) {
                    continue;
                }
                $map[$key] = $entry;
            }
        }

        return $map;
    }

    /**
     * Validate token for server-to-server cron sync endpoints.
     */
    private function assertValidCronSyncToken(Request $request): void
    {
        $expectedToken = (string) env('SYNC_CRON_TOKEN', '');
        if ($expectedToken === '') {
            Log::warning('[SYNC-CRON] SYNC_CRON_TOKEN is not configured in Laravel .env');
            abort(503, 'SYNC_CRON_TOKEN is not configured.');
        }

        $providedToken = (string) ($request->query('token') ?: $request->header('X-Sync-Token', ''));
        if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
            Log::warning('[SYNC-CRON] Unauthorized cron sync request blocked.', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'ua' => (string) $request->userAgent(),
            ]);
            abort(401, 'Unauthorized.');
        }
    }

    /**
     * Ensure a product exists for the given old product code and return mapped IDs.
     */
    private function ensureProductForCode($business_id, $rawCode, $sendLog = null): ?array
    {
        $code = $this->normalizeProductCode($rawCode);
        if ($code === '') {
            return null;
        }

        $existing = DB::table('products as p')
            ->leftJoin('variations as v', 'v.product_id', '=', 'p.id')
            ->where('p.business_id', $business_id)
            ->where(function ($q) use ($code) {
                $q->where('p.sku', $code)
                    ->orWhere('p.second_name', $code)
                    ->orWhere('v.sub_sku', $code);
            })
            ->select('p.id as product_id', 'v.id as variation_id')
            ->first();

        if ($existing) {
            return [
                'product_id' => $existing->product_id,
                'variation_id' => $existing->variation_id,
            ];
        }

        $oldProduct = DB::connection('old_pos')
            ->table('sma_products')
            ->where('code', $code)
            ->first();

        $fallbackItem = null;
        if (!$oldProduct) {
            $fallbackItem = DB::connection('old_pos')
                ->table('sma_sale_items')
                ->whereRaw('TRIM(product_code) = ?', [$code])
                ->orderByDesc('id')
                ->first(['product_name', 'unit_price', 'net_unit_price']);

            if (!$fallbackItem) {
                $fallbackItem = DB::connection('old_pos')
                    ->table('sma_quote_items')
                    ->whereRaw('TRIM(product_code) = ?', [$code])
                    ->orderByDesc('id')
                    ->first(['product_name', 'unit_price', 'net_unit_price']);
            }
        }

        $defaultLocation = DB::table('business_locations')
            ->where('business_id', $business_id)
            ->first();
        $locationId = $defaultLocation->id ?? 1;

        $fallbackPrice = (float) ($fallbackItem->unit_price ?? $fallbackItem->net_unit_price ?? 0);
        $defaultPurchasePrice = (float) ($oldProduct->cost ?? $fallbackPrice);
        $defaultSellPrice = (float) ($oldProduct->price ?? $fallbackPrice);
        $productName = $oldProduct->name
            ?? $fallbackItem->product_name
            ?? ('Legacy Product ' . $code);

        $productId = DB::table('products')->insertGetId([
            'name' => $productName,
            'second_name' => $oldProduct->second_name ?? null,
            'business_id' => $business_id,
            'type' => 'single',
            'sku' => $code,
            'barcode_type' => 'C128',
            'alert_quantity' => 10,
            'enable_stock' => 1,
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productVariationId = DB::table('product_variations')->insertGetId([
            'product_id' => $productId,
            'name' => 'DUMMY',
            'is_dummy' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $variationId = DB::table('variations')->insertGetId([
            'name' => 'DUMMY',
            'product_id' => $productId,
            'product_variation_id' => $productVariationId,
            'sub_sku' => $code,
            'default_purchase_price' => $defaultPurchasePrice,
            'dpp_inc_tax' => $defaultPurchasePrice,
            'profit_percent' => 0,
            'default_sell_price' => $defaultSellPrice,
            'sell_price_inc_tax' => $defaultSellPrice,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('product_locations')->insertOrIgnore([
            'product_id' => $productId,
            'location_id' => $locationId,
        ]);

        if ($sendLog) {
            $sendLog("Auto-created product for missing code {$code}", 'warning');
        }

        return [
            'product_id' => $productId,
            'variation_id' => $variationId,
        ];
    }

    /**
     * Resolve product mapping for one old code, auto-creating when needed.
     */
    private function resolveProductForCode($business_id, $rawCode, array &$productMap, $sendLog = null): ?array
    {
        $code = $this->normalizeProductCode($rawCode);
        if ($code === '') {
            return null;
        }

        if (isset($productMap[$code])) {
            return $productMap[$code];
        }

        $resolved = $this->ensureProductForCode($business_id, $code, $sendLog);
        if ($resolved) {
            $productMap[$code] = $resolved;
        }

        return $resolved;
    }

    /**
     * Get a valid contact ID for the business (creates one only if needed).
     */
    private function getFallbackContactId($business_id): int
    {
        $contactId = DB::table('contacts')
            ->where('business_id', $business_id)
            ->orderBy('id')
            ->value('id');

        if (!empty($contactId)) {
            return (int) $contactId;
        }

        return (int) DB::table('contacts')->insertGetId([
            'business_id' => $business_id,
            'type' => 'customer',
            'contact_status' => 'active',
            'name' => 'Walk-in Customer',
            'mobile' => '-',
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Show migration update page
     */
    public function index()
    {
        return view('migrate-update-data.index');
    }

    /**
     * Run migration with real-time streaming output
     */
    public function runMigration(Request $request)
    {
        // Set unlimited execution time and increase memory
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '0');

        // Disable output buffering for real-time streaming
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Set headers for SSE (Server-Sent Events)
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable nginx buffering

        // Send initial keep-alive comment
        echo ": ping\n\n";
        flush();

        // Function to send log message to browser
        $sendLog = function($message, $type = 'info') {
            $data = json_encode([
                'message' => $message,
                'type' => $type,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            echo "data: {$data}\n\n";

            // Send keep-alive ping every few messages
            if (rand(1, 5) == 1) {
                echo ": ping\n\n";
            }

            flush();

            // Prevent Apache/PHP timeout
            if (connection_status() != CONNECTION_NORMAL) {
                exit;
            }
        };

        try {
            $sendLog('[START] Starting migration process...', 'info');
            $sendLog('Connecting to old POS database...', 'info');

            // Connect to old database
            config(['database.connections.old_pos' => [
                'driver' => 'mysql',
                'host' => $this->oldDbConfig['host'],
                'port' => $this->oldDbConfig['port'],
                'database' => $this->oldDbConfig['database'],
                'username' => $this->oldDbConfig['username'],
                'password' => $this->oldDbConfig['password'],
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]]);

            DB::purge('old_pos');

            $sendLog('[OK] Connected to old POS database', 'success');

            // Get business_id from session
            $business_id = session('user.business_id', 1);
            $sendLog("Using business_id: {$business_id}", 'info');

            // Step 1: Migrate Contacts
            $sendLog('[STEP 1] Migrating Contacts...', 'info');
            $contactsCount = $this->migrateContacts($business_id, $sendLog);
            $sendLog("[OK] Migrated {$contactsCount} contacts", 'success');

            // Step 2: Migrate Categories (before products so we can map category_id)
            $sendLog('[STEP 2] Migrating Categories...', 'info');
            $categoryMapping = [];
            $categoriesCount = $this->migrateCategories($business_id, $sendLog, $categoryMapping);
            $sendLog("[OK] Migrated {$categoriesCount} categories", 'success');

            // Step 3: Migrate Products (with category mapping)
            $sendLog('[STEP 3] Migrating Products...', 'info');
            $productsCount = $this->migrateProducts($business_id, $sendLog, $categoryMapping);
            $sendLog("[OK] Migrated {$productsCount} products", 'success');

            // Step 4: Migrate Sales Transactions
            $sendLog('[STEP 4] Migrating Sales documents...', 'info');
            $vtCount = $this->migrateSales($business_id, $sendLog);
            $sendLog("[OK] Migrated {$vtCount} sales transactions", 'success');

            // Step 5: Migrate Sell Lines (product items for VT sales)
            $sendLog('[STEP 5] Migrating Sell Lines...', 'info');
            $sellLineMigration = $this->migrateSellLinesCore($business_id, $sendLog);
            $sellLinesCount = $sellLineMigration['items_migrated'];
            $sendLog("[OK] Migrated {$sellLinesCount} sell lines", 'success');

            // Step 6: Migrate Quotations
            $sendLog('[STEP 6] Migrating Quotations...', 'info');
            $quotationMigration = $this->migrateQuotationsCore($business_id, $sendLog);
            $quotationsCount = $quotationMigration['migrated'];
            $sendLog("[OK] Migrated {$quotationsCount} quotations", 'success');

            // Step 7: Migrate Payments (single-bill mode: VT + payment rows)
            $sendLog('[STEP 7] Migrating Payments (single-bill)...', 'info');
            $paymentMigration = $this->migratePayments($business_id, $sendLog);
            $paymentsCount = $paymentMigration['migrated'];
            $sendLog("[OK] Migrated {$paymentsCount} payments", 'success');
            $sendLog("[OK] Migrated {$paymentsCount} payment rows", 'success');
            $paymentStatusesRecalculated = $paymentMigration['statuses_recalculated'];
            $sendLog("[OK] Payment statuses recalculated: {$paymentStatusesRecalculated}", 'success');

            // Value validation for sales migration (completed docs only).
            $oldCompletedSalesValue = (float) DB::connection('old_pos')
                ->table('sma_sales')
                ->where('sale_status', 'completed')
                ->sum('grand_total');
            $newCompletedSalesValue = (float) DB::table('transactions')
                ->where('business_id', $business_id)
                ->where('type', 'sell')
                ->whereNotNull('old_pos_sale_id')
                ->where(function ($q) {
                    $q->whereNull('is_quotation')->orWhere('is_quotation', 0);
                })
                ->sum('final_total');
            $salesValueDiff = round($oldCompletedSalesValue - $newCompletedSalesValue, 2);
            $sendLog(
                sprintf(
                    'Sales Value Check (completed): Old %.2f | New %.2f | Diff %.2f',
                    $oldCompletedSalesValue,
                    $newCompletedSalesValue,
                    $salesValueDiff
                ),
                abs($salesValueDiff) > 0.01 ? 'warning' : 'success'
            );

            // Summary
            $sendLog('', 'info');
            $sendLog('═══════════════════════════════════════', 'info');
            $sendLog('[SUCCESS] MIGRATION COMPLETED SUCCESSFULLY!', 'success');
            $sendLog('═══════════════════════════════════════', 'info');
            $sendLog("Total Contacts: {$contactsCount}", 'success');
            $sendLog("Total Categories: {$categoriesCount}", 'success');
            $sendLog("Total Products: {$productsCount}", 'success');
            $sendLog("Total Sales: {$vtCount}", 'success');
            $sendLog("Total Sell Lines: {$sellLinesCount}", 'success');
            $sendLog("Total Quotations: {$quotationsCount}", 'success');
            $sendLog("Total Payment Rows: {$paymentsCount}", 'success');
            $sendLog("Total Payments: {$paymentsCount}", 'success');
            $sendLog("Payment statuses recalculated: {$paymentStatusesRecalculated}", 'success');
            $sendLog(sprintf("Old Completed Sales Value: %.2f", $oldCompletedSalesValue), 'success');
            $sendLog(sprintf("New Migrated Sales Value: %.2f", $newCompletedSalesValue), abs($salesValueDiff) > 0.01 ? 'warning' : 'success');
            $sendLog('═══════════════════════════════════════', 'info');

            echo "data: " . json_encode(['message' => 'DONE', 'type' => 'done']) . "\n\n";
            flush();

        } catch (Exception $e) {
            $sendLog('[ERROR] ' . $e->getMessage(), 'error');
            $sendLog('Stack trace: ' . $e->getTraceAsString(), 'error');
            echo "data: " . json_encode(['message' => 'ERROR', 'type' => 'error']) . "\n\n";
            flush();
        }

        exit;
    }

    /**
     * Migrate contacts from old POS
     */
    private function migrateContacts($business_id, $sendLog)
    {
        $sendLog('Fetching contacts from old database...', 'info');

        $total = DB::connection('old_pos')->table('sma_companies')->count();
        $sendLog("Found {$total} contacts to migrate", 'info');

        $count = 0;
        $processed = 0;
        $chunkSize = 200;

        DB::connection('old_pos')
            ->table('sma_companies')
            ->orderBy('id')
            ->chunk($chunkSize, function($oldContacts) use ($business_id, $sendLog, &$count, &$processed, $total) {
                foreach ($oldContacts as $oldContact) {
                    // Check if contact already exists
                    $exists = DB::table('contacts')
                        ->where('business_id', $business_id)
                        ->where('name', $oldContact->name)
                        ->exists();

                    if (!$exists) {
                        // Default to user ID 1 for contacts (no created_by in old table)
                        // Since old table is sma_companies, default to business type
                        DB::table('contacts')->insert([
                            'business_id' => $business_id,
                            'type' => 'customer',
                            'contact_type' => 'business', // Default to business since migrating from sma_companies
                            'supplier_business_name' => $oldContact->name, // Use name as business name
                            'name' => $oldContact->name,
                            'email' => $oldContact->email ?? null,
                            'mobile' => $oldContact->phone ?? null,
                            'address_line_1' => $oldContact->address ?? null,
                            'city' => $oldContact->city ?? null,
                            'state' => $oldContact->state ?? null,
                            'country' => $oldContact->country ?? null,
                            'zip_code' => $oldContact->postal_code ?? null,
                            'tax_number' => $oldContact->vat_no ?? null,
                            'created_by' => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $count++;
                    }
                    $processed++;
                }

                $sendLog("Processed {$processed}/{$total} contacts ({$count} new)", 'info');
            });

        return $count;
    }

    /**
     * Migrate categories from old POS
     * Creates a mapping of old_id => new_id for use in product migration
     */
    private function migrateCategories($business_id, $sendLog, &$categoryMapping)
    {
        $sendLog('Fetching categories from old database...', 'info');

        $total = DB::connection('old_pos')->table('sma_categories')->count();
        $sendLog("Found {$total} categories to migrate", 'info');

        $count = 0;
        $processed = 0;

        // First pass: migrate all categories (we'll fix parent_id relationships after)
        $oldCategories = DB::connection('old_pos')
            ->table('sma_categories')
            ->orderBy('parent_id') // Process parent categories first
            ->orderBy('id')
            ->get();

        foreach ($oldCategories as $oldCategory) {
            // Check if category already exists by name
            $existing = DB::table('categories')
                ->where('business_id', $business_id)
                ->where('name', $oldCategory->name)
                ->where('category_type', 'product')
                ->first();

            if ($existing) {
                // Map old ID to existing new ID
                $categoryMapping[$oldCategory->id] = $existing->id;
                $processed++;
                continue;
            }

            // Initially set parent_id to 0, we'll update it in second pass
            $newCategoryId = DB::table('categories')->insertGetId([
                'name' => $oldCategory->name,
                'short_code' => $oldCategory->code ?? null,
                'business_id' => $business_id,
                'parent_id' => 0, // Will be updated in second pass
                'category_type' => 'product',
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Store mapping
            $categoryMapping[$oldCategory->id] = $newCategoryId;
            $count++;
            $processed++;

            if ($processed % 50 == 0) {
                $sendLog("Processed {$processed}/{$total} categories ({$count} new)", 'info');
            }
        }

        // Second pass: Update parent_id relationships
        $sendLog('Updating category parent relationships...', 'info');
        $parentUpdates = 0;

        foreach ($oldCategories as $oldCategory) {
            if (!empty($oldCategory->parent_id) && $oldCategory->parent_id > 0) {
                // Get the new ID for this category
                $newId = $categoryMapping[$oldCategory->id] ?? null;
                // Get the new parent ID
                $newParentId = $categoryMapping[$oldCategory->parent_id] ?? null;

                if ($newId && $newParentId) {
                    DB::table('categories')
                        ->where('id', $newId)
                        ->update(['parent_id' => $newParentId]);
                    $parentUpdates++;
                }
            }
        }

        $sendLog("Updated {$parentUpdates} parent relationships", 'info');
        $sendLog("Processed {$processed}/{$total} categories ({$count} new)", 'info');

        return $count;
    }

    /**
     * Migrate products from old POS
     */
    private function migrateProducts($business_id, $sendLog, $categoryMapping = [])
    {
        $sendLog('Fetching products from old database...', 'info');

        $total = DB::connection('old_pos')->table('sma_products')->count();
        $sendLog("Found {$total} products to migrate", 'info');

        if (empty($categoryMapping)) {
            $sendLog('Category mapping not provided, building mapping by category name...', 'info');
            $oldCategories = DB::connection('old_pos')
                ->table('sma_categories')
                ->select('id', 'name')
                ->get();

            $newCategories = DB::table('categories')
                ->where('business_id', $business_id)
                ->select('id', 'name')
                ->get();

            $newCategoryByName = [];
            foreach ($newCategories as $newCategory) {
                $key = mb_strtolower(trim((string) $newCategory->name));
                if ($key !== '' && !isset($newCategoryByName[$key])) {
                    $newCategoryByName[$key] = (int) $newCategory->id;
                }
            }

            foreach ($oldCategories as $oldCategory) {
                $key = mb_strtolower(trim((string) $oldCategory->name));
                if ($key !== '' && isset($newCategoryByName[$key])) {
                    $categoryMapping[$oldCategory->id] = $newCategoryByName[$key];
                }
            }

            $sendLog('Built category mapping entries: ' . count($categoryMapping), 'info');
        }

        $locationId = (int) (DB::table('business_locations')
            ->where('business_id', $business_id)
            ->orderBy('id')
            ->value('id') ?? 1);

        $sendLog("Using location_id={$locationId} for product-location links", 'info');

        $sendLog('Building unit and brand mapping...', 'info');
        $unitMapping = [];
        $brandMapping = [];

        $newUnits = DB::table('units')
            ->where('business_id', $business_id)
            ->select('id', 'short_name', 'actual_name')
            ->get();
        $newUnitByKey = [];
        foreach ($newUnits as $newUnit) {
            foreach ([$newUnit->short_name ?? '', $newUnit->actual_name ?? ''] as $unitKeyRaw) {
                $unitKey = mb_strtolower(trim((string) $unitKeyRaw));
                if ($unitKey !== '' && !isset($newUnitByKey[$unitKey])) {
                    $newUnitByKey[$unitKey] = (int) $newUnit->id;
                }
            }
        }

        $oldUnits = DB::connection('old_pos')->table('sma_units')->select('id', 'code', 'name')->get();
        foreach ($oldUnits as $oldUnit) {
            foreach ([$oldUnit->code ?? '', $oldUnit->name ?? ''] as $oldUnitKeyRaw) {
                $oldUnitKey = mb_strtolower(trim((string) $oldUnitKeyRaw));
                if ($oldUnitKey !== '' && isset($newUnitByKey[$oldUnitKey])) {
                    $unitMapping[$oldUnit->id] = $newUnitByKey[$oldUnitKey];
                    break;
                }
            }
        }

        $newBrands = DB::table('brands')
            ->where('business_id', $business_id)
            ->select('id', 'name')
            ->get();
        $newBrandByName = [];
        foreach ($newBrands as $newBrand) {
            $brandKey = mb_strtolower(trim((string) $newBrand->name));
            if ($brandKey !== '' && !isset($newBrandByName[$brandKey])) {
                $newBrandByName[$brandKey] = (int) $newBrand->id;
            }
        }

        $oldBrands = DB::connection('old_pos')->table('sma_brands')->select('id', 'name')->get();
        foreach ($oldBrands as $oldBrand) {
            $brandKey = mb_strtolower(trim((string) $oldBrand->name));
            if ($brandKey !== '' && isset($newBrandByName[$brandKey])) {
                $brandMapping[$oldBrand->id] = $newBrandByName[$brandKey];
            }
        }

        $sendLog('Unit mapping: ' . count($unitMapping) . ' | Brand mapping: ' . count($brandMapping), 'info');

        $count = 0;
        $updated = 0;
        $skipped = 0;
        $processed = 0;
        $chunkSize = 200;

        DB::connection('old_pos')
            ->table('sma_products')
            ->orderBy('id')
            ->chunk($chunkSize, function($oldProducts) use ($business_id, $sendLog, $categoryMapping, $locationId, $unitMapping, $brandMapping, &$count, &$updated, &$skipped, &$processed, $total) {
                foreach ($oldProducts as $oldProduct) {
                    $oldCode = $this->normalizeProductCode($oldProduct->code ?? '');
                    $existingProduct = null;

                    if ($oldCode !== '') {
                        $existingProduct = DB::table('products as p')
                            ->leftJoin('variations as v', 'v.product_id', '=', 'p.id')
                            ->where('p.business_id', $business_id)
                            ->where(function ($q) use ($oldCode) {
                                $q->where('p.sku', $oldCode)
                                    ->orWhere('p.second_name', $oldCode)
                                    ->orWhere('v.sub_sku', $oldCode);
                            })
                            ->select('p.id as product_id', 'p.sku as product_sku', 'v.id as variation_id')
                            ->first();
                    } else {
                        $existingProduct = DB::table('products')
                            ->where('business_id', $business_id)
                            ->where('name', $oldProduct->name)
                            ->select('id as product_id', 'sku as product_sku')
                            ->first();
                    }

                    $defaultPurchasePrice = (float) ($oldProduct->cost ?? 0);
                    $defaultSellPrice = (float) ($oldProduct->price ?? 0);
                    $newCategoryId = null;
                    if (!empty($oldProduct->category_id)) {
                        $newCategoryId = $categoryMapping[$oldProduct->category_id] ?? null;
                    }

                    $mappedUnitId = !empty($oldProduct->unit) && isset($unitMapping[$oldProduct->unit])
                        ? $unitMapping[$oldProduct->unit]
                        : null;
                    $mappedBrandId = !empty($oldProduct->brand) && isset($brandMapping[$oldProduct->brand])
                        ? $brandMapping[$oldProduct->brand]
                        : null;

                    $resolvedName = trim((string) ($oldProduct->name ?? ''));
                    if ($resolvedName === '') {
                        if ($oldCode === '') {
                            $skipped++;
                            $processed++;
                            continue;
                        }
                        $resolvedName = 'Legacy Product ' . $oldCode;
                    }

                    if (!$existingProduct) {

                        $productId = DB::table('products')->insertGetId([
                            'name' => $resolvedName,
                            'second_name' => $oldProduct->second_name ?? null,
                            'business_id' => $business_id,
                            'type' => 'single',
                            'sku' => $oldCode !== '' ? $oldCode : 'SKU-' . time() . rand(1000, 9999),
                            'barcode_type' => 'C128',
                            'alert_quantity' => $oldProduct->alert_quantity ?? 10,
                            'enable_stock' => 1,
                            'category_id' => $newCategoryId,
                            'unit_id' => $mappedUnitId,
                            'brand_id' => $mappedBrandId,
                            'image' => $oldProduct->image ?? null,
                            'product_description' => $oldProduct->product_details ?? ($oldProduct->details ?? null),
                            'created_by' => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $productVariationId = DB::table('product_variations')->insertGetId([
                            'product_id' => $productId,
                            'name' => 'DUMMY',
                            'is_dummy' => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        // Create variation for the product
                        DB::table('variations')->insert([
                            'name' => 'DUMMY',
                            'product_id' => $productId,
                            'product_variation_id' => $productVariationId,
                            'sub_sku' => $oldCode !== '' ? $oldCode : 'SKU-' . time() . rand(1000, 9999),
                            'default_purchase_price' => $defaultPurchasePrice,
                            'dpp_inc_tax' => $defaultPurchasePrice,
                            'profit_percent' => 0,
                            'default_sell_price' => $defaultSellPrice,
                            'sell_price_inc_tax' => $defaultSellPrice,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        // Link product to default business location
                        DB::table('product_locations')->insertOrIgnore([
                            'product_id' => $productId,
                            'location_id' => $locationId,
                        ]);

                        $count++;
                    } else {
                        $productId = (int) $existingProduct->product_id;
                        $variationId = (int) ($existingProduct->variation_id ?? 0);

                        $productUpdate = [
                            'name' => $resolvedName,
                            'second_name' => $oldProduct->second_name ?? null,
                            'alert_quantity' => $oldProduct->alert_quantity ?? 10,
                            'enable_stock' => 1,
                            'image' => $oldProduct->image ?? null,
                            'product_description' => $oldProduct->product_details ?? ($oldProduct->details ?? null),
                            'updated_at' => now(),
                        ];

                        if ($newCategoryId !== null) {
                            $productUpdate['category_id'] = $newCategoryId;
                        }
                        if ($mappedUnitId !== null) {
                            $productUpdate['unit_id'] = $mappedUnitId;
                        }
                        if ($mappedBrandId !== null) {
                            $productUpdate['brand_id'] = $mappedBrandId;
                        }
                        if ($oldCode !== '' && trim((string) ($existingProduct->product_sku ?? '')) === '') {
                            $productUpdate['sku'] = $oldCode;
                        }

                        DB::table('products')
                            ->where('id', $productId)
                            ->update($productUpdate);

                        if ($variationId > 0) {
                            $variationUpdate = [
                                'default_purchase_price' => $defaultPurchasePrice,
                                'dpp_inc_tax' => $defaultPurchasePrice,
                                'default_sell_price' => $defaultSellPrice,
                                'sell_price_inc_tax' => $defaultSellPrice,
                                'updated_at' => now(),
                            ];
                            if ($oldCode !== '') {
                                $variationUpdate['sub_sku'] = $oldCode;
                            }

                            DB::table('variations')
                                ->where('id', $variationId)
                                ->update($variationUpdate);
                        } else {
                            $productVariationId = DB::table('product_variations')->insertGetId([
                                'product_id' => $productId,
                                'name' => 'DUMMY',
                                'is_dummy' => 1,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                            DB::table('variations')->insert([
                                'name' => 'DUMMY',
                                'product_id' => $productId,
                                'product_variation_id' => $productVariationId,
                                'sub_sku' => $oldCode !== '' ? $oldCode : ('SKU-' . time() . rand(1000, 9999)),
                                'default_purchase_price' => $defaultPurchasePrice,
                                'dpp_inc_tax' => $defaultPurchasePrice,
                                'profit_percent' => 0,
                                'default_sell_price' => $defaultSellPrice,
                                'sell_price_inc_tax' => $defaultSellPrice,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }

                        DB::table('product_locations')->insertOrIgnore([
                            'product_id' => $productId,
                            'location_id' => $locationId,
                        ]);

                        $updated++;
                    }
                    $processed++;
                }

                $sendLog("Processed {$processed}/{$total} products ({$count} new, {$updated} updated, {$skipped} skipped)", 'info');
            });

        $sendLog("Products migration summary: {$count} new, {$updated} updated, {$skipped} skipped", 'info');
        return $count;
    }

    /**
     * Migrate completed sales transactions from old POS.
     */
    private function migrateSales($business_id, $sendLog)
    {
        $sendLog('Fetching completed sales from old database...', 'info');

        $total = DB::connection('old_pos')
            ->table('sma_sales')
            ->where('sale_status', 'completed')
            ->count();
        $sendLog("Found {$total} sales to migrate", 'info');

        $count = 0;
        $processed = 0;
        $chunkSize = 100;

        DB::connection('old_pos')
            ->table('sma_sales')
            ->where('sale_status', 'completed')
            ->orderBy('id')
            ->chunk($chunkSize, function($oldSales) use ($business_id, $sendLog, &$count, &$processed, $total) {
                foreach ($oldSales as $oldSale) {
                    // Keep exact old POS reference format as canonical (e.g. VT/POS2026/5333).
                    $oldReferenceNo = trim((string) ($oldSale->reference_no ?? ''));
                    if ($oldReferenceNo === '') {
                        $processed++;
                        $sendLog("Skipping old sale #{$oldSale->id}: empty reference_no", 'warning');
                        continue;
                    }

                    // Check if sale already migrated by canonical old sale ID first.
                    $existingByOldSale = DB::table('transactions')
                        ->where('business_id', $business_id)
                        ->where('old_pos_sale_id', $oldSale->id)
                        ->select('id')
                        ->first();

                    if ($existingByOldSale) {
                        $this->markOldSaleAsSynced($oldSale->id, $existingByOldSale->id);
                        $processed++;
                        continue;
                    }

                    // Fallback: check by invoice_no to avoid duplicate inserts.
                    $existingTransaction = DB::table('transactions')
                        ->where('business_id', $business_id)
                        ->where('invoice_no', $oldReferenceNo)
                        ->select('id', 'old_pos_sale_id')
                        ->first();

                    $invoiceNoToUse = $oldReferenceNo;
                    if ($existingTransaction && !empty($existingTransaction->old_pos_sale_id)
                        && (int) $existingTransaction->old_pos_sale_id !== (int) $oldSale->id
                    ) {
                        // Same reference number belongs to a different old sale.
                        // Keep both rows by assigning a deterministic suffix.
                        $invoiceNoToUse = $oldReferenceNo . '-OS' . $oldSale->id;
                        $seq = 1;
                        while (
                            DB::table('transactions')
                                ->where('business_id', $business_id)
                                ->where('invoice_no', $invoiceNoToUse)
                                ->exists()
                        ) {
                            $invoiceNoToUse = $oldReferenceNo . '-OS' . $oldSale->id . '-' . $seq;
                            $seq++;
                        }
                        $sendLog("Duplicate reference {$oldReferenceNo} detected for old sale #{$oldSale->id}; using {$invoiceNoToUse}", 'warning');
                        $existingTransaction = null;
                    }

                    if (!$existingTransaction) {
                        // Find or create contact
                        $contact = DB::table('contacts')
                            ->where('business_id', $business_id)
                            ->where('name', 'LIKE', '%' . ($oldSale->customer ?? 'Walk-in Customer') . '%')
                            ->first();

                        if (!$contact) {
                            $contactId = DB::table('contacts')->insertGetId([
                                'business_id' => $business_id,
                                'type' => 'customer',
                                'contact_status' => 'active',
                                'name' => $oldSale->customer ?? 'Walk-in Customer',
                                'mobile' => '-',
                                'created_by' => 1,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        } else {
                            $contactId = $contact->id;
                        }

                        // Map the old user ID to new user ID
                        $mappedUserId = $this->mapUserId($oldSale->created_by ?? null);
                        $mappedPaymentStatus = $this->mapOldPaymentStatusToNew($oldSale->payment_status ?? 'unpaid');
                        $locationId = $this->mapOldWarehouseToNewLocation((int) ($oldSale->warehouse_id ?? 1));

                        // Insert canonical single-row sale transaction (VT + payments).
                        $newTransactionId = DB::table('transactions')->insertGetId([
                            'business_id' => $business_id,
                            'location_id' => $locationId,
                            'type' => 'sell',
                            'status' => 'final',
                            'sub_status' => null,
                            'document_type' => 'proforma',
                            'invoice_no' => $invoiceNoToUse,
                            'ref_no' => $invoiceNoToUse,
                            'contact_id' => $contactId,
                            'transaction_date' => $oldSale->date ?? now(),
                            'total_before_tax' => $oldSale->total ?? 0,
                            'tax_amount' => $oldSale->total_tax ?? 0,
                            'final_total' => $oldSale->grand_total ?? 0,
                            'payment_status' => $mappedPaymentStatus,
                            'created_by' => $mappedUserId,
                            'sync_source' => 'old_pos',
                            'synced_to_old_pos' => 1,
                            'old_pos_sale_id' => $oldSale->id,
                            'created_at' => $oldSale->date ?? now(),
                            'updated_at' => now(),
                        ]);

                        $this->markOldSaleAsSynced($oldSale->id, $newTransactionId);
                        $count++;
                    } else {
                        $this->markOldSaleAsSynced($oldSale->id, $existingTransaction->id);
                    }
                    $processed++;
                }

                $percentage = round(($processed / $total) * 100, 1);
                $sendLog("Processed {$processed}/{$total} sales ({$count} new) - {$percentage}%", 'info');
            });

        return $count;
    }

    /**
     * Core sell-line migration used by full migration pipeline.
     * Adds product items to VT transactions that have no sell lines yet.
     */
    private function migrateSellLinesCore($business_id, $sendLog): array
    {
        $sendLog('Building product mapping by SKU/code...', 'info');
        $productMap = $this->buildProductMapByCode($business_id);

        $sendLog('[OK] Product mapping built: ' . count($productMap) . ' products', 'success');

        $sendLog('Finding sell transactions without items...', 'info');
        $vtTransactions = DB::table('transactions as t')
            ->leftJoin('transaction_sell_lines as tsl', 'tsl.transaction_id', '=', 't.id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where(function ($query) {
                $query->where('t.invoice_no', 'LIKE', 'VT%')
                    ->orWhere('t.document_type', 'proforma')
                    ->orWhere('t.sub_status', 'proforma');
            })
            ->whereNull('tsl.id')
            ->select('t.id', 't.invoice_no', 't.ref_no', 't.old_pos_sale_id')
            ->get();

        $totalVT = $vtTransactions->count();
        $sendLog("Found {$totalVT} sales documents without sell lines", 'info');

        if ($totalVT === 0) {
            return [
                'total_transactions' => 0,
                'migrated_transactions' => 0,
                'items_migrated' => 0,
                'skipped' => 0,
                'product_not_found' => 0,
                'errors' => 0,
            ];
        }

        $migrated = 0;
        $skipped = 0;
        $itemsMigrated = 0;
        $productNotFound = 0;
        $errors = 0;
        $processed = 0;

        foreach ($vtTransactions as $vt) {
            try {
                $oldSale = null;

                if (!empty($vt->old_pos_sale_id)) {
                    $oldSale = DB::connection('old_pos')
                        ->table('sma_sales')
                        ->where('id', $vt->old_pos_sale_id)
                        ->first();
                }

                if (!$oldSale && !empty($vt->invoice_no)) {
                    $oldSale = DB::connection('old_pos')
                        ->table('sma_sales')
                        ->where('reference_no', $vt->invoice_no)
                        ->first();
                }

                if (!$oldSale && !empty($vt->ref_no) && $vt->ref_no !== $vt->invoice_no) {
                    $oldSale = DB::connection('old_pos')
                        ->table('sma_sales')
                        ->where('reference_no', $vt->ref_no)
                        ->first();
                }

                if (!$oldSale) {
                    $skipped++;
                    $processed++;
                    continue;
                }

                $saleItems = DB::connection('old_pos')
                    ->table('sma_sale_items')
                    ->where('sale_id', $oldSale->id)
                    ->get();

                if ($saleItems->isEmpty()) {
                    $skipped++;
                    $processed++;
                    continue;
                }

                foreach ($saleItems as $item) {
                    $newProduct = $this->resolveProductForCode($business_id, $item->product_code ?? null, $productMap, $sendLog);
                    if (!$newProduct) {
                        $productNotFound++;
                        continue;
                    }

                    $discountType = 'fixed';
                    $discountAmount = (float) ($item->item_discount ?? 0);

                    if (!empty($item->discount)) {
                        if (strpos((string) $item->discount, '%') !== false) {
                            $discountType = 'percentage';
                            $discountAmount = (float) str_replace('%', '', (string) $item->discount);
                        } else {
                            $discountAmount = (float) $item->discount;
                        }
                    }

                    $unitPrice = (float) ($item->net_unit_price ?: $item->unit_price ?: 0);
                    $unitPriceBeforeDiscount = (float) ($item->unit_price ?? $unitPrice);
                    $unitPriceIncTax = (float) ($item->unit_price ?? 0) + (float) ($item->item_tax ?? 0);

                    DB::table('transaction_sell_lines')->insert([
                        'transaction_id' => $vt->id,
                        'product_id' => $newProduct['product_id'],
                        'variation_id' => $newProduct['variation_id'],
                        'quantity' => (float) ($item->quantity ?? 0),
                        'unit_price_before_discount' => $unitPriceBeforeDiscount,
                        'unit_price' => $unitPrice,
                        'unit_price_inc_tax' => $unitPriceIncTax,
                        'item_tax' => (float) ($item->item_tax ?? 0),
                        'tax_id' => null,
                        'line_discount_type' => $discountType,
                        'line_discount_amount' => $discountAmount,
                        'sell_line_note' => $item->comment ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $itemsMigrated++;
                }

                $migrated++;
                $processed++;

                if ($processed % 200 === 0 || $processed === $totalVT) {
                    $percentage = round(($processed / $totalVT) * 100, 1);
                    $sendLog("Sell lines progress: {$processed}/{$totalVT} ({$itemsMigrated} items)", 'info');
                    $sendLog("Sell lines progress percent: {$percentage}%", 'info');
                }
            } catch (Exception $e) {
                $errors++;
                $processed++;
                if ($errors <= 10) {
                    $sendLog("Sell line migration warning for {$vt->invoice_no}: " . $e->getMessage(), 'warning');
                }
            }
        }

        return [
            'total_transactions' => $totalVT,
            'migrated_transactions' => $migrated,
            'items_migrated' => $itemsMigrated,
            'skipped' => $skipped,
            'product_not_found' => $productNotFound,
            'errors' => $errors,
        ];
    }

    /**
     * Core quotation migration used by full migration pipeline.
     */
    private function migrateQuotationsCore($business_id, $sendLog): array
    {
        $sendLog('Preparing quotation migration...', 'info');

        $defaultLocation = DB::table('business_locations')
            ->where('business_id', $business_id)
            ->first();
        $locationId = $defaultLocation->id ?? 1;
        $fallbackContactId = $this->getFallbackContactId($business_id);

        $productMap = $this->buildProductMapByCode($business_id);

        $total = DB::connection('old_pos')
            ->table('sma_quotes')
            ->count();

        $sendLog("Found {$total} quotations to migrate", 'info');

        if ($total === 0) {
            return [
                'total' => 0,
                'migrated' => 0,
                'skipped' => 0,
                'items_migrated' => 0,
            ];
        }

        $count = 0;
        $skipped = 0;
        $processed = 0;
        $itemsMigrated = 0;

        DB::connection('old_pos')
            ->table('sma_quotes')
            ->orderBy('id')
            ->chunk(100, function ($oldQuotes) use ($business_id, $locationId, $fallbackContactId, $productMap, $sendLog, &$count, &$skipped, &$processed, &$itemsMigrated, $total) {
                foreach ($oldQuotes as $oldQuote) {
                    $exists = DB::table('transactions')
                        ->where('business_id', $business_id)
                        ->where('invoice_no', $oldQuote->reference_no)
                        ->exists();

                    if ($exists) {
                        $skipped++;
                        $processed++;
                        continue;
                    }

                    $contactId = $fallbackContactId;
                    if (!empty($oldQuote->customer)) {
                        $contact = DB::table('contacts')
                            ->where('business_id', $business_id)
                            ->where('name', 'LIKE', '%' . $oldQuote->customer . '%')
                            ->first();
                        if ($contact) {
                            $contactId = $contact->id;
                        }
                    }

                    $mappedUserId = $this->mapUserId($oldQuote->created_by ?? null);

                    $transactionId = DB::table('transactions')->insertGetId([
                        'business_id' => $business_id,
                        'location_id' => $locationId,
                        'type' => 'sell',
                        'status' => 'draft',
                        'sub_status' => 'quotation',
                        'document_type' => 'quotation',
                        'is_quotation' => 1,
                        'payment_status' => 'due',
                        'contact_id' => $contactId,
                        'invoice_no' => $oldQuote->reference_no,
                        'ref_no' => $oldQuote->reference_no,
                        'transaction_date' => $oldQuote->date ?? now(),
                        'total_before_tax' => $oldQuote->total ?? 0,
                        'tax_amount' => $oldQuote->total_tax ?? 0,
                        'discount_type' => 'fixed',
                        'discount_amount' => $oldQuote->order_discount ?? 0,
                        'shipping_charges' => $oldQuote->shipping ?? 0,
                        'additional_notes' => $oldQuote->note ?? null,
                        'staff_note' => $oldQuote->internal_note ?? null,
                        'final_total' => $oldQuote->grand_total ?? 0,
                        'created_by' => $mappedUserId,
                        'sync_source' => 'old_pos',
                        'synced_to_old_pos' => 1,
                        'created_at' => $oldQuote->date ?? now(),
                        'updated_at' => now(),
                    ]);

                    $quoteItems = DB::connection('old_pos')
                        ->table('sma_quote_items')
                        ->where('quote_id', $oldQuote->id)
                        ->get();

                    foreach ($quoteItems as $item) {
                        $newProduct = $this->resolveProductForCode($business_id, $item->product_code ?? null, $productMap, $sendLog);
                        if (!$newProduct) {
                            continue;
                        }

                        $discountType = 'fixed';
                        $discountAmount = (float) ($item->item_discount ?? 0);

                        if (!empty($item->discount)) {
                            if (strpos((string) $item->discount, '%') !== false) {
                                $discountType = 'percentage';
                                $discountAmount = (float) str_replace('%', '', (string) $item->discount);
                            } else {
                                $discountAmount = (float) $item->discount;
                            }
                        }

                        $unitPrice = (float) ($item->net_unit_price ?: $item->unit_price ?: 0);
                        $unitPriceBeforeDiscount = (float) ($item->unit_price ?? $unitPrice);
                        $unitPriceIncTax = (float) ($item->unit_price ?? 0) + (float) ($item->item_tax ?? 0);

                        DB::table('transaction_sell_lines')->insert([
                            'transaction_id' => $transactionId,
                            'product_id' => $newProduct['product_id'],
                            'variation_id' => $newProduct['variation_id'],
                            'quantity' => (float) ($item->quantity ?? 0),
                            'unit_price_before_discount' => $unitPriceBeforeDiscount,
                            'unit_price' => $unitPrice,
                            'unit_price_inc_tax' => $unitPriceIncTax,
                            'item_tax' => (float) ($item->item_tax ?? 0),
                            'line_discount_type' => $discountType,
                            'line_discount_amount' => $discountAmount,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $itemsMigrated++;
                    }

                    $count++;
                    $processed++;
                }

                $percentage = $total > 0 ? round(($processed / $total) * 100, 1) : 0;
                $sendLog("Quotation progress: {$processed}/{$total} ({$count} new, {$skipped} skipped) - {$percentage}%", 'info');
            });

        return [
            'total' => $total,
            'migrated' => $count,
            'skipped' => $skipped,
            'items_migrated' => $itemsMigrated,
        ];
    }

    /**
     * Migrate IPAY transactions
     * In old database: sma_payments has sale_id that links to sma_sales.id
     * sma_payments.reference_no = IPAY number (e.g., IPAY2025/10452)
     * sma_sales.reference_no = VT number (e.g., VT2025/1362)
     */
    private function migrateIPAY($business_id, $sendLog)
    {
        $sendLog('Fetching IPAY from old database (joining payments with sales)...', 'info');

        // Count IPAY records (payments with IPAY reference_no)
        $total = DB::connection('old_pos')
            ->table('sma_payments')
            ->where('reference_no', 'LIKE', 'IPAY%')
            ->count();
        $sendLog("Found {$total} IPAY payment records to process", 'info');

        $count = 0;
        $skipped = 0;
        $processed = 0;
        $chunkSize = 200;

        // Join sma_payments with sma_sales to get the VT reference
        DB::connection('old_pos')
            ->table('sma_payments as p')
            ->leftJoin('sma_sales as s', 'p.sale_id', '=', 's.id')
            ->select(
                'p.id as payment_id',
                'p.reference_no as ipay_number',
                'p.sale_id',
                'p.date as payment_date',
                'p.amount',
                'p.paid_by',
                'p.created_by as payment_created_by',
                's.reference_no as vt_number',
                's.customer_id',
                's.grand_total'
            )
            ->where('p.reference_no', 'LIKE', 'IPAY%')
            ->orderBy('p.id')
            ->chunk($chunkSize, function($oldPayments) use ($business_id, $sendLog, &$count, &$skipped, &$processed, $total) {
                foreach ($oldPayments as $oldPayment) {
                    // Check if IPAY already migrated by invoice_no
                    $exists = DB::table('transactions')
                        ->where('business_id', $business_id)
                        ->where('invoice_no', $oldPayment->ipay_number)
                        ->exists();

                    if ($exists) {
                        $skipped++;
                        $processed++;
                        continue;
                    }

                    // Skip if no VT number linked
                    if (empty($oldPayment->vt_number)) {
                        $skipped++;
                        $processed++;
                        continue;
                    }

                    // Find related VT in new database
                    $vtTransaction = DB::table('transactions')
                        ->where('business_id', $business_id)
                        ->where('invoice_no', $oldPayment->vt_number)
                        ->first();

                    if ($vtTransaction) {
                        // Create IPAY transaction with proper linking
                        $ipayId = DB::table('transactions')->insertGetId([
                            'business_id' => $business_id,
                            'location_id' => $vtTransaction->location_id,
                            'type' => 'sell',
                            'status' => 'final',
                            'sub_status' => null,
                            'invoice_no' => $oldPayment->ipay_number,
                            'ref_no' => $oldPayment->vt_number, // Store VT number as ref for linking
                            'contact_id' => $vtTransaction->contact_id,
                            'transaction_date' => $oldPayment->payment_date ?? now(),
                            'total_before_tax' => $vtTransaction->total_before_tax,
                            'tax_amount' => $vtTransaction->tax_amount,
                            'final_total' => $oldPayment->amount ?? $vtTransaction->final_total,
                            'payment_status' => 'paid',
                            // Use transfer_parent_id for migrated data linking (matches existing migration)
                            'transfer_parent_id' => $vtTransaction->id,
                            'created_by' => $vtTransaction->created_by ?? 1,
                            'created_at' => $oldPayment->payment_date ?? now(),
                            'updated_at' => now(),
                        ]);

                        // Also link VT to IPAY (bidirectional - for new POS style linking)
                        DB::table('transactions')
                            ->where('id', $vtTransaction->id)
                            ->update(['linked_billing_receive_id' => $ipayId]);

                        $count++;
                    } else {
                        $skipped++;
                    }
                    $processed++;
                }

                $percentage = $total > 0 ? round(($processed / $total) * 100, 1) : 0;
                $sendLog("Processed {$processed}/{$total} IPAY records ({$count} created, {$skipped} skipped) - {$percentage}%", 'info');
            });

        return $count;
    }

    /**
     * Link VT and IPAY relationships
     * Supports multiple linking strategies:
     * 1. IPAY.ref_no = VT.invoice_no (from migrateIPAY)
     * 2. IPAY.transfer_parent_id = VT.id (from existing migration scripts)
     */
    private function linkVTandIPAY($business_id, $sendLog)
    {
        $sendLog('Finding unlinked VT-IPAY pairs...', 'info');

        // Find all VT transactions without linked IPAY
        $total = DB::table('transactions')
            ->where('business_id', $business_id)
            ->where('invoice_no', 'LIKE', 'VT%')
            ->whereNull('linked_billing_receive_id')
            ->count();

        $sendLog("Found {$total} VT transactions to check for linking", 'info');

        $count = 0;
        $processed = 0;
        $chunkSize = 200;

        DB::table('transactions')
            ->where('business_id', $business_id)
            ->where('invoice_no', 'LIKE', 'VT%')
            ->whereNull('linked_billing_receive_id')
            ->orderBy('id')
            ->chunk($chunkSize, function($vtTransactions) use ($business_id, $sendLog, &$count, &$processed, $total) {
                foreach ($vtTransactions as $vt) {
                    $ipay = null;
                    
                    // Strategy 1: Find IPAY by ref_no = VT invoice
                    $ipay = DB::table('transactions')
                        ->where('business_id', $business_id)
                        ->where('invoice_no', 'LIKE', 'IPAY%')
                        ->where('ref_no', $vt->invoice_no)
                        ->first();
                    
                    // Strategy 2: Find IPAY by transfer_parent_id = VT id
                    if (!$ipay) {
                        $ipay = DB::table('transactions')
                            ->where('business_id', $business_id)
                            ->where('invoice_no', 'LIKE', 'IPAY%')
                            ->where('transfer_parent_id', $vt->id)
                            ->first();
                    }

                    if ($ipay) {
                        // Link VT → IPAY (new POS style)
                        DB::table('transactions')
                            ->where('id', $vt->id)
                            ->update(['linked_billing_receive_id' => $ipay->id]);

                        // Link IPAY → VT (both new POS style and migrated data style)
                        DB::table('transactions')
                            ->where('id', $ipay->id)
                            ->update([
                                'linked_tax_invoice_id' => $vt->id,
                                'transfer_parent_id' => $vt->id  // Also set for consistency
                            ]);

                        $count++;
                    }
                    $processed++;
                }

                $percentage = $total > 0 ? round(($processed / $total) * 100, 1) : 0;
                $sendLog("Processed {$processed}/{$total} VT records ({$count} linked) - {$percentage}%", 'info');
            });

        return $count;
    }

    /**
     * Migrate payments
     * In old database: sma_payments has sale_id that links to sma_sales.id
     * Payments should be linked to the VT (proforma) transaction in new database
     */
    private function migratePayments($business_id, $sendLog)
    {
        $sendLog('Fetching payments from old database (joining with sales)...', 'info');

        $total = DB::connection('old_pos')->table('sma_payments')->count();
        $sendLog("Found {$total} payment records to process", 'info');

        $count = 0;
        $skipped = 0;
        $processed = 0;
        $chunkSize = 200;
        $touchedTransactionIds = [];

        // Join sma_payments with sma_sales to get the VT reference
        DB::connection('old_pos')
            ->table('sma_payments as p')
            ->leftJoin('sma_sales as s', 'p.sale_id', '=', 's.id')
            ->select(
                'p.id as payment_id',
                'p.reference_no as payment_reference',
                'p.sale_id',
                'p.date as payment_date',
                'p.amount',
                'p.paid_by',
                'p.created_by as payment_created_by',
                'p.attachment',
                'p.cheque_no',
                'p.cc_no',
                'p.cc_holder',
                'p.cc_month',
                'p.cc_year',
                'p.cc_type',
                'p.note',
                'p.approval_code',
                's.reference_no as vt_number'
            )
            ->orderBy('p.id')
            ->chunk($chunkSize, function($oldPayments) use ($business_id, $sendLog, &$count, &$skipped, &$processed, $total, &$touchedTransactionIds) {
                foreach ($oldPayments as $oldPayment) {
                    // Skip only when neither sale_id nor reference is available.
                    if (empty($oldPayment->sale_id) && empty($oldPayment->vt_number)) {
                        $skipped++;
                        $processed++;
                        continue;
                    }

                    // Find VT transaction in new database
                    $transaction = DB::table('transactions')
                        ->where('business_id', $business_id)
                        ->where('old_pos_sale_id', $oldPayment->sale_id)
                        ->first();

                    if (!$transaction) {
                        $transaction = DB::table('transactions')
                        ->where('business_id', $business_id)
                        ->where('invoice_no', $oldPayment->vt_number)
                        ->first();
                    }

                    if ($transaction) {
                        $touchedTransactionIds[$transaction->id] = true;

                        // Check if payment already exists (by amount and reference)
                        // Note: Added check for created_at to distinguish potentially duplicate payments if needed,
                        // but sticking to amount/ref/transaction_id logic for now.
                        $exists = DB::table('transaction_payments')
                            ->where('transaction_id', $transaction->id)
                            ->where('amount', $oldPayment->amount)
                            ->where('payment_ref_no', $oldPayment->payment_reference)
                            ->exists();

                        if (!$exists) {
                            $mappedUserId = $this->mapUserId($oldPayment->payment_created_by ?? null);

                            DB::table('transaction_payments')->insert([
                                'transaction_id' => $transaction->id,
                                'business_id' => $business_id,
                                'amount' => $oldPayment->amount ?? 0,
                                'method' => strtolower($oldPayment->paid_by ?? 'cash'),
                                'paid_on' => $oldPayment->payment_date ?? now(),
                                'payment_ref_no' => $oldPayment->payment_reference ?? null,
                                'document' => $oldPayment->attachment ?? null,
                                'cheque_number' => $oldPayment->cheque_no ?? null,
                                'card_number' => $oldPayment->cc_no ?? null,
                                'card_holder_name' => $oldPayment->cc_holder ?? null,
                                'card_month' => $oldPayment->cc_month ?? null,
                                'card_year' => $oldPayment->cc_year ?? null,
                                'card_type' => $oldPayment->cc_type ?? null,
                                'note' => $oldPayment->note ?? null,
                                'card_transaction_number' => $oldPayment->approval_code ?? null,
                                'created_by' => $mappedUserId,
                                'created_at' => $oldPayment->payment_date ?? now(),
                                'updated_at' => now(),
                            ]);

                            $count++;
                        } else {
                            // Update existing payment with missing details (especially document)
                            if (!empty($oldPayment->attachment) || !empty($oldPayment->cheque_no) || !empty($oldPayment->cc_no)) {
                                DB::table('transaction_payments')
                                    ->where('transaction_id', $transaction->id)
                                    ->where('amount', $oldPayment->amount)
                                    ->where('payment_ref_no', $oldPayment->payment_reference)
                                    ->limit(1) // Safety
                                    ->update([
                                        'document' => $oldPayment->attachment ?? null,
                                        'cheque_number' => $oldPayment->cheque_no ?? null,
                                        'card_number' => $oldPayment->cc_no ?? null,
                                        'card_holder_name' => $oldPayment->cc_holder ?? null,
                                        'card_month' => $oldPayment->cc_month ?? null,
                                        'card_year' => $oldPayment->cc_year ?? null,
                                        'card_type' => $oldPayment->cc_type ?? null,
                                        'note' => $oldPayment->note ?? null,
                                        'card_transaction_number' => $oldPayment->approval_code ?? null,
                                    ]);
                            }
                            $skipped++;
                        }
                    } else {
                        $skipped++;
                    }
                    $processed++;
                }

                $percentage = $total > 0 ? round(($processed / $total) * 100, 1) : 0;
                $sendLog("Processed {$processed}/{$total} payment records ({$count} new, {$skipped} skipped) - {$percentage}%", 'info');
            });

        $recalculated = $this->recalculatePaymentStatusForTransactions(array_keys($touchedTransactionIds));

        return [
            'migrated' => $count,
            'statuses_recalculated' => $recalculated,
        ];
    }

    /**
     * Recalculate payment_status from transaction_payments totals.
     */
    private function recalculatePaymentStatusForTransactions(array $transactionIds): int
    {
        if (empty($transactionIds)) {
            return 0;
        }

        $updated = 0;

        foreach ($transactionIds as $transactionId) {
            $transaction = DB::table('transactions')
                ->where('id', $transactionId)
                ->select('id', 'final_total', 'payment_status')
                ->first();

            if (!$transaction) {
                continue;
            }

            try {
                $paidAmount = (float) DB::table('transaction_payments')
                    ->where('transaction_id', $transactionId)
                    ->where(function ($q) {
                        $q->whereNull('is_return')->orWhere('is_return', 0);
                    })
                    ->sum('amount');
            } catch (Exception $e) {
                // Fallback for schemas without is_return.
                $paidAmount = (float) DB::table('transaction_payments')
                    ->where('transaction_id', $transactionId)
                    ->sum('amount');
            }

            $finalTotal = (float) ($transaction->final_total ?? 0);
            $newPaymentStatus = 'due';
            if ($paidAmount > 0 && $paidAmount + 0.00001 < $finalTotal) {
                $newPaymentStatus = 'partial';
            } elseif ($paidAmount >= $finalTotal && $finalTotal > 0) {
                $newPaymentStatus = 'paid';
            } elseif ($paidAmount > 0 && $finalTotal <= 0) {
                $newPaymentStatus = 'paid';
            }

            if ($transaction->payment_status !== $newPaymentStatus) {
                DB::table('transactions')
                    ->where('id', $transactionId)
                    ->update([
                        'payment_status' => $newPaymentStatus,
                        'updated_at' => now(),
                    ]);
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Clean migrated data from new database
     * This removes:
     * - Legacy IPAY transactions (if any historical clone rows exist)
     * - Legacy VT-IPAY linking data
     * - VT transactions (and their payment rows)
     * - Products & variations
     * - Contacts
     */
    public function cleanMigratedData()
    {
        // Set unlimited execution time
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        // Disable output buffering for real-time streaming
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Set headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        echo ": ping\n\n";
        flush();

        $sendLog = function($message, $type = 'info') {
            $data = json_encode([
                'message' => $message,
                'type' => $type,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            echo "data: {$data}\n\n";
            flush();

            if (connection_status() != CONNECTION_NORMAL) {
                exit;
            }
        };

        try {
            $sendLog('[CLEANUP] Starting data cleanup...', 'warning');

            $business_id = session('user.business_id', 1);
            $sendLog("Using business_id: {$business_id}", 'info');

            // Step 1: Delete legacy IPAY transactions (if any historical clones exist)
            $sendLog('[STEP 1] Deleting legacy IPAY transactions (if any)...', 'info');
            
            $ipayCount = DB::table('transactions')
                ->where('business_id', $business_id)
                ->where('invoice_no', 'LIKE', 'IPAY%')
                ->count();
            
            $sendLog("Found {$ipayCount} IPAY transactions to delete", 'info');
            
            if ($ipayCount > 0) {
                // First get the IDs of IPAY transactions
                $ipayIds = DB::table('transactions')
                    ->where('business_id', $business_id)
                    ->where('invoice_no', 'LIKE', 'IPAY%')
                    ->pluck('id');
                
                // Delete related transaction_sell_lines
                $linesDeleted = DB::table('transaction_sell_lines')
                    ->whereIn('transaction_id', $ipayIds)
                    ->delete();
                $sendLog("Deleted {$linesDeleted} IPAY sell lines", 'info');
                
                // Delete related payments
                $paymentsDeleted = DB::table('transaction_payments')
                    ->whereIn('transaction_id', $ipayIds)
                    ->delete();
                $sendLog("Deleted {$paymentsDeleted} IPAY payment records", 'info');
                
                // Delete the IPAY transactions
                $deleted = DB::table('transactions')
                    ->where('business_id', $business_id)
                    ->where('invoice_no', 'LIKE', 'IPAY%')
                    ->delete();
                $sendLog("[OK] Deleted {$deleted} IPAY transactions", 'success');
            }

            // Step 2: Clear legacy VT linking data
            $sendLog('[STEP 2] Clearing legacy VT linking data...', 'info');
            
            $vtWithLinks = DB::table('transactions')
                ->where('business_id', $business_id)
                ->where('invoice_no', 'LIKE', 'VT%')
                ->where(function($query) {
                    $query->whereNotNull('linked_billing_receive_id')
                          ->orWhereNotNull('linked_tax_invoice_id');
                })
                ->count();
            
            $sendLog("Found {$vtWithLinks} VT transactions with linking data", 'info');
            
            $updatedVT = DB::table('transactions')
                ->where('business_id', $business_id)
                ->where('invoice_no', 'LIKE', 'VT%')
                ->update([
                    'linked_billing_receive_id' => null,
                    'linked_tax_invoice_id' => null
                ]);
            $sendLog("[OK] Cleared linking data from {$updatedVT} VT transactions", 'success');

            // Step 3: Delete VT transactions and related lines/payments
            $sendLog('[STEP 3] Deleting VT transactions...', 'info');

            $vtCount = DB::table('transactions')
                ->where('business_id', $business_id)
                ->where('invoice_no', 'LIKE', 'VT%')
                ->count();
            $sendLog("Found {$vtCount} VT transactions to delete", 'info');

            if ($vtCount > 0) {
                $vtIds = DB::table('transactions')
                    ->where('business_id', $business_id)
                    ->where('invoice_no', 'LIKE', 'VT%')
                    ->pluck('id');

                $vtLinesDeleted = DB::table('transaction_sell_lines')
                    ->whereIn('transaction_id', $vtIds)
                    ->delete();
                $sendLog("Deleted {$vtLinesDeleted} VT sell lines", 'info');

                $vtPaymentsDeleted = DB::table('transaction_payments')
                    ->whereIn('transaction_id', $vtIds)
                    ->delete();
                $sendLog("Deleted {$vtPaymentsDeleted} VT payment records", 'info');

                $vtDeleted = DB::table('transactions')
                    ->where('business_id', $business_id)
                    ->where('invoice_no', 'LIKE', 'VT%')
                    ->delete();
                $sendLog("[OK] Deleted {$vtDeleted} VT transactions", 'success');
            }

            // Step 4: Delete products and variations
            $sendLog('[STEP 4] Deleting products and variations...', 'info');

            $productIds = DB::table('products')
                ->where('business_id', $business_id)
                ->pluck('id');

            $productsCount = $productIds->count();
            $sendLog("Found {$productsCount} products to delete", 'info');

            if ($productsCount > 0) {
                $variationIds = DB::table('variations')
                    ->whereIn('product_id', $productIds)
                    ->pluck('id');

                $variationLocationDeleted = DB::table('variation_location_details')
                    ->whereIn('product_id', $productIds)
                    ->delete();
                $sendLog("Deleted {$variationLocationDeleted} variation location records", 'info');

                $variationsDeleted = DB::table('variations')
                    ->whereIn('product_id', $productIds)
                    ->delete();
                $sendLog("Deleted {$variationsDeleted} variations", 'info');

                $productVariationsDeleted = DB::table('product_variations')
                    ->whereIn('product_id', $productIds)
                    ->delete();
                $sendLog("Deleted {$productVariationsDeleted} product variation records", 'info');

                $productsDeleted = DB::table('products')
                    ->where('business_id', $business_id)
                    ->delete();
                $sendLog("[OK] Deleted {$productsDeleted} products", 'success');
            }

            // Step 5: Delete migrated categories
            $sendLog('[STEP 5] Deleting migrated categories...', 'info');

            $categoriesCount = DB::table('categories')
                ->where('business_id', $business_id)
                ->where('category_type', 'product')
                ->count();
            $sendLog("Found {$categoriesCount} categories to delete", 'info');

            $categoriesDeleted = DB::table('categories')
                ->where('business_id', $business_id)
                ->where('category_type', 'product')
                ->delete();
            $sendLog("[OK] Deleted {$categoriesDeleted} categories", 'success');

            // Step 6: Delete contacts
            $sendLog('[STEP 6] Deleting contacts...', 'info');

            $contactsCount = DB::table('contacts')
                ->where('business_id', $business_id)
                ->count();
            $sendLog("Found {$contactsCount} contacts to delete", 'info');

            $contactsDeleted = DB::table('contacts')
                ->where('business_id', $business_id)
                ->delete();
            $sendLog("[OK] Deleted {$contactsDeleted} contacts", 'success');

            // Summary
            $sendLog('', 'info');
            $sendLog('═══════════════════════════════════════', 'info');
            $sendLog('[SUCCESS] CLEANUP COMPLETED!', 'success');
            $sendLog('═══════════════════════════════════════', 'info');
            $sendLog("IPAY Transactions Deleted: {$ipayCount}", 'success');
            $sendLog("VT Links Cleared: {$updatedVT}", 'success');
            $sendLog("VT Transactions Deleted: {$vtCount}", 'success');
            $sendLog("Products Deleted: {$productsCount}", 'success');
            $sendLog("Categories Deleted: {$categoriesDeleted}", 'success');
            $sendLog("Contacts Deleted: {$contactsDeleted}", 'success');
            $sendLog('═══════════════════════════════════════', 'info');
            $sendLog('You can now run a fresh migration.', 'info');

            echo "data: " . json_encode(['message' => 'DONE', 'type' => 'done']) . "\n\n";
            flush();

        } catch (Exception $e) {
            $sendLog('[ERROR] ' . $e->getMessage(), 'error');
            echo "data: " . json_encode(['message' => 'ERROR', 'type' => 'error']) . "\n\n";
            flush();
        }

        exit;
    }

    /**
     * Delete all bill transactions from current business (sell + sell_return) and related rows.
     */
    public function deleteAllBills(Request $request)
    {
        $business_id = session('user.business_id', 1);

        try {
            $result = DB::transaction(function () use ($business_id) {
                $transactionIds = DB::table('transactions')
                    ->where('business_id', $business_id)
                    ->whereIn('type', ['sell', 'sell_return'])
                    ->pluck('id');

                if ($transactionIds->isEmpty()) {
                    return [
                        'transactions_deleted' => 0,
                        'sell_lines_deleted' => 0,
                        'payments_deleted' => 0,
                        'sell_line_purchase_links_deleted' => 0,
                        'cash_register_transactions_deleted' => 0,
                        'account_transactions_by_payment_deleted' => 0,
                        'account_transactions_by_transaction_deleted' => 0,
                        'sync_logs_deleted' => 0,
                        'links_cleared' => [
                            'linked_billing_receive_id' => 0,
                            'linked_tax_invoice_id' => 0,
                            'transfer_parent_id' => 0,
                            'return_parent_id' => 0,
                        ],
                    ];
                }

                $now = now();
                $transactionIdChunks = $transactionIds->chunk(1000);

                $linksCleared = [
                    'linked_billing_receive_id' => 0,
                    'linked_tax_invoice_id' => 0,
                    'transfer_parent_id' => 0,
                    'return_parent_id' => 0,
                ];
                $sellLinePurchaseLinksDeleted = 0;
                $sellLinesDeleted = 0;
                $paymentsDeleted = 0;
                $cashRegisterTransactionsDeleted = 0;
                $accountTransactionsByPaymentDeleted = 0;
                $accountTransactionsByTransactionDeleted = 0;
                $syncLogsDeleted = 0;
                $transactionsDeleted = 0;

                if (Schema::hasColumn('transactions', 'linked_billing_receive_id')) {
                    foreach ($transactionIdChunks as $ids) {
                        $linksCleared['linked_billing_receive_id'] += DB::table('transactions')
                            ->whereIn('linked_billing_receive_id', $ids->all())
                            ->update([
                                'linked_billing_receive_id' => null,
                                'updated_at' => $now,
                            ]);
                    }
                }

                if (Schema::hasColumn('transactions', 'linked_tax_invoice_id')) {
                    foreach ($transactionIdChunks as $ids) {
                        $linksCleared['linked_tax_invoice_id'] += DB::table('transactions')
                            ->whereIn('linked_tax_invoice_id', $ids->all())
                            ->update([
                                'linked_tax_invoice_id' => null,
                                'updated_at' => $now,
                            ]);
                    }
                }

                if (Schema::hasColumn('transactions', 'transfer_parent_id')) {
                    foreach ($transactionIdChunks as $ids) {
                        $linksCleared['transfer_parent_id'] += DB::table('transactions')
                            ->whereIn('transfer_parent_id', $ids->all())
                            ->update([
                                'transfer_parent_id' => null,
                                'updated_at' => $now,
                            ]);
                    }
                }

                if (Schema::hasColumn('transactions', 'return_parent_id')) {
                    foreach ($transactionIdChunks as $ids) {
                        $linksCleared['return_parent_id'] += DB::table('transactions')
                            ->whereIn('return_parent_id', $ids->all())
                            ->update([
                                'return_parent_id' => null,
                                'updated_at' => $now,
                            ]);
                    }
                }

                foreach ($transactionIdChunks as $ids) {
                    $sellLineIds = DB::table('transaction_sell_lines')
                        ->whereIn('transaction_id', $ids->all())
                        ->pluck('id');

                    if ($sellLineIds->isNotEmpty() && Schema::hasTable('transaction_sell_lines_purchase_lines')) {
                        foreach ($sellLineIds->chunk(1000) as $sellLineIdChunk) {
                            $sellLinePurchaseLinksDeleted += DB::table('transaction_sell_lines_purchase_lines')
                                ->whereIn('sell_line_id', $sellLineIdChunk->all())
                                ->delete();
                        }
                    }

                    $sellLinesDeleted += DB::table('transaction_sell_lines')
                        ->whereIn('transaction_id', $ids->all())
                        ->delete();
                }

                foreach ($transactionIdChunks as $ids) {
                    $paymentIds = DB::table('transaction_payments')
                        ->whereIn('transaction_id', $ids->all())
                        ->pluck('id');

                    if ($paymentIds->isNotEmpty() && Schema::hasTable('account_transactions')) {
                        foreach ($paymentIds->chunk(1000) as $paymentIdChunk) {
                            $accountTransactionsByPaymentDeleted += DB::table('account_transactions')
                                ->whereIn('transaction_payment_id', $paymentIdChunk->all())
                                ->delete();
                        }
                    }

                    $paymentsDeleted += DB::table('transaction_payments')
                        ->whereIn('transaction_id', $ids->all())
                        ->delete();
                }

                if (Schema::hasTable('cash_register_transactions')) {
                    foreach ($transactionIdChunks as $ids) {
                        $cashRegisterTransactionsDeleted += DB::table('cash_register_transactions')
                            ->whereIn('transaction_id', $ids->all())
                            ->delete();
                    }
                }

                if (Schema::hasTable('account_transactions')) {
                    foreach ($transactionIdChunks as $ids) {
                        $accountTransactionsByTransactionDeleted += DB::table('account_transactions')
                            ->where(function ($query) use ($ids) {
                                $query->whereIn('transaction_id', $ids->all())
                                    ->orWhereIn('transfer_transaction_id', $ids->all());
                            })
                            ->delete();
                    }
                }

                if (Schema::hasTable('sync_logs') && Schema::hasColumn('sync_logs', 'new_transaction_id')) {
                    foreach ($transactionIdChunks as $ids) {
                        $syncLogsDeleted += DB::table('sync_logs')
                            ->whereIn('new_transaction_id', $ids->all())
                            ->delete();
                    }
                }

                foreach ($transactionIdChunks as $ids) {
                    $transactionsDeleted += DB::table('transactions')
                        ->whereIn('id', $ids->all())
                        ->delete();
                }

                return [
                    'transactions_deleted' => $transactionsDeleted,
                    'sell_lines_deleted' => $sellLinesDeleted,
                    'payments_deleted' => $paymentsDeleted,
                    'sell_line_purchase_links_deleted' => $sellLinePurchaseLinksDeleted,
                    'cash_register_transactions_deleted' => $cashRegisterTransactionsDeleted,
                    'account_transactions_by_payment_deleted' => $accountTransactionsByPaymentDeleted,
                    'account_transactions_by_transaction_deleted' => $accountTransactionsByTransactionDeleted,
                    'sync_logs_deleted' => $syncLogsDeleted,
                    'links_cleared' => $linksCleared,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'All bills deleted successfully. You can run migration again.',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            Log::error('[MIGRATION] Failed to delete all bills', [
                'business_id' => $business_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Delete bills failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete all bill transactions from all businesses (sell + sell_return) and related rows.
     */
    public function deleteAllBillsAllBusinesses(Request $request)
    {
        try {
            $result = DB::transaction(function () {
                $baseQuery = DB::table('transactions')
                    ->whereIn('type', ['sell', 'sell_return']);

                $businessesAffected = (clone $baseQuery)
                    ->distinct('business_id')
                    ->count('business_id');

                $transactionIds = (clone $baseQuery)->pluck('id');

                if ($transactionIds->isEmpty()) {
                    return [
                        'businesses_affected' => 0,
                        'transactions_deleted' => 0,
                        'sell_lines_deleted' => 0,
                        'payments_deleted' => 0,
                        'sell_line_purchase_links_deleted' => 0,
                        'cash_register_transactions_deleted' => 0,
                        'account_transactions_by_payment_deleted' => 0,
                        'account_transactions_by_transaction_deleted' => 0,
                        'sync_logs_deleted' => 0,
                        'links_cleared' => [
                            'linked_billing_receive_id' => 0,
                            'linked_tax_invoice_id' => 0,
                            'transfer_parent_id' => 0,
                            'return_parent_id' => 0,
                        ],
                    ];
                }

                $now = now();
                $transactionIdChunks = $transactionIds->chunk(1000);

                $linksCleared = [
                    'linked_billing_receive_id' => 0,
                    'linked_tax_invoice_id' => 0,
                    'transfer_parent_id' => 0,
                    'return_parent_id' => 0,
                ];
                $sellLinePurchaseLinksDeleted = 0;
                $sellLinesDeleted = 0;
                $paymentsDeleted = 0;
                $cashRegisterTransactionsDeleted = 0;
                $accountTransactionsByPaymentDeleted = 0;
                $accountTransactionsByTransactionDeleted = 0;
                $syncLogsDeleted = 0;
                $transactionsDeleted = 0;

                if (Schema::hasColumn('transactions', 'linked_billing_receive_id')) {
                    foreach ($transactionIdChunks as $ids) {
                        $linksCleared['linked_billing_receive_id'] += DB::table('transactions')
                            ->whereIn('linked_billing_receive_id', $ids->all())
                            ->update([
                                'linked_billing_receive_id' => null,
                                'updated_at' => $now,
                            ]);
                    }
                }

                if (Schema::hasColumn('transactions', 'linked_tax_invoice_id')) {
                    foreach ($transactionIdChunks as $ids) {
                        $linksCleared['linked_tax_invoice_id'] += DB::table('transactions')
                            ->whereIn('linked_tax_invoice_id', $ids->all())
                            ->update([
                                'linked_tax_invoice_id' => null,
                                'updated_at' => $now,
                            ]);
                    }
                }

                if (Schema::hasColumn('transactions', 'transfer_parent_id')) {
                    foreach ($transactionIdChunks as $ids) {
                        $linksCleared['transfer_parent_id'] += DB::table('transactions')
                            ->whereIn('transfer_parent_id', $ids->all())
                            ->update([
                                'transfer_parent_id' => null,
                                'updated_at' => $now,
                            ]);
                    }
                }

                if (Schema::hasColumn('transactions', 'return_parent_id')) {
                    foreach ($transactionIdChunks as $ids) {
                        $linksCleared['return_parent_id'] += DB::table('transactions')
                            ->whereIn('return_parent_id', $ids->all())
                            ->update([
                                'return_parent_id' => null,
                                'updated_at' => $now,
                            ]);
                    }
                }

                foreach ($transactionIdChunks as $ids) {
                    $sellLineIds = DB::table('transaction_sell_lines')
                        ->whereIn('transaction_id', $ids->all())
                        ->pluck('id');

                    if ($sellLineIds->isNotEmpty() && Schema::hasTable('transaction_sell_lines_purchase_lines')) {
                        foreach ($sellLineIds->chunk(1000) as $sellLineIdChunk) {
                            $sellLinePurchaseLinksDeleted += DB::table('transaction_sell_lines_purchase_lines')
                                ->whereIn('sell_line_id', $sellLineIdChunk->all())
                                ->delete();
                        }
                    }

                    $sellLinesDeleted += DB::table('transaction_sell_lines')
                        ->whereIn('transaction_id', $ids->all())
                        ->delete();
                }

                foreach ($transactionIdChunks as $ids) {
                    $paymentIds = DB::table('transaction_payments')
                        ->whereIn('transaction_id', $ids->all())
                        ->pluck('id');

                    if ($paymentIds->isNotEmpty() && Schema::hasTable('account_transactions')) {
                        foreach ($paymentIds->chunk(1000) as $paymentIdChunk) {
                            $accountTransactionsByPaymentDeleted += DB::table('account_transactions')
                                ->whereIn('transaction_payment_id', $paymentIdChunk->all())
                                ->delete();
                        }
                    }

                    $paymentsDeleted += DB::table('transaction_payments')
                        ->whereIn('transaction_id', $ids->all())
                        ->delete();
                }

                if (Schema::hasTable('cash_register_transactions')) {
                    foreach ($transactionIdChunks as $ids) {
                        $cashRegisterTransactionsDeleted += DB::table('cash_register_transactions')
                            ->whereIn('transaction_id', $ids->all())
                            ->delete();
                    }
                }

                if (Schema::hasTable('account_transactions')) {
                    foreach ($transactionIdChunks as $ids) {
                        $accountTransactionsByTransactionDeleted += DB::table('account_transactions')
                            ->where(function ($query) use ($ids) {
                                $query->whereIn('transaction_id', $ids->all())
                                    ->orWhereIn('transfer_transaction_id', $ids->all());
                            })
                            ->delete();
                    }
                }

                if (Schema::hasTable('sync_logs') && Schema::hasColumn('sync_logs', 'new_transaction_id')) {
                    foreach ($transactionIdChunks as $ids) {
                        $syncLogsDeleted += DB::table('sync_logs')
                            ->whereIn('new_transaction_id', $ids->all())
                            ->delete();
                    }
                }

                foreach ($transactionIdChunks as $ids) {
                    $transactionsDeleted += DB::table('transactions')
                        ->whereIn('id', $ids->all())
                        ->delete();
                }

                return [
                    'businesses_affected' => $businessesAffected,
                    'transactions_deleted' => $transactionsDeleted,
                    'sell_lines_deleted' => $sellLinesDeleted,
                    'payments_deleted' => $paymentsDeleted,
                    'sell_line_purchase_links_deleted' => $sellLinePurchaseLinksDeleted,
                    'cash_register_transactions_deleted' => $cashRegisterTransactionsDeleted,
                    'account_transactions_by_payment_deleted' => $accountTransactionsByPaymentDeleted,
                    'account_transactions_by_transaction_deleted' => $accountTransactionsByTransactionDeleted,
                    'sync_logs_deleted' => $syncLogsDeleted,
                    'links_cleared' => $linksCleared,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'All bills deleted for all businesses. You can run migration again.',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            Log::error('[MIGRATION] Failed to delete all bills for all businesses', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Delete all-business bills failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Migrate sell lines (product items) from old POS to new POS
     * Maps products by SKU/code between databases
     */
    public function migrateSellLines()
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '0');

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        echo ": ping\n\n";
        flush();

        $sendLog = function($message, $type = 'info', $progress = null) {
            $data = [
                'message' => $message,
                'type' => $type,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            if ($progress !== null) {
                $data['progress'] = $progress;
            }
            echo "data: " . json_encode($data) . "\n\n";

            if (rand(1, 5) == 1) {
                echo ": ping\n\n";
            }

            flush();

            if (connection_status() != CONNECTION_NORMAL) {
                exit;
            }
        };

        try {
            $sendLog('[START] Starting sell lines migration...', 'info');
            $sendLog('Connecting to old POS database...', 'info');

            config(['database.connections.old_pos' => [
                'driver' => 'mysql',
                'host' => $this->oldDbConfig['host'],
                'port' => $this->oldDbConfig['port'],
                'database' => $this->oldDbConfig['database'],
                'username' => $this->oldDbConfig['username'],
                'password' => $this->oldDbConfig['password'],
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]]);

            DB::purge('old_pos');

            $sendLog('[OK] Connected to old POS database', 'success');

            $business_id = session('user.business_id', 1);
            $sendLog("Using business_id: {$business_id}", 'info');

            $result = $this->migrateSellLinesCore($business_id, function($message, $type = 'info') use ($sendLog) {
                $sendLog($message, $type);
            });

            $sendLog('', 'info');
            $sendLog('═══════════════════════════════════════', 'info');
            $sendLog('[SUCCESS] SELL LINES MIGRATION COMPLETED!', 'success');
            $sendLog('═══════════════════════════════════════', 'info');
            $sendLog("Total docs processed: {$result['total_transactions']}", 'success');
            $sendLog("Documents with migrated lines: {$result['migrated_transactions']}", 'success');
            $sendLog("Total Sell Lines: {$result['items_migrated']}", 'success');
            $sendLog("Skipped (no old sale / no old items): {$result['skipped']}", 'info');
            $sendLog("Products not found: {$result['product_not_found']}", $result['product_not_found'] > 0 ? 'warning' : 'success');
            $sendLog("Errors: {$result['errors']}", $result['errors'] > 0 ? 'warning' : 'success');
            $sendLog('═══════════════════════════════════════', 'info');

            echo "data: " . json_encode(['message' => 'DONE', 'type' => 'done']) . "\n\n";
            flush();

        } catch (Exception $e) {
            $sendLog('[ERROR] ' . $e->getMessage(), 'error');
            $sendLog('Stack trace: ' . $e->getTraceAsString(), 'error');
            echo "data: " . json_encode(['message' => 'ERROR', 'type' => 'error']) . "\n\n";
            flush();
        }

        exit;
    }

    /**
     * Migrate products only, then sync/compare stock Old POS vs New POS by SKU.
     */
    public function migrateProductsOnlyWithStockCompare(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '0');

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        echo ": ping\n\n";
        flush();

        $sendLog = function ($message, $type = 'info', $extra = []) {
            $data = array_merge([
                'message' => $message,
                'type' => $type,
                'timestamp' => date('Y-m-d H:i:s'),
            ], $extra);
            echo "data: " . json_encode($data) . "\n\n";
            flush();

            if (connection_status() != CONNECTION_NORMAL) {
                exit;
            }
        };

        $formatQty = function ($value): string {
            $qty = (float) $value;
            if (abs($qty - floor($qty)) < 0.000001) {
                return (string) (int) round($qty);
            }

            return rtrim(rtrim(number_format($qty, 4, '.', ''), '0'), '.');
        };

        try {
            $sendLog('[START] Products-only migration + stock compare started...', 'info');
            $sendLog('Connecting to old POS database...', 'info');

            config(['database.connections.old_pos' => [
                'driver' => 'mysql',
                'host' => $this->oldDbConfig['host'],
                'port' => $this->oldDbConfig['port'],
                'database' => $this->oldDbConfig['database'],
                'username' => $this->oldDbConfig['username'],
                'password' => $this->oldDbConfig['password'],
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]]);
            DB::purge('old_pos');

            $business_id = session('user.business_id', 1);
            $locationId = (int) (DB::table('business_locations')
                ->where('business_id', $business_id)
                ->orderBy('id')
                ->value('id') ?? 1);

            $sendLog("[OK] Connected. business_id={$business_id}, location_id={$locationId}", 'success');

            $sendLog('[STEP 1] Preparing category mapping...', 'info');
            $categoryMapping = [];
            $categoriesCount = $this->migrateCategories($business_id, function ($message, $type = 'info') use ($sendLog) {
                $sendLog($message, $type);
            }, $categoryMapping);
            $sendLog("[OK] Categories ready. New categories migrated: {$categoriesCount}", 'success');

            $sendLog('[STEP 2] Migrating products (create + update existing)...', 'info');
            $productsCount = $this->migrateProducts($business_id, function ($message, $type = 'info') use ($sendLog) {
                $sendLog($message, $type);
            }, $categoryMapping);
            $sendLog("[OK] Product migration done. New products migrated: {$productsCount}", 'success');

            $sendLog('[STEP 3] Syncing stock and logging Old POS vs New POS comparison...', 'info');

            $productMap = $this->buildProductMapByCode($business_id);
            $total = (int) DB::connection('old_pos')
                ->table('sma_warehouses_products as swp')
                ->join('sma_products as sp', 'swp.product_id', '=', 'sp.id')
                ->where('swp.warehouse_id', 1)
                ->where('swp.quantity', '>', 0)
                ->count();

            $sendLog("Found {$total} stock rows in old POS warehouse_id=1", 'info');

            $processed = 0;
            $synced = 0;
            $matched = 0;
            $mismatched = 0;
            $missing = 0;

            DB::connection('old_pos')
                ->table('sma_warehouses_products as swp')
                ->join('sma_products as sp', 'swp.product_id', '=', 'sp.id')
                ->select('swp.quantity', 'sp.code')
                ->where('swp.warehouse_id', 1)
                ->where('swp.quantity', '>', 0)
                ->orderBy('sp.code')
                ->chunk(200, function ($rows) use (
                    $business_id,
                    $locationId,
                    &$productMap,
                    &$processed,
                    &$synced,
                    &$matched,
                    &$mismatched,
                    &$missing,
                    $total,
                    $sendLog,
                    $formatQty
                ) {
                    foreach ($rows as $row) {
                        $processed++;

                        $sku = $this->normalizeProductCode($row->code ?? '');
                        $oldQty = (float) $row->quantity;

                        if ($sku === '') {
                            $missing++;
                            $sendLog('SKU: [empty] older pos stock = ' . $formatQty($oldQty) . ' , new pos = N/A', 'warning');
                            continue;
                        }

                        $mapping = $productMap[$sku] ?? null;
                        if (!$mapping) {
                            $mapping = $this->resolveProductForCode($business_id, $sku, $productMap, function ($message, $type = 'info') use ($sendLog) {
                                $sendLog($message, $type);
                            });
                        }

                        if (!$mapping || empty($mapping['product_id']) || empty($mapping['variation_id'])) {
                            $missing++;
                            $sendLog("SKU: {$sku} older pos stock = " . $formatQty($oldQty) . ' , new pos = N/A (product not found)', 'warning');
                            continue;
                        }

                        $productId = (int) $mapping['product_id'];
                        $variationId = (int) $mapping['variation_id'];
                        $productVariationId = DB::table('variations')
                            ->where('id', $variationId)
                            ->value('product_variation_id');

                        if (empty($productVariationId)) {
                            $missing++;
                            $sendLog("SKU: {$sku} older pos stock = " . $formatQty($oldQty) . ' , new pos = N/A (variation missing)', 'warning');
                            continue;
                        }

                        DB::table('variation_location_details')->updateOrInsert(
                            [
                                'product_id' => $productId,
                                'product_variation_id' => (int) $productVariationId,
                                'variation_id' => $variationId,
                                'location_id' => $locationId,
                            ],
                            [
                                'qty_available' => $oldQty,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );

                        DB::table('product_locations')->insertOrIgnore([
                            'product_id' => $productId,
                            'location_id' => $locationId,
                        ]);

                        $newQty = (float) (DB::table('variation_location_details')
                            ->where('variation_id', $variationId)
                            ->where('location_id', $locationId)
                            ->value('qty_available') ?? 0);

                        $synced++;
                        if (abs($oldQty - $newQty) < 0.000001) {
                            $matched++;
                            $type = 'success';
                        } else {
                            $mismatched++;
                            $type = 'warning';
                        }

                        $sendLog(
                            "SKU: {$sku} older pos stock = " . $formatQty($oldQty) . ' , new pos = ' . $formatQty($newQty),
                            $type
                        );

                        if ($processed % 50 === 0 || $processed === $total) {
                            $progress = $total > 0 ? round(($processed / $total) * 100, 1) : 100;
                            $sendLog(
                                "Processed {$processed}/{$total} rows (synced {$synced}, matched {$matched}, mismatched {$mismatched}, missing {$missing})",
                                'info',
                                ['progress' => $progress]
                            );
                        }
                    }
                });

            $sendLog('', 'info');
            $sendLog('═══════════════════════════════════════', 'info');
            $sendLog('[SUCCESS] PRODUCTS-ONLY + STOCK COMPARE COMPLETED!', 'success');
            $sendLog("New Products Migrated: {$productsCount}", 'success');
            $sendLog("Stock Rows Processed: {$processed}", 'success');
            $sendLog("Stock Rows Synced: {$synced}", 'success');
            $sendLog("Matched Old vs New: {$matched}", 'success');
            $sendLog("Mismatched Old vs New: {$mismatched}", $mismatched > 0 ? 'warning' : 'success');
            $sendLog("Missing Product/Variation: {$missing}", $missing > 0 ? 'warning' : 'success');
            $sendLog('═══════════════════════════════════════', 'info');

            echo "data: " . json_encode(['message' => 'DONE', 'type' => 'done']) . "\n\n";
            flush();
        } catch (Exception $e) {
            $sendLog('[ERROR] ' . $e->getMessage(), 'error');
            echo "data: " . json_encode(['message' => 'ERROR', 'type' => 'error']) . "\n\n";
            flush();
        }

        exit;
    }
    
    /**
     * Migrate stock from old POS (sma_warehouses_products)
     */
    public function migrateStock(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        
        if (ob_get_level()) ob_end_clean();
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        echo ": ping\n\n";
        flush();

        $sendLog = function($message, $type = 'info', $extra = []) {
            $data = array_merge(['message' => $message, 'type' => $type, 'timestamp' => date('H:i:s')], $extra);
            echo "data: " . json_encode($data) . "\n\n";
            if (ob_get_level()) ob_flush();
            flush();
        };

        try {
            $sendLog('Starting stock migration...', 'info');
            $business_id = 1; // Hardcode business_id to avoid session issues
            
            // Connect to old DB
            config(['database.connections.old_pos' => [
                'driver' => 'mysql',
                'host' => $this->oldDbConfig['host'],
                'port' => $this->oldDbConfig['port'],
                'database' => $this->oldDbConfig['database'],
                'username' => $this->oldDbConfig['username'],
                'password' => $this->oldDbConfig['password'],
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]]);
            DB::purge('old_pos');

            // Count records
            $total = DB::connection('old_pos')->table('sma_warehouses_products')
                ->where('warehouse_id', 1) // Assuming Warehouse 1 -> Location 1
                ->where('quantity', '>', 0)
                ->count();
            
            $sendLog("Found {$total} stock entries to migrate from Warehouse 1", 'info');

            $count = 0;
            $processed = 0;
            $skipped = 0;

            DB::connection('old_pos')->table('sma_warehouses_products')
                ->join('sma_products', 'sma_warehouses_products.product_id', '=', 'sma_products.id')
                ->select('sma_warehouses_products.quantity', 'sma_products.code', 'sma_warehouses_products.product_id')
                ->where('sma_warehouses_products.warehouse_id', 1)
                ->where('sma_warehouses_products.quantity', '>', 0)
                ->orderBy('sma_warehouses_products.product_id')
                ->chunk(200, function($rows) use ($business_id, $sendLog, &$count, &$processed, &$skipped, $total) {
                    foreach ($rows as $row) {
                        // Find product in new DB
                        $product = DB::table('products')
                            ->where('business_id', $business_id)
                            ->where('sku', $row->code)
                            ->first();

                        if ($product) {
                            // Find variation
                            $variation = DB::table('variations')
                                ->where('product_id', $product->id)
                                ->first();

                            if ($variation) {
                                // Update or insert stock (use location_id 4 for this business)
                                DB::table('variation_location_details')->updateOrInsert(
                                    [
                                        'product_id' => $product->id,
                                        'product_variation_id' => $variation->product_variation_id,
                                        'variation_id' => $variation->id,
                                        'location_id' => 4 // Business location
                                    ],
                                    [
                                        'qty_available' => $row->quantity,
                                        'created_at' => now(),
                                        'updated_at' => now()
                                    ]
                                );

                                // Also ensure product is linked to location
                                DB::table('product_locations')->insertOrIgnore([
                                    'product_id' => $product->id,
                                    'location_id' => 4
                                ]);
                                $count++;
                            } else {
                                $skipped++;
                            }
                        } else {
                            $skipped++;
                        }
                        $processed++;
                    }
                    
                    $percentage = $total > 0 ? round(($processed / $total) * 100, 1) : 0;
                    $sendLog("Processed {$processed}/{$total} stock entries ({$count} migrated) - {$percentage}%", 'info', ['progress' => $percentage]);
                });

            $sendLog("Stock migration completed. Migrated: {$count}, Skipped: {$skipped}", 'success');
            echo "data: " . json_encode(['message' => 'DONE', 'type' => 'done']) . "\n\n";
            flush();

        } catch (Exception $e) {
            $sendLog('Error: ' . $e->getMessage(), 'error');
            echo "data: " . json_encode(['message' => 'ERROR', 'type' => 'error']) . "\n\n";
            flush();
        }
    
}

    /**
     * Migrate product images
     * Updates products table with image filenames from old database
     */
    public function migrateProductImages(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        echo ": ping\n\n";
        flush();

        $sendLog = function($message, $type = 'info') {
            $data = json_encode([
                'message' => $message,
                'type' => $type,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            echo "data: {$data}\n\n";
            flush();

            if (connection_status() != CONNECTION_NORMAL) {
                exit;
            }
        };

        try {
            $sendLog('[START] Starting product image migration...', 'info');

            $business_id = session('user.business_id', 1);
            $sendLog("Using business_id: {$business_id}", 'info');

            config(['database.connections.old_pos' => [
                'driver' => 'mysql',
                'host' => $this->oldDbConfig['host'],
                'port' => $this->oldDbConfig['port'],
                'database' => $this->oldDbConfig['database'],
                'username' => $this->oldDbConfig['username'],
                'password' => $this->oldDbConfig['password'],
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]]);
            
            DB::purge('old_pos');
            $sendLog('[OK] Connected to old POS database', 'success');

            $targetDir = public_path('uploads/' . config('constants.product_img_path', 'img'));
            if (!is_dir($targetDir)) {
                @mkdir($targetDir, 0775, true);
            }

            $sourceDirs = array_values(array_filter([
                base_path('../sale-pos-older-version/assets/uploads'),
                base_path('../sale-pos-older-version/uploads'),
                base_path('../sale-pos-older-version/public/uploads/img'),
            ], function ($dir) {
                return is_dir($dir);
            }));

            if (empty($sourceDirs)) {
                $sendLog('[WARNING] No old product image directory detected automatically. Will only use files already present in new uploads/img.', 'warning');
            } else {
                $sendLog('[OK] Image source directories: ' . implode(', ', $sourceDirs), 'info');
            }

            $sendLog('Fetching products with images from old database...', 'info');

            $total = DB::connection('old_pos')
                ->table('sma_products')
                ->whereNotNull('image')
                ->where('image', '!=', '')
                ->where('image', '!=', 'no_image.png')
                ->count();

            $sendLog("Found {$total} products with images to process", 'info');

            if ($total === 0) {
                $sendLog('[OK] No old product images to migrate.', 'success');
                echo "data: " . json_encode(['message' => 'DONE', 'type' => 'done']) . "\n\n";
                flush();
                exit;
            }

            $updated = 0;
            $unchanged = 0;
            $skipped = 0;
            $filesCopied = 0;
            $filesAlreadyPresent = 0;
            $filesMissing = 0;
            $processed = 0;
            $chunkSize = 200;

            DB::connection('old_pos')
                ->table('sma_products')
                ->select('code', 'image')
                ->whereNotNull('image')
                ->where('image', '!=', '')
                ->where('image', '!=', 'no_image.png')
                ->orderBy('id')
                ->chunk($chunkSize, function($oldProducts) use (
                    $business_id,
                    $sendLog,
                    $targetDir,
                    $sourceDirs,
                    &$updated,
                    &$unchanged,
                    &$skipped,
                    &$filesCopied,
                    &$filesAlreadyPresent,
                    &$filesMissing,
                    &$processed,
                    $total
                ) {
                    foreach ($oldProducts as $oldProduct) {
                        $code = $this->normalizeProductCode($oldProduct->code ?? '');
                        $imageName = basename($this->normalizeProductCode($oldProduct->image ?? ''));

                        if ($code === '' || $imageName === '' || strtolower($imageName) === 'no_image.png') {
                            $skipped++;
                            $processed++;
                            continue;
                        }

                        // Match by sku / second_name / sub_sku
                        $product = DB::table('products as p')
                            ->leftJoin('variations as v', 'v.product_id', '=', 'p.id')
                            ->where('p.business_id', $business_id)
                            ->where(function ($q) use ($code) {
                                $q->where('p.sku', $code)
                                    ->orWhere('p.second_name', $code)
                                    ->orWhere('v.sub_sku', $code);
                            })
                            ->select('p.id', 'p.image')
                            ->first();

                        if ($product) {
                            $targetPath = rtrim($targetDir, '/').'/'.$imageName;
                            $hasTargetFile = is_file($targetPath);

                            if ($hasTargetFile) {
                                $filesAlreadyPresent++;
                            } else {
                                $copied = false;
                                foreach ($sourceDirs as $sourceDir) {
                                    $sourcePath = rtrim($sourceDir, '/').'/'.$imageName;
                                    if (is_file($sourcePath)) {
                                        if (@copy($sourcePath, $targetPath)) {
                                            $filesCopied++;
                                            $copied = true;
                                        }
                                        break;
                                    }
                                }

                                if (!$copied && !is_file($targetPath)) {
                                    $filesMissing++;
                                }
                            }

                            // Set DB image only if file exists in new uploads directory
                            if (is_file($targetPath)) {
                                if ($product->image !== $imageName) {
                                    DB::table('products')
                                        ->where('id', $product->id)
                                        ->update([
                                            'image' => $imageName,
                                            'updated_at' => now(),
                                        ]);
                                    $updated++;
                                } else {
                                    $unchanged++;
                                }
                            } else {
                                $skipped++;
                            }
                        } else {
                            $skipped++;
                        }
                        $processed++;
                    }

                    $percentage = $total > 0 ? round(($processed / $total) * 100, 1) : 0;
                    $sendLog(
                        "Processed {$processed}/{$total} image rows ({$updated} updated, {$unchanged} unchanged, {$skipped} skipped, copied {$filesCopied}) - {$percentage}%",
                        'info'
                    );

                    // Keep SSE alive
                    echo ": ping\n\n";
                    flush();
                });

            // Last fallback: if any product still null image but file exists in target with known old mapping, update in bulk
            DB::connection('old_pos')
                ->table('sma_products')
                ->select('code', 'image')
                ->whereNotNull('image')
                ->where('image', '!=', '')
                ->where('image', '!=', 'no_image.png')
                ->orderBy('id')
                ->chunk($chunkSize, function($oldProducts) use ($business_id, $targetDir, &$updated) {
                    foreach ($oldProducts as $oldProduct) {
                        $code = $this->normalizeProductCode($oldProduct->code ?? '');
                        $imageName = basename($this->normalizeProductCode($oldProduct->image ?? ''));
                        if ($code === '' || $imageName === '' || !is_file(rtrim($targetDir, '/').'/'.$imageName)) {
                            continue;
                        }

                        DB::table('products as p')
                            ->leftJoin('variations as v', 'v.product_id', '=', 'p.id')
                            ->where('p.business_id', $business_id)
                            ->where(function ($q) use ($code) {
                                $q->where('p.sku', $code)
                                    ->orWhere('p.second_name', $code)
                                    ->orWhere('v.sub_sku', $code);
                            })
                            ->whereNull('p.image')
                            ->update([
                                'p.image' => $imageName,
                                'p.updated_at' => now(),
                            ]);
                    }
                });

            $sendLog('', 'info');
            $sendLog('═══════════════════════════════════════', 'info');
            $sendLog('[SUCCESS] IMAGE MIGRATION COMPLETED!', 'success');
            $sendLog("Total Old Image Rows: {$total}", 'info');
            $sendLog("DB image fields updated: {$updated}", 'success');
            $sendLog("DB image fields unchanged: {$unchanged}", 'info');
            $sendLog("Files copied from old POS: {$filesCopied}", 'success');
            $sendLog("Files already present in new POS: {$filesAlreadyPresent}", 'info');
            $sendLog("Files missing in both source/target: {$filesMissing}", $filesMissing > 0 ? 'warning' : 'success');
            $sendLog("Skipped rows: {$skipped}", 'warning');
            $sendLog("Target image directory: {$targetDir}", 'info');
            $sendLog('═══════════════════════════════════════', 'info');

            echo "data: " . json_encode(['message' => 'DONE', 'type' => 'done']) . "\n\n";
            flush();

        } catch (Exception $e) {
            $sendLog('[ERROR] ' . $e->getMessage(), 'error');
            echo "data: " . json_encode(['message' => 'ERROR', 'type' => 'error']) . "\n\n";
            flush();
        }

        exit;
    }

    /**
     * Migrate quotations from old POS
     */
    public function migrateQuotations(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        if (ob_get_level()) ob_end_clean();
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        echo ": ping\n\n";
        flush();

        $sendLog = function($message, $type = 'info', $extra = []) {
            $data = array_merge(['message' => $message, 'type' => $type, 'timestamp' => date('H:i:s')], $extra);
            echo "data: " . json_encode($data) . "\n\n";
            if (ob_get_level()) ob_flush();
            flush();

            if (connection_status() != CONNECTION_NORMAL) {
                exit;
            }
        };

        try {
            $sendLog('[START] Starting quotation migration...', 'info');
            $business_id = session('user.business_id', 1);

            config(['database.connections.old_pos' => [
                'driver' => 'mysql',
                'host' => $this->oldDbConfig['host'],
                'port' => $this->oldDbConfig['port'],
                'database' => $this->oldDbConfig['database'],
                'username' => $this->oldDbConfig['username'],
                'password' => $this->oldDbConfig['password'],
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]]);
            DB::purge('old_pos');
            $sendLog('[OK] Connected to old POS database', 'success');

            $result = $this->migrateQuotationsCore($business_id, function($message, $type = 'info') use ($sendLog) {
                $sendLog($message, $type);
            });

            $sendLog('', 'info');
            $sendLog('═══════════════════════════════════════', 'info');
            $sendLog('[SUCCESS] QUOTATION MIGRATION COMPLETED!', 'success');
            $sendLog("Total Quotations (old): {$result['total']}", 'success');
            $sendLog("Migrated Quotations: {$result['migrated']}", 'success');
            $sendLog("Total Items: {$result['items_migrated']}", 'success');
            $sendLog("Skipped: {$result['skipped']}", 'info');
            $sendLog('═══════════════════════════════════════', 'info');

            echo "data: " . json_encode(['message' => 'DONE', 'type' => 'done']) . "\n\n";
            flush();

        } catch (Exception $e) {
            $sendLog('[ERROR] ' . $e->getMessage(), 'error');
            echo "data: " . json_encode(['message' => 'ERROR', 'type' => 'error']) . "\n\n";
            flush();
        }

        exit;
    }

    /**
     * Fix missing tax data for migrated transactions
     */
    public function fixTaxData(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        if (ob_get_level()) ob_end_clean();
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        echo ": ping\n\n";
        flush();

        $sendLog = function($message, $type = 'info', $extra = []) {
            $data = array_merge(['message' => $message, 'type' => $type, 'timestamp' => date('H:i:s')], $extra);
            echo "data: " . json_encode($data) . "\n\n";
            if (ob_get_level()) ob_flush();
            flush();

            if (connection_status() != CONNECTION_NORMAL) {
                exit;
            }
        };

        try {
            $sendLog('[START] Starting fix for migrated tax data...', 'info');
            $business_id = session('user.business_id', 1);
            $taxId = 4; // "VAT @7%" tax rate

            $sendLog('Searching for transactions with missing tax data...', 'info');

            // Find transactions where tax_id is null AND (final_total is approx total_before_tax * 1.07)
            $transactions = DB::table('transactions')
                ->where('business_id', $business_id)
                ->where('type', 'sell')
                ->whereNull('tax_id')
                ->where('tax_amount', 0)
                ->whereRaw('ABS(final_total - total_before_tax * 1.07) < 0.01')
                ->where('total_before_tax', '>', 0)
                ->select('id', 'invoice_no', 'total_before_tax', 'final_total')
                ->get();

            $total = $transactions->count();
            $sendLog("Found {$total} transactions to fix.", 'info');

            if ($total == 0) {
                $sendLog('[OK] No transactions need fixing.', 'success');
                echo "data: " . json_encode(['message' => 'DONE', 'type' => 'done']) . "\n\n";
                flush();
                exit;
            }

            $count = 0;
            $processed = 0;
            foreach ($transactions as $transaction) {
                $expectedTax = $transaction->final_total - $transaction->total_before_tax;

                DB::beginTransaction();
                try {
                    // Update Transaction
                    DB::table('transactions')
                        ->where('id', $transaction->id)
                        ->update([
                            'tax_id' => $taxId,
                            'tax_amount' => $expectedTax,
                            'updated_at' => now()
                        ]);

                    // Update Sell Lines (7% item_tax)
                    DB::table('transaction_sell_lines')
                        ->where('transaction_id', $transaction->id)
                        ->update([
                            'tax_id' => $taxId,
                            'item_tax' => DB::raw('unit_price * 0.07'),
                            'unit_price_inc_tax' => DB::raw('unit_price * 1.07'),
                            'updated_at' => now()
                        ]);

                    DB::commit();
                    $count++;
                } catch (\Exception $e) {
                    DB::rollBack();
                    $sendLog("Error updating {$transaction->invoice_no}: " . $e->getMessage(), 'warning');
                }

                $processed++;
                if ($processed % 50 == 0) {
                    $percentage = round(($processed / $total) * 100, 1);
                    $sendLog("Fixed {$processed}/{$total} records...", 'info', ['progress' => $percentage]);
                }
            }

            $sendLog('', 'info');
            $sendLog('═══════════════════════════════════════', 'info');
            $sendLog('[SUCCESS] TAX DATA FIX COMPLETED!', 'success');
            $sendLog("Total Transactions Fixed: {$count}", 'success');
            $sendLog('═══════════════════════════════════════', 'info');

            echo "data: " . json_encode(['message' => 'DONE', 'type' => 'done']) . "\n\n";
            flush();

        } catch (Exception $e) {
            $sendLog('[ERROR] ' . $e->getMessage(), 'error');
            echo "data: " . json_encode(['message' => 'ERROR', 'type' => 'error']) . "\n\n";
            flush();
        }

        exit;
    }

    /**
     * Migrate units from old POS (sma_units) to new POS (units)
     */
    public function migrateUnits(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        if (ob_get_level()) ob_end_clean();
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        echo ": ping\n\n";
        flush();

        $sendLog = function($message, $type = 'info', $extra = []) {
            $data = array_merge(['message' => $message, 'type' => $type, 'timestamp' => date('H:i:s')], $extra);
            echo "data: " . json_encode($data) . "\n\n";
            if (ob_get_level()) ob_flush();
            flush();

            if (connection_status() != CONNECTION_NORMAL) {
                exit;
            }
        };

        try {
            $sendLog('[START] Starting units migration...', 'info');
            $business_id = session('user.business_id', 1);
            $sendLog("Using business_id: {$business_id}", 'info');

            // Connect to old DB
            config(['database.connections.old_pos' => [
                'driver' => 'mysql',
                'host' => $this->oldDbConfig['host'],
                'port' => $this->oldDbConfig['port'],
                'database' => $this->oldDbConfig['database'],
                'username' => $this->oldDbConfig['username'],
                'password' => $this->oldDbConfig['password'],
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]]);
            DB::purge('old_pos');
            $sendLog('[OK] Connected to old POS database', 'success');

            // Fetch units from old database
            $sendLog('Fetching units from old database (sma_units)...', 'info');

            $oldUnits = DB::connection('old_pos')
                ->table('sma_units')
                ->orderBy('id')
                ->get();

            $total = $oldUnits->count();
            $sendLog("Found {$total} units to migrate", 'info');

            if ($total == 0) {
                $sendLog('[OK] No units to migrate.', 'success');
                echo "data: " . json_encode(['message' => 'DONE', 'type' => 'done']) . "\n\n";
                flush();
                exit;
            }

            $count = 0;
            $skipped = 0;
            $unitMapping = [];

            foreach ($oldUnits as $oldUnit) {
                // Check if unit already exists by name or code
                $existing = DB::table('units')
                    ->where('business_id', $business_id)
                    ->where(function($query) use ($oldUnit) {
                        $query->where('short_name', $oldUnit->code ?? $oldUnit->name)
                              ->orWhere('actual_name', $oldUnit->name);
                    })
                    ->first();

                if ($existing) {
                    // Map old ID to existing new ID
                    $unitMapping[$oldUnit->id] = $existing->id;
                    $skipped++;
                    $sendLog("Skipped (exists): {$oldUnit->name} -> mapped to ID {$existing->id}", 'info');
                    continue;
                }

                // Determine if unit allows decimal (kg, g, liter, ml, etc.)
                $allowDecimal = 0;
                $unitNameLower = strtolower($oldUnit->name ?? '');
                $unitCodeLower = strtolower($oldUnit->code ?? '');
                if (in_array($unitCodeLower, ['kg', 'g', 'l', 'ml', 'liter', 'litre', 'gram', 'kilogram']) ||
                    strpos($unitNameLower, 'kg') !== false ||
                    strpos($unitNameLower, 'gram') !== false ||
                    strpos($unitNameLower, 'liter') !== false ||
                    strpos($unitNameLower, 'litre') !== false) {
                    $allowDecimal = 1;
                }

                // Insert new unit
                $newUnitId = DB::table('units')->insertGetId([
                    'business_id' => $business_id,
                    'actual_name' => $oldUnit->name,
                    'short_name' => $oldUnit->code ?? $oldUnit->name,
                    'allow_decimal' => $allowDecimal,
                    'base_unit_id' => null,
                    'base_unit_multiplier' => null,
                    'created_by' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $unitMapping[$oldUnit->id] = $newUnitId;
                $count++;
                $sendLog("Migrated: {$oldUnit->name} (ID: {$oldUnit->id} -> {$newUnitId})", 'success');
            }

            // Store mapping in session for later use (product migration)
            session(['unit_mapping' => $unitMapping]);

            $sendLog('', 'info');
            $sendLog('═══════════════════════════════════════', 'info');
            $sendLog('[SUCCESS] UNITS MIGRATION COMPLETED!', 'success');
            $sendLog("Total Units: {$total}", 'info');
            $sendLog("Migrated: {$count}", 'success');
            $sendLog("Skipped (already exist): {$skipped}", 'info');
            $sendLog('═══════════════════════════════════════', 'info');

            echo "data: " . json_encode(['message' => 'DONE', 'type' => 'done']) . "\n\n";
            flush();

        } catch (Exception $e) {
            $sendLog('[ERROR] ' . $e->getMessage(), 'error');
            $sendLog('Stack trace: ' . $e->getTraceAsString(), 'error');
            echo "data: " . json_encode(['message' => 'ERROR', 'type' => 'error']) . "\n\n";
            flush();
        }

        exit;
    }

    /**
     * Migrate brands from old POS (sma_brands) to new POS (brands)
     */
    public function migrateBrands(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        if (ob_get_level()) ob_end_clean();
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        echo ": ping\n\n";
        flush();

        $sendLog = function($message, $type = 'info', $extra = []) {
            $data = array_merge(['message' => $message, 'type' => $type, 'timestamp' => date('H:i:s')], $extra);
            echo "data: " . json_encode($data) . "\n\n";
            if (ob_get_level()) ob_flush();
            flush();

            if (connection_status() != CONNECTION_NORMAL) {
                exit;
            }
        };

        try {
            $sendLog('[START] Starting brands migration...', 'info');
            $business_id = session('user.business_id', 1);
            $sendLog("Using business_id: {$business_id}", 'info');

            // Connect to old DB
            config(['database.connections.old_pos' => [
                'driver' => 'mysql',
                'host' => $this->oldDbConfig['host'],
                'port' => $this->oldDbConfig['port'],
                'database' => $this->oldDbConfig['database'],
                'username' => $this->oldDbConfig['username'],
                'password' => $this->oldDbConfig['password'],
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]]);
            DB::purge('old_pos');
            $sendLog('[OK] Connected to old POS database', 'success');

            // Fetch brands from old database
            $sendLog('Fetching brands from old database (sma_brands)...', 'info');

            $oldBrands = DB::connection('old_pos')
                ->table('sma_brands')
                ->orderBy('id')
                ->get();

            $total = $oldBrands->count();
            $sendLog("Found {$total} brands to migrate", 'info');

            if ($total == 0) {
                $sendLog('[OK] No brands to migrate.', 'success');
                echo "data: " . json_encode(['message' => 'DONE', 'type' => 'done']) . "\n\n";
                flush();
                exit;
            }

            $count = 0;
            $skipped = 0;
            $brandMapping = [];

            foreach ($oldBrands as $oldBrand) {
                // Check if brand already exists by name
                $existing = DB::table('brands')
                    ->where('business_id', $business_id)
                    ->where('name', $oldBrand->name)
                    ->first();

                if ($existing) {
                    // Map old ID to existing new ID
                    $brandMapping[$oldBrand->id] = $existing->id;
                    $skipped++;
                    $sendLog("Skipped (exists): {$oldBrand->name} -> mapped to ID {$existing->id}", 'info');
                    continue;
                }

                // Insert new brand
                $newBrandId = DB::table('brands')->insertGetId([
                    'business_id' => $business_id,
                    'name' => $oldBrand->name,
                    'description' => $oldBrand->description ?? null,
                    'created_by' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $brandMapping[$oldBrand->id] = $newBrandId;
                $count++;
                $sendLog("Migrated: {$oldBrand->name} (ID: {$oldBrand->id} -> {$newBrandId})", 'success');
            }

            // Store mapping in session for later use (product migration)
            session(['brand_mapping' => $brandMapping]);

            $sendLog('', 'info');
            $sendLog('═══════════════════════════════════════', 'info');
            $sendLog('[SUCCESS] BRANDS MIGRATION COMPLETED!', 'success');
            $sendLog("Total Brands: {$total}", 'info');
            $sendLog("Migrated: {$count}", 'success');
            $sendLog("Skipped (already exist): {$skipped}", 'info');
            $sendLog('═══════════════════════════════════════', 'info');

            echo "data: " . json_encode(['message' => 'DONE', 'type' => 'done']) . "\n\n";
            flush();

        } catch (Exception $e) {
            $sendLog('[ERROR] ' . $e->getMessage(), 'error');
            $sendLog('Stack trace: ' . $e->getTraceAsString(), 'error');
            echo "data: " . json_encode(['message' => 'ERROR', 'type' => 'error']) . "\n\n";
            flush();
        }

        exit;
    }

    /**
     * Set brand_id = 1 for all existing products
     */
    public function setAllProductsBrand(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '256M');

        if (ob_get_level()) ob_end_clean();
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        echo ": ping\n\n";
        flush();

        $sendLog = function($message, $type = 'info') {
            $data = json_encode([
                'message' => $message,
                'type' => $type,
                'timestamp' => date('H:i:s')
            ]);
            echo "data: {$data}\n\n";
            flush();

            if (connection_status() != CONNECTION_NORMAL) {
                exit;
            }
        };

        try {
            $sendLog('[START] Setting brand_id = 1 for all products...', 'info');
            $business_id = session('user.business_id', 1);
            $sendLog("Using business_id: {$business_id}", 'info');

            $total = DB::table('products')
                ->where('business_id', $business_id)
                ->count();
            $sendLog("Found {$total} products to update", 'info');

            if ($total == 0) {
                $sendLog('[OK] No products to update.', 'success');
                echo "data: " . json_encode(['message' => 'DONE', 'type' => 'done']) . "\n\n";
                flush();
                exit;
            }

            $updated = DB::table('products')
                ->where('business_id', $business_id)
                ->update([
                    'brand_id' => 1,
                    'updated_at' => now()
                ]);

            $sendLog("[SUCCESS] Updated {$updated} products with brand_id = 1", 'success');

            echo "data: " . json_encode(['message' => 'DONE', 'type' => 'done']) . "\n\n";
            flush();

        } catch (Exception $e) {
            $sendLog('[ERROR] ' . $e->getMessage(), 'error');
            echo "data: " . json_encode(['message' => 'ERROR', 'type' => 'error']) . "\n\n";
            flush();
        }

        exit;
    }

    /**
     * Migrate second_name from old POS products to new POS products
     * Updates existing products by matching SKU/code
     */
    public function migrateSecondName(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        if (ob_get_level()) ob_end_clean();
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        echo ": ping\n\n";
        flush();

        $sendLog = function($message, $type = 'info', $extra = []) {
            $data = array_merge(['message' => $message, 'type' => $type, 'timestamp' => date('H:i:s')], $extra);
            echo "data: " . json_encode($data) . "\n\n";
            if (ob_get_level()) ob_flush();
            flush();

            if (connection_status() != CONNECTION_NORMAL) {
                exit;
            }
        };

        try {
            $sendLog('[START] Starting second_name migration...', 'info');
            $business_id = session('user.business_id', 1);
            $sendLog("Using business_id: {$business_id}", 'info');

            // Connect to old DB
            config(['database.connections.old_pos' => [
                'driver' => 'mysql',
                'host' => $this->oldDbConfig['host'],
                'port' => $this->oldDbConfig['port'],
                'database' => $this->oldDbConfig['database'],
                'username' => $this->oldDbConfig['username'],
                'password' => $this->oldDbConfig['password'],
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]]);
            DB::purge('old_pos');
            $sendLog('[OK] Connected to old POS database', 'success');

            // Fetch products with second_name from old database
            $sendLog('Fetching products with second_name from old database...', 'info');

            $total = DB::connection('old_pos')
                ->table('sma_products')
                ->whereNotNull('second_name')
                ->where('second_name', '!=', '')
                ->count();

            $sendLog("Found {$total} products with second_name to migrate", 'info');

            if ($total == 0) {
                $sendLog('[OK] No products with second_name to migrate.', 'success');
                echo "data: " . json_encode(['message' => 'DONE', 'type' => 'done']) . "\n\n";
                flush();
                exit;
            }

            $count = 0;
            $skipped = 0;
            $processed = 0;
            $chunkSize = 200;

            DB::connection('old_pos')
                ->table('sma_products')
                ->select('code', 'second_name')
                ->whereNotNull('second_name')
                ->where('second_name', '!=', '')
                ->orderBy('id')
                ->chunk($chunkSize, function($oldProducts) use ($business_id, $sendLog, &$count, &$skipped, &$processed, $total) {
                    foreach ($oldProducts as $oldProduct) {
                        // Find matching product in new DB by SKU/code
                        $product = DB::table('products')
                            ->where('business_id', $business_id)
                            ->where('sku', $oldProduct->code)
                            ->first();

                        if ($product) {
                            // Update second_name
                            DB::table('products')
                                ->where('id', $product->id)
                                ->update(['second_name' => $oldProduct->second_name]);

                            $count++;
                        } else {
                            $skipped++;
                        }
                        $processed++;
                    }

                    $percentage = $total > 0 ? round(($processed / $total) * 100, 1) : 0;
                    $sendLog("Processed {$processed}/{$total} ({$count} updated, {$skipped} skipped) - {$percentage}%", 'info', ['progress' => $percentage]);

                    // Keep connection alive
                    echo ": ping\n\n";
                    flush();
                });

            $sendLog('', 'info');
            $sendLog('═══════════════════════════════════════', 'info');
            $sendLog('[SUCCESS] SECOND_NAME MIGRATION COMPLETED!', 'success');
            $sendLog("Total Products Scanned: {$total}", 'info');
            $sendLog("Updated: {$count}", 'success');
            $sendLog("Skipped (product not found): {$skipped}", 'info');
            $sendLog('═══════════════════════════════════════', 'info');

            echo "data: " . json_encode(['message' => 'DONE', 'type' => 'done']) . "\n\n";
            flush();

        } catch (Exception $e) {
            $sendLog('[ERROR] ' . $e->getMessage(), 'error');
            $sendLog('Stack trace: ' . $e->getTraceAsString(), 'error');
            echo "data: " . json_encode(['message' => 'ERROR', 'type' => 'error']) . "\n\n";
            flush();
        }

        exit;
    }

    /**
     * Migrate unit_id and brand_id for existing products
     * Updates products by matching SKU/code between old and new database
     */
    public function migrateProductUnitsBrands(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        if (ob_get_level()) ob_end_clean();
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        echo ": ping\n\n";
        flush();

        $sendLog = function($message, $type = 'info', $extra = []) {
            $data = array_merge(['message' => $message, 'type' => $type, 'timestamp' => date('H:i:s')], $extra);
            echo "data: " . json_encode($data) . "\n\n";
            if (ob_get_level()) ob_flush();
            flush();

            if (connection_status() != CONNECTION_NORMAL) {
                exit;
            }
        };

        try {
            $sendLog('[START] Starting product unit & brand migration...', 'info');
            $business_id = session('user.business_id', 1);
            $sendLog("Using business_id: {$business_id}", 'info');

            // Connect to old DB
            config(['database.connections.old_pos' => [
                'driver' => 'mysql',
                'host' => $this->oldDbConfig['host'],
                'port' => $this->oldDbConfig['port'],
                'database' => $this->oldDbConfig['database'],
                'username' => $this->oldDbConfig['username'],
                'password' => $this->oldDbConfig['password'],
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]]);
            DB::purge('old_pos');
            $sendLog('[OK] Connected to old POS database', 'success');

            // Step 1: Build unit mapping (old_id -> new_id)
            $sendLog('[STEP 1] Building unit mapping...', 'info');
            $unitMapping = [];

            $oldUnits = DB::connection('old_pos')->table('sma_units')->get();
            foreach ($oldUnits as $oldUnit) {
                $newUnit = DB::table('units')
                    ->where('business_id', $business_id)
                    ->where(function($query) use ($oldUnit) {
                        $query->where('short_name', $oldUnit->code ?? $oldUnit->name)
                              ->orWhere('actual_name', $oldUnit->name);
                    })
                    ->first();

                if ($newUnit) {
                    $unitMapping[$oldUnit->id] = $newUnit->id;
                }
            }
            $sendLog("[OK] Unit mapping built: " . count($unitMapping) . " units mapped", 'success');

            // Step 2: Build brand mapping (old_id -> new_id)
            $sendLog('[STEP 2] Building brand mapping...', 'info');
            $brandMapping = [];

            $oldBrands = DB::connection('old_pos')->table('sma_brands')->get();
            foreach ($oldBrands as $oldBrand) {
                $oldBrandName = trim((string) ($oldBrand->name ?? ''));
                if ($oldBrandName === '') {
                    continue;
                }
                $normalized = mb_strtolower($oldBrandName);

                $newBrand = DB::table('brands')
                    ->where('business_id', $business_id)
                    ->whereRaw('LOWER(name) = ?', [$normalized])
                    ->first();

                if ($newBrand) {
                    $brandMapping[$oldBrand->id] = $newBrand->id;
                }
            }
            $sendLog("[OK] Brand mapping built: " . count($brandMapping) . " brands mapped", 'success');

            // Step 3: Update products with unit_id and brand_id
            $sendLog('[STEP 3] Updating products with unit_id and brand_id...', 'info');

            $total = DB::connection('old_pos')
                ->table('sma_products')
                ->where(function($query) {
                    $query->whereNotNull('unit')
                          ->orWhereNotNull('brand');
                })
                ->count();

            $sendLog("Found {$total} products to update", 'info');

            if ($total == 0) {
                $sendLog('[OK] No products to update.', 'success');
                echo "data: " . json_encode(['message' => 'DONE', 'type' => 'done']) . "\n\n";
                flush();
                exit;
            }

            $count = 0;
            $skipped = 0;
            $processed = 0;
            $unitUpdated = 0;
            $brandUpdated = 0;
            $chunkSize = 200;

            DB::connection('old_pos')
                ->table('sma_products')
                ->select('code', 'unit', 'brand')
                ->where(function($query) {
                    $query->whereNotNull('unit')
                          ->orWhereNotNull('brand');
                })
                ->orderBy('id')
                ->chunk($chunkSize, function($oldProducts) use ($business_id, $unitMapping, $brandMapping, $sendLog, &$count, &$skipped, &$processed, &$unitUpdated, &$brandUpdated, $total) {
                    foreach ($oldProducts as $oldProduct) {
                        $sku = trim((string) ($oldProduct->code ?? ''));
                        if ($sku === '') {
                            $skipped++;
                            $processed++;
                            continue;
                        }
                        // Find matching product in new DB by SKU/code
                        $product = DB::table('products')
                            ->where('business_id', $business_id)
                            ->where('sku', $sku)
                            ->first();

                        if (!$product) {
                            $variation = DB::table('variations')
                                ->where('sub_sku', $sku)
                                ->first();
                            if ($variation) {
                                $product = DB::table('products')
                                    ->where('business_id', $business_id)
                                    ->where('id', $variation->product_id)
                                    ->first();
                            }
                        }

                        if ($product) {
                            $updateData = [];

                            // Map unit_id
                            if (!empty($oldProduct->unit) && isset($unitMapping[$oldProduct->unit])) {
                                $updateData['unit_id'] = $unitMapping[$oldProduct->unit];
                                $unitUpdated++;
                            }

                            // Map brand_id
                            if (!empty($oldProduct->brand) && isset($brandMapping[$oldProduct->brand])) {
                                $updateData['brand_id'] = $brandMapping[$oldProduct->brand];
                                $brandUpdated++;
                            }

                            if (!empty($updateData)) {
                                $updateData['updated_at'] = now();
                                DB::table('products')
                                    ->where('id', $product->id)
                                    ->update($updateData);
                                $count++;
                            } else {
                                $skipped++;
                            }
                        } else {
                            $skipped++;
                        }
                        $processed++;
                    }

                    $percentage = $total > 0 ? round(($processed / $total) * 100, 1) : 0;
                    $sendLog("Processed {$processed}/{$total} ({$count} updated, {$skipped} skipped) - {$percentage}%", 'info', ['progress' => $percentage]);

                    // Keep connection alive
                    echo ": ping\n\n";
                    flush();
                });

            $sendLog('', 'info');
            $sendLog('═══════════════════════════════════════', 'info');
            $sendLog('[SUCCESS] PRODUCT UNITS & BRANDS MIGRATION COMPLETED!', 'success');
            $sendLog("Total Products Scanned: {$total}", 'info');
            $sendLog("Products Updated: {$count}", 'success');
            $sendLog("Unit IDs Updated: {$unitUpdated}", 'success');
            $sendLog("Brand IDs Updated: {$brandUpdated}", 'success');
            $sendLog("Skipped: {$skipped}", 'info');
            $sendLog('═══════════════════════════════════════', 'info');

            echo "data: " . json_encode(['message' => 'DONE', 'type' => 'done']) . "\n\n";
            flush();

        } catch (Exception $e) {
            $sendLog('[ERROR] ' . $e->getMessage(), 'error');
            $sendLog('Stack trace: ' . $e->getTraceAsString(), 'error');
            echo "data: " . json_encode(['message' => 'ERROR', 'type' => 'error']) . "\n\n";
            flush();
        }

        exit;
    }

    /**
     * Fix contact_type for existing contacts
     * Sets contact_type to 'business' for all contacts where it's NULL
     */
    public function fixContactType(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '256M');

        if (ob_get_level()) ob_end_clean();
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        echo ": ping\n\n";
        flush();

        $sendLog = function($message, $type = 'info', $progress = null) {
            $data = [
                'message' => $message,
                'type' => $type,
                'timestamp' => date('H:i:s')
            ];
            if ($progress !== null) {
                $data['progress'] = $progress;
            }
            echo "data: " . json_encode($data) . "\n\n";
            flush();

            if (connection_status() != CONNECTION_NORMAL) {
                exit;
            }
        };

        try {
            $sendLog('[START] Fixing contact_type for existing contacts...', 'info');
            $business_id = session('user.business_id', 1);
            $sendLog("Using business_id: {$business_id}", 'info');

            // Count contacts with NULL contact_type
            $total = DB::table('contacts')
                ->where('business_id', $business_id)
                ->whereNull('contact_type')
                ->count();

            $sendLog("Found {$total} contacts with NULL contact_type", 'info');

            if ($total == 0) {
                $sendLog('[OK] All contacts already have contact_type set.', 'success');
                echo "data: " . json_encode(['message' => 'DONE', 'type' => 'done']) . "\n\n";
                flush();
                exit;
            }

            // Update contacts to set contact_type = 'business' and supplier_business_name = name
            $updated = DB::table('contacts')
                ->where('business_id', $business_id)
                ->whereNull('contact_type')
                ->update([
                    'contact_type' => 'business',
                    'supplier_business_name' => DB::raw('name'),
                    'updated_at' => now()
                ]);

            $sendLog("[SUCCESS] Updated {$updated} contacts with contact_type = 'business'", 'success');
            $sendLog("Total Contacts Fixed: {$updated}", 'success');

            echo "data: " . json_encode(['message' => 'DONE', 'type' => 'done']) . "\n\n";
            flush();

        } catch (Exception $e) {
            $sendLog('[ERROR] ' . $e->getMessage(), 'error');
            echo "data: " . json_encode(['message' => 'ERROR', 'type' => 'error']) . "\n\n";
            flush();
        }

        exit;
    }

    /**
     * One-time repair for already synced Old→New records.
     * Fixes:
     * - document_type/sub_status based on reference prefix (VT/IPAY/QT)
     * - receiver/location_id based on old warehouse_id mapping
     */
    public function fixSyncedDocumentAndReceiver(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        echo ": ping\n\n";
        flush();

        $sendLog = function($message, $type = 'info', $progress = null) {
            $data = [
                'message' => $message,
                'type' => $type,
                'timestamp' => date('H:i:s')
            ];
            if ($progress !== null) {
                $data['progress'] = $progress;
            }
            echo "data: " . json_encode($data) . "\n\n";
            flush();

            if (connection_status() != CONNECTION_NORMAL) {
                exit;
            }
        };

        try {
            $sendLog('[START] Fixing synced document type + receiver mapping...', 'info');

            // Connect to old database for warehouse/source lookup.
            config(['database.connections.old_pos' => [
                'driver' => 'mysql',
                'host' => $this->oldDbConfig['host'],
                'port' => $this->oldDbConfig['port'],
                'database' => $this->oldDbConfig['database'],
                'username' => $this->oldDbConfig['username'],
                'password' => $this->oldDbConfig['password'],
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]]);
            DB::purge('old_pos');

            $business_id = session('user.business_id', 1);
            $sendLog("Using business_id: {$business_id}", 'info');

            $baseQuery = DB::table('transactions')
                ->where('business_id', $business_id)
                ->where('type', 'sell')
                ->where('sync_source', 'old_pos');

            $total = (clone $baseQuery)->count();
            $sendLog("Found {$total} synced Old→New transactions", 'info');

            if ($total === 0) {
                $sendLog('[OK] No synced records found to fix.', 'success');
                echo "data: " . json_encode(['message' => 'DONE', 'type' => 'done']) . "\n\n";
                flush();
                exit;
            }

            $processed = 0;
            $fixed = 0;
            $docFixed = 0;
            $receiverFixed = 0;
            $skipped = 0;
            $errors = 0;

            (clone $baseQuery)
                ->select('id', 'invoice_no', 'ref_no', 'status', 'sub_status', 'document_type', 'location_id', 'old_pos_sale_id')
                ->orderBy('id')
                ->chunk(200, function ($transactions) use (
                    $sendLog, $total, &$processed, &$fixed, &$docFixed, &$receiverFixed, &$skipped, &$errors
                ) {
                    foreach ($transactions as $transaction) {
                        try {
                            $oldSale = null;
                            if (!empty($transaction->old_pos_sale_id)) {
                                $oldSale = DB::connection('old_pos')
                                    ->table('sma_sales')
                                    ->where('id', $transaction->old_pos_sale_id)
                                    ->select('id', 'reference_no', 'warehouse_id')
                                    ->first();
                            }

                            $referenceNo = trim((string) ($transaction->invoice_no ?: $transaction->ref_no));
                            if ($referenceNo === '' && $oldSale && !empty($oldSale->reference_no)) {
                                $referenceNo = trim((string) $oldSale->reference_no);
                            }

                            [$expectedDocumentType, $expectedSubStatus] = $this->inferDocumentMetadataFromReference(
                                $referenceNo,
                                (string) ($transaction->status ?? 'final')
                            );

                            $updateData = [];
                            $docChanged = false;
                            $receiverChanged = false;

                            $currentDocumentType = $transaction->document_type ?? null;
                            if ($currentDocumentType !== $expectedDocumentType) {
                                $updateData['document_type'] = $expectedDocumentType;
                                $docChanged = true;
                            }

                            $currentSubStatus = $transaction->sub_status ?? null;
                            if ($currentSubStatus !== $expectedSubStatus) {
                                $updateData['sub_status'] = $expectedSubStatus;
                                $docChanged = true;
                            }

                            if ($oldSale && isset($oldSale->warehouse_id)) {
                                $expectedLocationId = $this->mapOldWarehouseToNewLocation((int) $oldSale->warehouse_id);
                                if ((int) $transaction->location_id !== $expectedLocationId) {
                                    $updateData['location_id'] = $expectedLocationId;
                                    $receiverChanged = true;
                                }
                            }

                            if (!empty($updateData)) {
                                $updateData['updated_at'] = now();
                                DB::table('transactions')
                                    ->where('id', $transaction->id)
                                    ->update($updateData);

                                $fixed++;
                                if ($docChanged) {
                                    $docFixed++;
                                }
                                if ($receiverChanged) {
                                    $receiverFixed++;
                                }
                            } else {
                                $skipped++;
                            }
                        } catch (Exception $e) {
                            $errors++;
                            if ($errors <= 10) {
                                $sendLog("Warning (transaction #{$transaction->id}): " . $e->getMessage(), 'warning');
                            }
                        }

                        $processed++;
                    }

                    $progress = $total > 0 ? round(($processed / $total) * 100, 1) : 100;
                    $sendLog(
                        "Processed {$processed}/{$total} (fixed {$fixed}, skipped {$skipped})",
                        'info',
                        $progress
                    );
                });

            $sendLog('', 'info');
            $sendLog('═══════════════════════════════════════', 'info');
            $sendLog('[SUCCESS] SYNCED DATA FIX COMPLETED!', 'success');
            $sendLog("Document Type/Sub-status Fixed: {$docFixed}", 'success');
            $sendLog("Receiver/Location Fixed: {$receiverFixed}", 'success');
            $sendLog("Total Synced Records Fixed: {$fixed}", 'success');
            $sendLog("Skipped (already correct): {$skipped}", 'info');
            $sendLog("Errors: {$errors}", $errors > 0 ? 'warning' : 'success');
            $sendLog('═══════════════════════════════════════', 'info');

            echo "data: " . json_encode(['message' => 'DONE', 'type' => 'done']) . "\n\n";
            flush();
        } catch (Exception $e) {
            $sendLog('[ERROR] ' . $e->getMessage(), 'error');
            echo "data: " . json_encode(['message' => 'ERROR', 'type' => 'error']) . "\n\n";
            flush();
        }

        exit;
    }

    private function inferDocumentMetadataFromReference(string $referenceNo, string $status): array
    {
        $reference = strtoupper(trim($referenceNo));

        if ($reference !== '' && str_starts_with($reference, 'IPAY')) {
            return ['final', null];
        }

        if ($reference !== '' && str_starts_with($reference, 'VT')) {
            return ['proforma', 'proforma'];
        }

        if ($reference !== '' && (str_starts_with($reference, 'QT') || str_starts_with($reference, 'QUO'))) {
            return ['quotation', 'quotation'];
        }

        if ($status === 'draft') {
            return ['proforma', 'proforma'];
        }

        return [null, null];
    }

    private function mapOldWarehouseToNewLocation(int $oldWarehouseId): int
    {
        $mapping = [
            1 => 4,
        ];

        return $mapping[$oldWarehouseId] ?? 4;
    }

    /**
     * Repair historical *-NP... duplicate invoice numbers in new POS.
     * Strategy:
     * - Find sell transactions with invoice_no ending in -NP{digits}[-{hash}]
     * - Resolve base invoice_no by removing suffix
     * - If canonical(base) exists: move payments/links, then delete duplicate when safe
     * - If canonical does not exist: rename duplicate invoice_no/ref_no to base when unique
     */
    public function fixNpDuplicateReferences(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        echo ": ping\n\n";
        flush();

        $sendLog = function($message, $type = 'info', $progress = null) {
            $data = [
                'message' => $message,
                'type' => $type,
                'timestamp' => date('H:i:s')
            ];
            if ($progress !== null) {
                $data['progress'] = $progress;
            }
            echo "data: " . json_encode($data) . "\n\n";
            flush();

            if (connection_status() != CONNECTION_NORMAL) {
                exit;
            }
        };

        try {
            $sendLog('[START] Fixing NP duplicate references...', 'info');
            $business_id = session('user.business_id', 1);
            $sendLog("Using business_id: {$business_id}", 'info');

            $duplicates = DB::table('transactions')
                ->where('business_id', $business_id)
                ->where('type', 'sell')
                ->where('invoice_no', 'LIKE', '%-NP%')
                ->select('id', 'invoice_no', 'ref_no', 'final_total', 'contact_id', 'transaction_date', 'old_pos_sale_id')
                ->orderBy('id')
                ->get();

            $total = $duplicates->count();
            $sendLog("Found {$total} NP-suffixed transactions", 'info');

            if ($total === 0) {
                $sendLog('[OK] No NP duplicates found.', 'success');
                echo "data: " . json_encode(['message' => 'DONE', 'type' => 'done']) . "\n\n";
                flush();
                exit;
            }

            $processed = 0;
            $fixed = 0;
            $renamed = 0;
            $merged = 0;
            $deleted = 0;
            $skipped = 0;
            $errors = 0;

            foreach ($duplicates as $dup) {
                try {
                    DB::beginTransaction();

                    $baseInvoiceNo = preg_replace('/-NP\d+(?:-[A-Fa-f0-9]{6})?$/i', '', (string) $dup->invoice_no);
                    $baseInvoiceNo = trim((string) $baseInvoiceNo);

                    if ($baseInvoiceNo === '' || $baseInvoiceNo === (string) $dup->invoice_no) {
                        $skipped++;
                        DB::commit();
                        $processed++;
                        continue;
                    }

                    $canonical = DB::table('transactions')
                        ->where('business_id', $business_id)
                        ->where('type', 'sell')
                        ->where('invoice_no', $baseInvoiceNo)
                        ->where('id', '!=', $dup->id)
                        ->orderBy('id')
                        ->first();

                    if (!$canonical) {
                        // No canonical transaction exists: rename to base only when unique.
                        $existsWithBase = DB::table('transactions')
                            ->where('business_id', $business_id)
                            ->where('invoice_no', $baseInvoiceNo)
                            ->exists();

                        if (!$existsWithBase) {
                            DB::table('transactions')
                                ->where('id', $dup->id)
                                ->update([
                                    'invoice_no' => $baseInvoiceNo,
                                    'ref_no' => $dup->ref_no === $dup->invoice_no ? $baseInvoiceNo : $dup->ref_no,
                                    'updated_at' => now(),
                                ]);
                            $fixed++;
                            $renamed++;
                        } else {
                            $skipped++;
                        }

                        DB::commit();
                        $processed++;
                        continue;
                    }

                    // Move payments to canonical (de-duplicate by key fields).
                    $dupPayments = DB::table('transaction_payments')
                        ->where('transaction_id', $dup->id)
                        ->get();

                    foreach ($dupPayments as $payment) {
                        $existsPaymentQuery = DB::table('transaction_payments')
                            ->where('transaction_id', $canonical->id)
                            ->where('amount', $payment->amount)
                            ->where('method', $payment->method)
                            ->where('paid_on', $payment->paid_on);

                        if ($payment->payment_ref_no === null) {
                            $existsPaymentQuery->whereNull('payment_ref_no');
                        } else {
                            $existsPaymentQuery->where('payment_ref_no', $payment->payment_ref_no);
                        }

                        $existsPayment = $existsPaymentQuery->exists();

                        if (!$existsPayment) {
                            DB::table('transaction_payments')
                                ->where('id', $payment->id)
                                ->update([
                                    'transaction_id' => $canonical->id,
                                    'updated_at' => now(),
                                ]);
                        } else {
                            DB::table('transaction_payments')->where('id', $payment->id)->delete();
                        }
                    }

                    // Move sell lines only if canonical has none.
                    $dupLineCount = DB::table('transaction_sell_lines')->where('transaction_id', $dup->id)->count();
                    $canonicalLineCount = DB::table('transaction_sell_lines')->where('transaction_id', $canonical->id)->count();

                    if ($dupLineCount > 0 && $canonicalLineCount === 0) {
                        DB::table('transaction_sell_lines')
                            ->where('transaction_id', $dup->id)
                            ->update([
                                'transaction_id' => $canonical->id,
                                'updated_at' => now(),
                            ]);
                    }

                    // Repoint transaction links.
                    DB::table('transactions')
                        ->where('linked_billing_receive_id', $dup->id)
                        ->update(['linked_billing_receive_id' => $canonical->id, 'updated_at' => now()]);
                    DB::table('transactions')
                        ->where('linked_tax_invoice_id', $dup->id)
                        ->update(['linked_tax_invoice_id' => $canonical->id, 'updated_at' => now()]);
                    DB::table('transactions')
                        ->where('transfer_parent_id', $dup->id)
                        ->update(['transfer_parent_id' => $canonical->id, 'updated_at' => now()]);

                    // Preserve old_pos mapping if missing on canonical.
                    if (empty($canonical->old_pos_sale_id) && !empty($dup->old_pos_sale_id)) {
                        DB::table('transactions')
                            ->where('id', $canonical->id)
                            ->update([
                                'old_pos_sale_id' => $dup->old_pos_sale_id,
                                'updated_at' => now(),
                            ]);
                    }

                    $remainingLines = DB::table('transaction_sell_lines')->where('transaction_id', $dup->id)->count();
                    $remainingPayments = DB::table('transaction_payments')->where('transaction_id', $dup->id)->count();

                    if ($remainingLines === 0 && $remainingPayments === 0) {
                        DB::table('transactions')->where('id', $dup->id)->delete();
                        $deleted++;
                        $fixed++;
                        $merged++;
                    } else {
                        // Keep duplicate if still carrying data we did not safely merge.
                        $skipped++;
                    }

                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();
                    $errors++;
                    if ($errors <= 10) {
                        $sendLog("Warning (transaction #{$dup->id}): " . $e->getMessage(), 'warning');
                    }
                }

                $processed++;
                $progress = $total > 0 ? round(($processed / $total) * 100, 1) : 100;
                if ($processed % 20 === 0 || $processed === $total) {
                    $sendLog("Processed {$processed}/{$total} (fixed {$fixed}, skipped {$skipped})", 'info', $progress);
                }
            }

            $sendLog('', 'info');
            $sendLog('═══════════════════════════════════════', 'info');
            $sendLog('[SUCCESS] NP DUPLICATE FIX COMPLETED!', 'success');
            $sendLog("Merged Into Canonical: {$merged}", 'success');
            $sendLog("Renamed To Base: {$renamed}", 'success');
            $sendLog("Deleted Duplicate Rows: {$deleted}", 'success');
            $sendLog("Total NP Duplicates Fixed: {$fixed}", 'success');
            $sendLog("Skipped: {$skipped}", 'info');
            $sendLog("Errors: {$errors}", $errors > 0 ? 'warning' : 'success');
            $sendLog('═══════════════════════════════════════', 'info');

            echo "data: " . json_encode(['message' => 'DONE', 'type' => 'done']) . "\n\n";
            flush();
        } catch (Exception $e) {
            $sendLog('[ERROR] ' . $e->getMessage(), 'error');
            echo "data: " . json_encode(['message' => 'ERROR', 'type' => 'error']) . "\n\n";
            flush();
        }

        exit;
    }

    // ─────────────────────────────────────────────────────────────────
    // AUTO SYNC METHODS
    // ─────────────────────────────────────────────────────────────────

    /**
     * One-time setup: add sync columns to old POS database.
     * Idempotent — safe to run multiple times.
     */
    public function setupSync()
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        echo ": ping\n\n";
        flush();

        $sendLog = function ($message, $type = 'info') {
            $data = json_encode([
                'message'   => $message,
                'type'      => $type,
                'timestamp' => date('Y-m-d H:i:s'),
            ]);
            echo "data: {$data}\n\n";
            flush();
        };

        try {
            $syncService = app(BidirectionalSyncService::class);
            $syncService->runSetup($sendLog);

            echo "data: " . json_encode(['message' => 'DONE', 'type' => 'done']) . "\n\n";
            flush();
        } catch (Exception $e) {
            $sendLog('[ERROR] ' . $e->getMessage(), 'error');
            echo "data: " . json_encode(['message' => 'ERROR', 'type' => 'error']) . "\n\n";
            flush();
        }

        exit;
    }

    /**
     * Manual sync trigger via SSE — streams real-time progress to the browser.
     */
    public function runSync()
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        echo ": ping\n\n";
        flush();

        $sendLog = function ($message, $type = 'info') {
            $data = json_encode([
                'message'   => $message,
                'type'      => $type,
                'timestamp' => date('Y-m-d H:i:s'),
            ]);
            echo "data: {$data}\n\n";
            if (rand(1, 5) == 1) {
                echo ": ping\n\n";
            }
            flush();

            if (connection_status() != CONNECTION_NORMAL) {
                exit;
            }
        };

        try {
            $syncService = app(BidirectionalSyncService::class);
            $limit = max(1, (int) env('SYNC_BATCH_LIMIT', 100));
            $maxCycles = max(1, (int) env('SYNC_MAX_CYCLES', 50));

            if (!$syncService->isSetupDone()) {
                $sendLog('[SYNC] Setup not complete. Running setup automatically...', 'warning');
                $syncService->runSetup($sendLog);
                $sendLog('[SYNC] Setup completed. Continuing with configured sync directions...', 'success');
            }

            $sendLog('[SYNC] Starting sync loop (2-way for Products, Old→New for Payments, 2-way for Quotations/Sales-Billing-Invoice)...', 'info');
            $sendLog("[SYNC] Batch limit per step: {$limit} | Max cycles: {$maxCycles}", 'info');

            $domains = [
                'products' => [
                    'label' => 'Products',
                    'steps' => [
                        'old_to_new' => [
                            'label' => 'Old→New',
                            'methods' => ['syncProductsOldToNew', 'syncProductOldToNew', 'syncOldProductsToNew'],
                        ],
                        'new_to_old' => [
                            'label' => 'New→Old',
                            'methods' => ['syncProductsNewToOld', 'syncProductNewToOld', 'syncNewProductsToOld'],
                        ],
                    ],
                ],
                'quotations' => [
                    'label' => 'Quotations',
                    'steps' => [
                        'old_to_new' => [
                            'label' => 'Old→New',
                            'methods' => ['syncOldQuotesToNew', 'syncQuotationsOldToNew', 'syncQuotationOldToNew'],
                        ],
                        'new_to_old' => [
                            'label' => 'New→Old',
                            'methods' => ['syncNewQuotesToOld', 'syncQuotationsNewToOld', 'syncQuotationNewToOld'],
                        ],
                    ],
                ],
                'sales_billing_invoice' => [
                    'label' => 'Sales/Billing/Invoice',
                    'steps' => [
                        'old_to_new' => [
                            'label' => 'Old→New',
                            'methods' => ['syncSalesBillingInvoiceOldToNew', 'syncOldToNewSales', 'syncOldToNew'],
                        ],
                        'old_to_new_tax_fix' => [
                            'label' => 'Old→New Tax Fix',
                            'methods' => ['syncOldToNewTaxFix'],
                        ],
                        'new_to_old' => [
                            'label' => 'New→Old',
                            'methods' => ['syncSalesBillingInvoiceNewToOld', 'syncNewToOldSales', 'syncNewToOld'],
                        ],
                    ],
                ],
                'payment_updates' => [
                    'label' => 'Payment Updates',
                    'steps' => [
                        'old_to_new' => [
                            'label' => 'Old→New',
                            'methods' => ['syncPaymentUpdatesOldToNew', 'syncPaymentUpdates'],
                        ],
                    ],
                ],
            ];

            $domainResults = [];
            $directionTotals = [
                'old_to_new' => ['synced' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0],
                'new_to_old' => ['synced' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0],
            ];
            $cyclesRun = 0;

            for ($cycle = 1; $cycle <= $maxCycles; $cycle++) {
                $cyclesRun = $cycle;
                $cycleTotals = ['synced' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];

                $sendLog('', 'info');
                $sendLog("════════════ SYNC CYCLE {$cycle}/{$maxCycles} ════════════", 'info');

                foreach ($domains as $domainKey => $domainConfig) {
                    $sendLog('', 'info');
                    $sendLog('───────────────────────────────────────', 'info');
                    $sendLog("[SYNC][Cycle {$cycle}][{$domainConfig['label']}] Starting...", 'info');

                    foreach ($domainConfig['steps'] as $directionKey => $stepConfig) {
                        $result = $this->runSyncServiceStep(
                            $syncService,
                            $stepConfig['methods'],
                            $sendLog,
                            $limit,
                            "[{$domainConfig['label']}] {$stepConfig['label']}"
                        );

                        $directionBucket = str_starts_with($directionKey, 'old_to_new') ? 'old_to_new' : 'new_to_old';
                        if (!isset($domainResults[$domainKey][$directionKey])) {
                            $domainResults[$domainKey][$directionKey] = ['synced' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
                        }

                        foreach (['synced', 'updated', 'skipped', 'failed'] as $metric) {
                            $metricValue = (int) ($result[$metric] ?? 0);
                            $domainResults[$domainKey][$directionKey][$metric] += $metricValue;
                            $directionTotals[$directionBucket][$metric] += $metricValue;
                            $cycleTotals[$metric] += $metricValue;
                        }

                        $sendLog(
                            "[SYNC][Cycle {$cycle}][{$domainConfig['label']}] {$stepConfig['label']} => synced {$result['synced']}, updated {$result['updated']}, skipped {$result['skipped']}, failed {$result['failed']}",
                            $result['failed'] > 0 ? 'warning' : 'success'
                        );
                    }
                }

                $cycleSyncedUpdated = $cycleTotals['synced'] + $cycleTotals['updated'];
                $sendLog(
                    "[SYNC][Cycle {$cycle}] Totals => synced {$cycleTotals['synced']}, updated {$cycleTotals['updated']}, skipped {$cycleTotals['skipped']}, failed {$cycleTotals['failed']}",
                    $cycleTotals['failed'] > 0 ? 'warning' : 'info'
                );

                if ($cycleSyncedUpdated === 0) {
                    $sendLog("[SYNC] No synced/updated rows in cycle {$cycle}. Stopping loop.", 'success');
                    break;
                }

                if ($cycle === $maxCycles) {
                    $sendLog("[SYNC] Reached max cycles ({$maxCycles}). Stopping to avoid endless loop.", 'warning');
                }
            }

            $totalSyncedOldToNew = $directionTotals['old_to_new']['synced'] + $directionTotals['old_to_new']['updated'];
            $totalSyncedNewToOld = $directionTotals['new_to_old']['synced'] + $directionTotals['new_to_old']['updated'];
            $totalSynced = $totalSyncedOldToNew + $totalSyncedNewToOld;
            $totalFailed = $directionTotals['old_to_new']['failed'] + $directionTotals['new_to_old']['failed'];

            $sendLog('', 'info');
            $sendLog('═══════════════════════════════════════', 'info');
            $sendLog("[DONE] Sync run complete after {$cyclesRun} cycle(s): {$totalSynced} synced/updated, {$totalFailed} failed", $totalFailed > 0 ? 'warning' : 'success');
            $sendLog('Final totals by domain/direction:', 'info');
            foreach ($domains as $domainKey => $domainConfig) {
                foreach ($domainConfig['steps'] as $directionKey => $stepConfig) {
                    $r = $domainResults[$domainKey][$directionKey] ?? ['synced' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
                    $sendLog(
                        " - {$domainConfig['label']} {$stepConfig['label']}: synced {$r['synced']}, updated {$r['updated']}, skipped {$r['skipped']}, failed {$r['failed']}",
                        'info'
                    );
                }
            }
            $sendLog("Old→New total: synced {$directionTotals['old_to_new']['synced']}, updated {$directionTotals['old_to_new']['updated']}, skipped {$directionTotals['old_to_new']['skipped']}, failed {$directionTotals['old_to_new']['failed']}", 'info');
            $sendLog("New→Old total: synced {$directionTotals['new_to_old']['synced']}, updated {$directionTotals['new_to_old']['updated']}, skipped {$directionTotals['new_to_old']['skipped']}, failed {$directionTotals['new_to_old']['failed']}", 'info');
            $sendLog("Total Synced Old→New: {$totalSyncedOldToNew}", 'success');
            $sendLog("Total Synced New→Old: {$totalSyncedNewToOld}", 'success');
            $sendLog('═══════════════════════════════════════', 'info');

            echo "data: " . json_encode(['message' => 'DONE', 'type' => 'done']) . "\n\n";
            flush();
        } catch (Exception $e) {
            $sendLog('[ERROR] ' . $e->getMessage(), 'error');
            echo "data: " . json_encode(['message' => 'ERROR', 'type' => 'error']) . "\n\n";
            flush();
        }

        exit;
    }

    /**
     * Token-protected cron endpoint for configured sync rules (no auth session required).
     */
    public function runSyncCron(Request $request)
    {
        $this->assertValidCronSyncToken($request);

        return $this->runSync();
    }

    /**
     * Sync products only (Old→New) as a dedicated cron/manual SSE stream.
     */
    public function runProductSync()
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        echo ": ping\n\n";
        flush();

        $sendLog = function ($message, $type = 'info') {
            $data = json_encode([
                'message'   => $message,
                'type'      => $type,
                'timestamp' => date('Y-m-d H:i:s'),
            ]);
            echo "data: {$data}\n\n";
            flush();

            if (connection_status() != CONNECTION_NORMAL) {
                exit;
            }
        };

        try {
            $syncService = app(BidirectionalSyncService::class);
            $limit = 500;

            if (!$syncService->isSetupDone()) {
                $sendLog('[PRODUCT-SYNC] Setup not complete. Running setup automatically...', 'warning');
                $syncService->runSetup($sendLog);
                $sendLog('[PRODUCT-SYNC] Setup completed. Continuing product sync...', 'success');
            }

            $sendLog('[PRODUCT-SYNC] Starting product sync (Old→New only)...', 'info');

            $oldToNew = $this->runSyncServiceStep(
                $syncService,
                ['syncProductsOldToNew', 'syncProductOldToNew', 'syncOldProductsToNew'],
                $sendLog,
                $limit,
                '[Products] Old→New'
            );

            $oldToNewTotal = $oldToNew['synced'] + $oldToNew['updated'];
            $failed = $oldToNew['failed'];

            $sendLog('', 'info');
            $sendLog('═══════════════════════════════════════', 'info');
            $sendLog(
                "[DONE] Product sync complete: {$oldToNewTotal} Old→New, {$failed} failed",
                $failed > 0 ? 'warning' : 'success'
            );
            $sendLog("Products Old→New: synced {$oldToNew['synced']}, updated {$oldToNew['updated']}, skipped {$oldToNew['skipped']}, failed {$oldToNew['failed']}", 'info');
            $sendLog("Total Synced Products Old→New: {$oldToNewTotal}", 'success');
            $sendLog('═══════════════════════════════════════', 'info');

            echo "data: " . json_encode(['message' => 'DONE', 'type' => 'done']) . "\n\n";
            flush();
        } catch (Exception $e) {
            $sendLog('[ERROR] ' . $e->getMessage(), 'error');
            echo "data: " . json_encode(['message' => 'ERROR', 'type' => 'error']) . "\n\n";
            flush();
        }

        exit;
    }

    /**
     * Token-protected cron endpoint for product sync (no auth session required).
     */
    public function runProductSyncCron(Request $request)
    {
        $this->assertValidCronSyncToken($request);

        return $this->runProductSync();
    }

    /**
     * Sync payment updates for already-synced Old→New bills (SSE stream).
     */
    public function runPaymentSync()
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        echo ": ping\n\n";
        flush();

        $sendLog = function ($message, $type = 'info') {
            $data = json_encode([
                'message'   => $message,
                'type'      => $type,
                'timestamp' => date('Y-m-d H:i:s'),
            ]);
            echo "data: {$data}\n\n";
            flush();

            if (connection_status() != CONNECTION_NORMAL) {
                exit;
            }
        };

        try {
            $syncService = app(BidirectionalSyncService::class);
            $limit = 500;

            if (!$syncService->isSetupDone()) {
                $sendLog('[PAY-SYNC] Setup not complete. Running setup automatically...', 'warning');
                $syncService->runSetup($sendLog);
                $sendLog('[PAY-SYNC] Setup completed. Continuing payment update sync...', 'success');
            }

            $sendLog('[PAY-SYNC] Starting payment update sync (Old→New only)...', 'info');

            $oldToNew = $this->runSyncServiceStep(
                $syncService,
                ['syncPaymentUpdatesOldToNew', 'syncPaymentUpdates'],
                $sendLog,
                $limit,
                '[Payment Updates] Old→New'
            );
            $oldToNewTotal = $oldToNew['synced'] + $oldToNew['updated'];
            $newToOldTotal = 0;
            $failed = $oldToNew['failed'];

            $sendLog('', 'info');
            $sendLog('═══════════════════════════════════════', 'info');
            $sendLog(
                "[DONE] Payment sync complete: {$oldToNewTotal} Old→New, {$newToOldTotal} New→Old, {$failed} failed",
                $failed > 0 ? 'warning' : 'success'
            );
            $sendLog("Payment Updates Old→New: synced {$oldToNew['synced']}, updated {$oldToNew['updated']}, skipped {$oldToNew['skipped']}, failed {$oldToNew['failed']}", 'info');
            $sendLog("Payment Updates New→Old: skipped by configuration", 'info');
            $sendLog("Total Payment Updates Old→New: {$oldToNewTotal}", 'success');
            $sendLog("Total Payment Updates New→Old: {$newToOldTotal}", 'success');
            $sendLog("Total Payment Updates: " . ($oldToNewTotal + $newToOldTotal), 'success');
            $sendLog('═══════════════════════════════════════', 'info');

            echo "data: " . json_encode(['message' => 'DONE', 'type' => 'done']) . "\n\n";
            flush();
        } catch (Exception $e) {
            $sendLog('[ERROR] ' . $e->getMessage(), 'error');
            echo "data: " . json_encode(['message' => 'ERROR', 'type' => 'error']) . "\n\n";
            flush();
        }

        exit;
    }

    /**
     * Token-protected cron endpoint for payment sync (no auth session required).
     */
    public function runPaymentSyncCron(Request $request)
    {
        $this->assertValidCronSyncToken($request);

        return $this->runPaymentSync();
    }

    /**
     * Sync quotations only (Old↔New) as a dedicated SSE stream.
     */
    public function runQuotationSync()
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        echo ": ping\n\n";
        flush();

        $sendLog = function ($message, $type = 'info') {
            $data = json_encode([
                'message'   => $message,
                'type'      => $type,
                'timestamp' => date('Y-m-d H:i:s'),
            ]);
            echo "data: {$data}\n\n";
            flush();

            if (connection_status() != CONNECTION_NORMAL) {
                exit;
            }
        };

        try {
            $syncService = app(BidirectionalSyncService::class);
            $limit = max(1, (int) env('SYNC_BATCH_LIMIT', 100));

            if (!$syncService->isSetupDone()) {
                $sendLog('[QUOTE-SYNC] Setup not complete. Running setup automatically...', 'warning');
                $syncService->runSetup($sendLog);
                $sendLog('[QUOTE-SYNC] Setup completed. Continuing quotation sync...', 'success');
            }

            $sendLog('[QUOTE-SYNC] Starting quotation sync (Old↔New only)...', 'info');

            $oldToNew = $this->runSyncServiceStep(
                $syncService,
                ['syncOldQuotesToNew', 'syncQuotationsOldToNew', 'syncQuotationOldToNew'],
                $sendLog,
                $limit,
                '[Quotations] Old→New'
            );

            $newToOld = $this->runSyncServiceStep(
                $syncService,
                ['syncNewQuotesToOld', 'syncQuotationsNewToOld', 'syncQuotationNewToOld'],
                $sendLog,
                $limit,
                '[Quotations] New→Old'
            );

            $oldToNewTotal = $oldToNew['synced'] + $oldToNew['updated'];
            $newToOldTotal = $newToOld['synced'] + $newToOld['updated'];
            $failed = $oldToNew['failed'] + $newToOld['failed'];

            $sendLog('', 'info');
            $sendLog('═══════════════════════════════════════', 'info');
            $sendLog(
                "[DONE] Quotation sync complete: {$oldToNewTotal} Old→New, {$newToOldTotal} New→Old, {$failed} failed",
                $failed > 0 ? 'warning' : 'success'
            );
            $sendLog("Quotations Old→New: synced {$oldToNew['synced']}, updated {$oldToNew['updated']}, skipped {$oldToNew['skipped']}, failed {$oldToNew['failed']}", 'info');
            $sendLog("Quotations New→Old: synced {$newToOld['synced']}, updated {$newToOld['updated']}, skipped {$newToOld['skipped']}, failed {$newToOld['failed']}", 'info');
            $sendLog("Total Quotation Sync Old→New: {$oldToNewTotal}", 'success');
            $sendLog("Total Quotation Sync New→Old: {$newToOldTotal}", 'success');
            $sendLog("Total Quotations Synced: " . ($oldToNewTotal + $newToOldTotal), 'success');
            $sendLog('═══════════════════════════════════════', 'info');

            echo "data: " . json_encode(['message' => 'DONE', 'type' => 'done']) . "\n\n";
            flush();
        } catch (Exception $e) {
            $sendLog('[ERROR] ' . $e->getMessage(), 'error');
            echo "data: " . json_encode(['message' => 'ERROR', 'type' => 'error']) . "\n\n";
            flush();
        }

        exit;
    }

    /**
     * Return sync status as JSON (used by auto-refreshing status widget in UI).
     */
    public function syncStatus()
    {
        $base = [
            'pending_old_to_new' => 0,
            'pending_new_to_old' => 0,
            'last_sync_at'       => null,
            'last_24h_synced'    => 0,
            'recent_errors'      => [],
            'setup_done'         => false,
        ];

        try {
            $syncService = app(BidirectionalSyncService::class);
            $status = (array) $syncService->getStatus();
            $payload = array_merge($base, $status);

            // If service provides additional status providers, merge them gracefully.
            foreach (['getExtendedStatus', 'getDetailedStatus', 'getDomainStatus', 'getSyncCounters'] as $method) {
                if (!method_exists($syncService, $method)) {
                    continue;
                }
                try {
                    $extra = $syncService->{$method}();
                    if (is_array($extra)) {
                        $payload = array_replace_recursive($payload, $extra);
                    }
                } catch (Exception $e) {
                    // Keep backward compatibility: ignore optional provider failures.
                }
            }

            // Optional scalar counters exposed by future service versions.
            $counterMethods = [
                'getPendingProductsOldToNew'      => 'pending_products_old_to_new',
                'getPendingProductsNewToOld'      => 'pending_products_new_to_old',
                'getPendingQuotationsOldToNew'    => 'pending_quotations_old_to_new',
                'getPendingQuotationsNewToOld'    => 'pending_quotations_new_to_old',
                'getPendingSalesOldToNew'         => 'pending_sales_old_to_new',
                'getPendingSalesNewToOld'         => 'pending_sales_new_to_old',
                'getPendingPaymentsOldToNew'      => 'pending_payments_old_to_new',
                'getPendingPaymentsNewToOld'      => 'pending_payments_new_to_old',
                'getPendingPaymentUpdatesOldToNew'=> 'pending_payment_updates_old_to_new',
                'getPendingPaymentUpdatesNewToOld'=> 'pending_payment_updates_new_to_old',
            ];

            foreach ($counterMethods as $method => $field) {
                if (!method_exists($syncService, $method)) {
                    continue;
                }
                try {
                    $value = $syncService->{$method}();
                    if (is_numeric($value)) {
                        $payload[$field] = (int) $value;
                    }
                } catch (Exception $e) {
                    // Ignore optional counter failures to preserve compatibility.
                }
            }

            // UI policy: only Quotations are allowed New→Old.
            if (array_key_exists('pending_quotations_new_to_old', $payload)) {
                $payload['pending_new_to_old'] = (int) $payload['pending_quotations_new_to_old'];
            }

            return response()->json($payload);
        } catch (Exception $e) {
            return response()->json(array_merge($base, [
                'error'              => $e->getMessage(),
            ]));
        }
    }

    /**
     * Execute first available sync service method from candidates and normalize output.
     */
    private function runSyncServiceStep(
        BidirectionalSyncService $syncService,
        array $methodCandidates,
        callable $sendLog,
        int $limit,
        string $stepLabel
    ): array {
        foreach ($methodCandidates as $method) {
            if (!method_exists($syncService, $method)) {
                continue;
            }

            $sendLog("[SYNC] {$stepLabel}: calling {$method}()", 'info');
            $raw = $this->invokeSyncMethod($syncService, $method, $sendLog, $limit);

            $normalized = [
                'synced'  => (int) ($raw['synced'] ?? $raw['migrated'] ?? 0),
                'updated' => (int) ($raw['updated'] ?? 0),
                'skipped' => (int) ($raw['skipped'] ?? 0),
                'failed'  => (int) ($raw['failed'] ?? 0),
                'method'  => $method,
            ];

            return $normalized;
        }

        $sendLog(
            "[SYNC] {$stepLabel}: skipped (service method not available: " . implode(', ', $methodCandidates) . ')',
            'warning'
        );

        return [
            'synced'  => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed'  => 0,
            'method'  => null,
        ];
    }

    /**
     * Invoke service sync method with compatible argument count.
     */
    private function invokeSyncMethod(BidirectionalSyncService $syncService, string $method, callable $sendLog, int $limit): array
    {
        $reflection = new \ReflectionMethod($syncService, $method);
        $required = $reflection->getNumberOfRequiredParameters();
        $total = $reflection->getNumberOfParameters();

        if ($total >= 2 || $required >= 2) {
            $result = $syncService->{$method}($sendLog, $limit);
        } elseif ($total >= 1 || $required >= 1) {
            $result = $syncService->{$method}($sendLog);
        } else {
            $result = $syncService->{$method}();
        }

        return is_array($result) ? $result : [];
    }
}
