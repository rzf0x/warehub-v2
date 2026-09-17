<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProductVariant;

$jsonPath = 'C:\\Users\\Rizky\\Downloads\\CD_Tiktok_HENDRY .json';
if (!file_exists($jsonPath)) {
    echo "File not found: $jsonPath\n";
    exit;
}

$raw = file_get_contents($jsonPath);
$json = json_decode($raw, true);

$rows = [];
if (isset($json['data']) && is_array($json['data'])) {
    $rows = $json['data'];
} elseif (isset($json['items']) && is_array($json['items'])) {
    $rows = $json['items'];
} elseif (is_array($json)) {
    $rows = $json;
}

echo "Total items in HENDRY JSON: " . count($rows) . "\n\n";

echo "Sample 10 items in HENDRY JSON:\n";
foreach (array_slice($rows, 0, 10) as $i => $item) {
    $sku = $item['Nomor Referensi SKU'] ?? ($item['SKU'] ?? ($item['sku'] ?? ''));
    $prod = $item['Nama Produk'] ?? ($item['Nama Barang'] ?? '');
    $var = $item['Nama Variasi'] ?? ($item['Variasi'] ?? '');
    echo "[$i] SKU: '$sku' | Prod: '$prod' | Var: '$var'\n";
}

echo "\n--- Checking against DB Product Variants ---\n";
$dbVariants = ProductVariant::with('product')->get();
$skuMap = [];
foreach ($dbVariants as $v) {
    $skuMap[strtoupper(trim($v->sku))] = $v;
}

echo "Total DB Variants: " . count($skuMap) . "\n\n";

foreach ($rows as $idx => $r) {
    $rawSku = trim((string)($r['Nomor Referensi SKU'] ?? $r['Seller SKU'] ?? $r['sku'] ?? ''));
    $varName = trim((string)($r['Nama Variasi'] ?? $r['Variation'] ?? ''));
    $prodName = trim((string)($r['Nama Produk'] ?? $r['Product Name'] ?? ''));

    echo "Row [$idx] Raw SKU: '$rawSku' | Var: '$varName'\n";

    // Split SKU by - or + or , or space
    $parts = preg_split('/[\-+\,\s]+/', strtoupper($rawSku));
    $parts = array_filter(array_map('trim', $parts));

    foreach ($parts as $p) {
        $clean = trim($p);
        if (!$clean) continue;

        // Try direct match first
        if (isset($skuMap[$clean])) {
            echo "   -> Part '$clean': DIRECT MATCH ({$skuMap[$clean]->sku})\n";
            continue;
        }

        // Try prefix match in DB
        $matchesInDb = [];
        foreach ($skuMap as $dbSku => $v) {
            if (str_starts_with($dbSku, $clean)) {
                $matchesInDb[] = $dbSku;
            }
        }

        if (count($matchesInDb) > 0) {
            echo "   -> Part '$clean': Found DB candidates: " . implode(', ', array_slice($matchesInDb, 0, 5)) . "\n";
        } else {
            echo "   -> Part '$clean': NO CANDIDATES IN DB ❌\n";
        }
    }
    echo "---------------------------------------------------------\n";
}
