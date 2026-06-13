<?php

namespace App\Console\Commands;

use App\Services\BidirectionalSyncService;
use Illuminate\Console\Command;
use Exception;
use ReflectionMethod;

class SyncBidirectional extends Command
{
    protected $signature = 'sync:bidirectional
                            {--direction=both : Which direction to sync: both, old-to-new, new-to-old}
                            {--limit=50 : Maximum records to sync per run}';

    protected $description = 'Bidirectional sync of new bills between old POS and new POS';

    public function handle(BidirectionalSyncService $syncService): int
    {
        $direction = $this->option('direction');
        $limit = (int) $this->option('limit');

        $this->info('[SYNC] Starting expanded bidirectional sync cycle...');

        $log = function (string $message, string $type = 'info') {
            match ($type) {
                'success' => $this->info($message),
                'warning' => $this->warn($message),
                'error'   => $this->error($message),
                default   => $this->line($message),
            };
        };

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
                    'old_to_new_update' => [
                        'label' => 'Old→New Update',
                        'methods' => ['syncOldToNewUpdates'],
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
                    'new_to_old' => [
                        'label' => 'New→Old',
                        'methods' => ['syncPaymentUpdatesNewToOld'],
                    ],
                ],
            ],
        ];

        $allowedDirections = match ($direction) {
            'old-to-new' => ['old_to_new', 'old_to_new_tax_fix', 'old_to_new_update'],
            'new-to-old' => ['new_to_old'],
            default => ['old_to_new', 'old_to_new_tax_fix', 'old_to_new_update', 'new_to_old'],
        };

        $results = [];
        $directionTotals = [
            'old_to_new' => ['synced' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0],
            'new_to_old' => ['synced' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0],
        ];

        try {
            if (!$syncService->isSetupDone()) {
                $this->warn('[SYNC] Setup not complete. Running setup automatically...');
                $syncService->runSetup($log);
                $this->info('[SYNC] Setup complete. Continuing sync.');
            }

            foreach ($domains as $domainKey => $domainConfig) {
                $this->line('');
                $this->line('───────────────────────────────────────');
                $this->line("[SYNC] {$domainConfig['label']}");

                foreach ($domainConfig['steps'] as $directionKey => $stepConfig) {
                    if (!in_array($directionKey, $allowedDirections, true)) {
                        continue;
                    }

                    $result = $this->runSyncServiceStep(
                        $syncService,
                        $stepConfig['methods'],
                        $log,
                        $limit,
                        "{$domainConfig['label']} {$stepConfig['label']}"
                    );

                    $directionBucket = str_starts_with($directionKey, 'old_to_new') ? 'old_to_new' : 'new_to_old';
                    $results[$domainKey][$directionKey] = $result;
                    $directionTotals[$directionBucket]['synced'] += $result['synced'];
                    $directionTotals[$directionBucket]['updated'] += $result['updated'];
                    $directionTotals[$directionBucket]['skipped'] += $result['skipped'];
                    $directionTotals[$directionBucket]['failed'] += $result['failed'];

                    $severity = $result['failed'] > 0 ? 'warning' : 'success';
                    $log(
                        "[SYNC] {$domainConfig['label']} {$stepConfig['label']} => synced {$result['synced']}, updated {$result['updated']}, skipped {$result['skipped']}, failed {$result['failed']}",
                        $severity
                    );
                }
            }
        } catch (Exception $e) {
            $this->error('[SYNC] Fatal error: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $totalSyncedOldToNew = $directionTotals['old_to_new']['synced'] + $directionTotals['old_to_new']['updated'];
        $totalSyncedNewToOld = $directionTotals['new_to_old']['synced'] + $directionTotals['new_to_old']['updated'];
        $totalSynced = $totalSyncedOldToNew + $totalSyncedNewToOld;
        $totalFailed = $directionTotals['old_to_new']['failed'] + $directionTotals['new_to_old']['failed'];

        $this->line('');
        $this->line('═══════════════════════════════════════');
        $this->info("[SYNC] Full cycle complete — {$totalSynced} synced/updated, {$totalFailed} failed");
        $this->line('[SYNC] Final totals by domain/direction:');
        foreach ($domains as $domainKey => $domainConfig) {
            foreach ($domainConfig['steps'] as $directionKey => $stepConfig) {
                if (!in_array($directionKey, $allowedDirections, true)) {
                    continue;
                }
                $r = $results[$domainKey][$directionKey] ?? ['synced' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
                $this->line(" - {$domainConfig['label']} {$stepConfig['label']}: synced {$r['synced']}, updated {$r['updated']}, skipped {$r['skipped']}, failed {$r['failed']}");
            }
        }
        if (in_array('old_to_new', $allowedDirections, true)) {
            $this->line("Old→New total: synced {$directionTotals['old_to_new']['synced']}, updated {$directionTotals['old_to_new']['updated']}, skipped {$directionTotals['old_to_new']['skipped']}, failed {$directionTotals['old_to_new']['failed']}");
            $this->line("Total Synced Old→New: {$totalSyncedOldToNew}");
        }
        if (in_array('new_to_old', $allowedDirections, true)) {
            $this->line("New→Old total: synced {$directionTotals['new_to_old']['synced']}, updated {$directionTotals['new_to_old']['updated']}, skipped {$directionTotals['new_to_old']['skipped']}, failed {$directionTotals['new_to_old']['failed']}");
            $this->line("Total Synced New→Old: {$totalSyncedNewToOld}");
        }
        $this->line('═══════════════════════════════════════');

        return $totalFailed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function runSyncServiceStep(
        BidirectionalSyncService $syncService,
        array $methodCandidates,
        callable $log,
        int $limit,
        string $stepLabel
    ): array {
        foreach ($methodCandidates as $method) {
            if (!method_exists($syncService, $method)) {
                continue;
            }

            $log("[SYNC] {$stepLabel}: calling {$method}()", 'info');
            $raw = $this->invokeSyncMethod($syncService, $method, $log, $limit);

            return [
                'synced'  => (int) ($raw['synced'] ?? $raw['migrated'] ?? 0),
                'updated' => (int) ($raw['updated'] ?? 0),
                'skipped' => (int) ($raw['skipped'] ?? 0),
                'failed'  => (int) ($raw['failed'] ?? 0),
                'method'  => $method,
            ];
        }

        $log(
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

    private function invokeSyncMethod(BidirectionalSyncService $syncService, string $method, callable $log, int $limit): array
    {
        $reflection = new ReflectionMethod($syncService, $method);
        $required = $reflection->getNumberOfRequiredParameters();
        $total = $reflection->getNumberOfParameters();

        if ($total >= 2 || $required >= 2) {
            $result = $syncService->{$method}($log, $limit);
        } elseif ($total >= 1 || $required >= 1) {
            $result = $syncService->{$method}($log);
        } else {
            $result = $syncService->{$method}();
        }

        return is_array($result) ? $result : [];
    }
}
