<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProductVariant;

$skuStr = "SK04-SK03-SK02";
$variantName = "Warna Kombinasi, L";

echo "Testing splitting '$skuStr' into individual product rows:\n";

// Delimiters: - or + or , or space
$parts = preg_split('/[\-+\,\s]+/', strtoupper($skuStr));
echo "Parts count: " . count($parts) . "\n";
foreach ($parts as $p) {
    echo "  - Component SKU: '$p'\n";
}

$dbVariants = ProductVariant::with('product')->get();
$skuMap = [];
foreach ($dbVariants as $v) {
    $skuMap[strtoupper(trim($v->sku))] = $v;
}

echo "\nDatabase matches for components:\n";
foreach ($parts as $p) {
    $clean = trim($p);
    $found = false;
    foreach ($skuMap as $dbSku => $variant) {
        if (str_starts_with($dbSku, $clean)) {
            echo "  Component '$clean' -> Matched DB SKU '{$variant->sku}' ({$variant->product->product_name})\n";
            $found = true;
            break;
        }
    }
    if (!$found) {
        echo "  Component '$clean' -> Not found in DB\n";
    }
}
