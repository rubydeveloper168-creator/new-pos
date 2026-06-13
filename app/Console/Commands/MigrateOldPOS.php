<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class MigrateOldPOS extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'migrate:old-pos 
                            {--phase=all : Phase to run (all, reference, master, transactional)}
                            {--dry-run : Run without making changes}
                            {--rollback : Rollback all migrated data}
                            {--validate : Validate migration counts}
                            {--batch=500 : Batch size for processing}';

    /**
     * The console command description.
     */
    protected $description = 'Main orchestrator for migrating from old CodeIgniter POS to new Laravel POS';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $phase = $this->option('phase');
        $dryRun = $this->option('dry-run');
        $rollback = $this->option('rollback');
        $validate = $this->option('validate');
        $batch = $this->option('batch');
        
        $this->printBanner();
        
        // Handle rollback
        if ($rollback) {
            return $this->handleRollback();
        }
        
        // Handle validation
        if ($validate) {
            return $this->handleValidation();
        }
        
        // Check prerequisites
        if (!$this->checkPrerequisites()) {
            return Command::FAILURE;
        }
        
        $startTime = now();
        
        $this->info("Starting migration...");
        if ($dryRun) {
            $this->warn("DRY RUN MODE - No changes will be made");
        }
        $this->newLine();
        
        try {
            switch ($phase) {
                case 'all':
                    $this->runReferencePhase($dryRun);
                    $this->runMasterPhase($dryRun, $batch);
                    $this->runTransactionalPhase($dryRun, $batch);
                    break;
                    
                case 'reference':
                    $this->runReferencePhase($dryRun);
                    break;
                    
                case 'master':
                    $this->runMasterPhase($dryRun, $batch);
                    break;
                    
                case 'transactional':
                    $this->runTransactionalPhase($dryRun, $batch);
                    break;
                    
                default:
                    $this->error("Unknown phase: {$phase}");
                    return Command::FAILURE;
            }
            
            $this->newLine();
            $this->printSummary($startTime);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Migration failed: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
    
    /**
     * Print welcome banner.
     */
    protected function printBanner(): void
    {
        $this->newLine();
        $this->info('╔══════════════════════════════════════════════════════════╗');
        $this->info('║          OLD POS → NEW POS MIGRATION TOOL                ║');
        $this->info('║                    Hybrid Approach #4                    ║');
        $this->info('╚══════════════════════════════════════════════════════════╝');
        $this->newLine();
    }
    
    /**
     * Check prerequisites before migration.
     */
    protected function checkPrerequisites(): bool
    {
        $this->info('Checking prerequisites...');
        
        // Check old database connection
        try {
            DB::connection('old_pos')->getPdo();
            $this->info('  ✓ Old POS database connection OK');
        } catch (\Exception $e) {
            $this->error('  ✗ Cannot connect to old POS database');
            $this->error('    Please check OLD_POS_DB_* environment variables');
            return false;
        }
        
        // Check new database connection
        try {
            DB::connection('mysql')->getPdo();
            $this->info('  ✓ New POS database connection OK');
        } catch (\Exception $e) {
            $this->error('  ✗ Cannot connect to new POS database');
            return false;
        }
        
        // Check migration_mappings table exists
        if (!DB::getSchemaBuilder()->hasTable('migration_mappings')) {
            $this->error('  ✗ migration_mappings table does not exist');
            $this->error('    Please run: php artisan migrate');
            return false;
        }
        $this->info('  ✓ migration_mappings table exists');
        
        // Check business exists
        $business = DB::table('business')->first();
        if (!$business) {
            $this->error('  ✗ No business record found');
            $this->error('    Please create a business record before migration');
            return false;
        }
        $this->info("  ✓ Business exists: {$business->name}");
        
        $this->newLine();
        return true;
    }
    
    /**
     * Run reference data phase (seeders).
     */
    protected function runReferencePhase(bool $dryRun): void
    {
        $this->info('═══════════════════════════════════════');
        $this->info('PHASE 1: Reference Data (Seeders)');
        $this->info('═══════════════════════════════════════');
        $this->newLine();
        
        if ($dryRun) {
            $this->warn('Skipping seeders in dry-run mode');
            return;
        }
        
        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\MigrateReferenceDataSeeder',
            '--force' => true,
        ]);
        
        $this->info(Artisan::output());
    }
    
    /**
     * Run master data phase (Eloquent commands).
     */
    protected function runMasterPhase(bool $dryRun, int $batch): void
    {
        $this->info('═══════════════════════════════════════');
        $this->info('PHASE 2: Master Data (Eloquent)');
        $this->info('═══════════════════════════════════════');
        $this->newLine();
        
        $options = ['--batch' => $batch];
        if ($dryRun) {
            $options['--dry-run'] = true;
        }
        
        // Migrate contacts
        $this->call('migrate:contacts', $options);
        $this->newLine();
        
        // Migrate products
        $this->call('migrate:products', $options);
        $this->newLine();
        
        // Migrate inventory
        $this->call('migrate:inventory', $options);
        $this->newLine();
    }
    
    /**
     * Run transactional data phase (Raw SQL commands).
     */
    protected function runTransactionalPhase(bool $dryRun, int $batch): void
    {
        $this->info('═══════════════════════════════════════');
        $this->info('PHASE 3: Transactional Data (Raw SQL)');
        $this->info('═══════════════════════════════════════');
        $this->newLine();
        
        $options = ['--batch' => $batch];
        if ($dryRun) {
            $options['--dry-run'] = true;
        }
        
        // Migrate purchases
        $this->call('migrate:purchases', $options);
        $this->newLine();
        
        // Migrate sales
        $this->call('migrate:sales', $options);
        $this->newLine();
        
        // Migrate payments
        $this->call('migrate:payments', $options);
        $this->newLine();
    }
    
    /**
     * Handle rollback of migrated data.
     */
    protected function handleRollback(): int
    {
        $this->warn('═══════════════════════════════════════');
        $this->warn('ROLLBACK MODE');
        $this->warn('═══════════════════════════════════════');
        $this->newLine();
        
        if (!$this->confirm('This will DELETE all migrated data. Are you sure?')) {
            $this->info('Rollback cancelled.');
            return Command::SUCCESS;
        }
        
        $this->info('Rolling back migrations...');
        
        // Get all mappings and delete in reverse order
        $tables = [
            'transaction_payments',
            'transaction_sell_lines',
            'purchase_lines',
            'transactions',
            'variation_location_details',
            'variations',
            'products',
            'contacts',
            'business_locations',
            'customer_groups',
            'brands',
            'categories',
            'tax_rates',
        ];
        
        foreach ($tables as $table) {
            $mappings = DB::table('migration_mappings')
                ->where('new_table', $table)
                ->get();
                
            if ($mappings->count() > 0) {
                $ids = $mappings->pluck('new_id')->toArray();
                
                try {
                    DB::table($table)->whereIn('id', $ids)->delete();
                    $this->info("  ✓ Deleted {$mappings->count()} records from {$table}");
                } catch (\Exception $e) {
                    $this->warn("  ⚠ Could not delete from {$table}: " . $e->getMessage());
                }
            }
        }
        
        // Clear migration mappings
        DB::table('migration_mappings')->truncate();
        $this->info('  ✓ Cleared migration_mappings table');
        
        $this->newLine();
        $this->info('Rollback completed.');
        
        return Command::SUCCESS;
    }
    
    /**
     * Handle validation of migration counts.
     */
    protected function handleValidation(): int
    {
        $this->info('═══════════════════════════════════════');
        $this->info('VALIDATION MODE');
        $this->info('═══════════════════════════════════════');
        $this->newLine();
        
        $results = [];
        
        // Compare counts
        $comparisons = [
            ['old' => 'categories', 'new' => 'categories', 'label' => 'Categories'],
            ['old' => 'brands', 'new' => 'brands', 'label' => 'Brands'],
            ['old' => 'products', 'new' => 'products', 'label' => 'Products'],
            ['old' => 'companies', 'new' => 'contacts', 'label' => 'Contacts'],
            ['old' => 'sales', 'new' => 'transactions', 'label' => 'Sales', 'filter' => "type = 'sell'"],
            ['old' => 'purchases', 'new' => 'transactions', 'label' => 'Purchases', 'filter' => "type = 'purchase'"],
            ['old' => 'payments', 'new' => 'transaction_payments', 'label' => 'Payments'],
        ];
        
        $headers = ['Data Type', 'Old Count', 'New Count', 'Migrated', 'Status'];
        $rows = [];
        
        foreach ($comparisons as $comp) {
            $oldCount = DB::connection('old_pos')->table($comp['old'])->count();
            
            $newQuery = DB::table($comp['new']);
            if (isset($comp['filter'])) {
                $newQuery->whereRaw($comp['filter']);
            }
            $newCount = $newQuery->count();
            
            $migratedCount = DB::table('migration_mappings')
                ->where('old_table', $comp['old'])
                ->count();
            
            $status = $migratedCount >= $oldCount ? '✓' : '⚠';
            
            $rows[] = [
                $comp['label'],
                $oldCount,
                $newCount,
                $migratedCount,
                $status,
            ];
        }
        
        $this->table($headers, $rows);
        
        return Command::SUCCESS;
    }
    
    /**
     * Print migration summary.
     */
    protected function printSummary(\Carbon\Carbon $startTime): void
    {
        $duration = now()->diffInSeconds($startTime);
        $minutes = floor($duration / 60);
        $seconds = $duration % 60;
        
        $totalMigrated = DB::table('migration_mappings')->count();
        
        $this->info('═══════════════════════════════════════');
        $this->info('MIGRATION SUMMARY');
        $this->info('═══════════════════════════════════════');
        $this->info("  Total records migrated: {$totalMigrated}");
        $this->info("  Duration: {$minutes}m {$seconds}s");
        $this->newLine();
        $this->info('Run validation with: php artisan migrate:old-pos --validate');
    }
}
