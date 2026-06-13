<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Product;
use App\Variation;

class MigrateProducts extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'migrate:products 
                            {--dry-run : Run without making changes}
                            {--batch= : Batch size for processing (default: 500)}';

    /**
     * The console command description.
     */
    protected $description = 'Migrate products and variations from old POS to new POS';

    protected $businessId = 1;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $batchSize = $this->option('batch') ?? 500;
        
        $this->info('========================================');
        $this->info('Migrating Products & Variations');
        $this->info('========================================');
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }
        
        try {
            // Get products from old POS
            $oldProducts = DB::connection('old_pos')
                ->table('products')
                ->get();
                
            $this->info("Found {$oldProducts->count()} products to migrate.");
            
            $bar = $this->output->createProgressBar($oldProducts->count());
            $bar->start();
            
            $migrated = 0;
            $skipped = 0;
            
            DB::beginTransaction();
            
            foreach ($oldProducts as $oldProduct) {
                // Check if already migrated
                $existing = DB::table('migration_mappings')
                    ->where('old_table', 'products')
                    ->where('old_id', $oldProduct->id)
                    ->first();
                    
                if ($existing) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }
                
                // Get category mapping
                $categoryMapping = null;
                if ($oldProduct->category_id) {
                    $categoryMapping = DB::table('migration_mappings')
                        ->where('old_table', 'categories')
                        ->where('old_id', $oldProduct->category_id)
                        ->first();
                }
                
                // Get subcategory mapping
                $subCategoryMapping = null;
                if (isset($oldProduct->subcategory_id) && $oldProduct->subcategory_id) {
                    $subCategoryMapping = DB::table('migration_mappings')
                        ->where('old_table', 'categories')
                        ->where('old_id', $oldProduct->subcategory_id)
                        ->first();
                }
                
                // Get brand mapping
                $brandMapping = null;
                if (isset($oldProduct->brand) && $oldProduct->brand) {
                    $brandMapping = DB::table('migration_mappings')
                        ->where('old_table', 'brands')
                        ->where('old_id', $oldProduct->brand)
                        ->first();
                }
                
                // Get tax mapping
                $taxMapping = null;
                if (isset($oldProduct->tax_rate) && $oldProduct->tax_rate) {
                    $taxMapping = DB::table('migration_mappings')
                        ->where('old_table', 'tax_rates')
                        ->where('old_id', $oldProduct->tax_rate)
                        ->first();
                }
                
                // Map product type
                $productType = 'single';
                if (isset($oldProduct->type)) {
                    switch ($oldProduct->type) {
                        case 'standard':
                            $productType = 'single';
                            break;
                        case 'combo':
                            $productType = 'combo';
                            break;
                        case 'service':
                            $productType = 'single';
                            break;
                        default:
                            $productType = 'single';
                    }
                }
                
                if (!$dryRun) {
                    // Create new product
                    $newProduct = Product::create([
                        'business_id' => $this->businessId,
                        'name' => $oldProduct->name,
                        'type' => $productType,
                        'unit_id' => 1, // Default unit
                        'sub_unit_ids' => null,
                        'brand_id' => $brandMapping->new_id ?? null,
                        'category_id' => $categoryMapping->new_id ?? null,
                        'sub_category_id' => $subCategoryMapping->new_id ?? null,
                        'tax' => $taxMapping->new_id ?? null,
                        'tax_type' => 'exclusive',
                        'enable_stock' => 1,
                        'alert_quantity' => $oldProduct->alert_quantity ?? 0,
                        'sku' => $oldProduct->code,
                        'barcode_type' => 'C128',
                        'expiry_period' => null,
                        'expiry_period_type' => null,
                        'enable_sr_no' => 0,
                        'weight' => null,
                        'product_custom_field1' => $oldProduct->cf1 ?? null,
                        'product_custom_field2' => $oldProduct->cf2 ?? null,
                        'product_custom_field3' => $oldProduct->cf3 ?? null,
                        'product_custom_field4' => $oldProduct->cf4 ?? null,
                        'image' => $oldProduct->image ?? null,
                        'product_description' => $oldProduct->product_details ?? null,
                        'created_by' => 1,
                        'warranty_id' => null,
                        'is_inactive' => 0,
                        'not_for_selling' => 0,
                        'created_at' => $oldProduct->date ?? now(),
                        'updated_at' => now(),
                    ]);
                    
                    // Record product mapping
                    DB::table('migration_mappings')->insert([
                        'old_table' => 'products',
                        'old_id' => $oldProduct->id,
                        'new_table' => 'products',
                        'new_id' => $newProduct->id,
                        'migrated_at' => now(),
                    ]);
                    
                    // First create a product_variation record for single products
                    $productVariationId = DB::table('product_variations')->insertGetId([
                        'product_id' => $newProduct->id,
                        'name' => 'DUMMY',
                        'is_dummy' => 1,
                    ]);
                    
                    // Create default variation for this product using raw SQL
                    $variationId = DB::table('variations')->insertGetId([
                        'name' => 'DUMMY',
                        'product_id' => $newProduct->id,
                        'sub_sku' => $oldProduct->code,
                        'product_variation_id' => $productVariationId,
                        'variation_value_id' => null,
                        'default_purchase_price' => $oldProduct->cost ?? 0,
                        'dpp_inc_tax' => ($oldProduct->cost ?? 0) * 1.07,
                        'profit_percent' => 0,
                        'default_sell_price' => $oldProduct->price ?? 0,
                        'sell_price_inc_tax' => ($oldProduct->price ?? 0) * 1.07,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    
                    // Record variation mapping
                    DB::table('migration_mappings')->insert([
                        'old_table' => 'products_variation',
                        'old_id' => $oldProduct->id,
                        'new_table' => 'variations',
                        'new_id' => $variationId,
                        'migrated_at' => now(),
                        'notes' => 'Default variation for product',
                    ]);
                }
                
                $migrated++;
                $bar->advance();
            }
            
            $bar->finish();
            $this->newLine();
            
            if (!$dryRun) {
                DB::commit();
                $this->info("✓ Migrated {$migrated} products with variations.");
            } else {
                DB::rollBack();
                $this->info("Would migrate {$migrated} products with variations.");
            }
            
            if ($skipped > 0) {
                $this->warn("Skipped {$skipped} already migrated products.");
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Migration failed: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
