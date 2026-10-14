<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$variants = App\Models\ProductVariant::where('sku', 'LIKE', '%BKA%')->limit(15)->get(['id', 'sku', 'size', 'color']);
echo "Total BKA found: " . $variants->count() . "\n";
foreach ($variants as $v) {
    echo "ID: {$v->id} | SKU: '{$v->sku}' | Size: '{$v->size}' | Color: '{$v->color}'\n";
}
