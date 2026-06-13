<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Contact;

class MigrateContacts extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'migrate:contacts 
                            {--dry-run : Run without making changes}
                            {--batch= : Batch size for processing (default: 500)}';

    /**
     * The console command description.
     */
    protected $description = 'Migrate customers and suppliers from old POS to contacts table';

    protected $businessId = 1;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $batchSize = $this->option('batch') ?? 500;
        
        $this->info('========================================');
        $this->info('Migrating Contacts (Customers & Suppliers)');
        $this->info('========================================');
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }
        
        try {
            // Get companies from old POS
            // group_id = 3 (customer), group_id = 4 (supplier)
            $oldCompanies = DB::connection('old_pos')
                ->table('companies')
                ->whereIn('group_id', [3, 4])
                ->get();
                
            $this->info("Found {$oldCompanies->count()} contacts to migrate.");
            
            $bar = $this->output->createProgressBar($oldCompanies->count());
            $bar->start();
            
            $migrated = 0;
            $skipped = 0;
            
            DB::beginTransaction();
            
            foreach ($oldCompanies as $oldCompany) {
                // Check if already migrated
                $existing = DB::table('migration_mappings')
                    ->where('old_table', 'companies')
                    ->where('old_id', $oldCompany->id)
                    ->first();
                    
                if ($existing) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }
                
                // Determine contact type
                $contactType = $oldCompany->group_id == 3 ? 'customer' : 'supplier';
                
                // Get customer group mapping if exists
                $customerGroupMapping = null;
                if ($oldCompany->customer_group_id) {
                    $customerGroupMapping = DB::table('migration_mappings')
                        ->where('old_table', 'customer_groups')
                        ->where('old_id', $oldCompany->customer_group_id)
                        ->first();
                }
                
                if (!$dryRun) {
                    // Create new contact
                    $newContact = Contact::create([
                        'business_id' => $this->businessId,
                        'type' => $contactType,
                        'supplier_business_name' => $oldCompany->company ?? null,
                        'name' => $oldCompany->name,
                        'prefix' => null,
                        'first_name' => $oldCompany->name,
                        'middle_name' => null,
                        'last_name' => null,
                        'email' => !empty($oldCompany->email) ? $oldCompany->email : null,
                        'contact_id' => 'C' . str_pad($oldCompany->id, 6, '0', STR_PAD_LEFT),
                        'contact_status' => 'active',
                        'tax_number' => $oldCompany->vat_no ?? $oldCompany->gst_no ?? null,
                        'city' => $oldCompany->city ?? null,
                        'state' => $oldCompany->state ?? null,
                        'country' => $oldCompany->country ?? null,
                        'address_line_1' => $oldCompany->address ?? null,
                        'address_line_2' => null,
                        'zip_code' => $oldCompany->postal_code ?? null,
                        'mobile' => $oldCompany->phone ?? null,
                        'landline' => null,
                        'alternate_number' => null,
                        'pay_term_number' => $oldCompany->payment_term ?? null,
                        'pay_term_type' => $oldCompany->payment_term ? 'days' : null,
                        'credit_limit' => null,
                        'customer_group_id' => $customerGroupMapping->new_id ?? null,
                        'custom_field1' => $oldCompany->cf1 ?? null,
                        'custom_field2' => $oldCompany->cf2 ?? null,
                        'custom_field3' => $oldCompany->cf3 ?? null,
                        'custom_field4' => $oldCompany->cf4 ?? null,
                        'custom_field5' => $oldCompany->cf5 ?? null,
                        'custom_field6' => $oldCompany->cf6 ?? null,
                        'created_by' => 1,
                        'is_default' => $oldCompany->id == 1 ? 1 : 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    
                    // Record mapping
                    DB::table('migration_mappings')->insert([
                        'old_table' => 'companies',
                        'old_id' => $oldCompany->id,
                        'new_table' => 'contacts',
                        'new_id' => $newContact->id,
                        'migrated_at' => now(),
                        'notes' => "type: {$contactType}",
                    ]);
                }
                
                $migrated++;
                $bar->advance();
            }
            
            $bar->finish();
            $this->newLine();
            
            if (!$dryRun) {
                DB::commit();
                $this->info("✓ Migrated {$migrated} contacts.");
            } else {
                DB::rollBack();
                $this->info("Would migrate {$migrated} contacts.");
            }
            
            if ($skipped > 0) {
                $this->warn("Skipped {$skipped} already migrated contacts.");
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Migration failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
