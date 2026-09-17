<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use App\Models\ProductVariant;

echo "--- Searching Products for 'BKA' or 'BABAN' or 'FIVE' ---\n";
$prods = Product::where('product_name', 'LIKE', '%BKA%')
    ->orWhere('product_name', 'LIKE', '%BABAN%')
    ->orWhere('product_name', 'LIKE', '%FIVE%')
    ->orWhere('sku', 'LIKE', '%BKA%')
    ->get();

echo "Found " . $prods->count() . " products:\n";
foreach ($prods as $p) {
    echo "Product ID: {$p->id} | SKU: '{$p->sku}' | Name: '{$p->product_name}' | Seller ID: {$p->seller_id}\n";
}

echo "\n--- Searching Product Variants for 'BKA' ---\n";
$vars = ProductVariant::where('sku', 'LIKE', '%BKA%')->get();
echo "Found " . $vars->count() . " variants with BKA SKU:\n";
foreach ($vars->take(20) as $v) {
    echo "Variant ID: {$v->id} | SKU: '{$v->sku}' | Size: '{$v->size}' | Color: '{$v->color}'\n";
}

echo "\n--- Searching Product Variants for any 'BKA' substring ---\n";
$vars2 = ProductVariant::with('product')->whereHas('product', function($q) {
    $q->where('product_name', 'LIKE', '%BKA%')->orWhere('sku', 'LIKE', '%BKA%');
})->get();
echo "Found " . $vars2->count() . " variants under BKA products:\n";
foreach ($vars2->take(20) as $v) {
    echo "Variant ID: {$v->id} | SKU: '{$v->sku}' | Prod Name: '{$v->product->product_name}'\n";
}
