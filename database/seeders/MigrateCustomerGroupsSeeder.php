<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\CustomerGroup;

class MigrateCustomerGroupsSeeder extends Seeder
{
    protected $businessId = 1;
    
    /**
     * Migrate customer groups from old POS to new POS.
     * 
     * Old: sma_customer_groups
     * New: customer_groups
     */
    public function run(): void
    {
        $this->command->info('Migrating customer groups...');
        
        $oldGroups = DB::connection('old_pos')
            ->table('customer_groups')
            ->get();
            
        $migrated = 0;
        
        foreach ($oldGroups as $oldGroup) {
            // Check if already migrated
            $existing = DB::table('migration_mappings')
                ->where('old_table', 'customer_groups')
                ->where('old_id', $oldGroup->id)
                ->first();
                
            if ($existing) {
                $this->command->warn("Customer group '{$oldGroup->name}' already migrated, skipping.");
                continue;
            }
            
            // Create new customer group
            $newGroup = CustomerGroup::create([
                'business_id' => $this->businessId,
                'name' => $oldGroup->name,
                'amount' => abs($oldGroup->percent ?? 0), // Convert negative discounts to positive
                'price_calculation_type' => ($oldGroup->percent ?? 0) < 0 ? 'percentage' : 'fixed',
                'selling_price_group_id' => null,
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Record mapping
            DB::table('migration_mappings')->insert([
                'old_table' => 'customer_groups',
                'old_id' => $oldGroup->id,
                'new_table' => 'customer_groups',
                'new_id' => $newGroup->id,
                'migrated_at' => now(),
            ]);
            
            $migrated++;
        }
        
        $this->command->info("Migrated {$migrated} customer groups.");
    }
}
