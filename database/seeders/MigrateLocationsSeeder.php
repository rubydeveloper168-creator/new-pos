<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\BusinessLocation;

class MigrateLocationsSeeder extends Seeder
{
    protected $businessId = 1;
    
    /**
     * Migrate warehouse/locations from old POS to new POS.
     * 
     * Old: sma_warehouses
     * New: business_locations
     */
    public function run(): void
    {
        $this->command->info('Migrating locations (warehouses)...');
        
        $oldWarehouses = DB::connection('old_pos')
            ->table('warehouses')
            ->get();
            
        $migrated = 0;
        
        foreach ($oldWarehouses as $oldWarehouse) {
            // Check if already migrated
            $existing = DB::table('migration_mappings')
                ->where('old_table', 'warehouses')
                ->where('old_id', $oldWarehouse->id)
                ->first();
                
            if ($existing) {
                $this->command->warn("Warehouse '{$oldWarehouse->name}' already migrated, skipping.");
                continue;
            }
            
            // Create new business location
            $newLocation = BusinessLocation::create([
                'business_id' => $this->businessId,
                'location_id' => 'BL' . str_pad($oldWarehouse->id, 4, '0', STR_PAD_LEFT),
                'name' => $oldWarehouse->name,
                'landmark' => $oldWarehouse->address ?? null,
                'country' => $oldWarehouse->country ?? '',
                'state' => $oldWarehouse->state ?? '',
                'city' => $oldWarehouse->city ?? '',
                'zip_code' => $oldWarehouse->postal_code ?? '',
                'mobile' => $oldWarehouse->phone ?? null,
                'email' => $oldWarehouse->email ?? null,
                'invoice_scheme_id' => 1, // Default invoice scheme
                'invoice_layout_id' => 1, // Default invoice layout
                'sale_invoice_layout_id' => 1,
                'selling_price_group_id' => null,
                'print_receipt_on_invoice' => 1,
                'receipt_printer_type' => 'browser',
                'is_active' => 1,
                'default_payment_accounts' => '{}',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Record mapping
            DB::table('migration_mappings')->insert([
                'old_table' => 'warehouses',
                'old_id' => $oldWarehouse->id,
                'new_table' => 'business_locations',
                'new_id' => $newLocation->id,
                'migrated_at' => now(),
            ]);
            
            $migrated++;
        }
        
        $this->command->info("Migrated {$migrated} locations.");
    }
}
