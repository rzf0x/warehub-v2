<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProductVariant;
use App\Models\Product;

echo "--- Searching ProductVariants for price near 237.000 or bundle SKUs ---\n";
$vars = ProductVariant::with('product')->where('price', '>', 100000)->get();
echo "Found " . $vars->count() . " variants with price > 100.000:\n";
foreach ($vars as $v) {
    echo "ID: {$v->id} | SKU: '{$v->sku}' | Price: " . number_format($v->price, 0, ',', '.') . " | Prod: '{$v->product->product_name}'\n";
}
