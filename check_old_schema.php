<?php
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

// Configure old DB connection dynamically
config(['database.connections.old_pos' => [
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'port' => '8889',
    'database' => 'rubyshop_co_th_sale_pos',
    'username' => 'root',
    'password' => 'root',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
]]);

echo "--- Old DB Schema Check ---\n";

try {
    // Check fields in sma_products
    $productColumns = DB::connection('old_pos')->getSchemaBuilder()->getColumnListing('sma_products');
    echo "Columns in sma_products: " . implode(', ', $productColumns) . "\n\n";

    // Check if sma_warehouses_products exists
    $hasWarehouseProducts = DB::connection('old_pos')->getSchemaBuilder()->hasTable('sma_warehouses_products');
    if ($hasWarehouseProducts) {
        $wpColumns = DB::connection('old_pos')->getSchemaBuilder()->getColumnListing('sma_warehouses_products');
        echo "Columns in sma_warehouses_products: " . implode(', ', $wpColumns) . "\n\n";
        
        // Sample data
        $sample = DB::connection('old_pos')->table('sma_warehouses_products')->first();
        echo "Sample sma_warehouses_products data: " . json_encode($sample) . "\n";
    } else {
        echo "Table sma_warehouses_products DOES NOT exist.\n";
    }

    // Sample product data
    $productSample = DB::connection('old_pos')->table('sma_products')->select('id', 'code', 'quantity')->first();
    echo "Sample sma_products data: " . json_encode($productSample) . "\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
