<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProductVariant;
use App\Models\Warehouse;

$jsonPath = 'C:\\Users\\Rizky\\Downloads\\CD_Tiktok_HENDRY .json';
$raw = file_get_contents($jsonPath);
$json = json_decode($raw, true);
$rows = $json['data'] ?? (is_array($json) ? $json : []);

$skuMap = [];
ProductVariant::with(['product.seller', 'stocks'])->get()->each(function ($v) use (&$skuMap) {
    $cleanSku = strtoupper(trim($v->sku));
    $skuMap[$cleanSku] = $v;
    $noSpace = str_replace(' ', '', $cleanSku);
    if (!isset($skuMap[$noSpace])) $skuMap[$noSpace] = $v;
});

function parseSizeTokens($text) {
    $sizes = [];
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

function resolveVariantFromSkuAndSize($sku, $varName, $rawSku, $skuMap) {
    $cleanSku = strtoupper(trim($sku));
    $noSpaceSku = str_replace(' ', '', $cleanSku);
    if (!$cleanSku) return null;

    if (isset($skuMap[$cleanSku])) return $skuMap[$cleanSku];
    if (isset($skuMap[$noSpaceSku])) return $skuMap[$noSpaceSku];

    $sizes = parseSizeTokens($varName . ' ' . $rawSku);
    foreach ($sizes as $size) {
        if (isset($skuMap[$cleanSku . '-' . $size])) return $skuMap[$cleanSku . '-' . $size];
        if (isset($skuMap[$noSpaceSku . '-' . $size])) return $skuMap[$noSpaceSku . '-' . $size];
        if (isset($skuMap[$cleanSku . $size])) return $skuMap[$cleanSku . $size];
        if (isset($skuMap[$noSpaceSku . $size])) return $skuMap[$noSpaceSku . $size];
        if (isset($skuMap[$cleanSku . '-' . $size . '-1'])) return $skuMap[$cleanSku . '-' . $size . '-1'];
        if (isset($skuMap[$noSpaceSku . '-' . $size . '-1'])) return $skuMap[$noSpaceSku . '-' . $size . '-1'];
    }
    return null;
}

$matchedSK08L = [];
foreach ($rows as $idx => $item) {
    $rawSku = trim((string)($item['Nomor Referensi SKU'] ?? $item['Seller SKU'] ?? $item['SKU Induk'] ?? $item['sku'] ?? ''));
    $variantName = trim((string)($item['Nama Variasi'] ?? $item['Variation'] ?? ''));
    $productName = trim((string)($item['Nama Produk'] ?? $item['Product Name'] ?? ''));
    $qty = intval($item['Jumlah'] ?? $item['Quantity'] ?? $item['Qty'] ?? 1);

    if (!$rawSku) continue;

    $parts = preg_split('/[\+,\,]+/', $rawSku);
    if (count($parts) === 1 && preg_match('/^([A-Z0-9\s]+)-([A-Z0-9\s]+)/i', $rawSku)) {
        $parts = explode('-', $rawSku);
    }
    $parts = array_filter(array_map('trim', $parts));

    foreach ($parts as $part) {
        $v = resolveVariantFromSkuAndSize($part, $variantName, $rawSku, $skuMap);
        if ($v && $v->sku === 'SK08-L') {
            $matchedSK08L[] = [
                'json_row' => $idx + 1,
                'rawSku' => $rawSku,
                'variantName' => $variantName,
                'productName' => $productName,
                'qty' => $qty,
                'matched_sku' => $v->sku,
            ];
        }
    }
}

echo "Total matched items with SKU 'SK08-L': " . count($matchedSK08L) . " rows\n";
$totalQtySK08L = array_sum(array_column($matchedSK08L, 'qty'));
echo "Total Qty of 'SK08-L': " . $totalQtySK08L . " pcs\n\n";

foreach ($matchedSK08L as $m) {
    echo "Row {$m['json_row']} | Qty: {$m['qty']} | Raw SKU: {$m['rawSku']} | Variant String: '{$m['variantName']}'\n";
}
