<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProductVariant;

$jsonPath = 'C:\\Users\\Rizky\\Downloads\\CD_Tiktok_HENDRY .json';
$raw = file_get_contents($jsonPath);
$json = json_decode($raw, true);
$rows = $json['data'] ?? (is_array($json) ? $json : []);

echo "Total HENDRY rows: " . count($rows) . "\n";

$dbVariants = ProductVariant::with(['product.seller', 'stocks'])->get();
$skuMap = [];
foreach ($dbVariants as $v) {
    $skuMap[strtoupper(trim($v->sku))] = $v;
}

function parseSizeTokens($text) {
    $sizes = [];
    // Extract size tokens like 5XL, 4XL, 3XL, 2XL, XXL, XL, L, M, S, XS
    if (preg_match_all('/\b(5XL|4XL|3XL|2XL|XXL|XL|L|M|S|XS)\b/i', $text, $m)) {
        foreach ($m[1] as $s) {
            $sz = strtoupper($s);
            $sizes[] = $sz;
            if ($sz === '2XL') $sizes[] = 'XXL';
            if ($sz === 'XXL') $sizes[] = '2XL';
            if ($sz === '3XL') $sizes[] = 'XXXL';
            if ($sz === 'XXXL') $sizes[] = '3XL';
        }
    }
    return array_unique($sizes);
}

function resolveVariantSmart($rawSku, $varText, $skuMap) {
    $cleanSku = strtoupper(trim($rawSku));
    if (!$cleanSku) return null;

    // Direct match
    if (isset($skuMap[$cleanSku])) {
        return $skuMap[$cleanSku];
    }

    $sizes = parseSizeTokens($varText . ' ' . $rawSku);

    foreach ($sizes as $size) {
        if (isset($skuMap[$cleanSku . '-' . $size])) return $skuMap[$cleanSku . '-' . $size];
        if (isset($skuMap[$cleanSku . $size])) return $skuMap[$cleanSku . $size];
        if (isset($skuMap[$cleanSku . '-' . $size . '-1'])) return $skuMap[$cleanSku . '-' . $size . '-1'];

        // Prefix match
        $prefix = $cleanSku . '-';
        foreach ($skuMap as $dbSku => $variant) {
            if (str_starts_with($dbSku, $prefix) && (strtoupper($variant->size) === $size || ($size === 'XXL' && strtoupper($variant->size) === '2XL') || ($size === '2XL' && strtoupper($variant->size) === 'XXL'))) {
                return $variant;
            }
        }
    }

    return null;
}

$matchedCount = 0;
$missingCount = 0;
$missingSkus = [];

foreach ($rows as $idx => $r) {
    $rawSku = trim((string)($r['Nomor Referensi SKU'] ?? $r['Seller SKU'] ?? $r['sku'] ?? ''));
    $varName = trim((string)($r['Nama Variasi'] ?? $r['Variation'] ?? ''));
    $prodName = trim((string)($r['Nama Produk'] ?? $r['Product Name'] ?? ''));

    if (!$rawSku) continue;

    $parts = preg_split('/[\-+\,\s]+/', $rawSku);
    $parts = array_filter(array_map('trim', $parts));

    $subMatched = [];
    $hasMissing = false;

    foreach ($parts as $part) {
        $v = resolveVariantSmart($part, $varName, $skuMap);
        if ($v) {
            $subMatched[] = $v;
        } else {
            $hasMissing = true;
            $missingSkus[$part] = [
                'sku' => $part,
                'var' => $varName,
                'prod' => $prodName
            ];
        }
    }

    if (!$hasMissing && count($subMatched) > 0) {
        $matchedCount += count($subMatched);
    } else {
        $missingCount++;
    }
}

echo "\n--- TEST RESULT FOR HENDRY JSON ---\n";
echo "Total Matched Component Rows: $matchedCount\n";
echo "Total Missing Rows: $missingCount\n";
echo "Total Distinct Missing SKUs: " . count($missingSkus) . "\n";
if (count($missingSkus) > 0) {
    echo "\nSample Missing SKUs:\n";
    foreach (array_slice($missingSkus, 0, 10) as $sku => $info) {
        echo "  - SKU: '$sku' | Var: '{$info['var']}' | Prod: '{$info['prod']}'\n";
    }
}
