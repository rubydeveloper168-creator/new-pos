<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Category;

class MigrateCategoriesSeeder extends Seeder
{
    protected $businessId = 1;
    
    /**
     * Migrate categories from old POS to new POS.
     * 
     * Old: sma_categories (2-level hierarchy with parent_id)
     * New: categories (5-level hierarchy support)
     */
    public function run(): void
    {
        $this->command->info('Migrating categories...');
        
        // First, migrate parent categories (parent_id = 0 or NULL)
        $parentCategories = DB::connection('old_pos')
            ->table('categories')
            ->where(function ($query) {
                $query->where('parent_id', 0)
                      ->orWhereNull('parent_id');
            })
            ->get();
            
        $migrated = 0;
        
        foreach ($parentCategories as $oldCat) {
            $newId = $this->migrateCategory($oldCat, 0);
            if ($newId) $migrated++;
        }
        
        // Then migrate subcategories
        $subCategories = DB::connection('old_pos')
            ->table('categories')
            ->where('parent_id', '>', 0)
            ->get();
            
        foreach ($subCategories as $oldCat) {
            // Get the new parent ID from mapping
            $parentMapping = DB::table('migration_mappings')
                ->where('old_table', 'categories')
                ->where('old_id', $oldCat->parent_id)
                ->first();
                
            $newParentId = $parentMapping ? $parentMapping->new_id : 0;
            
            $newId = $this->migrateCategory($oldCat, $newParentId);
            if ($newId) $migrated++;
        }
        
        $this->command->info("Migrated {$migrated} categories.");
    }
    
    /**
     * Migrate a single category.
     */
    protected function migrateCategory($oldCat, $newParentId): ?int
    {
        // Check if already migrated
        $existing = DB::table('migration_mappings')
            ->where('old_table', 'categories')
            ->where('old_id', $oldCat->id)
            ->first();
            
        if ($existing) {
            $this->command->warn("Category '{$oldCat->name}' already migrated, skipping.");
            return null;
        }
        
        // Create new category
        $newCategory = Category::create([
            'business_id' => $this->businessId,
            'name' => $oldCat->name,
            'short_code' => $oldCat->code ?? null,
            'parent_id' => $newParentId,
            'category_type' => 'product',
            'description' => $oldCat->description ?? null,
            'slug' => $oldCat->slug ?? \Str::slug($oldCat->name),
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Record mapping
        DB::table('migration_mappings')->insert([
            'old_table' => 'categories',
            'old_id' => $oldCat->id,
            'new_table' => 'categories',
            'new_id' => $newCategory->id,
            'migrated_at' => now(),
        ]);
        
        return $newCategory->id;
    }
}
