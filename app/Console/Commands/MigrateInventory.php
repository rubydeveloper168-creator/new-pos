<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateInventory extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'migrate:inventory 
                            {--dry-run : Run without making changes}
                            {--batch= : Batch size for processing (default: 500)}';

    /**
     * The console command description.
     */
    protected $description = 'Migrate inventory levels from old POS to new POS';

    protected $businessId = 1;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $batchSize = $this->option('batch') ?? 500;
        
        $this->info('========================================');
        $this->info('Migrating Inventory Levels');
        $this->info('========================================');
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }
        
        try {
            // Get warehouse products from old POS
            $oldInventory = DB::connection('old_pos')
                ->table('warehouses_products')
                ->get();
                
            $this->info("Found {$oldInventory->count()} inventory records to migrate.");
            
            $bar = $this->output->createProgressBar($oldInventory->count());
            $bar->start();
            
            $migrated = 0;
            $skipped = 0;
            $errors = 0;
            
            DB::beginTransaction();
            
            foreach ($oldInventory as $oldStock) {
                // Get product mapping
                $productMapping = DB::table('migration_mappings')
                    ->where('old_table', 'products')
                    ->where('old_id', $oldStock->product_id)
                    ->first();
                    
                if (!$productMapping) {
                    $errors++;
                    $bar->advance();
                    continue;
                }
                
                // Get variation mapping (we use the default variation created with product)
                $variationMapping = DB::table('migration_mappings')
                    ->where('old_table', 'products_variation')
                    ->where('old_id', $oldStock->product_id)
                    ->first();
                    
                if (!$variationMapping) {
                    $errors++;
                    $bar->advance();
                    continue;
                }
                
                // Get location mapping
                $locationMapping = DB::table('migration_mappings')
                    ->where('old_table', 'warehouses')
                    ->where('old_id', $oldStock->warehouse_id)
                    ->first();
                    
                if (!$locationMapping) {
                    $errors++;
                    $bar->advance();
                    continue;
                }
                
                // Check if already migrated
                $existingVLD = DB::table('variation_location_details')
                    ->where('product_id', $productMapping->new_id)
                    ->where('variation_id', $variationMapping->new_id)
                    ->where('location_id', $locationMapping->new_id)
                    ->first();
                    
                if ($existingVLD) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }
                
                if (!$dryRun) {
                    // Create variation location detail
                    $vldId = DB::table('variation_location_details')->insertGetId([
                        'product_id' => $productMapping->new_id,
                        'product_variation_id' => 1, // Default product variation
                        'variation_id' => $variationMapping->new_id,
                        'location_id' => $locationMapping->new_id,
                        'qty_available' => $oldStock->quantity ?? 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    
                    // Record mapping
                    DB::table('migration_mappings')->insert([
                        'old_table' => 'warehouses_products',
                        'old_id' => $oldStock->id ?? 0,
                        'new_table' => 'variation_location_details',
                        'new_id' => $vldId,
                        'migrated_at' => now(),
                        'notes' => "product:{$oldStock->product_id}, warehouse:{$oldStock->warehouse_id}",
                    ]);
                }
                
                $migrated++;
                $bar->advance();
            }
            
            $bar->finish();
            $this->newLine();
            
            if (!$dryRun) {
                DB::commit();
                $this->info("✓ Migrated {$migrated} inventory records.");
            } else {
                DB::rollBack();
                $this->info("Would migrate {$migrated} inventory records.");
            }
            
            if ($skipped > 0) {
                $this->warn("Skipped {$skipped} already existing inventory records.");
            }
            
            if ($errors > 0) {
                $this->error("Failed to migrate {$errors} records (missing mappings).");
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Migration failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
