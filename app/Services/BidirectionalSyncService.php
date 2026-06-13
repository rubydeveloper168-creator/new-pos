<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * BidirectionalSyncService
 *
 * Syncs new sales bills between old POS (CodeIgniter/SMA) and new POS (Laravel).
 * - Old → New: picks up sma_sales WHERE synced_to_new_pos=0 AND sync_source IS NULL
 * - New → Old: picks up transactions WHERE type='sell' AND synced_to_old_pos=0 AND sync_source IS NULL
 *
 * Loop prevention: synced records are immediately marked with sync_source and synced flag,
 * so they are never picked up again in the opposite direction.
 */
class BidirectionalSyncService
{
    /**
     * User ID mapping: old POS user ID → new POS user ID
     */
    private array $userIdMapping = [
        4 => 3,
        5 => 18,
    ];

    public function __construct()
    {
        $this->bootOldConnection();
        $this->hydrateLocationMappings();
    }

    /**
     * Ensure the old_pos DB connection uses the correct database.
     * The MigrationUpdateController does this inline; we replicate it here
     * so the service works even if the .env OLD_POS_DB_DATABASE is stale.
     */
    private function bootOldConnection(): void
    {
        config(['database.connections.old_pos' => [
            'driver'    => 'mysql',
            'host'      => env('OLD_POS_DB_HOST', '127.0.0.1'),
            'port'      => env('OLD_POS_DB_PORT', '8889'),
            'database'  => env('OLD_POS_DB_DATABASE', 'rubyshop_co_th_sale_pos'),
            'username'  => env('OLD_POS_DB_USERNAME', 'root'),
            'password'  => env('OLD_POS_DB_PASSWORD', 'root'),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
        ]]);

        DB::purge('old_pos');
    }

    /**
     * Default business and location IDs for new POS
     */
    private int $businessId = 1;
    private int $locationId = 4;

    /**
     * Default biller info for old POS (ID 1 assumed to be the main biller)
     */
    private int $defaultBillerId = 1;
    private int $defaultWarehouseId = 1;

    /**
     * Explicit location mapping to keep receiver/location consistent across systems.
     * old_pos warehouse_id => new_pos location_id
     */
    private array $oldWarehouseToNewLocationMapping = [
        1 => 4,
    ];

    /**
     * new_pos location_id => old_pos warehouse_id
     */
    private array $newLocationToOldWarehouseMapping = [
        4 => 1,
    ];

    /**
     * old_pos warehouse_id => old_pos biller_id
     * Keeps receiver ("ผู้รับ") consistent in old POS when syncing from new POS.
     */
    private array $oldWarehouseToOldBillerMapping = [
        1 => 3,
    ];

    /**
     * Prevent repeated mapping bootstrap per service instance.
     */
    private bool $locationMappingsHydrated = false;

    /**
     * Cached columns for old_pos.sma_sale_items (used for schema-safe inserts).
     */
    private ?array $oldSaleItemColumns = null;
    private ?array $oldQuoteItemColumns = null;
    private ?array $newProductColumns = null;
    private ?array $oldToNewTaxRateIdMap = null;
    private array $businessTaxRatesByBusinessId = [];

    // ─────────────────────────────────────────────────────────────────
    // SETUP: Add sync columns to old POS database
    // ─────────────────────────────────────────────────────────────────

    /**
     * Set up sync columns in both databases.
     * Idempotent: checks if columns already exist before altering.
     * Returns array of log messages.
     */
    public function runSetup(callable $log): void
    {
        $log('Checking old POS database for sync columns...', 'info');

        // Check and add columns to sma_sales
        $salesColumns = $this->getOldTableColumns('sma_sales');

        if (!in_array('synced_to_new_pos', $salesColumns)) {
            DB::connection('old_pos')->statement(
                "ALTER TABLE sma_sales ADD COLUMN synced_to_new_pos TINYINT(1) NOT NULL DEFAULT 0"
            );
            $log('Added synced_to_new_pos column to sma_sales', 'success');
        } else {
            $log('synced_to_new_pos column already exists in sma_sales', 'info');
        }

        if (!in_array('sync_source', $salesColumns)) {
            DB::connection('old_pos')->statement(
                "ALTER TABLE sma_sales ADD COLUMN sync_source VARCHAR(20) NULL DEFAULT NULL COMMENT 'NULL=native, new_pos=synced from new POS'"
            );
            $log('Added sync_source column to sma_sales', 'success');
        } else {
            $log('sync_source column already exists in sma_sales', 'info');
        }

        if (!in_array('new_pos_transaction_id', $salesColumns)) {
            DB::connection('old_pos')->statement(
                "ALTER TABLE sma_sales ADD COLUMN new_pos_transaction_id INT NULL DEFAULT NULL"
            );
            $log('Added new_pos_transaction_id column to sma_sales', 'success');
        } else {
            $log('new_pos_transaction_id column already exists in sma_sales', 'info');
        }

        // Check and add columns to sma_companies
        $companiesColumns = $this->getOldTableColumns('sma_companies');

        if (!in_array('new_pos_contact_id', $companiesColumns)) {
            DB::connection('old_pos')->statement(
                "ALTER TABLE sma_companies ADD COLUMN new_pos_contact_id INT NULL DEFAULT NULL"
            );
            $log('Added new_pos_contact_id column to sma_companies', 'success');
        } else {
            $log('new_pos_contact_id column already exists in sma_companies', 'info');
        }

        // Check and add columns to sma_quotes (bidirectional quote sync)
        $quotesColumns = $this->getOldTableColumns('sma_quotes');
        if (!in_array('synced_to_new_pos', $quotesColumns, true)) {
            DB::connection('old_pos')->statement(
                "ALTER TABLE sma_quotes ADD COLUMN synced_to_new_pos TINYINT(1) NOT NULL DEFAULT 0"
            );
            $log('Added synced_to_new_pos column to sma_quotes', 'success');
        } else {
            $log('synced_to_new_pos column already exists in sma_quotes', 'info');
        }

        if (!in_array('sync_source', $quotesColumns, true)) {
            DB::connection('old_pos')->statement(
                "ALTER TABLE sma_quotes ADD COLUMN sync_source VARCHAR(20) NULL DEFAULT NULL COMMENT 'NULL=native, new_pos=synced from new POS'"
            );
            $log('Added sync_source column to sma_quotes', 'success');
        } else {
            $log('sync_source column already exists in sma_quotes', 'info');
        }

        if (!in_array('new_pos_transaction_id', $quotesColumns, true)) {
            DB::connection('old_pos')->statement(
                "ALTER TABLE sma_quotes ADD COLUMN new_pos_transaction_id INT NULL DEFAULT NULL"
            );
            $log('Added new_pos_transaction_id column to sma_quotes', 'success');
        } else {
            $log('new_pos_transaction_id column already exists in sma_quotes', 'info');
        }

        // Check and add columns to sma_products (bidirectional product sync)
        $oldProductsColumns = $this->getOldTableColumns('sma_products');
        if (!in_array('synced_to_new_pos', $oldProductsColumns, true)) {
            DB::connection('old_pos')->statement(
                "ALTER TABLE sma_products ADD COLUMN synced_to_new_pos TINYINT(1) NOT NULL DEFAULT 0"
            );
            $log('Added synced_to_new_pos column to sma_products', 'success');
        } else {
            $log('synced_to_new_pos column already exists in sma_products', 'info');
        }

        if (!in_array('sync_source', $oldProductsColumns, true)) {
            DB::connection('old_pos')->statement(
                "ALTER TABLE sma_products ADD COLUMN sync_source VARCHAR(20) NULL DEFAULT NULL COMMENT 'NULL=native, new_pos=synced from new POS'"
            );
            $log('Added sync_source column to sma_products', 'success');
        } else {
            $log('sync_source column already exists in sma_products', 'info');
        }

        if (!in_array('new_pos_product_id', $oldProductsColumns, true)) {
            DB::connection('old_pos')->statement(
                "ALTER TABLE sma_products ADD COLUMN new_pos_product_id INT NULL DEFAULT NULL"
            );
            $log('Added new_pos_product_id column to sma_products', 'success');
        } else {
            $log('new_pos_product_id column already exists in sma_products', 'info');
        }

        // Check and add columns to new products table (reverse product sync tracking)
        $newProductsColumns = $this->getNewTableColumns('products');
        if (!in_array('synced_to_old_pos', $newProductsColumns, true)) {
            DB::statement(
                "ALTER TABLE products ADD COLUMN synced_to_old_pos TINYINT(1) NOT NULL DEFAULT 0"
            );
            $log('Added synced_to_old_pos column to products', 'success');
        } else {
            $log('synced_to_old_pos column already exists in products', 'info');
        }

        if (!in_array('sync_source', $newProductsColumns, true)) {
            DB::statement(
                "ALTER TABLE products ADD COLUMN sync_source VARCHAR(20) NULL DEFAULT NULL COMMENT 'NULL=native, old_pos=synced from old POS'"
            );
            $log('Added sync_source column to products', 'success');
        } else {
            $log('sync_source column already exists in products', 'info');
        }

        if (!in_array('old_pos_product_id', $newProductsColumns, true)) {
            DB::statement(
                "ALTER TABLE products ADD COLUMN old_pos_product_id INT NULL DEFAULT NULL"
            );
            $log('Added old_pos_product_id column to products', 'success');
        } else {
            $log('old_pos_product_id column already exists in products', 'info');
        }

        // Mark all EXISTING sma_sales as synced (they were migrated previously)
        $marked = DB::connection('old_pos')
            ->table('sma_sales')
            ->whereNull('sync_source')
            ->where('synced_to_new_pos', 0)
            ->update(['synced_to_new_pos' => 1]);

        $log("Marked {$marked} existing old POS sales as already synced (preventing re-migration)", 'success');

        // Mark all EXISTING new POS transactions as synced
        $markedNew = DB::table('transactions')
            ->whereNull('sync_source')
            ->where('synced_to_old_pos', 0)
            ->update(['synced_to_old_pos' => 1]);

        $log("Marked {$markedNew} existing new POS transactions as already synced", 'success');

        // Mark all EXISTING old POS quotations as synced
        $markedOldQuotes = DB::connection('old_pos')
            ->table('sma_quotes')
            ->whereNull('sync_source')
            ->where('synced_to_new_pos', 0)
            ->update(['synced_to_new_pos' => 1]);

        $log("Marked {$markedOldQuotes} existing old POS quotations as already synced", 'success');

        // Mark all EXISTING old POS products as synced
        $markedOldProducts = DB::connection('old_pos')
            ->table('sma_products')
            ->whereNull('sync_source')
            ->where('synced_to_new_pos', 0)
            ->update(['synced_to_new_pos' => 1]);

        $log("Marked {$markedOldProducts} existing old POS products as already synced", 'success');

        // Mark all EXISTING new POS products as synced
        $markedNewProducts = DB::table('products')
            ->whereNull('sync_source')
            ->where('synced_to_old_pos', 0)
            ->update(['synced_to_old_pos' => 1]);

        $log("Marked {$markedNewProducts} existing new POS products as already synced", 'success');

        $log('Setup complete! Sync is ready to run.', 'success');
    }

    private function getOldTableColumns(string $table): array
    {
        $cols = DB::connection('old_pos')
            ->select("SHOW COLUMNS FROM {$table}");
        return array_column(array_map(fn($c) => (array)$c, $cols), 'Field');
    }

    private function getNewTableColumns(string $table): array
    {
        $cols = DB::select("SHOW COLUMNS FROM {$table}");
        return array_column(array_map(fn($c) => (array)$c, $cols), 'Field');
    }

