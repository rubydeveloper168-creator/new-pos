<?php

namespace App\Console\Commands;

use App\Services\CustomerMasterSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncCustomerMasterBidirectional extends Command
{
    protected $signature = 'sync:customer-master-bidirectional
                            {--direction=both : Sync direction: both, old-to-new, new-to-old}
                            {--limit=500 : Maximum records to evaluate per direction}
                            {--dry-run : Simulate the sync without writing changes}';

    protected $description = 'Bidirectional sync for customer master contacts between old POS and new POS';

    public function handle(CustomerMasterSyncService $syncService): int
    {
        $direction = (string) $this->option('direction');
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $logger = function (string $message, string $level = 'info'): void {
            match ($level) {
                'success' => $this->info($message),
                'warning' => $this->warn($message),
                'error' => $this->error($message),
                default => $this->line($message),
            };
        };

        try {
            $summary = $syncService->sync($direction, $limit, $dryRun, $logger);
        } catch (Throwable $e) {
            $this->error('[Customer Sync] Failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $this->newLine();
        $this->info('Customer master sync summary');
        if ($dryRun) {
            $this->warn('Dry run enabled. No database changes were written.');
        }

        foreach (['old_to_new' => 'Old -> New', 'new_to_old' => 'New -> Old'] as $key => $label) {
            $row = $summary[$key] ?? [];
            $this->line(sprintf(
                '%s: created %d, updated %d, matched %d, skipped %d, conflicts %d, failed %d',
                $label,
                (int) ($row['created'] ?? 0),
                (int) ($row['updated'] ?? 0),
                (int) ($row['matched'] ?? 0),
                (int) ($row['skipped'] ?? 0),
                (int) ($row['conflicts'] ?? 0),
                (int) ($row['failed'] ?? 0)
            ));
        }

        $totals = $summary['totals'] ?? [];
        $this->line(sprintf(
            'Total: created %d, updated %d, matched %d, skipped %d, conflicts %d, failed %d',
            (int) ($totals['created'] ?? 0),
            (int) ($totals['updated'] ?? 0),
            (int) ($totals['matched'] ?? 0),
            (int) ($totals['skipped'] ?? 0),
            (int) ($totals['conflicts'] ?? 0),
            (int) ($totals['failed'] ?? 0)
        ));

        return ((int) ($totals['failed'] ?? 0) > 0) ? Command::FAILURE : Command::SUCCESS;
    }
}
