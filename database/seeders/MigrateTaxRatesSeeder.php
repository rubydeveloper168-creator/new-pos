<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\TaxRate;

class MigrateTaxRatesSeeder extends Seeder
{
    protected $businessId = 1;
    
    /**
     * Migrate tax rates from old POS to new POS.
     * 
     * Old: sma_tax_rates
     * New: tax_rates
     */
    public function run(): void
    {
        $this->command->info('Migrating tax rates...');
        
        $oldTaxRates = DB::connection('old_pos')
            ->table('tax_rates')
            ->get();
            
        $migrated = 0;
        
        foreach ($oldTaxRates as $oldTax) {
            // Check if already migrated
            $existing = DB::table('migration_mappings')
                ->where('old_table', 'tax_rates')
                ->where('old_id', $oldTax->id)
                ->first();
                
            if ($existing) {
                $this->command->warn("Tax rate '{$oldTax->name}' already migrated, skipping.");
                continue;
            }
            
            // Create new tax rate
            $newTaxRate = TaxRate::create([
                'business_id' => $this->businessId,
                'name' => $oldTax->name,
                'amount' => $oldTax->rate ?? 0,
                'is_tax_group' => 0,
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Record mapping
            DB::table('migration_mappings')->insert([
                'old_table' => 'tax_rates',
                'old_id' => $oldTax->id,
                'new_table' => 'tax_rates',
                'new_id' => $newTaxRate->id,
                'migrated_at' => now(),
            ]);
            
            $migrated++;
        }
        
        $this->command->info("Migrated {$migrated} tax rates.");
    }
}
