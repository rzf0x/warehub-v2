<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProductVariant;
use App\Models\Product;

$jsonPath = 'C:\\Users\\Rizky\\Downloads\\CD_Tiktok_BABAN 01 - 07 JUNI 2026.json';
if (!file_exists($jsonPath)) {
    echo "File not found at $jsonPath\n";
    exit;
}

$raw = file_get_contents($jsonPath);
$data = json_decode($raw, true);

echo "Total items in JSON: " . count($data) . "\n";

// Sample first 10 items
echo "Sample JSON items:\n";
$skusInJson = [];
foreach (array_slice($data, 0, 15) as $i => $item) {
    $sku = $item['Nomor Referensi SKU'] ?? ($item['SKU'] ?? ($item['sku'] ?? ''));
    $namaProduk = $item['Nama Produk'] ?? ($item['Nama Barang'] ?? '');
    $variasi = $item['Nama Variasi'] ?? ($item['Variasi'] ?? '');
    echo "[$i] SKU: '$sku' | Prod: '$namaProduk' | Var: '$variasi'\n";
    if ($sku) $skusInJson[] = trim($sku);
}

// Collect ALL SKUs from JSON
$allSkus = [];
foreach ($data as $item) {
    $sku = trim($item['Nomor Referensi SKU'] ?? ($item['SKU'] ?? ($item['sku'] ?? '')));
    if ($sku !== '') {
        $allSkus[$sku] = ($allSkus[$sku] ?? 0) + 1;
    }
}

echo "\nTotal distinct SKUs in JSON: " . count($allSkus) . "\n";

// Check against DB product_variants
$dbVariants = ProductVariant::with('product')->get();
echo "Total DB variants: " . $dbVariants->count() . "\n";

$dbSkuMap = [];
foreach ($dbVariants as $v) {
    $skuClean = strtoupper(trim($v->sku));
    $dbSkuMap[$skuClean] = $v;
}

$matchedCount = 0;
$missingSkus = [];

foreach ($allSkus as $skuStr => $count) {
    // Test direct match
    $cleanSku = strtoupper(trim($skuStr));
    if (isset($dbSkuMap[$cleanSku])) {
        $matchedCount++;
        continue;
    }

    // Test bundle splitting if SKU contains '-' or '+' or space
    // e.g. BKA41-BKA42-BKA43
    $subSkus = preg_split('/[\-+\,\s]+/', $cleanSku);
    $allSubMatched = true;
    if (count($subSkus) > 1) {
        foreach ($subSkus as $sub) {
            $subClean = strtoupper(trim($sub));
            if ($subClean !== '' && !isset($dbSkuMap[$subClean])) {
                $allSubMatched = false;
                break;
            }
        }
    } else {
        $allSubMatched = false;
    }

    if ($allSubMatched) {
        $matchedCount++;
    } else {
        $missingSkus[$skuStr] = $count;
    }
}

echo "\nDirect/Bundle Matched SKUs: $matchedCount / " . count($allSkus) . "\n";
echo "Missing SKUs count: " . count($missingSkus) . "\n";

echo "\nTop 20 Missing SKUs:\n";
$i = 0;
foreach ($missingSkus as $sku => $count) {
    echo "  - '$sku' (qty: $count)\n";
    $i++;
    if ($i >= 20) break;
}

// Let's also check sample product names in DB to see how product_variants are named in DB
echo "\nSample DB product variants:\n";
foreach ($dbVariants->take(15) as $v) {
    echo "  ID: {$v->id} | SKU: '{$v->sku}' | Prod: '{$v->product->product_name}' | Size: '{$v->size}' | Color: '{$v->color}'\n";
}
