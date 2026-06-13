<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Product;
use App\BusinessLocation;

class LinkProductsToLocationsSeeder extends Seeder
{
    /**
     * Link all products to all business locations.
     * This is required for product search to work properly.
     */
    public function run()
    {
        $this->command->info('Linking products to locations...');
        
        $products = Product::pluck('id')->toArray();
        $locations = BusinessLocation::pluck('id')->toArray();
        
        $this->command->info('Products: ' . count($products));
        $this->command->info('Locations: ' . count($locations));
        
        $count = 0;
        $batchData = [];
        
        foreach ($products as $productId) {
            foreach ($locations as $locationId) {
                // Check if already exists
                $exists = DB::table('product_locations')
                    ->where('product_id', $productId)
                    ->where('location_id', $locationId)
                    ->exists();
                    
                if (!$exists) {
                    $batchData[] = [
                        'product_id' => $productId,
                        'location_id' => $locationId,
                    ];
                    $count++;
                    
                    // Insert in batches of 500
                    if (count($batchData) >= 500) {
                        DB::table('product_locations')->insert($batchData);
                        $batchData = [];
                        $this->command->info("Progress: {$count} links created...");
                    }
                }
            }
        }
        
        // Insert remaining
        if (!empty($batchData)) {
            DB::table('product_locations')->insert($batchData);
        }
        
        $total = DB::table('product_locations')->count();
        $this->command->info("Done! Created {$count} new links.");
        $this->command->info("Total product-location links: {$total}");
    }
}