    private function oldTableHasColumns(string $table, array $requiredColumns): bool
    {
        try {
            $cols = $this->getOldTableColumns($table);
            foreach ($requiredColumns as $col) {
                if (!in_array($col, $cols, true)) {
                    return false;
                }
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    private function newTableHasColumns(string $table, array $requiredColumns): bool
    {
        try {
            $cols = $this->getNewTableColumns($table);
            foreach ($requiredColumns as $col) {
                if (!in_array($col, $cols, true)) {
                    return false;
                }
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Treat native rows as records where sync_source is NULL or empty string.
     */
    private function applyNativeSyncSourceFilter($query, string $column = 'sync_source'): void
    {
        $query->where(function ($q) use ($column) {
            $q->whereNull($column)
                ->orWhere($column, '');
        });
    }

    /**
     * Shared quotation matcher for new POS transactions.
     * Supports legacy rows where type might be quotation/draft.
     */
    private function applyQuotationTransactionFilter($query): void
    {
        $query->where(function ($q) {
            $q->where('type', 'quotation')
                ->orWhere(function ($nested) {
                    $nested->whereIn('type', ['sell', 'draft'])
                        ->where(function ($flags) {
                            $flags->where('sub_status', 'quotation')
                                ->orWhere('document_type', 'quotation')
                                ->orWhere('is_quotation', 1);
                        });
                });
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // SYNC STATUS
    // ─────────────────────────────────────────────────────────────────

    public function getStatus(): array
    {
        $pendingOldToNew = 0;
        $pendingNewToOld = 0;

        try {
            // Check if sync columns exist before querying
            $salesCols = $this->getOldTableColumns('sma_sales');
            if (in_array('synced_to_new_pos', $salesCols)) {
                $oldToNewQuery = DB::connection('old_pos')
                    ->table('sma_sales')
                    ->where('synced_to_new_pos', 0);
                $this->applyNativeSyncSourceFilter($oldToNewQuery);
                $pendingOldToNew = $oldToNewQuery->count();
            }
        } catch (Exception $e) {
            // Columns not set up yet
        }

        try {
            // Only quotations sync New→Old. Sales only go Old→New.
            $newToOldQuery = DB::table('transactions')
                ->where('synced_to_old_pos', 0);
            $this->applyNativeSyncSourceFilter($newToOldQuery);
            $this->applyQuotationTransactionFilter($newToOldQuery);
            $pendingNewToOld = $newToOldQuery->count();
        } catch (Exception $e) {
            // Column not yet migrated
        }

        $lastSync = DB::table('sync_logs')
            ->orderBy('created_at', 'desc')
            ->value('created_at');

        $last24hSynced = DB::table('sync_logs')
            ->where('status', 'success')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $recentErrors = DB::table('sync_logs')
            ->where('status', 'failed')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['direction', 'old_sale_id', 'new_transaction_id', 'error_message', 'created_at']);

        return [
            'pending_old_to_new' => $pendingOldToNew,
            'pending_new_to_old' => $pendingNewToOld,
            'last_sync_at' => $lastSync,
            'last_24h_synced' => $last24hSynced,
            'recent_errors' => $recentErrors,
            'setup_done' => $this->isSetupDone(),
        ];
    }

    public function isSetupDone(): bool
    {
        return $this->oldTableHasColumns('sma_sales', ['synced_to_new_pos', 'sync_source', 'new_pos_transaction_id'])
            && $this->oldTableHasColumns('sma_quotes', ['synced_to_new_pos', 'sync_source', 'new_pos_transaction_id'])
            && $this->oldTableHasColumns('sma_products', ['synced_to_new_pos', 'sync_source', 'new_pos_product_id'])
            && $this->newTableHasColumns('transactions', ['synced_to_old_pos', 'sync_source', 'old_pos_sale_id'])
            && $this->newTableHasColumns('products', ['synced_to_old_pos', 'sync_source', 'old_pos_product_id']);
    }

    public function getPendingQuotationsOldToNew(): int
    {
        try {
            if (!$this->oldTableHasColumns('sma_quotes', ['synced_to_new_pos', 'sync_source'])) {
                return 0;
            }

            $query = DB::connection('old_pos')
                ->table('sma_quotes')
                ->where('synced_to_new_pos', 0);
            $this->applyNativeSyncSourceFilter($query);

            return (int) $query->count();
        } catch (Exception $e) {
            return 0;
        }
    }

    public function getPendingQuotationsNewToOld(): int
    {
        try {
            if (!$this->newTableHasColumns('transactions', ['synced_to_old_pos', 'sync_source'])) {
                return 0;
            }

            $query = DB::table('transactions')
                ->where('synced_to_old_pos', 0);
            $this->applyNativeSyncSourceFilter($query);
            $this->applyQuotationTransactionFilter($query);

            return (int) $query->count();
        } catch (Exception $e) {
            return 0;
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // DIRECTION 1: Old POS → New POS
    // ─────────────────────────────────────────────────────────────────

    public function syncOldToNew(callable $log, int $limit = 50): array
    {
        $log('[OLD→NEW] Checking old POS for new bills...', 'info');

        $pendingSales = DB::connection('old_pos')
            ->table('sma_sales')
            ->where('synced_to_new_pos', 0)
            ->whereNull('sync_source')
            // Keep sync processing stable by business time/reference, not just DB insert id.
            ->orderBy('date')
            ->orderBy('reference_no')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($pendingSales->isEmpty()) {
            $log('[OLD→NEW] No new bills to sync from old POS', 'info');
            return ['synced' => 0, 'failed' => 0];
        }

        $log("[OLD→NEW] Found {$pendingSales->count()} bill(s) to sync", 'info');

        // Get default biller name
        $biller = DB::connection('old_pos')->table('sma_companies')
            ->where('id', $this->defaultBillerId)
            ->first();
        $billerName = $biller->name ?? 'Default Biller';

        $synced = 0;
        $failed = 0;

        foreach ($pendingSales as $oldSale) {
            try {
                // 0. Self-heal: if a transaction was already created for this old sale in a
                //    previous run but the old row was never marked synced (e.g. a crash right
                //    after commit), just re-link it instead of inserting a duplicate.
                $existingTx = DB::table('transactions')
                    ->where('old_pos_sale_id', $oldSale->id)
                    ->first();
                if ($existingTx) {
                    DB::connection('old_pos')->table('sma_sales')
                        ->where('id', $oldSale->id)
                        ->update([
                            'synced_to_new_pos'      => 1,
                            'new_pos_transaction_id' => $existingTx->id,
                        ]);
                    $synced++;
                    $log("[OLD→NEW] Re-linked existing transaction #{$existingTx->id} for bill #{$oldSale->id} (recovered)", 'success');
                    continue;
                }

                DB::beginTransaction();

                // 1. Find or create contact in new POS
                $newContactId = $this->findOrCreateNewContact($oldSale->customer_id, $oldSale->customer ?? '');

                // 2. Map user ID
                $newUserId = $this->mapUserId($oldSale->created_by ?? null);

                // 3. Map statuses
                $newStatus = $this->mapOldStatusToNew($oldSale->sale_status ?? 'completed');
                $newPaymentStatus = $this->mapOldPaymentStatusToNew($oldSale->payment_status ?? 'unpaid');
                $newDocumentType = $this->resolveNewDocumentTypeFromReference($oldSale->reference_no ?? '', $newStatus);
                $newSubStatus = $this->resolveNewSubStatusFromDocumentType($newDocumentType);
                $newLocationId = $this->mapOldWarehouseToNewLocation($oldSale->warehouse_id ?? null);
                $newTaxId = $this->resolveNewTaxIdFromOld(
                    isset($oldSale->order_tax_id) ? (int) $oldSale->order_tax_id : null,
                    (float) ($oldSale->total_tax ?? 0),
                    (float) ($oldSale->total ?? 0),
                    $this->businessId
                );

                // 4. Keep exact old POS reference as canonical (e.g. VT/POS2026/5333)
                $refNo = trim((string) ($oldSale->reference_no ?? ''));
                if ($refNo === '') {
                    throw new Exception("Empty old reference_no for sale #{$oldSale->id}");
                }

                // 5. Create transaction in new POS
                $newTransactionId = DB::table('transactions')->insertGetId([
                    'business_id'       => $this->businessId,
                    'location_id'       => $newLocationId,
                    'type'              => 'sell',
                    'status'            => $newStatus,
                    'sub_status'        => $newSubStatus,
                    'document_type'     => $newDocumentType,
                    'invoice_no'        => $refNo,
                    'ref_no'            => $refNo,
                    'contact_id'        => $newContactId,
                    'transaction_date'  => $oldSale->date ?? now(),
                    'total_before_tax'  => $oldSale->total ?? 0,
                    'tax_id'            => $newTaxId,
                    'tax_amount'        => ($oldSale->total_tax ?? 0),
                    'final_total'       => $oldSale->grand_total ?? 0,
                    'discount_amount'   => $oldSale->total_discount ?? 0,
                    'shipping_charges'  => $oldSale->shipping ?? 0,
                    'payment_status'    => $newPaymentStatus,
                    'created_by'        => $newUserId,
                    'sync_source'       => 'old_pos',
                    'synced_to_old_pos' => 1, // Already in old POS
                    'old_pos_sale_id'   => $oldSale->id,
                    'created_at'        => $oldSale->date ?? now(),
                    'updated_at'        => now(),
                ]);

                // 6. Create sell lines. If any product can't be mapped, fail the whole
                //    bill instead of inserting a partial bill whose header totals no longer
                //    match its line items (silent wrong totals).
                $sellResult = $this->createNewSellLines($oldSale->id, $newTransactionId, $log);
                if (!empty($sellResult['skipped'])) {
                    $missing = implode(', ', $sellResult['skipped']);
                    throw new Exception("Unmapped product(s) [{$missing}] for sale #{$oldSale->id} — bill not synced to avoid wrong totals");
                }

                // 7. Create payments
                $this->createNewPayments($oldSale->id, $newTransactionId, $newUserId, $log);

                // 8. Log success
                DB::table('sync_logs')->insert([
                    'direction'          => 'old_to_new',
                    'old_sale_id'        => $oldSale->id,
                    'new_transaction_id' => $newTransactionId,
                    'ref_no'             => $refNo,
                    'status'             => 'success',
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);

                DB::commit();

                // 9. Mark old sale as synced AFTER the new POS transaction is committed.
                //    Doing this last guarantees we never mark a bill as synced while its new
                //    transaction was rolled back. If this update itself fails, the bill stays
                //    pending and the self-heal check (step 0) re-links on the next run.
                DB::connection('old_pos')->table('sma_sales')
                    ->where('id', $oldSale->id)
                    ->update([
                        'synced_to_new_pos'       => 1,
                        'new_pos_transaction_id'  => $newTransactionId,
                    ]);

                $synced++;
                $log("[OLD→NEW] Synced bill #{$oldSale->id} ({$refNo}) → new transaction #{$newTransactionId}", 'success');

            } catch (Exception $e) {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                $failed++;

                // Mark as synced with error to prevent infinite retries (log the error)
                try {
                    DB::table('sync_logs')->insert([
                        'direction'     => 'old_to_new',
                        'old_sale_id'   => $oldSale->id,
                        'ref_no'        => $oldSale->reference_no ?? null,
                        'status'        => 'failed',
                        'error_message' => $e->getMessage(),
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                } catch (Exception $logErr) {
                    // Ignore logging error
                }

                $log("[OLD→NEW] FAILED bill #{$oldSale->id}: " . $e->getMessage(), 'error');
                Log::error("Sync old→new failed for sale #{$oldSale->id}: " . $e->getMessage());
            }
        }

        $log("[OLD→NEW] Done: {$synced} synced, {$failed} failed", $failed > 0 ? 'warning' : 'success');
        return ['synced' => $synced, 'failed' => $failed];
    }

    private function findOrCreateNewContact(int $oldContactId, string $fallbackName): int
    {
        // The denormalized 'customer' field from sma_sales (fallbackName) is more reliable
        // than following customer_id → company name, because company names can change after sale.
        // Priority: denormalized name > company name > email (with name check) > create

        $oldCompany = DB::connection('old_pos')
            ->table('sma_companies')
            ->where('id', $oldContactId)
            ->first();

        // Determine the canonical customer name to search for
        // Prefer the denormalized sale name; fall back to current company name
        $saleCustomerName = trim($fallbackName);
        $companyName      = $oldCompany ? trim($oldCompany->name) : '';
        $searchName       = $saleCustomerName ?: $companyName ?: 'Walk-in Customer';
        $mappedContactType = $this->resolveNewContactTypeFromOldCompany($oldCompany);
        $supplierBusinessName = $this->resolveNewSupplierBusinessNameFromOldCompany($oldCompany, $searchName, $mappedContactType);
        $normalizedPhone = $this->normalizeRequiredContactMobile($oldCompany->phone ?? null);

        // 1. Cached mapping on the company record (only trust if names still agree)
        if ($oldCompany && !empty($oldCompany->new_pos_contact_id)) {
            $cached = DB::table('contacts')->where('id', $oldCompany->new_pos_contact_id)->first();
            if ($cached && $cached->name === $searchName) {
                $this->syncNewContactProfileFromOldCompany($oldCompany->new_pos_contact_id, $oldCompany, $searchName);
                return $oldCompany->new_pos_contact_id;
            }
            // Cache is stale or mismatched — fall through to re-match by name
        }

        // 2. Match by the denormalized sale customer name (primary — most accurate)
        if ($saleCustomerName) {
            $contact = DB::table('contacts')
                ->where('business_id', $this->businessId)
                ->where('name', $saleCustomerName)
                ->first();
            if ($contact) {
                $this->cacheOldContactMapping($oldContactId, $contact->id);
                $this->syncNewContactProfileFromOldCompany($contact->id, $oldCompany, $searchName);
                return $contact->id;
            }
        }

        // 3. Match by current company name (secondary — in case denormalized name differs slightly)
        if ($companyName && $companyName !== $saleCustomerName) {
            $contact = DB::table('contacts')
                ->where('business_id', $this->businessId)
                ->where('name', $companyName)
                ->first();
            if ($contact) {
                $this->cacheOldContactMapping($oldContactId, $contact->id);
                $this->syncNewContactProfileFromOldCompany($contact->id, $oldCompany, $searchName);
                return $contact->id;
            }
        }

        // 4. Match by email WITH name verification (no phone — too many false positives)
        if ($oldCompany && !empty($oldCompany->email)) {
            $contact = DB::table('contacts')
                ->where('business_id', $this->businessId)
                ->where('email', $oldCompany->email)
                ->where('name', $searchName)
                ->first();
            if ($contact) {
                $this->cacheOldContactMapping($oldContactId, $contact->id);
                $this->syncNewContactProfileFromOldCompany($contact->id, $oldCompany, $searchName);
                return $contact->id;
            }
        }

        // 5. Create new contact using the denormalized sale name (not company name)
        $newContactId = DB::table('contacts')->insertGetId([
            'business_id'           => $this->businessId,
            'type'                  => 'customer',
            'contact_type'          => $mappedContactType,
            'supplier_business_name'=> $supplierBusinessName,
            'name'                  => $searchName,
            'email'                 => $oldCompany->email ?? null,
            'mobile'                => $normalizedPhone,
            'address_line_1'        => $oldCompany->address ?? null,
            'city'                  => $oldCompany->city ?? null,
            'tax_number'            => $oldCompany->vat_no ?? null,
            'created_by'            => 1,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
        $this->cacheOldContactMapping($oldContactId, $newContactId);
        return $newContactId;
    }

    private function resolveNewContactTypeFromOldCompany($oldCompany): string
    {
        if (!$oldCompany) {
            return 'individual';
        }

        $companyLabel = trim((string) ($oldCompany->company ?? ''));
        return $companyLabel !== '' ? 'business' : 'individual';
    }

    private function resolveNewSupplierBusinessNameFromOldCompany($oldCompany, string $searchName, string $contactType): ?string
    {
        if ($contactType !== 'business') {
            return null;
        }

        $companyLabel = trim((string) ($oldCompany->company ?? ''));
        if ($companyLabel !== '') {
            return $companyLabel;
        }

        return $searchName !== '' ? $searchName : null;
    }

    private function normalizeRequiredContactMobile(?string $phone): string
    {
        $normalized = trim((string) $phone);

        return $normalized !== '' ? $normalized : '-';
    }

    private function syncNewContactProfileFromOldCompany(int $newContactId, $oldCompany, string $searchName): void
    {
        if (!$oldCompany) {
            return;
        }

        $contactType = $this->resolveNewContactTypeFromOldCompany($oldCompany);
        $supplierBusinessName = $this->resolveNewSupplierBusinessNameFromOldCompany($oldCompany, $searchName, $contactType);

        $updates = [
            'contact_type' => $contactType,
            'supplier_business_name' => $supplierBusinessName,
            'updated_at' => now(),
        ];

        // Keep contact name aligned with the sale/customer name used in sync matching.
        if ($searchName !== '') {
            $updates['name'] = $searchName;
        }

        if (!empty($oldCompany->email)) {
            $updates['email'] = $oldCompany->email;
        }
        $normalizedPhone = $this->normalizeRequiredContactMobile($oldCompany->phone ?? null);
        if ($normalizedPhone !== '-') {
            $updates['mobile'] = $normalizedPhone;
        }
        if (!empty($oldCompany->address)) {
            $updates['address_line_1'] = $oldCompany->address;
        }
        if (!empty($oldCompany->city)) {
            $updates['city'] = $oldCompany->city;
        }
        if (!empty($oldCompany->vat_no)) {
            $updates['tax_number'] = $oldCompany->vat_no;
        }

        DB::table('contacts')
            ->where('id', $newContactId)
            ->update($updates);
    }

    private function cacheOldContactMapping(int $oldContactId, int $newContactId): void
    {
        try {
            DB::connection('old_pos')->table('sma_companies')
                ->where('id', $oldContactId)
                ->update(['new_pos_contact_id' => $newContactId]);
        } catch (Exception $e) {
            // Column may not exist yet (before setup), ignore
        }
    }

    /**
     * Insert sell lines for a transaction from the old POS sale items.
     *
     * @return array{inserted:int, skipped:array<int,string>} Count inserted and the
     *         product codes that could not be mapped to a new-POS variation.
     */
    private function createNewSellLines(int $oldSaleId, int $newTransactionId, callable $log): array
    {
        $items = DB::connection('old_pos')
            ->table('sma_sale_items')
            ->where('sale_id', $oldSaleId)
            ->get();

        $inserted = 0;
        $skipped = [];

        foreach ($items as $item) {
            // Find variation by SKU code
            $variation = DB::table('variations as v')
                ->join('products as p', 'p.id', '=', 'v.product_id')
                ->where('v.sub_sku', $item->product_code)
                ->select('v.id as variation_id', 'p.id as product_id', 'p.name as product_name')
                ->first();

            if (!$variation) {
                // Try matching by product name
                $variation = DB::table('variations as v')
                    ->join('products as p', 'p.id', '=', 'v.product_id')
                    ->where('p.name', $item->product_name)
                    ->select('v.id as variation_id', 'p.id as product_id', 'p.name as product_name')
                    ->first();
            }

            if (!$variation) {
                $skipped[] = (string) ($item->product_code ?? $item->product_name ?? 'unknown');
                $log("[OLD→NEW] Warning: product '{$item->product_code}' not found in new POS", 'warning');
                continue;
            }

            // Map fields to match transaction_sell_lines schema (same as migrateSellLines in MigrationUpdateController)
            $unitPrice         = $item->net_unit_price ?: ($item->unit_price ?? 0);
            $unitPriceIncTax   = $item->net_unit_price ?? $item->unit_price ?? 0;
            $discountAmount    = $item->item_discount ?? 0;
            $discountType      = $discountAmount > 0 ? 'fixed' : 'fixed';
            $lineBaseAmount    = (float) ($unitPrice * ($item->quantity ?? 1));
            $lineTaxId         = $this->resolveNewTaxIdFromOld(
                isset($item->tax_rate_id) ? (int) $item->tax_rate_id : null,
                (float) ($item->item_tax ?? 0),
                $lineBaseAmount,
                $this->businessId
            );

            DB::table('transaction_sell_lines')->insert([
                'transaction_id'             => $newTransactionId,
                'product_id'                 => $variation->product_id,
                'variation_id'               => $variation->variation_id,
                'quantity'                   => $item->quantity ?? 1,
                'unit_price_before_discount' => $item->unit_price ?? 0,
                'unit_price'                 => $unitPrice,
                'unit_price_inc_tax'         => $unitPriceIncTax,
                'item_tax'                   => $item->item_tax ?? 0,
                'tax_id'                     => $lineTaxId,
                'line_discount_type'         => $discountType,
                'line_discount_amount'       => $discountAmount,
                'sell_line_note'             => $item->comment ?? null,
                'created_at'                 => now(),
                'updated_at'                 => now(),
            ]);

            $inserted++;
        }

        return ['inserted' => $inserted, 'skipped' => $skipped];
    }

    private function createNewPayments(int $oldSaleId, int $newTransactionId, int $userId, callable $log): void
    {
        $payments = DB::connection('old_pos')
            ->table('sma_payments')
            ->where('sale_id', $oldSaleId)
            ->get();

        foreach ($payments as $payment) {
            $method = $this->mapOldPaymentMethodToNew($payment->paid_by ?? 'cash');

            DB::table('transaction_payments')->insert([
                'transaction_id'  => $newTransactionId,
                'business_id'     => $this->businessId,
                'amount'          => $payment->amount ?? 0,
                'method'          => $method,
                'paid_on'         => $payment->date ?? now(),
                'payment_ref_no'  => $payment->reference_no ?? null,
                'cheque_number'   => $payment->cheque_no ?? null,
                'note'            => $payment->note ?? null,
                'created_by'      => $this->mapUserId($payment->created_by ?? null),
                'created_at'      => $payment->date ?? now(),
                'updated_at'      => now(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // DIRECTION 1b: Old POS → New POS (propagate edits to already-synced bills)
    // ─────────────────────────────────────────────────────────────────

    /**
     * Detect edits made to bills that were already synced old→new (line items,
     * quantities, prices, totals, discount, tax, status, or payments) and rebuild
     * the matching new-POS transaction so both systems stay in sync.
     *
     * Change detection compares a content signature of the old sale against the new
     * transaction. The signature covers header totals, line/payment aggregates, mapped
     * statuses, and the customer name, so any of those edits trigger a rebuild.
     */
    public function syncOldToNewUpdates(callable $log, int $limit = 200): array
    {
        $log('[UPDATE-SYNC] Checking old POS for edits on already-synced bills...', 'info');

        // Sweep ALL synced bills over successive runs using a forward id cursor, so edits
        // to older bills are eventually detected — not only the most recent window. The
        // `id > cursor ORDER BY id` range scan uses the primary key and stays cheap even
        // on large tables, so we use a generous page size regardless of the create limit.
        $pageSize = max($limit, 300);
        $cursor = $this->getSyncCursor('old_to_new_update');

        $oldSales = DB::connection('old_pos')
            ->table('sma_sales')
            ->where('synced_to_new_pos', 1)
            ->whereNotNull('new_pos_transaction_id')
            ->where('id', '>', $cursor)
            ->orderBy('id')
            ->limit($pageSize)
            ->get();

        // Advance/wrap the cursor BEFORE processing so a row that always fails can't pin
        // the sweep in place forever (failures are logged and retried on the next pass).
        if ($oldSales->count() < $pageSize) {
            $this->setSyncCursor('old_to_new_update', 0); // reached the end → restart sweep
        } else {
            $this->setSyncCursor('old_to_new_update', (int) $oldSales->max('id'));
        }

        if ($oldSales->isEmpty()) {
            $log('[UPDATE-SYNC] No synced bills in this page (cursor wrapped to start)', 'info');
            return ['synced' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $log("[UPDATE-SYNC] Checking {$oldSales->count()} synced bill(s) (from id > {$cursor}) for edits...", 'info');

        $updated = 0;
        $skipped = 0;
        $failed  = 0;

        foreach ($oldSales as $oldSale) {
            try {
                $newTxId = (int) $oldSale->new_pos_transaction_id;
                $newTx = DB::table('transactions')->where('id', $newTxId)->first();
                if (!$newTx) {
                    $skipped++;
                    continue;
                }

                // No change → nothing to do.
                if ($this->buildOldSaleSignature($oldSale) === $this->buildNewTransactionSignature($newTx)) {
                    $skipped++;
                    continue;
                }

                DB::beginTransaction();

                $newContactId   = $this->findOrCreateNewContact($oldSale->customer_id, $oldSale->customer ?? '');
                $newUserId      = $this->mapUserId($oldSale->created_by ?? null);
                $newStatus      = $this->mapOldStatusToNew($oldSale->sale_status ?? 'completed');
                $newPaymentStat = $this->mapOldPaymentStatusToNew($oldSale->payment_status ?? 'unpaid');
                $newDocType     = $this->resolveNewDocumentTypeFromReference($oldSale->reference_no ?? '', $newStatus);
                $newSubStatus   = $this->resolveNewSubStatusFromDocumentType($newDocType);
                $newLocationId  = $this->mapOldWarehouseToNewLocation($oldSale->warehouse_id ?? null);
                $newTaxId       = $this->resolveNewTaxIdFromOld(
                    isset($oldSale->order_tax_id) ? (int) $oldSale->order_tax_id : null,
                    (float) ($oldSale->total_tax ?? 0),
                    (float) ($oldSale->total ?? 0),
                    $this->businessId
                );

                $refNo = trim((string) ($oldSale->reference_no ?? ''));
                if ($refNo === '') {
                    throw new Exception("Empty old reference_no for sale #{$oldSale->id}");
                }

                // 1. Update the transaction header.
                DB::table('transactions')->where('id', $newTxId)->update([
                    'location_id'      => $newLocationId,
                    'status'           => $newStatus,
                    'sub_status'       => $newSubStatus,
                    'document_type'    => $newDocType,
                    'invoice_no'       => $refNo,
                    'ref_no'           => $refNo,
                    'contact_id'       => $newContactId,
                    'transaction_date' => $oldSale->date ?? $newTx->transaction_date,
                    'total_before_tax' => $oldSale->total ?? 0,
                    'tax_id'           => $newTaxId,
                    'tax_amount'       => $oldSale->total_tax ?? 0,
                    'final_total'      => $oldSale->grand_total ?? 0,
                    'discount_amount'  => $oldSale->total_discount ?? 0,
                    'shipping_charges' => $oldSale->shipping ?? 0,
                    'payment_status'   => $newPaymentStat,
                    'updated_at'       => now(),
                ]);

                // 2. Rebuild sell lines from the (possibly edited) old sale. Abort if any
                //    product can't be mapped, so we never leave a bill with wrong totals.
                DB::table('transaction_sell_lines')->where('transaction_id', $newTxId)->delete();
                $sellResult = $this->createNewSellLines($oldSale->id, $newTxId, $log);
                if (!empty($sellResult['skipped'])) {
                    $missing = implode(', ', $sellResult['skipped']);
                    throw new Exception("Unmapped product(s) [{$missing}] for sale #{$oldSale->id} — update aborted to avoid wrong totals");
                }

                // 3. Rebuild payments.
                DB::table('transaction_payments')->where('transaction_id', $newTxId)->delete();
                $this->createNewPayments($oldSale->id, $newTxId, $newUserId, $log);

                DB::table('sync_logs')->insert([
                    'direction'          => 'old_to_new_update',
                    'old_sale_id'        => $oldSale->id,
                    'new_transaction_id' => $newTxId,
                    'ref_no'             => $refNo,
                    'status'             => 'success',
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);

                DB::commit();
                $updated++;
                $log("[UPDATE-SYNC] Re-synced edited bill #{$oldSale->id} ({$refNo}) → transaction #{$newTxId}", 'success');

            } catch (Exception $e) {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                $failed++;
                try {
                    DB::table('sync_logs')->insert([
                        'direction'     => 'old_to_new_update',
                        'old_sale_id'   => $oldSale->id,
                        'ref_no'        => $oldSale->reference_no ?? null,
                        'status'        => 'failed',
                        'error_message' => $e->getMessage(),
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                } catch (Exception $logErr) {
                    // Ignore logging error
                }
                $log("[UPDATE-SYNC] FAILED bill #{$oldSale->id}: " . $e->getMessage(), 'error');
                Log::error("syncOldToNewUpdates failed for old sale #{$oldSale->id}: " . $e->getMessage());
            }
        }

        $log("[UPDATE-SYNC] Done: {$updated} updated, {$skipped} unchanged, {$failed} failed", $failed > 0 ? 'warning' : 'success');
        return ['synced' => 0, 'updated' => $updated, 'skipped' => $skipped, 'failed' => $failed];
    }

    /**
     * Build a content signature of an old POS sale (header financials + line/payment
     * aggregates + mapped statuses) used to detect edits against the new transaction.
     *
     * @return array<int, mixed>
     */
    private function buildOldSaleSignature(object $oldSale): array
    {
        $items = DB::connection('old_pos')
            ->table('sma_sale_items')
            ->where('sale_id', $oldSale->id)
            ->get();

        $itemCount = $items->count();
        $qtySum = 0.0;
        $lineSum = 0.0;
        foreach ($items as $item) {
            $qty  = (float) ($item->quantity ?? 0);
            $unit = (float) ($item->net_unit_price ?: ($item->unit_price ?? 0));
            $qtySum  += $qty;
            $lineSum += $unit * $qty;
        }

        $payAgg = DB::connection('old_pos')
            ->table('sma_payments')
            ->where('sale_id', $oldSale->id)
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(amount), 0) as s')
            ->first();

        return [
            round((float) ($oldSale->grand_total ?? 0), 2),
            round((float) ($oldSale->total ?? 0), 2),
            round((float) ($oldSale->total_tax ?? 0), 2),
            round((float) ($oldSale->total_discount ?? 0), 2),
            $this->mapOldStatusToNew($oldSale->sale_status ?? 'completed'),
            $this->mapOldPaymentStatusToNew($oldSale->payment_status ?? 'unpaid'),
            $itemCount,
            round($qtySum, 2),
            round($lineSum, 2),
            (int) ($payAgg->c ?? 0),
            round((float) ($payAgg->s ?? 0), 2),
            $this->resolveOldSearchName($oldSale),
        ];
    }

    /**
     * Resolve the canonical customer name for an old sale the same way
     * findOrCreateNewContact() does, so the value can be compared against the linked
     * new contact's name to detect a customer change. The company name is only looked
     * up when the denormalized sale name is empty (rare).
     */
    private function resolveOldSearchName(object $oldSale): string
    {
        $saleCustomerName = trim((string) ($oldSale->customer ?? ''));
        if ($saleCustomerName !== '') {
            return $saleCustomerName;
        }

        $companyName = DB::connection('old_pos')
            ->table('sma_companies')
            ->where('id', $oldSale->customer_id ?? 0)
            ->value('name');

        return trim((string) ($companyName ?? '')) ?: 'Walk-in Customer';
    }

    /**
     * Read a persisted sync sweep cursor from the key/value `system` table.
     */
    private function getSyncCursor(string $name): int
    {
        $row = DB::table('system')->where('key', 'sync_cursor_' . $name)->first();

        return $row ? (int) $row->value : 0;
    }

    /**
     * Persist a sync sweep cursor in the key/value `system` table.
     */
    private function setSyncCursor(string $name, int $value): void
    {
        $key = 'sync_cursor_' . $name;

        if (DB::table('system')->where('key', $key)->exists()) {
            DB::table('system')->where('key', $key)->update(['value' => (string) $value]);
        } else {
            DB::table('system')->insert(['key' => $key, 'value' => (string) $value]);
        }
    }

    /**
     * Build the matching signature for a new POS transaction, in the same shape and
     * order as buildOldSaleSignature() so the two can be compared with ===.
     *
     * @return array<int, mixed>
     */
    private function buildNewTransactionSignature(object $newTx): array
    {
        $lines = DB::table('transaction_sell_lines')
            ->where('transaction_id', $newTx->id)
            ->get();

        $itemCount = $lines->count();
        $qtySum = 0.0;
        $lineSum = 0.0;
        foreach ($lines as $line) {
            $qty  = (float) ($line->quantity ?? 0);
            $unit = (float) ($line->unit_price ?? 0);
            $qtySum  += $qty;
            $lineSum += $unit * $qty;
        }

        $payAgg = DB::table('transaction_payments')
            ->where('transaction_id', $newTx->id)
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(amount), 0) as s')
            ->first();

        // Name of the currently linked contact — compared against the old sale's
        // resolved customer name to catch a customer change.
        $contactName = DB::table('contacts')
            ->where('id', $newTx->contact_id ?? 0)
            ->value('name');

        return [
            round((float) ($newTx->final_total ?? 0), 2),
            round((float) ($newTx->total_before_tax ?? 0), 2),
            round((float) ($newTx->tax_amount ?? 0), 2),
            round((float) ($newTx->discount_amount ?? 0), 2),
            (string) ($newTx->status ?? ''),
            (string) ($newTx->payment_status ?? ''),
            $itemCount,
            round($qtySum, 2),
            round($lineSum, 2),
            (int) ($payAgg->c ?? 0),
            round((float) ($payAgg->s ?? 0), 2),
            trim((string) ($contactName ?? '')),
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // DIRECTION 2: New POS → Old POS
    // ─────────────────────────────────────────────────────────────────

    public function syncNewToOld(callable $log, int $limit = 50): array
    {
        $log('[NEW→OLD] Checking new POS for new bills...', 'info');

        $pendingTransactions = DB::table('transactions')
            ->where('type', 'sell')
            ->where('synced_to_old_pos', 0)
            ->whereNull('sync_source')
            ->where(function ($query) {
                $query->whereNull('sub_status')
                    ->orWhere('sub_status', '!=', 'quotation');
            })
            ->where(function ($query) {
                $query->whereNull('document_type')
                    ->orWhere('document_type', '!=', 'quotation');
            })
            ->where(function ($query) {
                $query->whereNull('is_quotation')
                    ->orWhere('is_quotation', '!=', 1);
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($pendingTransactions->isEmpty()) {
            $log('[NEW→OLD] No new bills to sync from new POS', 'info');
            return ['synced' => 0, 'failed' => 0];
        }

        $log("[NEW→OLD] Found {$pendingTransactions->count()} bill(s) to sync", 'info');

        $synced = 0;
        $failed = 0;

        foreach ($pendingTransactions as $transaction) {
            try {
                DB::beginTransaction();
                DB::connection('old_pos')->beginTransaction();

                // 1. Find or create customer in old POS
                $oldCustomerId = $this->findOrCreateOldContact($transaction->contact_id, $log);

                // Use the NEW POS contact name for the denormalized customer field
                // (don't look up old company — it may be a different/wrong record)
                $newContact = DB::table('contacts')->where('id', $transaction->contact_id)->first();
                $customerName = $newContact->name ?? 'Walk-in Customer';

                // 2. Map user ID (reverse)
                $oldUserId = $this->mapNewUserToOld($transaction->created_by ?? 1);

                // 3. Map statuses
                $oldSaleStatus = $this->mapNewStatusToOld($transaction->status ?? 'final');
                $oldPaymentStatus = $this->mapNewPaymentStatusToOld($transaction->payment_status ?? 'due');
                $oldWarehouseId = $this->mapNewLocationToOldWarehouse($transaction->location_id ?? null);
                $oldBiller = $this->resolveOldBillerForWarehouse($oldWarehouseId);

                // 4. Generate a unique reference_no for old POS
                $baseRefNo = $transaction->invoice_no ?: ($transaction->ref_no ?? ('NEW-' . $transaction->id));
                $existingOldSale = $this->findOldSaleByReference((string) $baseRefNo);
                if ($existingOldSale && $this->canAttachToExistingOldSale($existingOldSale, (int) $transaction->id)) {
                    // Link to existing old record instead of creating a -NP duplicate reference.
                    DB::table('transactions')
                        ->where('id', $transaction->id)
                        ->update([
                            'synced_to_old_pos' => 1,
                            'old_pos_sale_id'   => $existingOldSale->id,
                        ]);

                    DB::connection('old_pos')->table('sma_sales')
                        ->where('id', $existingOldSale->id)
                        ->update([
                            'synced_to_new_pos'      => 1,
                            'new_pos_transaction_id' => $transaction->id,
                        ]);

                    DB::table('sync_logs')->insert([
                        'direction'          => 'new_to_old',
                        'old_sale_id'        => $existingOldSale->id,
                        'new_transaction_id' => $transaction->id,
                        'ref_no'             => $baseRefNo,
                        'status'             => 'success',
                        'error_message'      => 'Linked to existing old sale by reference_no (no duplicate created)',
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);

                    DB::connection('old_pos')->commit();
                    DB::commit();
                    $synced++;
                    $log("[NEW→OLD] Linked transaction #{$transaction->id} to existing old sale #{$existingOldSale->id} ({$baseRefNo})", 'info');
                    continue;
                }

                $refNo = $this->buildUniqueOldReferenceNo((string) $baseRefNo, (int) $transaction->id);

                // 5. Create sale in old POS
                $totalTax = $transaction->tax_amount ?? 0;
                $total = $transaction->total_before_tax ?? ($transaction->final_total - $totalTax);

                $oldSaleId = DB::connection('old_pos')->table('sma_sales')->insertGetId([
                    'date'              => $transaction->transaction_date ?? now(),
                    'reference_no'      => $refNo,
                    'customer_id'       => $oldCustomerId,
                    'customer'          => $customerName,
                    'biller_id'         => $oldBiller['id'],
                    'biller'            => $oldBiller['name'],
                    'warehouse_id'      => $oldWarehouseId,
                    'total'             => $total,
                    'product_tax'       => $totalTax,
                    'order_tax_id'      => null,
                    'order_tax'         => 0,
                    'total_tax'         => $totalTax,
                    'total_discount'    => $transaction->discount_amount ?? 0,
                    'order_discount'    => $transaction->discount_amount ?? 0,
                    'shipping'          => $transaction->shipping_charges ?? 0,
                    'grand_total'       => $transaction->final_total ?? 0,
                    'paid'              => in_array($transaction->payment_status, ['paid']) ? ($transaction->final_total ?? 0) : 0,
                    'sale_status'       => $oldSaleStatus,
                    'payment_status'    => $oldPaymentStatus,
                    'note'              => $transaction->additional_notes ?? null,
                    'created_by'        => $oldUserId,
                    'updated_at'        => now(),
                    'total_items'       => 0, // Will be set after items inserted
                    // Sync tracking: mark as synced FROM new POS so it won't be re-synced back
                    'sync_source'            => 'new_pos',
                    'synced_to_new_pos'      => 1,
                    'new_pos_transaction_id' => $transaction->id,
                ]);

                // 6. Create sale items in old POS
                $itemCount = $this->createOldSaleItems($transaction->id, $oldSaleId, $oldWarehouseId, $log);

                // Update total_items count
                DB::connection('old_pos')->table('sma_sales')
                    ->where('id', $oldSaleId)
                    ->update(['total_items' => $itemCount]);

                // 7. Create payments in old POS
                $this->createOldPayments($transaction->id, $oldSaleId, $oldUserId, $log);

                // 8. Mark new transaction as synced
                DB::table('transactions')
                    ->where('id', $transaction->id)
                    ->update([
                        'synced_to_old_pos' => 1,
                        'old_pos_sale_id'   => $oldSaleId,
                    ]);

                // 9. Log success
                DB::table('sync_logs')->insert([
                    'direction'          => 'new_to_old',
                    'old_sale_id'        => $oldSaleId,
                    'new_transaction_id' => $transaction->id,
                    'ref_no'             => $refNo,
                    'status'             => 'success',
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);

                DB::connection('old_pos')->commit();
                DB::commit();
                $synced++;
                $log("[NEW→OLD] Synced transaction #{$transaction->id} ({$refNo}) → old sale #{$oldSaleId}", 'success');

            } catch (Exception $e) {
                try {
                    DB::connection('old_pos')->rollBack();
                } catch (Exception $oldRollbackErr) {
                    // Ignore old_pos rollback errors
                }
                DB::rollBack();
                $failed++;

                try {
                    DB::table('sync_logs')->insert([
                        'direction'          => 'new_to_old',
                        'new_transaction_id' => $transaction->id,
                        'ref_no'             => $transaction->ref_no ?? null,
                        'status'             => 'failed',
                        'error_message'      => $e->getMessage(),
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);
                } catch (Exception $logErr) {
                    // Ignore
                }

                $log("[NEW→OLD] FAILED transaction #{$transaction->id}: " . $e->getMessage(), 'error');
                Log::error("Sync new→old failed for transaction #{$transaction->id}: " . $e->getMessage());
            }
        }

        $log("[NEW→OLD] Done: {$synced} synced, {$failed} failed", $failed > 0 ? 'warning' : 'success');
        return ['synced' => $synced, 'failed' => $failed];
    }

    private function findOrCreateOldContact(int $newContactId, callable $log): int
    {
        $newContact = DB::table('contacts')->where('id', $newContactId)->first();

        if (!$newContact) {
            return 1; // Default walk-in customer
        }

        // 1. Match by name FIRST — most reliable, avoids wrong phone matches
        $oldCompany = DB::connection('old_pos')->table('sma_companies')
            ->where('name', $newContact->name)
            ->first();
        if ($oldCompany) {
            $this->syncOldCompanyProfileFromNewContact((int) $oldCompany->id, $newContact);
            return $oldCompany->id;
        }

        // 2. Match by supplier_business_name (some contacts store company name separately)
        if (!empty($newContact->supplier_business_name) && $newContact->supplier_business_name !== $newContact->name) {
            $oldCompany = DB::connection('old_pos')->table('sma_companies')
                ->where('name', $newContact->supplier_business_name)
                ->first();
            if ($oldCompany) {
                $this->syncOldCompanyProfileFromNewContact((int) $oldCompany->id, $newContact);
                return $oldCompany->id;
            }
        }

        // 3. Match by email AND name together (avoid false phone-only matches)
        if (!empty($newContact->email)) {
            $oldCompany = DB::connection('old_pos')->table('sma_companies')
                ->where('email', $newContact->email)
                ->first();
            if ($oldCompany && $oldCompany->name === $newContact->name) {
                $this->syncOldCompanyProfileFromNewContact((int) $oldCompany->id, $newContact);
                return $oldCompany->id;
            }
        }

        // Create new contact in old POS
        $oldCompanyLabel = $this->resolveOldCompanyLabelFromNewContact($newContact);
        $oldContactId = DB::connection('old_pos')->table('sma_companies')->insertGetId([
            'name'       => $newContact->name,
            'company'    => $oldCompanyLabel,
            'email'      => $newContact->email ?? null,
            'phone'      => $newContact->mobile ?? null,
            'address'    => $newContact->address_line_1 ?? null,
            'city'       => $newContact->city ?? null,
            'vat_no'     => $newContact->tax_number ?? null,
            'group_id'   => 1, // Default customer group
            'created'    => now(),
        ]);

        $log("[NEW→OLD] Created new customer in old POS: {$newContact->name} (id: {$oldContactId})", 'info');
        return $oldContactId;
    }

    private function resolveOldCompanyLabelFromNewContact($newContact): ?string
    {
        $contactType = strtolower(trim((string) ($newContact->contact_type ?? '')));
        if ($contactType === 'business') {
            $supplierBusinessName = trim((string) ($newContact->supplier_business_name ?? ''));
            if ($supplierBusinessName !== '') {
                return $supplierBusinessName;
            }

            $name = trim((string) ($newContact->name ?? ''));
            return $name !== '' ? $name : null;
        }

        if ($contactType === 'individual') {
            return null;
        }

        return null;
    }

    private function syncOldCompanyProfileFromNewContact(int $oldCompanyId, $newContact): void
    {
        $updates = [];

        if (!empty($newContact->email)) {
            $updates['email'] = $newContact->email;
        }
        if (!empty($newContact->mobile)) {
            $updates['phone'] = $newContact->mobile;
        }
        if (!empty($newContact->address_line_1)) {
            $updates['address'] = $newContact->address_line_1;
        }
        if (!empty($newContact->city)) {
            $updates['city'] = $newContact->city;
        }
        if (!empty($newContact->tax_number)) {
            $updates['vat_no'] = $newContact->tax_number;
        }

        $contactType = strtolower(trim((string) ($newContact->contact_type ?? '')));
        if ($contactType === 'business') {
            $updates['company'] = $this->resolveOldCompanyLabelFromNewContact($newContact);
        } elseif ($contactType === 'individual') {
            $updates['company'] = null;
        }

        if (!empty($updates)) {
            DB::connection('old_pos')->table('sma_companies')
                ->where('id', $oldCompanyId)
                ->update($updates);
        }
    }

    private function createOldSaleItems(int $newTransactionId, int $oldSaleId, int $oldWarehouseId, callable $log): int
    {
        $oldSaleItemColumns = $this->getOldSaleItemColumns();

        $sellLines = DB::table('transaction_sell_lines as sl')
            ->join('variations as v', 'v.id', '=', 'sl.variation_id')
            ->join('products as p', 'p.id', '=', 'sl.product_id')
            ->where('sl.transaction_id', $newTransactionId)
            ->select(
                'sl.*',
                'v.sub_sku as product_code',
                'p.name as product_name',
                'p.type as product_type'
            )
            ->get();

        $count = 0;
        foreach ($sellLines as $line) {
            // Find product in old POS by SKU
            $oldProduct = DB::connection('old_pos')->table('sma_products')
                ->where('code', $line->product_code)
                ->first();

            if (!$oldProduct) {
                $log("[NEW→OLD] Warning: product '{$line->product_code}' ({$line->product_name}) not found in old POS — skipping", 'warning');
                continue;
            }

            $qty      = $line->quantity ?? 1;
            $netPrice = $line->unit_price_inc_tax ?? $line->unit_price ?? 0;
            $unitPrice = $line->unit_price_before_discount ?? $netPrice;
            $subtotal = round($qty * $netPrice, 4);

            $insertData = [
                'sale_id'        => $oldSaleId,
                'product_id'     => $oldProduct->id,
                'product_code'   => $line->product_code,
                'product_name'   => $line->product_name,
                'product_type'   => $oldProduct->type ?? 'standard',
                'net_unit_price' => $netPrice,
                'unit_price'     => $unitPrice,
                'quantity'       => $qty,
                'warehouse_id'   => $oldWarehouseId,
                'item_tax'       => $line->item_tax ?? 0,
                'tax_rate_id'    => null,
                'tax'            => 0,
                'discount'       => $line->line_discount_amount ?? 0,
                'item_discount'  => $line->line_discount_amount ?? 0,
                'subtotal'       => $subtotal,
            ];

            // Some old POS schemas require these legacy fields and do not provide defaults.
            if (in_array('unit_quantity', $oldSaleItemColumns, true)) {
                $insertData['unit_quantity'] = $qty;
            }
            if (in_array('real_unit_price', $oldSaleItemColumns, true)) {
                $insertData['real_unit_price'] = $unitPrice;
            }
            if (in_array('gross_total', $oldSaleItemColumns, true)) {
                $insertData['gross_total'] = $subtotal;
            }
            if (in_array('quantity_balance', $oldSaleItemColumns, true)) {
                $insertData['quantity_balance'] = $qty;
            }
            if (in_array('option_id', $oldSaleItemColumns, true)) {
                $insertData['option_id'] = null;
            }
            if (in_array('serial_no', $oldSaleItemColumns, true)) {
                $insertData['serial_no'] = null;
            }
            if (in_array('product_unit_id', $oldSaleItemColumns, true)) {
                $insertData['product_unit_id'] = $oldProduct->unit ?? null;
            }

            DB::connection('old_pos')->table('sma_sale_items')->insert($insertData);
            $count++;
        }

        return $count;
    }

    private function createOldPayments(int $newTransactionId, int $oldSaleId, int $oldUserId, callable $log): void
    {
        $payments = DB::table('transaction_payments')
            ->where('transaction_id', $newTransactionId)
            ->get();

        foreach ($payments as $payment) {
            $oldMethod = $this->mapNewPaymentMethodToOld($payment->method ?? 'cash');

            DB::connection('old_pos')->table('sma_payments')->insert([
                'date'         => $payment->paid_on ?? now(),
                'sale_id'      => $oldSaleId,
                'reference_no' => $payment->payment_ref_no ?? ('PAY-' . $newTransactionId . '-' . $payment->id),
                'paid_by'      => $oldMethod,
                'amount'       => $payment->amount ?? 0,
                'cheque_no'    => $payment->cheque_number ?? null,
                'note'         => $payment->note ?? null,
                'created_by'   => $oldUserId,
                'type'         => 'received',
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // QUOTATION SYNC (2-WAY)
    // ─────────────────────────────────────────────────────────────────

    public function syncOldQuotesToNew(callable $log, int $limit = 50): array
    {
        if (!$this->oldTableHasColumns('sma_quotes', ['synced_to_new_pos', 'sync_source', 'new_pos_transaction_id'])) {
            $log('[OLD→NEW:QUOTE] Sync columns missing on sma_quotes. Running setup...', 'warning');
            $this->runSetup($log);
        }

        $log('[OLD→NEW:QUOTE] Checking old POS for new quotations...', 'info');

        $pendingQuotesQuery = DB::connection('old_pos')
            ->table('sma_quotes')
            ->where('synced_to_new_pos', 0);
        $this->applyNativeSyncSourceFilter($pendingQuotesQuery);

        $pendingQuotes = $pendingQuotesQuery
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($pendingQuotes->isEmpty()) {
            $log('[OLD→NEW:QUOTE] No new quotations to sync', 'info');
            return ['synced' => 0, 'failed' => 0, 'skipped' => 0];
        }

        $log("[OLD→NEW:QUOTE] Found {$pendingQuotes->count()} quotation(s) to sync", 'info');

        $synced = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($pendingQuotes as $oldQuote) {
            try {
                DB::beginTransaction();

                $refNo = trim((string) ($oldQuote->reference_no ?? '')) ?: ('QT-' . $oldQuote->id);

                $existingNewQuote = null;
                if (!empty($oldQuote->new_pos_transaction_id)) {
                    $existingNewQuote = DB::table('transactions')
                        ->where('id', (int) $oldQuote->new_pos_transaction_id)
                        ->first();
                    if ($existingNewQuote && !$this->isQuotationTransaction($existingNewQuote)) {
                        $existingNewQuote = null;
                    }
                }

                if (!$existingNewQuote) {
                    $existingNewQuote = $this->findNewQuotationByReference($refNo);
                }

                if ($existingNewQuote) {
                    DB::connection('old_pos')->table('sma_quotes')
                        ->where('id', $oldQuote->id)
                        ->update([
                            'synced_to_new_pos'      => 1,
                            'new_pos_transaction_id' => $existingNewQuote->id,
                        ]);

                    DB::table('transactions')
                        ->where('id', $existingNewQuote->id)
                        ->update([
                            'synced_to_old_pos' => 1,
                            'updated_at'        => now(),
                        ]);

                    DB::table('sync_logs')->insert([
                        'direction'          => 'old_to_new',
                        'old_sale_id'        => $oldQuote->id,
                        'new_transaction_id' => $existingNewQuote->id,
                        'ref_no'             => $refNo,
                        'status'             => 'success',
                        'error_message'      => 'Linked existing quotation transaction by reference_no',
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);

                    DB::commit();
                    $synced++;
                    $log("[OLD→NEW:QUOTE] Linked old quote #{$oldQuote->id} to existing new transaction #{$existingNewQuote->id}", 'info');
                    continue;
                }

                $newContactId = $this->findOrCreateNewContact(
                    (int) ($oldQuote->customer_id ?? 1),
                    (string) ($oldQuote->customer ?? '')
                );
                $newUserId = $this->mapUserId($oldQuote->created_by ?? null);
                $newLocationId = $this->mapOldWarehouseToNewLocation($oldQuote->warehouse_id ?? null);
                $newStatus = $this->mapOldQuoteStatusToNew((string) ($oldQuote->status ?? 'pending'));
                $paymentStatus = $this->mapOldPaymentStatusToNew('unpaid');
                $newTaxId = $this->resolveNewTaxIdFromOld(
                    isset($oldQuote->order_tax_id) ? (int) $oldQuote->order_tax_id : null,
                    (float) ($oldQuote->total_tax ?? ($oldQuote->product_tax ?? 0)),
                    (float) ($oldQuote->total ?? 0),
                    $this->businessId
                );

                $newTransactionId = DB::table('transactions')->insertGetId([
                    'business_id'       => $this->businessId,
                    'location_id'       => $newLocationId,
                    'type'              => 'sell',
                    'status'            => $newStatus,
                    'sub_status'        => 'quotation',
                    'document_type'     => 'quotation',
                    'is_quotation'      => 1,
                    'invoice_no'        => $refNo,
                    'ref_no'            => $refNo,
                    'contact_id'        => $newContactId,
                    'transaction_date'  => $oldQuote->date ?? now(),
                    'total_before_tax'  => $oldQuote->total ?? 0,
                    'tax_id'            => $newTaxId,
                    'tax_amount'        => $oldQuote->total_tax ?? ($oldQuote->product_tax ?? 0),
                    'final_total'       => $oldQuote->grand_total ?? 0,
                    'discount_type'     => 'fixed',
                    'discount_amount'   => $oldQuote->total_discount ?? ($oldQuote->order_discount ?? 0),
                    'shipping_charges'  => $oldQuote->shipping ?? 0,
                    'payment_status'    => $paymentStatus,
                    'additional_notes'  => $oldQuote->note ?? null,
                    'staff_note'        => $oldQuote->internal_note ?? null,
                    'document'          => $oldQuote->attachment ?? null,
                    'created_by'        => $newUserId,
                    'is_direct_sale'    => 0,
                    'sync_source'       => 'old_pos',
                    'synced_to_old_pos' => 1,
                    'created_at'        => $oldQuote->date ?? now(),
                    'updated_at'        => now(),
                ]);

                $this->createNewQuotationLines((int) $oldQuote->id, $newTransactionId, $log);

                DB::connection('old_pos')->table('sma_quotes')
                    ->where('id', $oldQuote->id)
                    ->update([
                        'synced_to_new_pos'      => 1,
                        'new_pos_transaction_id' => $newTransactionId,
                    ]);

                DB::table('sync_logs')->insert([
                    'direction'          => 'old_to_new',
                    'old_sale_id'        => $oldQuote->id,
                    'new_transaction_id' => $newTransactionId,
                    'ref_no'             => $refNo,
                    'status'             => 'success',
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);

                DB::commit();
                $synced++;
                $log("[OLD→NEW:QUOTE] Synced old quote #{$oldQuote->id} ({$refNo}) → new transaction #{$newTransactionId}", 'success');
            } catch (Exception $e) {
                DB::rollBack();
                $failed++;

                try {
                    DB::table('sync_logs')->insert([
                        'direction'     => 'old_to_new',
                        'old_sale_id'   => $oldQuote->id,
                        'ref_no'        => $oldQuote->reference_no ?? null,
                        'status'        => 'failed',
                        'error_message' => $e->getMessage(),
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                } catch (Exception $logErr) {
                    // Ignore log write failures.
                }

                $log("[OLD→NEW:QUOTE] FAILED quote #{$oldQuote->id}: {$e->getMessage()}", 'error');
                Log::error("syncOldQuotesToNew failed for quote #{$oldQuote->id}: {$e->getMessage()}");
            }
        }

        $log("[OLD→NEW:QUOTE] Done: {$synced} synced, {$failed} failed, {$skipped} skipped", $failed > 0 ? 'warning' : 'success');
        return ['synced' => $synced, 'failed' => $failed, 'skipped' => $skipped];
    }

    public function syncNewQuotesToOld(callable $log, int $limit = 50): array
    {
        if (!$this->oldTableHasColumns('sma_quotes', ['synced_to_new_pos', 'sync_source', 'new_pos_transaction_id'])) {
            $log('[NEW→OLD:QUOTE] Sync columns missing on sma_quotes. Running setup...', 'warning');
            $this->runSetup($log);
        }

        $log('[NEW→OLD:QUOTE] Checking new POS for new quotations...', 'info');

        $pendingQuotesQuery = DB::table('transactions')
            ->where('synced_to_old_pos', 0);
        $this->applyNativeSyncSourceFilter($pendingQuotesQuery);
        $this->applyQuotationTransactionFilter($pendingQuotesQuery);

        $pendingQuotes = $pendingQuotesQuery
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($pendingQuotes->isEmpty()) {
            $log('[NEW→OLD:QUOTE] No new quotations to sync', 'info');
            return ['synced' => 0, 'failed' => 0, 'skipped' => 0];
        }

        $log("[NEW→OLD:QUOTE] Found {$pendingQuotes->count()} quotation(s) to sync", 'info');

        $synced = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($pendingQuotes as $transaction) {
            try {
                DB::beginTransaction();
                DB::connection('old_pos')->beginTransaction();

                $baseRefNo = trim((string) ($transaction->invoice_no ?: ($transaction->ref_no ?? '')));
                if ($baseRefNo === '') {
                    $baseRefNo = 'QT-' . $transaction->id;
                }

                $existingOldQuote = $this->findOldQuoteByReference($baseRefNo);
                if ($existingOldQuote && $this->canAttachToExistingOldQuote($existingOldQuote, (int) $transaction->id)) {
                    DB::table('transactions')
                        ->where('id', $transaction->id)
                        ->update([
                            'synced_to_old_pos' => 1,
                            'old_pos_sale_id'   => $existingOldQuote->id,
                            'updated_at'        => now(),
                        ]);

                    DB::connection('old_pos')->table('sma_quotes')
                        ->where('id', $existingOldQuote->id)
                        ->update([
                            'synced_to_new_pos'      => 1,
                            'new_pos_transaction_id' => $transaction->id,
                        ]);

                    DB::table('sync_logs')->insert([
                        'direction'          => 'new_to_old',
                        'old_sale_id'        => $existingOldQuote->id,
                        'new_transaction_id' => $transaction->id,
                        'ref_no'             => $baseRefNo,
                        'status'             => 'success',
                        'error_message'      => 'Linked to existing old quotation by reference_no',
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);

                    DB::connection('old_pos')->commit();
                    DB::commit();
                    $synced++;
                    $log("[NEW→OLD:QUOTE] Linked transaction #{$transaction->id} to existing old quote #{$existingOldQuote->id}", 'info');
                    continue;
                }

                $oldCustomerId = $this->findOrCreateOldContact((int) ($transaction->contact_id ?? 0), $log);
                $newContact = DB::table('contacts')->where('id', $transaction->contact_id)->first();
                $customerName = $newContact->name ?? 'Walk-in Customer';
                $oldWarehouseId = $this->mapNewLocationToOldWarehouse($transaction->location_id ?? null);
                $oldBiller = $this->resolveOldBillerForWarehouse($oldWarehouseId);
                $oldUserId = $this->mapNewUserToOld($transaction->created_by ?? null);
                $oldStatus = $this->mapNewQuoteStatusToOld((string) ($transaction->status ?? 'draft'));
                $oldPaymentStatus = $this->mapNewPaymentStatusToOld((string) ($transaction->payment_status ?? 'due'));

                $refNo = $this->buildUniqueOldQuoteReferenceNo($baseRefNo, (int) $transaction->id);
                $totalTax = (float) ($transaction->tax_amount ?? 0);
                $total = (float) ($transaction->total_before_tax ?? (($transaction->final_total ?? 0) - $totalTax));
                $totalDiscount = (float) ($transaction->discount_amount ?? 0);

                $oldQuoteId = DB::connection('old_pos')->table('sma_quotes')->insertGetId([
                    'date'                   => $transaction->transaction_date ?? now(),
                    'reference_no'           => $refNo,
                    'customer_id'            => $oldCustomerId,
                    'customer'               => $customerName,
                    'warehouse_id'           => $oldWarehouseId,
                    'biller_id'              => $oldBiller['id'],
                    'biller'                 => $oldBiller['name'],
                    'note'                   => $transaction->additional_notes ?? null,
                    'internal_note'          => $transaction->staff_note ?? null,
                    'total'                  => $total,
                    'product_discount'       => 0,
                    'order_discount'         => $totalDiscount,
                    'total_discount'         => $totalDiscount,
                    'product_tax'            => $totalTax,
                    'order_tax_id'           => null,
                    'order_tax'              => 0,
                    'total_tax'              => $totalTax,
                    'shipping'               => $transaction->shipping_charges ?? 0,
                    'grand_total'            => $transaction->final_total ?? 0,
                    'status'                 => $oldStatus,
                    'created_by'             => $oldUserId,
                    'updated_by'             => $oldUserId,
                    'updated_at'             => now(),
                    'attachment'             => $transaction->document ?? null,
                    'sync_source'            => 'new_pos',
                    'synced_to_new_pos'      => 1,
                    'new_pos_transaction_id' => $transaction->id,
                ]);

                $this->createOldQuoteItems((int) $transaction->id, $oldQuoteId, $oldWarehouseId, $log);

                DB::table('transactions')
                    ->where('id', $transaction->id)
                    ->update([
                        'synced_to_old_pos' => 1,
                        'old_pos_sale_id'   => $oldQuoteId,
                        'updated_at'        => now(),
                    ]);

                DB::table('sync_logs')->insert([
                    'direction'          => 'new_to_old',
                    'old_sale_id'        => $oldQuoteId,
                    'new_transaction_id' => $transaction->id,
                    'ref_no'             => $refNo,
                    'status'             => 'success',
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);

                DB::connection('old_pos')->commit();
                DB::commit();
                $synced++;
                $log("[NEW→OLD:QUOTE] Synced transaction #{$transaction->id} ({$refNo}) → old quote #{$oldQuoteId}", 'success');
            } catch (Exception $e) {
                try {
                    DB::connection('old_pos')->rollBack();
                } catch (Exception $rollbackErr) {
                    // Ignore rollback errors on old DB.
                }
                DB::rollBack();
                $failed++;

                try {
                    DB::table('sync_logs')->insert([
                        'direction'          => 'new_to_old',
                        'new_transaction_id' => $transaction->id,
                        'ref_no'             => $transaction->ref_no ?? null,
                        'status'             => 'failed',
                        'error_message'      => $e->getMessage(),
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);
                } catch (Exception $logErr) {
                    // Ignore.
                }

                $log("[NEW→OLD:QUOTE] FAILED transaction #{$transaction->id}: {$e->getMessage()}", 'error');
                Log::error("syncNewQuotesToOld failed for transaction #{$transaction->id}: {$e->getMessage()}");
            }
        }

        $log("[NEW→OLD:QUOTE] Done: {$synced} synced, {$failed} failed, {$skipped} skipped", $failed > 0 ? 'warning' : 'success');
        return ['synced' => $synced, 'failed' => $failed, 'skipped' => $skipped];
    }

    // ─────────────────────────────────────────────────────────────────
    // PRODUCT SYNC (2-WAY)
    // ─────────────────────────────────────────────────────────────────

    public function syncOldProductsToNew(callable $log, int $limit = 200): array
    {
        if (!$this->oldTableHasColumns('sma_products', ['synced_to_new_pos', 'sync_source', 'new_pos_product_id'])) {
            $log('[OLD→NEW:PRODUCT] Sync columns missing on sma_products. Running setup...', 'warning');
            $this->runSetup($log);
        }
        if (!$this->newTableHasColumns('products', ['synced_to_old_pos', 'sync_source', 'old_pos_product_id'])) {
            $log('[OLD→NEW:PRODUCT] Sync columns missing on products. Running setup...', 'warning');
            $this->runSetup($log);
        }

        $log('[OLD→NEW:PRODUCT] Checking old POS for new products...', 'info');

        $pendingProducts = DB::connection('old_pos')
            ->table('sma_products')
            ->where('synced_to_new_pos', 0)
            ->whereNull('sync_source')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($pendingProducts->isEmpty()) {
            $log('[OLD→NEW:PRODUCT] No products to sync', 'info');
            return ['synced' => 0, 'failed' => 0, 'skipped' => 0];
        }

        $log("[OLD→NEW:PRODUCT] Found {$pendingProducts->count()} product(s) to sync", 'info');

        $synced = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($pendingProducts as $oldProduct) {
            try {
                DB::beginTransaction();

                $sku = trim((string) ($oldProduct->code ?? ''));
                if ($sku === '') {
                    $skipped++;
                    DB::commit();
                    $log("[OLD→NEW:PRODUCT] Skipped old product #{$oldProduct->id} (empty code)", 'warning');
                    continue;
                }

                $matched = $this->findNewProductBySku($sku);

                if (!$matched && !empty($oldProduct->new_pos_product_id)) {
                    $linked = DB::table('products')
                        ->where('id', (int) $oldProduct->new_pos_product_id)
                        ->where('business_id', $this->businessId)
                        ->first();
                    if ($linked) {
                        $matched = (object) [
                            'product_id'   => $linked->id,
                            'variation_id' => null,
                        ];
                    }
                }

                $newCategoryId = $this->mapOldCategoryToNew($oldProduct->category_id ?? null);
                $oldName = trim((string) ($oldProduct->name ?? '')) ?: $sku;
                $oldSecondName = property_exists($oldProduct, 'second_name') ? $oldProduct->second_name : null;
                $oldCost = (float) ($oldProduct->cost ?? 0);
                $oldPrice = (float) ($oldProduct->price ?? 0);
                $oldAlertQty = (float) ($oldProduct->alert_quantity ?? 0);
                $oldImage = $oldProduct->image ?? null;

                if (!$matched) {
                    $productInsert = [
                        'name'           => $oldName,
                        'business_id'    => $this->businessId,
                        'type'           => 'single',
                        'unit_id'        => 1,
                        'category_id'    => $newCategoryId,
                        'tax_type'       => 'exclusive',
                        'enable_stock'   => 1,
                        'alert_quantity' => $oldAlertQty,
                        'sku'            => $sku,
                        'barcode_type'   => 'C128',
                        'image'          => $oldImage,
                        'product_description' => $oldProduct->product_details ?? ($oldProduct->details ?? null),
                        'created_by'     => 1,
                        'is_inactive'    => 0,
                        'not_for_selling'=> 0,
                        'sync_source'    => 'old_pos',
                        'synced_to_old_pos' => 1,
                        'old_pos_product_id' => $oldProduct->id,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ];

                    if ($this->hasNewProductColumn('second_name')) {
                        $productInsert['second_name'] = $oldSecondName;
                    }

                    $newProductId = DB::table('products')->insertGetId($productInsert);

                    $productVariationId = DB::table('product_variations')->insertGetId([
                        'name'       => 'DUMMY',
                        'product_id' => $newProductId,
                        'is_dummy'   => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $variationId = DB::table('variations')->insertGetId([
                        'name'                   => 'DUMMY',
                        'product_id'             => $newProductId,
                        'sub_sku'                => $sku,
                        'product_variation_id'   => $productVariationId,
                        'variation_value_id'     => null,
                        'default_purchase_price' => $oldCost,
                        'dpp_inc_tax'            => $oldCost,
                        'profit_percent'         => 0,
                        'default_sell_price'     => $oldPrice,
                        'sell_price_inc_tax'     => $oldPrice,
                        'created_at'             => now(),
                        'updated_at'             => now(),
                    ]);
                } else {
                    $newProductId = (int) $matched->product_id;
                    $variationId = (int) ($matched->variation_id ?? 0);

                    $productUpdate = [
                        'name'           => $oldName,
                        'alert_quantity' => $oldAlertQty,
                        'image'          => $oldImage,
                        'product_description' => $oldProduct->product_details ?? ($oldProduct->details ?? null),
                        'synced_to_old_pos' => 1,
                        'sync_source'    => 'old_pos',
                        'old_pos_product_id' => $oldProduct->id,
                        'updated_at'     => now(),
                    ];

                    if ($newCategoryId !== null) {
                        $productUpdate['category_id'] = $newCategoryId;
                    }
                    if ($this->hasNewProductColumn('second_name')) {
                        $productUpdate['second_name'] = $oldSecondName;
                    }

                    DB::table('products')->where('id', $newProductId)->update($productUpdate);

                    if ($variationId <= 0) {
                        $variationId = $this->ensureDummyVariationForProduct($newProductId, $sku, $oldCost, $oldPrice);
                    } else {
                        DB::table('variations')->where('id', $variationId)->update([
                            'sub_sku'                => $sku,
                            'default_purchase_price' => $oldCost,
                            'dpp_inc_tax'            => $oldCost,
                            'default_sell_price'     => $oldPrice,
                            'sell_price_inc_tax'     => $oldPrice,
                            'updated_at'             => now(),
                        ]);
                    }
                }

                DB::connection('old_pos')->table('sma_products')
                    ->where('id', $oldProduct->id)
                    ->update([
                        'synced_to_new_pos' => 1,
                        'new_pos_product_id' => $newProductId,
                    ]);

                DB::table('sync_logs')->insert([
                    'direction'          => 'old_to_new',
                    'old_sale_id'        => $oldProduct->id,
                    'new_transaction_id' => $newProductId,
                    'ref_no'             => $sku,
                    'status'             => 'success',
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);

                DB::commit();
                $synced++;
                $log("[OLD→NEW:PRODUCT] Synced old product #{$oldProduct->id} ({$sku}) → new product #{$newProductId}", 'success');
            } catch (Exception $e) {
                DB::rollBack();
                $failed++;
                $log("[OLD→NEW:PRODUCT] FAILED product #{$oldProduct->id}: {$e->getMessage()}", 'error');
                Log::error("syncOldProductsToNew failed for old product #{$oldProduct->id}: {$e->getMessage()}");
            }
        }

        $log("[OLD→NEW:PRODUCT] Done: {$synced} synced, {$failed} failed, {$skipped} skipped", $failed > 0 ? 'warning' : 'success');
        return ['synced' => $synced, 'failed' => $failed, 'skipped' => $skipped];
    }

    public function syncNewProductsToOld(callable $log, int $limit = 200): array
    {
        if (!$this->newTableHasColumns('products', ['synced_to_old_pos', 'sync_source', 'old_pos_product_id'])) {
            $log('[NEW→OLD:PRODUCT] Sync columns missing on products. Running setup...', 'warning');
            $this->runSetup($log);
        }
        if (!$this->oldTableHasColumns('sma_products', ['synced_to_new_pos', 'sync_source', 'new_pos_product_id'])) {
            $log('[NEW→OLD:PRODUCT] Sync columns missing on sma_products. Running setup...', 'warning');
            $this->runSetup($log);
        }

        $log('[NEW→OLD:PRODUCT] Checking new POS for new products...', 'info');

        $pendingProducts = DB::table('products')
            ->where('business_id', $this->businessId)
            ->where('synced_to_old_pos', 0)
            ->whereNull('sync_source')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($pendingProducts->isEmpty()) {
            $log('[NEW→OLD:PRODUCT] No products to sync', 'info');
            return ['synced' => 0, 'failed' => 0, 'skipped' => 0];
        }

        $log("[NEW→OLD:PRODUCT] Found {$pendingProducts->count()} product(s) to sync", 'info');

        $synced = 0;
        $failed = 0;
        $skipped = 0;

        $oldProductColumns = $this->getOldTableColumns('sma_products');
        $hasOldSecondName = in_array('second_name', $oldProductColumns, true);

        foreach ($pendingProducts as $newProduct) {
            try {
                DB::beginTransaction();
                DB::connection('old_pos')->beginTransaction();

                $variation = $this->findPrimaryVariationForProduct((int) $newProduct->id);
                $sku = trim((string) ($variation->sub_sku ?? ($newProduct->sku ?? '')));
                if ($sku === '') {
                    DB::connection('old_pos')->rollBack();
                    DB::rollBack();
                    $skipped++;
                    $log("[NEW→OLD:PRODUCT] Skipped new product #{$newProduct->id} (missing sku/sub_sku)", 'warning');
                    continue;
                }

                $oldProduct = null;
                if (!empty($newProduct->old_pos_product_id)) {
                    $oldProduct = DB::connection('old_pos')->table('sma_products')
                        ->where('id', (int) $newProduct->old_pos_product_id)
                        ->first();
                }
                if (!$oldProduct) {
                    $oldProduct = DB::connection('old_pos')->table('sma_products')
                        ->where('code', $sku)
                        ->first();
                }

                $cost = (float) ($variation->default_purchase_price ?? 0);
                $price = (float) ($variation->default_sell_price ?? ($variation->sell_price_inc_tax ?? 0));
                $mappedOldCategoryId = $this->mapNewCategoryToOld($newProduct->category_id ?? null);
                $secondName = property_exists($newProduct, 'second_name') ? $newProduct->second_name : null;
                $name = trim((string) ($newProduct->name ?? '')) ?: $sku;

                if ($oldProduct) {
                    $oldProductId = (int) $oldProduct->id;
                    $oldUpdate = [
                        'code'            => $sku,
                        'name'            => $name,
                        'cost'            => $cost,
                        'price'           => $price,
                        'alert_quantity'  => $newProduct->alert_quantity ?? 0,
                        'image'           => $newProduct->image ?? $oldProduct->image,
                        'product_details' => $newProduct->product_description ?? ($oldProduct->product_details ?? null),
                        'sync_source'     => 'new_pos',
                        'synced_to_new_pos' => 1,
                        'new_pos_product_id' => $newProduct->id,
                    ];
                    if ($mappedOldCategoryId !== null) {
                        $oldUpdate['category_id'] = $mappedOldCategoryId;
                    }
                    if ($hasOldSecondName) {
                        $oldUpdate['second_name'] = $secondName;
                    }

                    DB::connection('old_pos')->table('sma_products')
                        ->where('id', $oldProductId)
                        ->update($oldUpdate);
                } else {
                    $oldInsert = [
                        'code'              => $sku,
                        'name'              => $name,
                        'unit'              => 1,
                        'cost'              => $cost,
                        'price'             => $price,
                        'alert_quantity'    => $newProduct->alert_quantity ?? 0,
                        'image'             => $newProduct->image ?? 'no_image.png',
                        'category_id'       => $mappedOldCategoryId ?? 1,
                        'quantity'          => 0,
                        'track_quantity'    => 1,
                        'barcode_symbology' => 'code128',
                        'product_details'   => $newProduct->product_description ?? null,
                        'type'              => 'standard',
                        'hide'              => 0,
                        'hide_pos'          => 0,
                        'sync_source'       => 'new_pos',
                        'synced_to_new_pos' => 1,
                        'new_pos_product_id' => $newProduct->id,
                    ];
                    if ($hasOldSecondName) {
                        $oldInsert['second_name'] = $secondName;
                    }

                    $oldProductId = DB::connection('old_pos')->table('sma_products')->insertGetId($oldInsert);
                }

                DB::table('products')
                    ->where('id', $newProduct->id)
                    ->update([
                        'synced_to_old_pos' => 1,
                        'old_pos_product_id' => $oldProductId,
                        'updated_at' => now(),
                    ]);

                DB::table('sync_logs')->insert([
                    'direction'          => 'new_to_old',
                    'old_sale_id'        => $oldProductId,
                    'new_transaction_id' => $newProduct->id,
                    'ref_no'             => $sku,
                    'status'             => 'success',
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);

                DB::connection('old_pos')->commit();
                DB::commit();
                $synced++;
                $log("[NEW→OLD:PRODUCT] Synced new product #{$newProduct->id} ({$sku}) → old product #{$oldProductId}", 'success');
            } catch (Exception $e) {
                try {
                    DB::connection('old_pos')->rollBack();
                } catch (Exception $rollbackErr) {
                    // Ignore rollback errors.
                }
                DB::rollBack();
                $failed++;
                $log("[NEW→OLD:PRODUCT] FAILED product #{$newProduct->id}: {$e->getMessage()}", 'error');
                Log::error("syncNewProductsToOld failed for new product #{$newProduct->id}: {$e->getMessage()}");
            }
        }

        $log("[NEW→OLD:PRODUCT] Done: {$synced} synced, {$failed} failed, {$skipped} skipped", $failed > 0 ? 'warning' : 'success');
        return ['synced' => $synced, 'failed' => $failed, 'skipped' => $skipped];
    }

    public function syncStockOldToNew(callable $log, int $limit = 500): array
    {
        if (!$this->oldTableHasColumns('sma_products', ['synced_to_new_pos', 'sync_source', 'new_pos_product_id'])) {
            $log('[OLD→NEW:STOCK] Sync columns missing on sma_products. Running setup...', 'warning');
            $this->runSetup($log);
        }
        if (!$this->newTableHasColumns('products', ['synced_to_old_pos', 'sync_source', 'old_pos_product_id'])) {
            $log('[OLD→NEW:STOCK] Sync columns missing on products. Running setup...', 'warning');
            $this->runSetup($log);
        }

        if (!$this->oldTableHasColumns('sma_warehouses_products', ['product_id', 'warehouse_id', 'quantity'])) {
            $log('[OLD→NEW:STOCK] Source stock table sma_warehouses_products is unavailable or incomplete', 'warning');
            return ['synced' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        }
        if (!$this->newTableHasColumns('variation_location_details', ['product_id', 'product_variation_id', 'variation_id', 'location_id', 'qty_available'])) {
            $log('[OLD→NEW:STOCK] Target stock table variation_location_details is unavailable or incomplete', 'warning');
            return ['synced' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $log('[OLD→NEW:STOCK] Checking old POS stock rows...', 'info');

        $oldStockRows = DB::connection('old_pos')
            ->table('sma_warehouses_products as wp')
            ->join('sma_products as p', 'p.id', '=', 'wp.product_id')
            ->where('p.synced_to_new_pos', 1)
            ->whereNull('p.sync_source')
            ->select(
                'wp.product_id',
                'wp.warehouse_id',
                'wp.quantity',
                'p.code as product_code',
                'p.new_pos_product_id'
            )
            ->orderBy('wp.product_id')
            ->orderBy('wp.warehouse_id')
            ->limit($limit)
            ->get();

        if ($oldStockRows->isEmpty()) {
            $log('[OLD→NEW:STOCK] No stock rows to sync', 'info');
            return ['synced' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $newVariationColumns = $this->getNewTableColumns('variation_location_details');
        $hasNewTimestamps = in_array('created_at', $newVariationColumns, true) && in_array('updated_at', $newVariationColumns, true);
        $hasProductLocations = $this->newTableHasColumns('product_locations', ['product_id', 'location_id']);

        $synced = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($oldStockRows as $oldStockRow) {
            try {
                $sourceWarehouseId = (int) ($oldStockRow->warehouse_id ?? 0);
                if (!array_key_exists($sourceWarehouseId, $this->oldWarehouseToNewLocationMapping)) {
                    $skipped++;
                    $log("[OLD→NEW:STOCK] Skipped old product #{$oldStockRow->product_id} (warehouse {$sourceWarehouseId} is not mapped)", 'warning');
                    continue;
                }

                $sku = trim((string) ($oldStockRow->product_code ?? ''));
                $quantity = (float) ($oldStockRow->quantity ?? 0);
                $target = $this->resolveNewProductForStockSync(
                    !empty($oldStockRow->new_pos_product_id) ? (int) $oldStockRow->new_pos_product_id : null,
                    $sku
                );

                if (!$target) {
                    $skipped++;
                    $log("[OLD→NEW:STOCK] Skipped old product #{$oldStockRow->product_id} ({$sku}) - no linked new product found", 'warning');
                    continue;
                }

                $newLocationId = $this->mapOldWarehouseToNewLocation($sourceWarehouseId);
                $newProductId = (int) $target['product']->id;
                $variation = $target['variation'];
                $variationId = (int) $variation->id;
                $productVariationId = (int) ($variation->product_variation_id ?? 0);

                if ($productVariationId <= 0) {
                    $skipped++;
                    $log("[OLD→NEW:STOCK] Skipped old product #{$oldStockRow->product_id} ({$sku}) - target variation is incomplete", 'warning');
                    continue;
                }

                DB::beginTransaction();

                $existingStock = DB::table('variation_location_details')
                    ->where('product_id', $newProductId)
                    ->where('product_variation_id', $productVariationId)
                    ->where('variation_id', $variationId)
                    ->where('location_id', $newLocationId)
                    ->select('id', 'qty_available')
                    ->first();

                if ($existingStock) {
                    if (abs(((float) ($existingStock->qty_available ?? 0)) - $quantity) < 0.0001) {
                        DB::rollBack();
                        $skipped++;
                        $log("[OLD→NEW:STOCK] Skipped new product #{$newProductId} ({$sku}) - quantity unchanged", 'info');
                        continue;
                    }

                    $updateData = [
                        'qty_available' => $quantity,
                    ];
                    if ($hasNewTimestamps) {
                        $updateData['updated_at'] = now();
                    }

                    DB::table('variation_location_details')
                        ->where('id', (int) $existingStock->id)
                        ->update($updateData);

                    $updated++;
                } else {
                    $insertData = [
                        'product_id'           => $newProductId,
                        'product_variation_id' => $productVariationId,
                        'variation_id'         => $variationId,
                        'location_id'          => $newLocationId,
                        'qty_available'        => $quantity,
                    ];
                    if ($hasNewTimestamps) {
                        $insertData['created_at'] = now();
                        $insertData['updated_at'] = now();
                    }

                    DB::table('variation_location_details')->insert($insertData);
                    $synced++;
                }

                if ($hasProductLocations) {
                    DB::table('product_locations')->insertOrIgnore([
                        'product_id'  => $newProductId,
                        'location_id' => $newLocationId,
                    ]);
                }

                DB::commit();
                $log("[OLD→NEW:STOCK] Synced old product #{$oldStockRow->product_id} ({$sku}) -> new product #{$newProductId} at location #{$newLocationId}", 'success');
            } catch (Exception $e) {
                try {
                    DB::rollBack();
                } catch (Exception $rollbackErr) {
                    // Ignore rollback errors on the new DB.
                }
                $failed++;
                $log("[OLD→NEW:STOCK] FAILED old product #{$oldStockRow->product_id}: {$e->getMessage()}", 'error');
                Log::error("syncStockOldToNew failed for old product #{$oldStockRow->product_id}: {$e->getMessage()}");
            }
        }

        $log("[OLD→NEW:STOCK] Done: {$synced} synced, {$updated} updated, {$skipped} skipped, {$failed} failed", $failed > 0 ? 'warning' : 'success');
        return ['synced' => $synced, 'updated' => $updated, 'skipped' => $skipped, 'failed' => $failed];
    }

    public function syncStockNewToOld(callable $log, int $limit = 500): array
    {
        if (!$this->newTableHasColumns('products', ['synced_to_old_pos', 'sync_source', 'old_pos_product_id'])) {
            $log('[NEW→OLD:STOCK] Sync columns missing on products. Running setup...', 'warning');
            $this->runSetup($log);
        }
        if (!$this->oldTableHasColumns('sma_products', ['synced_to_new_pos', 'sync_source', 'new_pos_product_id'])) {
            $log('[NEW→OLD:STOCK] Sync columns missing on sma_products. Running setup...', 'warning');
            $this->runSetup($log);
        }

        if (!$this->newTableHasColumns('variation_location_details', ['product_id', 'product_variation_id', 'variation_id', 'location_id', 'qty_available'])) {
            $log('[NEW→OLD:STOCK] Source stock table variation_location_details is unavailable or incomplete', 'warning');
            return ['synced' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        }
        if (!$this->oldTableHasColumns('sma_warehouses_products', ['product_id', 'warehouse_id', 'quantity'])) {
            $log('[NEW→OLD:STOCK] Target stock table sma_warehouses_products is unavailable or incomplete', 'warning');
            return ['synced' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $log('[NEW→OLD:STOCK] Checking new POS stock rows...', 'info');

        $newStockRows = DB::table('variation_location_details as vld')
            ->join('variations as v', 'v.id', '=', 'vld.variation_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('p.business_id', $this->businessId)
            ->where('p.synced_to_old_pos', 1)
            ->whereNull('p.sync_source')
            ->select(
                'vld.product_id',
                'vld.product_variation_id',
                'vld.variation_id',
                'vld.location_id',
                'vld.qty_available',
                'p.sku as product_sku',
                'p.old_pos_product_id',
                'v.sub_sku as variation_sku'
            )
            ->orderBy('vld.product_id')
            ->orderBy('vld.location_id')
            ->limit($limit)
            ->get();

        if ($newStockRows->isEmpty()) {
            $log('[NEW→OLD:STOCK] No stock rows to sync', 'info');
            return ['synced' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $oldProductColumns = $this->getOldTableColumns('sma_products');
        $hasOldQuantityColumn = in_array('quantity', $oldProductColumns, true);
        $hasOldTimestamps = in_array('created_at', $oldProductColumns, true) && in_array('updated_at', $oldProductColumns, true);

        $synced = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($newStockRows as $newStockRow) {
            try {
                $sourceLocationId = (int) ($newStockRow->location_id ?? 0);
                if (!array_key_exists($sourceLocationId, $this->newLocationToOldWarehouseMapping)) {
                    $skipped++;
                    $log("[NEW→OLD:STOCK] Skipped new product #{$newStockRow->product_id} (location {$sourceLocationId} is not mapped)", 'warning');
                    continue;
                }

                $oldWarehouseId = $this->mapNewLocationToOldWarehouse($sourceLocationId);
                $quantity = (float) ($newStockRow->qty_available ?? 0);
                $sku = trim((string) ($newStockRow->variation_sku ?? ($newStockRow->product_sku ?? '')));

                $oldProduct = null;
                if (!empty($newStockRow->old_pos_product_id)) {
                    $oldProduct = DB::connection('old_pos')->table('sma_products')
                        ->where('id', (int) $newStockRow->old_pos_product_id)
                        ->first();
                }
                if (!$oldProduct && $sku !== '') {
                    $oldProduct = $this->findOldProductBySku($sku, $oldProductColumns);
                }

                if (!$oldProduct) {
                    $skipped++;
                    $log("[NEW→OLD:STOCK] Skipped new product #{$newStockRow->product_id} ({$sku}) - no linked old product found", 'warning');
                    continue;
                }

                $oldProductId = (int) $oldProduct->id;

                DB::connection('old_pos')->beginTransaction();

                $existingStock = DB::connection('old_pos')->table('sma_warehouses_products')
                    ->where('product_id', $oldProductId)
                    ->where('warehouse_id', $oldWarehouseId)
                    ->select('quantity')
                    ->first();

                $oldQuantityNeedsUpdate = $hasOldQuantityColumn && abs(((float) ($oldProduct->quantity ?? 0)) - $quantity) >= 0.0001;

                if ($existingStock && abs(((float) ($existingStock->quantity ?? 0)) - $quantity) < 0.0001 && !$oldQuantityNeedsUpdate) {
                    DB::connection('old_pos')->rollBack();
                    $skipped++;
                    $log("[NEW→OLD:STOCK] Skipped old product #{$oldProductId} ({$sku}) - quantity unchanged", 'info');
                    continue;
                }

                $stockData = [
                    'quantity' => $quantity,
                ];
                if ($hasOldTimestamps) {
                    $stockData['updated_at'] = now();
                }

                if ($existingStock) {
                    DB::connection('old_pos')->table('sma_warehouses_products')
                        ->where('product_id', $oldProductId)
                        ->where('warehouse_id', $oldWarehouseId)
                        ->update($stockData);
                    $updated++;
                } else {
                    if ($hasOldTimestamps) {
                        $stockData['created_at'] = now();
                    }

                    DB::connection('old_pos')->table('sma_warehouses_products')->insert(array_merge([
                        'product_id'   => $oldProductId,
                        'warehouse_id' => $oldWarehouseId,
                    ], $stockData));
                    $synced++;
                }

                if ($oldQuantityNeedsUpdate) {
                    $productUpdate = [
                        'quantity' => $quantity,
                    ];
                    if ($hasOldTimestamps) {
                        $productUpdate['updated_at'] = now();
                    }

                    DB::connection('old_pos')->table('sma_products')
                        ->where('id', $oldProductId)
                        ->update($productUpdate);
                }

                DB::connection('old_pos')->commit();
                $log("[NEW→OLD:STOCK] Synced new product #{$newStockRow->product_id} ({$sku}) -> old product #{$oldProductId} at warehouse #{$oldWarehouseId}", 'success');
            } catch (Exception $e) {
                try {
                    DB::connection('old_pos')->rollBack();
                } catch (Exception $rollbackErr) {
                    // Ignore rollback errors on the old DB.
                }
                $failed++;
                $log("[NEW→OLD:STOCK] FAILED new product #{$newStockRow->product_id}: {$e->getMessage()}", 'error');
                Log::error("syncStockNewToOld failed for new product #{$newStockRow->product_id}: {$e->getMessage()}");
            }
        }

        $log("[NEW→OLD:STOCK] Done: {$synced} synced, {$updated} updated, {$skipped} skipped, {$failed} failed", $failed > 0 ? 'warning' : 'success');
        return ['synced' => $synced, 'updated' => $updated, 'skipped' => $skipped, 'failed' => $failed];
    }

    private function isQuotationTransaction(object $transaction): bool
    {
        $type = strtolower((string) ($transaction->type ?? ''));
        if ($type === 'quotation') {
            return true;
        }

        if (!in_array($type, ['sell', 'draft'], true)) {
            return false;
        }

        return (($transaction->sub_status ?? null) === 'quotation')
            || (($transaction->document_type ?? null) === 'quotation')
            || ((int) ($transaction->is_quotation ?? 0) === 1);
    }

    private function findNewQuotationByReference(string $referenceNo): ?object
    {
        $referenceNo = trim($referenceNo);
        if ($referenceNo === '') {
            return null;
        }

        $query = DB::table('transactions');
        $this->applyQuotationTransactionFilter($query);

        return $query
            ->where(function ($query) use ($referenceNo) {
                $query->where('invoice_no', $referenceNo)
                    ->orWhere('ref_no', $referenceNo);
            })
            ->orderBy('id', 'desc')
            ->first();
    }

    private function createNewQuotationLines(int $oldQuoteId, int $newTransactionId, callable $log): void
    {
        $items = DB::connection('old_pos')
            ->table('sma_quote_items')
            ->where('quote_id', $oldQuoteId)
            ->get();

        foreach ($items as $item) {
            $code = trim((string) ($item->product_code ?? ''));

            $variation = null;
            if ($code !== '') {
                $variation = DB::table('variations as v')
                    ->join('products as p', 'p.id', '=', 'v.product_id')
                    ->where('v.sub_sku', $code)
                    ->where('p.business_id', $this->businessId)
                    ->select('v.id as variation_id', 'p.id as product_id')
                    ->first();
            }

            if (!$variation && !empty($item->product_name)) {
                $variation = DB::table('variations as v')
                    ->join('products as p', 'p.id', '=', 'v.product_id')
                    ->where('p.business_id', $this->businessId)
                    ->where('p.name', $item->product_name)
                    ->select('v.id as variation_id', 'p.id as product_id')
                    ->first();
            }

            if (!$variation) {
                $log("[OLD→NEW:QUOTE] Warning: product '{$code}' not found in new POS — skipping quote line", 'warning');
                continue;
            }

            $qty = (float) ($item->quantity ?? 1);
            $unitPrice = (float) ($item->unit_price ?? 0);
            $unitPriceIncTax = (float) ($item->net_unit_price ?? $unitPrice);
            $discountAmount = (float) ($item->item_discount ?? 0);
            $lineBaseAmount = (float) ($unitPrice * $qty);
            $lineTaxId = $this->resolveNewTaxIdFromOld(
                isset($item->tax_rate_id) ? (int) $item->tax_rate_id : null,
                (float) ($item->item_tax ?? 0),
                $lineBaseAmount,
                $this->businessId
            );

            DB::table('transaction_sell_lines')->insert([
                'transaction_id'             => $newTransactionId,
                'product_id'                 => $variation->product_id,
                'variation_id'               => $variation->variation_id,
                'quantity'                   => $qty,
                'unit_price_before_discount' => $unitPrice,
                'unit_price'                 => $unitPriceIncTax,
                'unit_price_inc_tax'         => $unitPriceIncTax,
                'item_tax'                   => $item->item_tax ?? 0,
                'tax_id'                     => $lineTaxId,
                'line_discount_type'         => 'fixed',
                'line_discount_amount'       => $discountAmount,
                'sell_line_note'             => null,
                'created_at'                 => now(),
                'updated_at'                 => now(),
            ]);
        }
    }

    /**
     * Old→New data integrity step:
     * Backfill missing transactions.tax_id for records synced from old POS.
     */
    public function syncOldToNewTaxFix(callable $log, int $limit = 500): array
    {
        $candidates = DB::table('transactions')
            ->where('business_id', $this->businessId)
            ->where(function ($query) {
                $query->where('sync_source', 'old_pos')
                    ->orWhereNotNull('old_pos_sale_id');
            })
            ->whereNull('tax_id')
            ->where('tax_amount', '>', 0)
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'old_pos_sale_id', 'tax_amount', 'total_before_tax', 'invoice_no']);

        if ($candidates->isEmpty()) {
            $log('[OLD→NEW:TAX] No tax_id backfill candidates found', 'info');
            return ['updated' => 0, 'failed' => 0, 'skipped' => 0];
        }

        $updated = 0;
        $failed = 0;
        $skipped = 0;
        $lineUpdates = 0;

        foreach ($candidates as $transaction) {
            try {
                $oldTaxId = null;
                if (!empty($transaction->old_pos_sale_id)) {
                    $oldSale = DB::connection('old_pos')
                        ->table('sma_sales')
                        ->where('id', (int) $transaction->old_pos_sale_id)
                        ->first(['id', 'order_tax_id']);
                    if ($oldSale && isset($oldSale->order_tax_id)) {
                        $oldTaxId = (int) $oldSale->order_tax_id;
                    }
                }

                $resolvedTaxId = $this->resolveNewTaxIdFromOld(
                    $oldTaxId,
                    (float) $transaction->tax_amount,
                    (float) ($transaction->total_before_tax ?? 0),
                    $this->businessId
                );

                if (empty($resolvedTaxId)) {
                    $skipped++;
                    continue;
                }

                DB::table('transactions')
                    ->where('id', (int) $transaction->id)
                    ->whereNull('tax_id')
                    ->update([
                        'tax_id' => $resolvedTaxId,
                        'updated_at' => now(),
                    ]);

                $lineUpdates += DB::table('transaction_sell_lines')
                    ->where('transaction_id', (int) $transaction->id)
                    ->whereNull('tax_id')
                    ->where('item_tax', '>', 0)
                    ->update([
                        'tax_id' => $resolvedTaxId,
                        'updated_at' => now(),
                    ]);

                $updated++;
            } catch (Exception $e) {
                $failed++;
                Log::warning('[OLD→NEW:TAX] Failed to backfill transaction tax_id', [
                    'transaction_id' => $transaction->id ?? null,
                    'invoice_no' => $transaction->invoice_no ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $log(
            "[OLD→NEW:TAX] Backfill done: updated {$updated}, skipped {$skipped}, failed {$failed}, line-updates {$lineUpdates}",
            $failed > 0 ? 'warning' : 'success'
        );

        return ['updated' => $updated, 'failed' => $failed, 'skipped' => $skipped];
    }

    private function createOldQuoteItems(int $newTransactionId, int $oldQuoteId, int $oldWarehouseId, callable $log): int
    {
        $oldQuoteItemColumns = $this->getOldQuoteItemColumns();

        $sellLines = DB::table('transaction_sell_lines as sl')
            ->leftJoin('variations as v', 'v.id', '=', 'sl.variation_id')
            ->leftJoin('products as p', 'p.id', '=', 'sl.product_id')
            ->where('sl.transaction_id', $newTransactionId)
            ->select(
                'sl.*',
                'v.sub_sku as product_code',
                'p.sku as product_sku',
                'p.name as product_name'
            )
            ->get();

        $count = 0;

        foreach ($sellLines as $line) {
            $productCode = trim((string) ($line->product_code ?: ($line->product_sku ?? '')));
            $productName = trim((string) ($line->product_name ?? ''));

            $oldProduct = null;
            if ($productCode !== '') {
                $oldProduct = DB::connection('old_pos')->table('sma_products')
                    ->where('code', $productCode)
                    ->first();
            }

            if (!$oldProduct && $productName !== '') {
                $oldProduct = DB::connection('old_pos')->table('sma_products')
                    ->where('name', $productName)
                    ->first();
            }

            if (!$oldProduct) {
                $log("[NEW→OLD:QUOTE] Warning: product '{$productCode}' ({$productName}) not found in old POS — skipping quote line", 'warning');
                continue;
            }

            $qty = (float) ($line->quantity ?? 1);
            $netPrice = (float) ($line->unit_price_inc_tax ?? ($line->unit_price ?? 0));
            $unitPrice = (float) ($line->unit_price_before_discount ?? $netPrice);
            $discountAmount = (float) ($line->line_discount_amount ?? 0);
            $subtotal = round($qty * $netPrice, 4);

            $insertData = [
                'quote_id'        => $oldQuoteId,
                'product_id'      => $oldProduct->id,
                'product_code'    => $oldProduct->code ?? $productCode,
                'product_name'    => $oldProduct->name ?? $productName,
                'product_type'    => $oldProduct->type ?? 'standard',
                'net_unit_price'  => $netPrice,
                'unit_price'      => $unitPrice,
                'quantity'        => $qty,
                'warehouse_id'    => $oldWarehouseId,
                'item_tax'        => $line->item_tax ?? 0,
                'tax_rate_id'     => null,
                'tax'             => '0',
                'discount'        => (string) $discountAmount,
                'item_discount'   => $discountAmount,
                'subtotal'        => $subtotal,
                'unit_quantity'   => $qty,
            ];

            if (in_array('real_unit_price', $oldQuoteItemColumns, true)) {
                $insertData['real_unit_price'] = $unitPrice;
            }
            if (in_array('option_id', $oldQuoteItemColumns, true)) {
                $insertData['option_id'] = null;
            }
            if (in_array('serial_no', $oldQuoteItemColumns, true)) {
                $insertData['serial_no'] = null;
            }
            if (in_array('product_unit_id', $oldQuoteItemColumns, true)) {
                $insertData['product_unit_id'] = $oldProduct->unit ?? null;
            }
            if (in_array('product_unit_code', $oldQuoteItemColumns, true)) {
                $insertData['product_unit_code'] = null;
            }

            DB::connection('old_pos')->table('sma_quote_items')->insert($insertData);
            $count++;
        }

        return $count;
    }

    private function findOldQuoteByReference(string $referenceNo): ?object
    {
        $referenceNo = trim($referenceNo);
        if ($referenceNo === '') {
            return null;
        }

        return DB::connection('old_pos')
            ->table('sma_quotes')
            ->where('reference_no', $referenceNo)
            ->select('id', 'reference_no', 'new_pos_transaction_id')
            ->first();
    }

    private function canAttachToExistingOldQuote(object $oldQuote, int $newTransactionId): bool
    {
        if (empty($oldQuote->new_pos_transaction_id)) {
            return true;
        }

        return (int) $oldQuote->new_pos_transaction_id === $newTransactionId;
    }

    private function buildUniqueOldQuoteReferenceNo(string $baseRefNo, int $transactionId): string
    {
        $referenceNo = trim($baseRefNo) !== '' ? trim($baseRefNo) : ('QT-' . $transactionId);
        if (!$this->oldQuoteReferenceNoExists($referenceNo)) {
            return $referenceNo;
        }

        $candidate = $referenceNo . '-NQ' . $transactionId;
        if (!$this->oldQuoteReferenceNoExists($candidate)) {
            return $candidate;
        }

        return 'NQ-' . $transactionId . '-' . substr(md5($referenceNo), 0, 6);
    }

    private function oldQuoteReferenceNoExists(string $referenceNo): bool
    {
        return DB::connection('old_pos')
            ->table('sma_quotes')
            ->where('reference_no', $referenceNo)
            ->exists();
    }

    private function getOldQuoteItemColumns(): array
    {
        if ($this->oldQuoteItemColumns === null) {
            $this->oldQuoteItemColumns = $this->getOldTableColumns('sma_quote_items');
        }

        return $this->oldQuoteItemColumns;
    }

    private function getNewProductColumns(): array
    {
        if ($this->newProductColumns === null) {
            $this->newProductColumns = $this->getNewTableColumns('products');
        }

        return $this->newProductColumns;
    }

    private function hasNewProductColumn(string $column): bool
    {
        return in_array($column, $this->getNewProductColumns(), true);
    }

    private function findPrimaryVariationForProduct(int $productId): ?object
    {
        return DB::table('variations')
            ->where('product_id', $productId)
            ->orderByRaw("CASE WHEN sub_sku IS NULL OR sub_sku = '' THEN 1 ELSE 0 END")
            ->orderBy('id')
            ->first();
    }

    private function ensureDummyVariationForProduct(int $productId, string $sku, float $cost, float $price): int
    {
        $existingVariation = $this->findPrimaryVariationForProduct($productId);
        if ($existingVariation) {
            DB::table('variations')->where('id', $existingVariation->id)->update([
                'sub_sku'                => $sku,
                'default_purchase_price' => $cost,
                'dpp_inc_tax'            => $cost,
                'default_sell_price'     => $price,
                'sell_price_inc_tax'     => $price,
                'updated_at'             => now(),
            ]);

            return (int) $existingVariation->id;
        }

        $productVariation = DB::table('product_variations')
            ->where('product_id', $productId)
            ->orderBy('id')
            ->first();

        if (!$productVariation) {
            $productVariationId = DB::table('product_variations')->insertGetId([
                'name'       => 'DUMMY',
                'product_id' => $productId,
                'is_dummy'   => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $productVariationId = (int) $productVariation->id;
        }

        return (int) DB::table('variations')->insertGetId([
            'name'                   => 'DUMMY',
            'product_id'             => $productId,
            'sub_sku'                => $sku,
            'product_variation_id'   => $productVariationId,
            'variation_value_id'     => null,
            'default_purchase_price' => $cost,
            'dpp_inc_tax'            => $cost,
            'profit_percent'         => 0,
            'default_sell_price'     => $price,
            'sell_price_inc_tax'     => $price,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);
    }

    private function findNewProductBySku(string $sku): ?object
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        $variationMatch = DB::table('variations as v')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('p.business_id', $this->businessId)
            ->where('v.sub_sku', $sku)
            ->select('p.id as product_id', 'v.id as variation_id')
            ->first();

        if ($variationMatch) {
            return $variationMatch;
        }

        $productMatch = DB::table('products')
            ->where('business_id', $this->businessId)
            ->where('sku', $sku)
            ->select('id')
            ->first();

        if (!$productMatch) {
            return null;
        }

        $variation = $this->findPrimaryVariationForProduct((int) $productMatch->id);
        return (object) [
            'product_id'   => (int) $productMatch->id,
            'variation_id' => $variation ? (int) $variation->id : null,
        ];
    }

    private function findNewVariationBySku(int $productId, string $sku): ?object
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        return DB::table('variations')
            ->where('product_id', $productId)
            ->where('sub_sku', $sku)
            ->select('id', 'product_id', 'product_variation_id', 'sub_sku')
            ->first();
    }

    private function resolveNewProductForStockSync(?int $linkedProductId, string $sku): ?array
    {
        $sku = trim($sku);
        $product = null;
        $variation = null;

        if ($linkedProductId) {
            $product = DB::table('products')
                ->where('business_id', $this->businessId)
                ->where('id', $linkedProductId)
                ->first();

            if ($product) {
                if ($sku !== '') {
                    $variation = $this->findNewVariationBySku((int) $product->id, $sku);
                }

                if (!$variation) {
                    $variation = $this->findPrimaryVariationForProduct((int) $product->id);
                }
            }
        }

        if ((!$product || !$variation) && $sku !== '') {
            $matched = $this->findNewProductBySku($sku);

            if ($matched) {
                $product = DB::table('products')
                    ->where('business_id', $this->businessId)
                    ->where('id', (int) $matched->product_id)
                    ->first();

                if ($product) {
                    if (!empty($matched->variation_id)) {
                        $variation = DB::table('variations')
                            ->where('id', (int) $matched->variation_id)
                            ->first();
                    }

                    if (!$variation) {
                        $variation = $this->findPrimaryVariationForProduct((int) $product->id);
                    }
                }
            }
        }

        if (!$product || !$variation) {
            return null;
        }

        return [
            'product'   => $product,
            'variation' => $variation,
        ];
    }

    private function findOldProductBySku(string $sku, array $oldProductColumns): ?object
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        $select = ['id', 'code'];
        foreach (['name', 'sync_source', 'synced_to_new_pos', 'new_pos_product_id', 'quantity'] as $column) {
            if (in_array($column, $oldProductColumns, true)) {
                $select[] = $column;
            }
        }

        return DB::connection('old_pos')
            ->table('sma_products')
            ->where('code', $sku)
            ->select($select)
            ->first();
    }

    private function mapOldCategoryToNew(?int $oldCategoryId): ?int
    {
        if (empty($oldCategoryId)) {
            return null;
        }

        $oldCategory = DB::connection('old_pos')->table('sma_categories')
            ->where('id', $oldCategoryId)
            ->select('name')
            ->first();

        if (!$oldCategory || trim((string) $oldCategory->name) === '') {
            return null;
        }

        $normalized = mb_strtolower(trim((string) $oldCategory->name));
        $newCategory = DB::table('categories')
            ->where('business_id', $this->businessId)
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->select('id')
            ->first();

        return $newCategory ? (int) $newCategory->id : null;
    }

    private function mapNewCategoryToOld(?int $newCategoryId): ?int
    {
        if (empty($newCategoryId)) {
            return null;
        }

        $newCategory = DB::table('categories')
            ->where('id', $newCategoryId)
            ->select('name')
            ->first();

        if (!$newCategory || trim((string) $newCategory->name) === '') {
            return null;
        }

        $normalized = mb_strtolower(trim((string) $newCategory->name));
        $oldCategory = DB::connection('old_pos')->table('sma_categories')
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->select('id')
            ->first();

        return $oldCategory ? (int) $oldCategory->id : null;
    }

    // ─────────────────────────────────────────────────────────────────
    // MAPPING HELPERS
    // ─────────────────────────────────────────────────────────────────

    private function mapUserId(?int $oldUserId): int
    {
        return $this->userIdMapping[$oldUserId] ?? 1;
    }

    private function resolveNewTaxIdFromOld(?int $oldTaxRateId, float $taxAmount, float $baseAmount, int $businessId): ?int
    {
        if (!empty($oldTaxRateId)) {
            $mappedById = $this->mapOldTaxRateToNew($oldTaxRateId, $businessId);
            if (!empty($mappedById)) {
                return $mappedById;
            }
        }

        if ($taxAmount <= 0 || $baseAmount <= 0) {
            return null;
        }

        $percent = ($taxAmount / $baseAmount) * 100;
        return $this->findClosestBusinessTaxRateByPercent($businessId, $percent);
    }

    private function mapOldTaxRateToNew(int $oldTaxRateId, int $businessId): ?int
    {
        if ($this->oldToNewTaxRateIdMap === null) {
            $this->oldToNewTaxRateIdMap = DB::table('migration_mappings')
                ->where('old_table', 'tax_rates')
                ->where('new_table', 'tax_rates')
                ->pluck('new_id', 'old_id')
                ->mapWithKeys(function ($newId, $oldId) {
                    return [(int) $oldId => (int) $newId];
                })
                ->all();
        }

        if (!empty($this->oldToNewTaxRateIdMap[$oldTaxRateId])) {
            return (int) $this->oldToNewTaxRateIdMap[$oldTaxRateId];
        }

        $sameIdExists = DB::table('tax_rates')
            ->where('business_id', $businessId)
            ->where('id', $oldTaxRateId)
            ->exists();

        return $sameIdExists ? $oldTaxRateId : null;
    }

    private function findClosestBusinessTaxRateByPercent(int $businessId, float $percent): ?int
    {
        $rates = $this->loadBusinessTaxRates($businessId);
        if (empty($rates)) {
            return null;
        }

        $bestId = null;
        $bestDelta = null;
        foreach ($rates as $rate) {
            $delta = abs(((float) $rate->amount) - $percent);
            if ($bestDelta === null || $delta < $bestDelta) {
                $bestDelta = $delta;
                $bestId = (int) $rate->id;
            }
        }

        if ($bestDelta === null || $bestDelta > 0.25) {
            return null;
        }

        return $bestId;
    }

    private function loadBusinessTaxRates(int $businessId): array
    {
        if (isset($this->businessTaxRatesByBusinessId[$businessId])) {
            return $this->businessTaxRatesByBusinessId[$businessId];
        }

        $this->businessTaxRatesByBusinessId[$businessId] = DB::table('tax_rates')
            ->where('business_id', $businessId)
            ->where(function ($query) {
                $query->whereNull('is_tax_group')
                    ->orWhere('is_tax_group', 0);
            })
            ->orderBy('id')
            ->get(['id', 'amount'])
            ->all();

        return $this->businessTaxRatesByBusinessId[$businessId];
    }

    private function mapNewUserToOld(?int $newUserId): int
    {
        $reversed = array_flip($this->userIdMapping);
        return $reversed[$newUserId] ?? 4; // Default to old user 4
    }

    private function mapOldStatusToNew(string $oldStatus): string
    {
        return match ($oldStatus) {
            'completed' => 'final',
            'pending'   => 'draft',
            'cancelled' => 'cancelled',
            default     => 'final',
        };
    }

    private function mapNewStatusToOld(string $newStatus): string
    {
        return match ($newStatus) {
            'final'     => 'completed',
            'draft'     => 'pending',
            'cancelled' => 'cancelled',
            default     => 'completed',
        };
    }

    private function mapOldQuoteStatusToNew(string $oldStatus): string
    {
        $oldStatus = strtolower(trim($oldStatus));

        return match ($oldStatus) {
            'cancelled', 'canceled' => 'cancelled',
            'completed', 'approved' => 'final',
            default                 => 'draft',
        };
    }

    private function mapNewQuoteStatusToOld(string $newStatus): string
    {
        $newStatus = strtolower(trim($newStatus));

        return match ($newStatus) {
            'cancelled' => 'cancelled',
            'final'     => 'approved',
            default     => 'pending',
        };
    }

    private function mapOldPaymentStatusToNew(string $oldStatus): string
    {
        return match ($oldStatus) {
            'paid'    => 'paid',
            'partial' => 'partial',
            'unpaid'  => 'due',
            default   => 'due',
        };
    }

    private function mapNewPaymentStatusToOld(string $newStatus): string
    {
        return match ($newStatus) {
            'paid'    => 'paid',
            'partial' => 'partial',
            'due'     => 'unpaid',
            default   => 'unpaid',
        };
    }

    private function mapOldPaymentMethodToNew(string $oldMethod): string
    {
        return match (strtolower($oldMethod)) {
            'cash'          => 'cash',
            'cheque'        => 'cheque',
            'cc'            => 'card',
            'deposit'       => 'custom_1',
            'transfer',
            'bank_transfer' => 'bank_transfer',
            'gift_card',
            'other'         => 'other',
            default         => 'cash',
        };
    }

    private function mapNewPaymentMethodToOld(string $newMethod): string
    {
        return match (strtolower($newMethod)) {
            'cash'          => 'cash',
            'cheque'        => 'Cheque',
            'card'          => 'CC',
            'bank_transfer',
            'transfer'      => 'bank_transfer',
            'custom_1'      => 'deposit',
            'other'         => 'other',
            default         => 'cash',
        };
    }

    private function resolveNewDocumentTypeFromReference(string $referenceNo, string $newStatus): ?string
    {
        $reference = strtoupper(trim($referenceNo));

        if ($reference !== '' && str_starts_with($reference, 'IPAY')) {
            return 'final';
        }

        if ($reference !== '' && str_starts_with($reference, 'VT')) {
            return 'proforma';
        }

        if ($reference !== '' && (str_starts_with($reference, 'QT') || str_starts_with($reference, 'QUO'))) {
            return 'quotation';
        }

        if ($newStatus === 'draft') {
            return 'proforma';
        }

        return null;
    }

    private function resolveNewSubStatusFromDocumentType(?string $documentType): ?string
    {
        return match ($documentType) {
            'proforma'  => 'proforma',
            'quotation' => 'quotation',
            default     => null,
        };
    }

    /**
     * Hydrate old/new location mapping from DB so multi-location sync stays isolated.
     * Priority:
     * 1) hardcoded mapping defaults
     * 2) migration_mappings (warehouses -> business_locations)
     * 3) business_locations.location_id suffix (e.g. BL0002 -> warehouse 2)
     */
    private function hydrateLocationMappings(): void
    {
        if ($this->locationMappingsHydrated) {
            return;
        }

        $this->locationMappingsHydrated = true;

        $resolvedOldToNew = $this->normalizeIdMap($this->oldWarehouseToNewLocationMapping);
        $resolvedNewToOld = $this->normalizeIdMap($this->newLocationToOldWarehouseMapping);
        $businessLocations = $this->loadBusinessLocationIndex();

        if (!empty($businessLocations)) {
            foreach ($this->loadWarehouseLocationMappingsFromMigration($businessLocations) as $oldWarehouseId => $newLocationId) {
                $resolvedOldToNew[(int) $oldWarehouseId] = (int) $newLocationId;
            }

            $oldWarehouseIds = $this->loadOldWarehouseIds();
            if (!empty($oldWarehouseIds)) {
                foreach ($businessLocations as $location) {
                    $oldWarehouseId = $this->extractWarehouseIdFromLocationCode($location['location_code']);
                    if ($oldWarehouseId === null || !isset($oldWarehouseIds[$oldWarehouseId])) {
                        continue;
                    }

                    if (!isset($resolvedOldToNew[$oldWarehouseId])) {
                        $resolvedOldToNew[$oldWarehouseId] = (int) $location['id'];
                    }
                }
            }
        }

        foreach ($resolvedOldToNew as $oldWarehouseId => $newLocationId) {
            if (!isset($businessLocations[$newLocationId])) {
                continue;
            }
            $resolvedNewToOld[$newLocationId] = $oldWarehouseId;
        }

        foreach ($resolvedNewToOld as $newLocationId => $oldWarehouseId) {
            if (!isset($businessLocations[$newLocationId])) {
                unset($resolvedNewToOld[$newLocationId]);
            }
        }

        if (!empty($resolvedOldToNew)) {
            $this->oldWarehouseToNewLocationMapping = $resolvedOldToNew;
        }

        if (!empty($resolvedNewToOld)) {
            $this->newLocationToOldWarehouseMapping = $resolvedNewToOld;
        }

        if (isset($this->oldWarehouseToNewLocationMapping[$this->defaultWarehouseId])) {
            $this->locationId = (int) $this->oldWarehouseToNewLocationMapping[$this->defaultWarehouseId];
        } elseif (!isset($businessLocations[$this->locationId]) && !empty($this->oldWarehouseToNewLocationMapping)) {
            $this->locationId = (int) reset($this->oldWarehouseToNewLocationMapping);
        }

        if (isset($this->newLocationToOldWarehouseMapping[$this->locationId])) {
            $this->defaultWarehouseId = (int) $this->newLocationToOldWarehouseMapping[$this->locationId];
        } elseif (!empty($this->newLocationToOldWarehouseMapping)) {
            $this->defaultWarehouseId = (int) reset($this->newLocationToOldWarehouseMapping);
        }

        $this->hydrateWarehouseBillerMappings();
    }

    private function loadBusinessLocationIndex(): array
    {
        try {
            $rows = DB::table('business_locations')
                ->where('business_id', $this->businessId)
                ->select('id', 'location_id')
                ->get();
        } catch (\Throwable $e) {
            Log::warning('[SYNC] Failed to load business locations for mapping hydration: ' . $e->getMessage());
            return [];
        }

        $locations = [];
        foreach ($rows as $row) {
            $id = (int) ($row->id ?? 0);
            if ($id <= 0) {
                continue;
            }

            $locations[$id] = [
                'id' => $id,
                'location_code' => trim((string) ($row->location_id ?? '')),
            ];
        }

        return $locations;
    }

    private function loadWarehouseLocationMappingsFromMigration(array $businessLocations): array
    {
        try {
            $rows = DB::table('migration_mappings')
                ->whereIn('old_table', ['warehouses', 'sma_warehouses'])
                ->where('new_table', 'business_locations')
                ->select('old_id', 'new_id')
                ->get();
        } catch (\Throwable $e) {
            return [];
        }

        $mappings = [];
        foreach ($rows as $row) {
            $oldWarehouseId = (int) ($row->old_id ?? 0);
            $newLocationId = (int) ($row->new_id ?? 0);

            if ($oldWarehouseId <= 0 || $newLocationId <= 0 || !isset($businessLocations[$newLocationId])) {
                continue;
            }

            $mappings[$oldWarehouseId] = $newLocationId;
        }

        return $mappings;
    }

    private function loadOldWarehouseIds(): array
    {
        $table = $this->oldTableHasColumns('sma_warehouses', ['id']) ? 'sma_warehouses' : null;
        if ($table === null && $this->oldTableHasColumns('warehouses', ['id'])) {
            $table = 'warehouses';
        }

        if ($table === null) {
            return [];
        }

        try {
            $rows = DB::connection('old_pos')
                ->table($table)
                ->select('id')
                ->get();
        } catch (\Throwable $e) {
            return [];
        }

        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row->id ?? 0);
            if ($id > 0) {
                $ids[$id] = true;
            }
        }

        return $ids;
    }

    private function extractWarehouseIdFromLocationCode(?string $locationCode): ?int
    {
        if ($locationCode === null) {
            return null;
        }

        $locationCode = strtoupper(trim($locationCode));
        if ($locationCode === '') {
            return null;
        }

        if (preg_match('/(\d+)$/', $locationCode, $matches) !== 1) {
            return null;
        }

        $warehouseId = (int) ltrim($matches[1], '0');
        if ($warehouseId <= 0) {
            return null;
        }

        return $warehouseId;
    }

    private function hydrateWarehouseBillerMappings(): void
    {
        $resolved = $this->normalizeIdMap($this->oldWarehouseToOldBillerMapping);

        try {
            $rows = DB::connection('old_pos')
                ->table('sma_sales')
                ->select('warehouse_id', 'biller_id', DB::raw('COUNT(*) as aggregate_count'))
                ->whereNotNull('warehouse_id')
                ->whereNotNull('biller_id')
                ->groupBy('warehouse_id', 'biller_id')
                ->orderBy('warehouse_id')
                ->orderByDesc('aggregate_count')
                ->get();
        } catch (\Throwable $e) {
            $rows = collect();
        }

        foreach ($rows as $row) {
            $warehouseId = (int) ($row->warehouse_id ?? 0);
            $billerId = (int) ($row->biller_id ?? 0);

            if ($warehouseId <= 0 || $billerId <= 0 || isset($resolved[$warehouseId])) {
                continue;
            }

            $resolved[$warehouseId] = $billerId;
        }

        if (!empty($resolved)) {
            $this->oldWarehouseToOldBillerMapping = $resolved;
        }

        if (isset($this->oldWarehouseToOldBillerMapping[$this->defaultWarehouseId])) {
            $this->defaultBillerId = (int) $this->oldWarehouseToOldBillerMapping[$this->defaultWarehouseId];
        }
    }

    private function normalizeIdMap(array $input): array
    {
        $normalized = [];

        foreach ($input as $source => $target) {
            $sourceId = (int) $source;
            $targetId = (int) $target;
            if ($sourceId <= 0 || $targetId <= 0) {
                continue;
            }

            $normalized[$sourceId] = $targetId;
        }

        return $normalized;
    }

    private function mapOldWarehouseToNewLocation(?int $oldWarehouseId): int
    {
        $this->hydrateLocationMappings();

        $warehouseId = $oldWarehouseId !== null ? (int) $oldWarehouseId : null;
        if ($warehouseId !== null && isset($this->oldWarehouseToNewLocationMapping[$warehouseId])) {
            return (int) $this->oldWarehouseToNewLocationMapping[$warehouseId];
        }

        // A non-null warehouse with no mapping would otherwise be silently dumped into the
        // default location. Warn so multi-warehouse data isn't mislocated without a trace.
        if ($warehouseId !== null) {
            Log::warning("[SYNC] Old warehouse #{$warehouseId} has no new-POS location mapping; falling back to default location #{$this->locationId}");
        }

        return (int) $this->locationId;
    }

    private function mapNewLocationToOldWarehouse(?int $newLocationId): int
    {
        $this->hydrateLocationMappings();

        $locationId = $newLocationId !== null ? (int) $newLocationId : null;
        if ($locationId !== null && isset($this->newLocationToOldWarehouseMapping[$locationId])) {
            return (int) $this->newLocationToOldWarehouseMapping[$locationId];
        }

        return (int) $this->defaultWarehouseId;
    }

    /**
     * Resolve biller (receiver) in old POS by warehouse mapping, with safe fallback.
     */
    private function resolveOldBillerForWarehouse(int $oldWarehouseId): array
    {
        $this->hydrateLocationMappings();

        $billerId = $this->oldWarehouseToOldBillerMapping[$oldWarehouseId] ?? $this->defaultBillerId;

        $biller = DB::connection('old_pos')->table('sma_companies')
            ->where('id', $billerId)
            ->select('id', 'company', 'name')
            ->first();

        if (!$biller) {
            $biller = DB::connection('old_pos')->table('sma_companies')
                ->where('id', $this->defaultBillerId)
                ->select('id', 'company', 'name')
                ->first();
        }

        return [
            'id' => (int) ($biller->id ?? $this->defaultBillerId),
            'name' => (string) ($biller->company ?? $biller->name ?? 'Default'),
        ];
    }

    private function buildUniqueOldReferenceNo(string $baseRefNo, int $transactionId): string
    {
        $referenceNo = trim($baseRefNo) !== '' ? trim($baseRefNo) : ('NEW-' . $transactionId);

        if (!$this->oldReferenceNoExists($referenceNo)) {
            return $referenceNo;
        }

        $preservedPrefixCandidate = $referenceNo . '-NP' . $transactionId;
        if (!$this->oldReferenceNoExists($preservedPrefixCandidate)) {
            return $preservedPrefixCandidate;
        }

        return 'NP-' . $transactionId . '-' . substr(md5($referenceNo), 0, 6);
    }

    private function oldReferenceNoExists(string $referenceNo): bool
    {
        return DB::connection('old_pos')
            ->table('sma_sales')
            ->where('reference_no', $referenceNo)
            ->exists();
    }

    private function findOldSaleByReference(string $referenceNo): ?object
    {
        if (trim($referenceNo) === '') {
            return null;
        }

        return DB::connection('old_pos')
            ->table('sma_sales')
            ->where('reference_no', trim($referenceNo))
            ->select('id', 'reference_no', 'new_pos_transaction_id')
            ->first();
    }

    private function canAttachToExistingOldSale(object $oldSale, int $newTransactionId): bool
    {
        if (empty($oldSale->new_pos_transaction_id)) {
            return true;
        }

        return (int) $oldSale->new_pos_transaction_id === $newTransactionId;
    }

    private function getOldSaleItemColumns(): array
    {
        if ($this->oldSaleItemColumns === null) {
            $this->oldSaleItemColumns = $this->getOldTableColumns('sma_sale_items');
        }

        return $this->oldSaleItemColumns;
    }

    // ─────────────────────────────────────────────────────────────────
    // PAYMENT UPDATE SYNC: Re-sync payment status/records for already-synced bills
    // ─────────────────────────────────────────────────────────────────

    /**
     * For bills already synced Old→New, check if payment_status has changed
     * in the old POS and push the update (payments + status) to the new POS.
     */
    public function syncPaymentUpdates(callable $log, int $limit = 200): array
    {
        $log('[PAY-SYNC] Checking old POS for payment updates on synced bills...', 'info');

        // Fetch synced old sales that have a matching new transaction
        $oldSales = DB::connection('old_pos')
            ->table('sma_sales')
            ->where('synced_to_new_pos', 1)
            ->whereNotNull('new_pos_transaction_id')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();

        if ($oldSales->isEmpty()) {
            $log('[PAY-SYNC] No synced bills found in old POS', 'info');
            return ['updated' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $log("[PAY-SYNC] Checking {$oldSales->count()} synced bill(s) for payment changes...", 'info');

        $updated = 0;
        $skipped = 0;
        $failed  = 0;

        foreach ($oldSales as $oldSale) {
            try {
                $newTxId = (int) $oldSale->new_pos_transaction_id;

                $newTx = DB::table('transactions')->where('id', $newTxId)->first();
                if (!$newTx) {
                    $skipped++;
                    continue;
                }

                $newPaymentStatus = $this->mapOldPaymentStatusToNew($oldSale->payment_status ?? 'unpaid');

                // Skip if payment status already matches
                if ($newTx->payment_status === $newPaymentStatus) {
                    $skipped++;
                    continue;
                }

                DB::beginTransaction();

                // Delete existing payment rows for this transaction
                DB::table('transaction_payments')
                    ->where('transaction_id', $newTxId)
                    ->delete();

                // Re-insert all payments from old POS
                $newUserId = $this->mapUserId(null);
                $this->createNewPayments($oldSale->id, $newTxId, $newUserId, $log);

                // Update payment_status on the transaction
                DB::table('transactions')
                    ->where('id', $newTxId)
                    ->update([
                        'payment_status' => $newPaymentStatus,
                        'updated_at'     => now(),
                    ]);

                DB::commit();
                $updated++;
                $log(
                    "[PAY-SYNC] Updated {$oldSale->reference_no}: {$newTx->payment_status} → {$newPaymentStatus}",
                    'success'
                );

            } catch (Exception $e) {
                DB::rollBack();
                $failed++;
                $log("[PAY-SYNC] FAILED {$oldSale->reference_no}: " . $e->getMessage(), 'error');
                Log::error("syncPaymentUpdates failed for old sale #{$oldSale->id}: " . $e->getMessage());
            }
        }

        $log("[PAY-SYNC] Done: {$updated} updated, {$skipped} skipped (no change), {$failed} failed", $failed > 0 ? 'warning' : 'success');
        return ['updated' => $updated, 'skipped' => $skipped, 'failed' => $failed];
    }

    /**
     * For bills already synced New→Old, check if payment_status has changed
     * in the new POS and push the update (payments + status) to old POS.
     */
    public function syncPaymentUpdatesNewToOld(callable $log, int $limit = 200): array
    {
        $log('[PAY-SYNC NEW→OLD] Checking new POS for payment updates on linked bills...', 'info');

        $newTransactions = DB::table('transactions')
            ->where('type', 'sell')
            ->where('synced_to_old_pos', 1)
            ->whereNotNull('old_pos_sale_id')
            ->where(function ($query) {
                $query->whereNull('sub_status')
                    ->orWhere('sub_status', '!=', 'quotation');
            })
            ->where(function ($query) {
                $query->whereNull('document_type')
                    ->orWhere('document_type', '!=', 'quotation');
            })
            ->where(function ($query) {
                $query->whereNull('is_quotation')
                    ->orWhere('is_quotation', '!=', 1);
            })
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();

        if ($newTransactions->isEmpty()) {
            $log('[PAY-SYNC NEW→OLD] No linked bills found in new POS', 'info');
            return ['updated' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $log("[PAY-SYNC NEW→OLD] Checking {$newTransactions->count()} linked bill(s) for payment changes...", 'info');

        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($newTransactions as $transaction) {
            try {
                $oldSaleId = (int) $transaction->old_pos_sale_id;
                $oldSale = DB::connection('old_pos')->table('sma_sales')->where('id', $oldSaleId)->first();
                if (!$oldSale) {
                    $skipped++;
                    continue;
                }

                $oldPaymentStatus = $this->mapNewPaymentStatusToOld((string) ($transaction->payment_status ?? 'due'));
                if (($oldSale->payment_status ?? null) === $oldPaymentStatus) {
                    $skipped++;
                    continue;
                }

                DB::connection('old_pos')->beginTransaction();

                DB::connection('old_pos')->table('sma_payments')
                    ->where('sale_id', $oldSaleId)
                    ->delete();

                $oldUserId = $this->mapNewUserToOld($transaction->created_by ?? null);
                $this->createOldPayments((int) $transaction->id, $oldSaleId, $oldUserId, $log);

                $paidAmount = (float) DB::connection('old_pos')->table('sma_payments')
                    ->where('sale_id', $oldSaleId)
                    ->sum('amount');

                DB::connection('old_pos')->table('sma_sales')
                    ->where('id', $oldSaleId)
                    ->update([
                        'payment_status' => $oldPaymentStatus,
                        'paid'           => $paidAmount,
                        'updated_at'     => now(),
                    ]);

                DB::connection('old_pos')->commit();
                $updated++;
                $log(
                    "[PAY-SYNC NEW→OLD] Updated bill #{$oldSaleId}: {$oldSale->payment_status} → {$oldPaymentStatus}",
                    'success'
                );
            } catch (Exception $e) {
                try {
                    DB::connection('old_pos')->rollBack();
                } catch (Exception $rollbackErr) {
                    // Ignore rollback errors.
                }
                $failed++;
                $log("[PAY-SYNC NEW→OLD] FAILED transaction #{$transaction->id}: {$e->getMessage()}", 'error');
                Log::error("syncPaymentUpdatesNewToOld failed for transaction #{$transaction->id}: {$e->getMessage()}");
            }
        }

        $log("[PAY-SYNC NEW→OLD] Done: {$updated} updated, {$skipped} skipped (no change), {$failed} failed", $failed > 0 ? 'warning' : 'success');
        return ['updated' => $updated, 'skipped' => $skipped, 'failed' => $failed];
    }
}
