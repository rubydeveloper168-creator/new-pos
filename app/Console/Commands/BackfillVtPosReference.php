<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class BackfillVtPosReference extends Command
{
    protected $signature = 'sync:backfill-vt-pos-reference
                            {--business-id= : Limit to one business_id}
                            {--chunk=200 : Chunk size for processing}
                            {--dry-run : Preview only, do not update transactions}';

    protected $description = 'Backfill sell VT/proforma invoice_no/ref_no from old_pos.sma_sales.reference_no (exact VT/POS format)';

    public function handle(): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $businessId = $this->option('business-id');
        $dryRun = (bool) $this->option('dry-run');

        $this->bootOldConnection();
        $hasNewPosTxnIdColumn = $this->oldTableHasColumn('sma_sales', 'new_pos_transaction_id');

        $stats = [
            'scanned' => 0,
            'matched' => 0,
            'updated' => 0,
            'would_update' => 0,
            'already_aligned' => 0,
            'unmatched' => 0,
            'overwritten_collisions' => 0,
            'failed' => 0,
        ];

        $this->line('[Backfill VT/POS] Starting...');
        $this->line('[Backfill VT/POS] Mode: ' . ($dryRun ? 'DRY RUN' : 'WRITE'));
        $this->line('[Backfill VT/POS] Chunk size: ' . $chunk);
        if (!empty($businessId)) {
            $this->line('[Backfill VT/POS] Business filter: ' . $businessId);
        }
        $this->line('[Backfill VT/POS] old_pos.sma_sales.new_pos_transaction_id column: ' . ($hasNewPosTxnIdColumn ? 'yes' : 'no'));

        $query = DB::table('transactions')
            ->select('id', 'business_id', 'type', 'invoice_no', 'ref_no', 'document_type', 'sub_status', 'old_pos_sale_id')
            ->where('type', 'sell')
            ->where(function ($q) {
                $q->whereNotNull('old_pos_sale_id')
                    ->orWhere('document_type', 'proforma')
                    ->orWhere('sub_status', 'proforma')
                    ->orWhere('invoice_no', 'LIKE', 'VT%')
                    ->orWhere('ref_no', 'LIKE', 'VT%');
            });

        if (!empty($businessId) && is_numeric($businessId)) {
            $query->where('business_id', (int) $businessId);
        }

        $query->orderBy('id')->chunkById($chunk, function ($rows) use (&$stats, $dryRun, $hasNewPosTxnIdColumn) {
            foreach ($rows as $transaction) {
                $stats['scanned']++;

                try {
                    [$oldSale, $matchMethod] = $this->resolveOldSaleForTransaction($transaction, $hasNewPosTxnIdColumn);

                    if (!$oldSale) {
                        $stats['unmatched']++;
                        if ($stats['unmatched'] <= 20) {
                            $this->warn("[Backfill VT/POS] UNMATCHED tx#{$transaction->id} (invoice_no={$transaction->invoice_no}, ref_no={$transaction->ref_no}, old_pos_sale_id={$transaction->old_pos_sale_id})");
                        }
                        continue;
                    }

                    $stats['matched']++;

                    $targetReference = trim((string) ($oldSale->reference_no ?? ''));
                    if ($targetReference === '') {
                        $stats['unmatched']++;
                        $this->warn("[Backfill VT/POS] EMPTY reference_no in old sale#{$oldSale->id} for tx#{$transaction->id}");
                        continue;
                    }

                    $needsUpdate = ((string) $transaction->invoice_no !== $targetReference)
                        || ((string) $transaction->ref_no !== $targetReference);

                    if (!$needsUpdate) {
                        $stats['already_aligned']++;
                        continue;
                    }

                    $collisionIds = DB::table('transactions')
                        ->where('business_id', $transaction->business_id)
                        ->where('invoice_no', $targetReference)
                        ->where('id', '!=', $transaction->id)
                        ->pluck('id')
                        ->all();

                    if (!empty($collisionIds)) {
                        $stats['overwritten_collisions']++;
                        $this->warn('[Backfill VT/POS] COLLISION ' . json_encode([
                            'transaction_id' => (int) $transaction->id,
                            'business_id' => (int) $transaction->business_id,
                            'old_pos_sale_id' => (int) $oldSale->id,
                            'match_method' => $matchMethod,
                            'from_invoice_no' => (string) $transaction->invoice_no,
                            'from_ref_no' => (string) $transaction->ref_no,
                            'to_reference_no' => $targetReference,
                            'conflicting_transaction_ids' => array_map('intval', $collisionIds),
                        ], JSON_UNESCAPED_UNICODE));
                    } else {
                        $this->line("[Backfill VT/POS] UPDATE tx#{$transaction->id} via {$matchMethod}: {$transaction->invoice_no} -> {$targetReference}");
                    }

                    if ($dryRun) {
                        $stats['would_update']++;
                    } else {
                        DB::table('transactions')
                            ->where('id', $transaction->id)
                            ->update([
                                'invoice_no' => $targetReference,
                                'ref_no' => $targetReference,
                                'updated_at' => now(),
                            ]);
                        $stats['updated']++;
                    }
                } catch (Throwable $e) {
                    $stats['failed']++;
                    $this->error("[Backfill VT/POS] FAILED tx#{$transaction->id}: {$e->getMessage()}");
                }
            }
        });

        $this->line('');
        $this->line('═══════════════════════════════════════');
        $this->info('[Backfill VT/POS] Completed');
        foreach ($stats as $k => $v) {
            $this->line(sprintf('%s: %d', $k, $v));
        }
        $this->line('═══════════════════════════════════════');

        return $stats['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return array{0:object|null,1:string}
     */
    private function resolveOldSaleForTransaction(object $transaction, bool $hasNewPosTxnIdColumn): array
    {
        if (!empty($transaction->old_pos_sale_id)) {
            $oldSale = DB::connection('old_pos')
                ->table('sma_sales')
                ->where('id', (int) $transaction->old_pos_sale_id)
                ->select('id', 'reference_no')
                ->first();

            if ($oldSale) {
                return [$oldSale, 'old_pos_sale_id'];
            }
        }

        if ($hasNewPosTxnIdColumn) {
            $oldSale = DB::connection('old_pos')
                ->table('sma_sales')
                ->where('new_pos_transaction_id', (int) $transaction->id)
                ->select('id', 'reference_no')
                ->first();

            if ($oldSale) {
                return [$oldSale, 'old_pos.new_pos_transaction_id'];
            }
        }

        $referenceCandidates = $this->buildReferenceCandidates($transaction);

        foreach ($referenceCandidates as $candidate) {
            $oldSale = DB::connection('old_pos')
                ->table('sma_sales')
                ->where('reference_no', $candidate)
                ->select('id', 'reference_no')
                ->first();

            if ($oldSale) {
                return [$oldSale, 'reference_exact:' . $candidate];
            }
        }

        foreach ($referenceCandidates as $candidate) {
            $oldSale = DB::connection('old_pos')
                ->table('sma_sales')
                ->whereRaw('UPPER(reference_no) = ?', [strtoupper($candidate)])
                ->select('id', 'reference_no')
                ->first();

            if ($oldSale) {
                return [$oldSale, 'reference_ci:' . $candidate];
            }
        }

        return [null, 'unmatched'];
    }

    /**
     * @return string[]
     */
    private function buildReferenceCandidates(object $transaction): array
    {
        $candidates = [];

        foreach ([(string) ($transaction->ref_no ?? ''), (string) ($transaction->invoice_no ?? '')] as $raw) {
            $ref = trim($raw);
            if ($ref === '') {
                continue;
            }

            $candidates[] = $ref;

            $upper = strtoupper($ref);
            if (str_starts_with($upper, 'VT/') && !str_starts_with($upper, 'VT/POS')) {
                $candidates[] = 'VT/POS' . substr($ref, 3);
            }

            if (str_starts_with($upper, 'VT') && !str_starts_with($upper, 'VT/POS') && !str_starts_with($upper, 'VT/')) {
                $candidates[] = 'VT/POS' . substr($ref, 2);
            }
        }

        return array_values(array_unique(array_filter($candidates)));
    }

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

    private function oldTableHasColumn(string $table, string $column): bool
    {
        $database = (string) DB::connection('old_pos')->getDatabaseName();

        if ($database === '') {
            return false;
        }

        $exists = DB::connection('old_pos')
            ->table('information_schema.columns')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->exists();

        return (bool) $exists;
    }
}
