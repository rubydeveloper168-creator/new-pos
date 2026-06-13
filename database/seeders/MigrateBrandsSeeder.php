<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Brands;

class MigrateBrandsSeeder extends Seeder
{
    protected $businessId = 1;
    
    /**
     * Migrate brands from old POS to new POS.
     * 
     * Old: sma_brands
     * New: brands
     */
    public function run(): void
    {
        $this->command->info('Migrating brands...');
        
        $oldBrands = DB::connection('old_pos')
            ->table('brands')
            ->get();
            
        $migrated = 0;
        
        foreach ($oldBrands as $oldBrand) {
            // Check if already migrated
            $existing = DB::table('migration_mappings')
                ->where('old_table', 'brands')
                ->where('old_id', $oldBrand->id)
                ->first();
                
            if ($existing) {
                $this->command->warn("Brand '{$oldBrand->name}' already migrated, skipping.");
                continue;
            }
            
            // Create new brand
            $newBrand = Brands::create([
                'business_id' => $this->businessId,
                'name' => $oldBrand->name,
                'description' => $oldBrand->description ?? null,
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Record mapping
            DB::table('migration_mappings')->insert([
                'old_table' => 'brands',
                'old_id' => $oldBrand->id,
                'new_table' => 'brands',
                'new_id' => $newBrand->id,
                'migrated_at' => now(),
            ]);
            
            $migrated++;
        }
        
        $this->command->info("Migrated {$migrated} brands.");
    }
}
