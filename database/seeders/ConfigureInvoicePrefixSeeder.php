<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Business;
use App\InvoiceScheme;
use App\ReferenceCount;
use Illuminate\Support\Facades\DB;

/**
 * Seeder to configure invoice prefixes matching the old CodeIgniter POS system.
 * 
 * Prefix mapping:
 * - VT = Sales/Tax Invoice
 * - IPAY = Billing Receipt (Payments)
 * - QUOTE = Quotation
 * 
 * Run with: php artisan db:seed --class=ConfigureInvoicePrefixSeeder
 */
class ConfigureInvoicePrefixSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('Configuring invoice prefixes...');

        // Get the business (assuming business_id = 1, adjust if needed)
        $business = Business::first();
        
        if (!$business) {
            $this->command->error('No business found. Please create a business first.');
            return;
        }

        // Configure reference number prefixes
        $prefixes = [
            'sell' => 'VT',              // Tax Invoice / Sales
            'sell_return' => 'SR',       // Sales Return
            'sell_payment' => 'IPAY',    // Billing Receipt / Payment
            'purchase' => 'PO',          // Purchase Order
            'purchase_return' => 'PR',   // Purchase Return
            'purchase_payment' => 'PPAY', // Purchase Payment
            'expense' => 'EXP',          // Expense
            'stock_transfer' => 'ST',    // Stock Transfer
            'stock_adjustment' => 'SA',  // Stock Adjustment
            'contacts' => 'CO',          // Contacts
            'quotation' => 'QUOTE',      // Quotation
            'sales_order' => 'SO',       // Sales Order
            'purchase_order' => 'PO',    // Purchase Order
            'opening_balance' => 'OB',   // Opening Balance
            'subscription' => 'SUB',     // Subscription
        ];

        // Merge with existing prefixes (if any)
        // Note: ref_no_prefixes is already cast as array by Business model
        $existing = is_array($business->ref_no_prefixes) 
            ? $business->ref_no_prefixes 
            : (json_decode($business->ref_no_prefixes, true) ?? []);
        $merged = array_merge($existing, $prefixes);
        
        $business->ref_no_prefixes = $merged;
        $business->save();

        $this->command->info('✓ Updated ref_no_prefixes in business table');

        // Configure Invoice Scheme for Sales
        $invoiceScheme = InvoiceScheme::where('business_id', $business->id)
            ->where('is_default', 1)
            ->first();

        if ($invoiceScheme) {
            $invoiceScheme->prefix = 'VT';
            $invoiceScheme->scheme_type = 'year';
            $invoiceScheme->total_digits = 4;
            $invoiceScheme->save();
            $this->command->info('✓ Updated default invoice scheme with VT prefix');
        } else {
            // Create default invoice scheme if not exists
            InvoiceScheme::create([
                'business_id' => $business->id,
                'name' => 'Default',
                'scheme_type' => 'year',
                'prefix' => 'VT',
                'start_number' => 1,
                'invoice_count' => 0,
                'total_digits' => 4,
                'is_default' => 1,
            ]);
            $this->command->info('✓ Created default invoice scheme with VT prefix');
        }

        // Optionally sync counters from old system
        // Uncomment and adjust these values based on your old system's last numbers
        /*
        $this->syncCounter($business->id, 'sell_payment', 10637);  // Last IPAY number
        $this->syncCounter($business->id, 'quotation', 3492);      // Last QUOTE number
        $this->syncCounter($business->id, 'sell', 1543);           // Last VT number
        */

        $this->command->info('');
        $this->command->info('=== Invoice Prefix Configuration Complete ===');
        $this->command->info('');
        $this->command->info('Configured prefixes:');
        foreach ($prefixes as $type => $prefix) {
            $this->command->line("  - {$type}: {$prefix}");
        }
        $this->command->info('');
        $this->command->info('Example outputs:');
        $this->command->line('  - Tax Invoice: VT2025/0001');
        $this->command->line('  - Payment: IPAY2025/0001');
        $this->command->line('  - Quotation: QUOTE2025/0001');
    }

    /**
     * Sync reference counter to continue from old system's last number
     */
    private function syncCounter($business_id, $ref_type, $count)
    {
        ReferenceCount::updateOrCreate(
            ['business_id' => $business_id, 'ref_type' => $ref_type],
            ['ref_count' => $count]
        );
        $this->command->info("✓ Synced {$ref_type} counter to {$count}");
    }
}
