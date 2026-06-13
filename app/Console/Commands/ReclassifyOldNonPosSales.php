<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReclassifyOldNonPosSales extends Command
{
    protected $signature = 'sync:reclassify-old-nonpos-sales
                            {--business-id= : Limit to one business_id}
                            {--chunk=200 : Chunk size for processing}
                            {--apply : Apply updates (default is dry-run)}';

    protected $description = 'Reclassify migrated old POS sales with old pos!=1 as direct-sale in new POS (hide from /pos list).';

    public function handle(): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $businessId = $this->option('business-id');
        $apply = (bool) $this->option('apply');

        $this->ensureOldConnection();
        $oldSalesTable = $this->resolveOldSalesTable();

        $stats = [
            'scanned' => 0,
            'old_sale_found' => 0,
            'old_pos_rows' => 0,
            'non_pos_rows' => 0,
            'already_direct' => 0,
            'would_update' => 0,
            'updated' => 0,
            'missing_old_sale' => 0,
            'failed' => 0,
        ];

        $this->line('[Reclassify Non-POS] Starting...');
        $this->line('[Reclassify Non-POS] Mode: ' . ($apply ? 'APPLY' : 'DRY RUN'));
        $this->line('[Reclassify Non-POS] Chunk size: ' . $chunk);
        $this->line('[Reclassify Non-POS] Old sales table: ' . $oldSalesTable);
        if (!empty($businessId)) {
            $this->line('[Reclassify Non-POS] Business filter: ' . $businessId);
        }

        $query = DB::table('transactions')
            ->select('id', 'business_id', 'invoice_no', 'old_pos_sale_id', 'is_direct_sale', 'sync_source')
            ->where('type', 'sell')
            ->whereNotNull('old_pos_sale_id');

        if (!empty($businessId) && is_numeric($businessId)) {
            $query->where('business_id', (int) $businessId);
        }

        $query->orderBy('id')->chunkById($chunk, function ($rows) use (&$stats, $apply, $oldSalesTable) {
            foreach ($rows as $transaction) {
                $stats['scanned']++;

                try {
                    $oldSale = DB::connection('old_pos')
                        ->table($oldSalesTable)
                        ->where('id', (int) $transaction->old_pos_sale_id)
                        ->select('id', 'reference_no', 'pos')
                        ->first();

                    if (!$oldSale) {
                        $stats['missing_old_sale']++;
                        continue;
                    }

                    $stats['old_sale_found']++;
                    $isPosSale = (int) ($oldSale->pos ?? 0) === 1;

                    if ($isPosSale) {
                        $stats['old_pos_rows']++;
                        continue;
                    }

                    $stats['non_pos_rows']++;

                    if ((int) ($transaction->is_direct_sale ?? 0) === 1) {
                        $stats['already_direct']++;
                        continue;
                    }

                    if ($apply) {
                        DB::table('transactions')
                            ->where('id', (int) $transaction->id)
                            ->update([
                                'is_direct_sale' => 1,
                                'updated_at' => now(),
                            ]);
                        $stats['updated']++;
                    } else {
                        $stats['would_update']++;
                    }
                } catch (Throwable $e) {
                    $stats['failed']++;
                    $this->error("[Reclassify Non-POS] FAILED tx#{$transaction->id}: {$e->getMessage()}");
                }
            }
        });

        $this->line('');
        $this->line('═══════════════════════════════════════');
        $this->info('[Reclassify Non-POS] Completed');
        foreach ($stats as $k => $v) {
            $this->line(sprintf('%s: %d', $k, $v));
        }
        $this->line('═══════════════════════════════════════');

        return $stats['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function ensureOldConnection(): void
    {
        try {
            DB::connection('old_pos')->getPdo();
            return;
        } catch (Throwable $e) {
            // Fall through to env-based fallback.
        }

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
        DB::connection('old_pos')->getPdo();
    }

    private function resolveOldSalesTable(): string
    {
        $prefix = (string) DB::connection('old_pos')->getTablePrefix();
        $database = (string) DB::connection('old_pos')->getDatabaseName();
        if ($database === '') {
            return $prefix !== '' ? 'sales' : 'sma_sales';
        }

        $hasSmaSales = $this->tableExistsInSchema($database, 'sma_sales');

        if ($hasSmaSales) {
            // When old_pos connection has prefix (e.g. "sma_"), query builder should use "sales".
            return $prefix !== '' ? 'sales' : 'sma_sales';
        }

        $hasSales = $this->tableExistsInSchema($database, 'sales');

        return $hasSales ? 'sales' : 'sma_sales';
    }

    private function tableExistsInSchema(string $database, string $tableName): bool
    {
        $row = DB::connection('old_pos')->selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
            [$database, $tableName]
        );

        return (int) ($row->c ?? 0) > 0;
    }
}
