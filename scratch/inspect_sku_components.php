<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProductVariant;

$jsonPath = 'C:\\Users\\Rizky\\Downloads\\CD_Tiktok_BABAN 01 - 07 JUNI 2026.json';
$raw = file_get_contents($jsonPath);
$json = json_decode($raw, true);
$rows = $json['data'] ?? (is_array($json) ? $json : []);

$dbVariants = ProductVariant::with('product')->get();
$dbSkuMap = [];
foreach ($dbVariants as $v) {
    $skuClean = strtoupper(trim($v->sku));
    $dbSkuMap[$skuClean] = $v;
}

echo "Total DB variants: " . count($dbSkuMap) . "\n\n";

foreach ($rows as $idx => $r) {
    $skuStr = trim($r['Nomor Referensi SKU'] ?? '');
    echo "Row [$idx] SKU: '$skuStr'\n";

    // Split SKU by - or + or space
    $parts = preg_split('/[\-+\,\s]+/', strtoupper($skuStr));
    foreach ($parts as $p) {
        $pClean = trim($p);
        if ($pClean === '') continue;
        $found = isset($dbSkuMap[$pClean]);
        $prodName = $found ? $dbSkuMap[$pClean]->product->product_name : 'NOT FOUND';
        echo "   -> Component '$pClean': " . ($found ? "FOUND ($prodName)" : "MISSING ❌") . "\n";
    }
}
