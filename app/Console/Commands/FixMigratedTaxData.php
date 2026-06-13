<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Transaction;
use App\TransactionSellLine;
use Illuminate\Support\Facades\DB;

class FixMigratedTaxData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:migrated-tax {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix missing tax data for migrated transactions where final_total includes 7% tax';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $taxId = 4; // "VAT @7%" tax rate

        $this->info('Searching for transactions with missing tax data...');

        // Find transactions where tax_id is null AND (final_total is approx total_before_tax * 1.07)
        // We use a small epsilon for floating point comparison
        $transactions = Transaction::where('type', 'sell')
            ->whereNull('tax_id')
            ->where('tax_amount', 0)
            ->whereRaw('ABS(final_total - total_before_tax * 1.07) < 0.01')
            ->where('total_before_tax', '>', 0)
            ->get();

        $count = $transactions->count();
        $this->info("Found {$count} transactions to fix.");

        if ($count == 0) {
            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($transactions as $transaction) {
            $expectedTax = $transaction->final_total - $transaction->total_before_tax;

            if (!$dryRun) {
                DB::beginTransaction();
                try {
                    // Update Transaction
                    $transaction->tax_id = $taxId;
                    $transaction->tax_amount = $expectedTax;
                    $transaction->save();

                    // Update Sell Lines (Proportional distribution if multiple lines, but usually simple)
                    $sellLines = TransactionSellLine::where('transaction_id', $transaction->id)->get();
                    foreach ($sellLines as $line) {
                        // Assuming the entire transaction tax is distributed to lines
                        // In many cases, it's just one line or all lines have the same tax rate
                        // For 7% tax, item_tax = unit_price * 0.07
                        $linePrice = $line->unit_price;
                        $lineTax = $linePrice * 0.07;
                        
                        $line->tax_id = $taxId;
                        $line->item_tax = $lineTax;
                        $line->unit_price_inc_tax = $linePrice + $lineTax;
                        $line->save();
                    }

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error("\nFailed to update transaction ID {$transaction->id}: " . $e->getMessage());
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        
        if ($dryRun) {
            $this->info("Dry run completed. Would have updated {$count} transactions.");
        } else {
            $this->info("Successfully updated {$count} transactions.");
        }

        return Command::SUCCESS;
    }
}
