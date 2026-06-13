<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigratePayments extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'migrate:payments 
                            {--dry-run : Run without making changes}
                            {--batch= : Batch size for processing (default: 500)}';

    /**
     * The console command description.
     */
    protected $description = 'Migrate payments from old POS to new POS (uses raw SQL for performance)';

    protected $businessId = 1;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $batchSize = (int) ($this->option('batch') ?? 500);
        
        $this->info('========================================');
        $this->info('Migrating Payments (Raw SQL)');
        $this->info('========================================');
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }
        
        try {
            // Count total payments
            $totalPayments = DB::connection('old_pos')
                ->table('payments')
                ->count();
                
            $this->info("Found {$totalPayments} payments to migrate.");
            
            if ($totalPayments == 0) {
                $this->info('No payments to migrate.');
                return Command::SUCCESS;
            }
            
            $bar = $this->output->createProgressBar($totalPayments);
            $bar->start();
            
            $migrated = 0;
            $skipped = 0;
            $errors = 0;
            $offset = 0;
            
            while ($offset < $totalPayments) {
                $payments = DB::connection('old_pos')
                    ->table('payments')
                    ->orderBy('id')
                    ->offset($offset)
                    ->limit($batchSize)
                    ->get();
                    
                if ($payments->isEmpty()) break;
                
                DB::beginTransaction();
                
                foreach ($payments as $oldPayment) {
                    // Check if already migrated
                    $existing = DB::table('migration_mappings')
                        ->where('old_table', 'payments')
                        ->where('old_id', $oldPayment->id)
                        ->first();
                        
                    if ($existing) {
                        $skipped++;
                        $bar->advance();
                        continue;
                    }
                    
                    // Get transaction mapping
                    $transactionMapping = null;
                    if ($oldPayment->sale_id) {
                        $transactionMapping = DB::table('migration_mappings')
                            ->where('old_table', 'sales')
                            ->where('old_id', $oldPayment->sale_id)
                            ->first();
                    } elseif ($oldPayment->purchase_id) {
                        $transactionMapping = DB::table('migration_mappings')
                            ->where('old_table', 'purchases')
                            ->where('old_id', $oldPayment->purchase_id)
                            ->first();
                    }
                    
                    if (!$transactionMapping) {
                        $errors++;
                        $bar->advance();
                        continue;
                    }
                    
                    // Map payment method
                    $paymentMethod = $this->mapPaymentMethod($oldPayment->paid_by ?? 'cash');
                    
                    if (!$dryRun) {
                        // Insert payment
                        $paymentId = DB::table('transaction_payments')->insertGetId([
                            'transaction_id' => $transactionMapping->new_id,
                            'business_id' => $this->businessId,
                            'is_return' => 0,
                            'amount' => $oldPayment->amount ?? 0,
                            'method' => $paymentMethod,
                            'transaction_no' => $oldPayment->transaction_id ?? null,
                            'card_transaction_number' => $oldPayment->cc_no ?? null,
                            'card_number' => $oldPayment->cc_no ?? null,
                            'card_type' => $oldPayment->cc_type ?? null,
                            'card_holder_name' => $oldPayment->cc_holder ?? null,
                            'card_month' => $oldPayment->cc_month ?? null,
                            'card_year' => $oldPayment->cc_year ?? null,
                            'card_security' => null,
                            'cheque_number' => $oldPayment->cheque_no ?? null,
                            'bank_account_number' => null,
                            'paid_on' => $oldPayment->date,
                            'created_by' => 1,
                            'paid_through_link' => 0,
                            'gateway' => null,
                            'is_advance' => 0,
                            'payment_for' => null,
                            'parent_id' => null,
                            'note' => $oldPayment->note ?? null,
                            'document' => $oldPayment->attachment ?? null,
                            'payment_ref_no' => $oldPayment->reference_no,
                            'account_id' => null,
                            'created_at' => $oldPayment->date,
                            'updated_at' => now(),
                        ]);
                        
                        // Record mapping
                        DB::table('migration_mappings')->insert([
                            'old_table' => 'payments',
                            'old_id' => $oldPayment->id,
                            'new_table' => 'transaction_payments',
                            'new_id' => $paymentId,
                            'migrated_at' => now(),
                        ]);
                    }
                    
                    $migrated++;
                    $bar->advance();
                }
                
                if (!$dryRun) {
                    DB::commit();
                } else {
                    DB::rollBack();
                }
                
                $offset += $batchSize;
            }
            
            $bar->finish();
            $this->newLine();
            
            if (!$dryRun) {
                $this->info("✓ Migrated {$migrated} payments.");
            } else {
                $this->info("Would migrate {$migrated} payments.");
            }
            
            if ($skipped > 0) {
                $this->warn("Skipped {$skipped} already migrated payments.");
            }
            
            if ($errors > 0) {
                $this->error("Failed to migrate {$errors} payments (missing transaction mapping).");
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Migration failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    /**
     * Map old payment method to new payment method.
     */
    protected function mapPaymentMethod(string $oldMethod): string
    {
        $mapping = [
            'cash' => 'cash',
            'CC' => 'card',
            'cheque' => 'cheque',
            'Cheque' => 'cheque',
            'transfer' => 'bank_transfer',
            'bank_transfer' => 'bank_transfer',
            'gift_card' => 'other',
            'paypal' => 'other',
            'stripe' => 'card',
            'other' => 'other',
        ];
        
        return $mapping[$oldMethod] ?? 'cash';
    }
}
