<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MigrateReferenceDataSeeder extends Seeder
{
    /**
     * Master seeder to run all reference data migrations in order.
     * 
     * Run with: php artisan db:seed --class=MigrateReferenceDataSeeder
     */
    public function run(): void
    {
        $this->command->info('========================================');
        $this->command->info('Starting Reference Data Migration');
        $this->command->info('========================================');
        
        // Run seeders in order of dependency
        $this->call([
            MigrateTaxRatesSeeder::class,
            MigrateCategoriesSeeder::class,
            MigrateBrandsSeeder::class,
            MigrateCustomerGroupsSeeder::class,
            MigrateLocationsSeeder::class,
        ]);
        
        $this->command->info('========================================');
        $this->command->info('Reference Data Migration Complete!');
        $this->command->info('========================================');
    }
}
