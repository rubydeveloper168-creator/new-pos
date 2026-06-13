<?php

namespace App\Console\Commands;

use App\Services\PosReconciliationService;
use Illuminate\Console\Command;
use RuntimeException;

class GeneratePosReconciliationReport extends Command
{
    protected $signature = 'pos:reconciliation-report
                            {--write : Write the JSON report to storage/app/reconciliation/}
                            {--filename= : Optional filename for the written report}';

    protected $description = 'Generate a read-only reconciliation report for old and new POS data';

    public function handle(PosReconciliationService $service): int
    {
        try {
            $report = $service->generateReport();

            if ($this->option('write')) {
                $report['output_path'] = $service->writeReport($report, $this->option('filename'));
            }

            $json = json_encode(
                $report,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );

            if ($json === false) {
                throw new RuntimeException('Unable to encode reconciliation report as JSON.');
            }

            $this->line($json);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $error = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];

            $json = json_encode(
                $error,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );

            if ($json === false) {
                $json = '{"status":"error","message":"Unable to encode error response."}';
            }

            $this->line($json);

            return Command::FAILURE;
        }
    }
}
