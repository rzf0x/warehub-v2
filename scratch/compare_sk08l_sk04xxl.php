<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProductVariant;

$jsonPath = 'C:\\Users\\Rizky\\Downloads\\CD_Tiktok_HENDRY .json';
$raw = file_get_contents($jsonPath);
$json = json_decode($raw, true);
$rows = $json['data'] ?? (is_array($json) ? $json : []);

$dbVariants = ProductVariant::with(['product.seller', 'stocks'])->get();
$skuMap = [];
foreach ($dbVariants as $v) {
    $skuMap[strtoupper(trim($v->sku))] = $v;
    $noSpaceSku = str_replace(' ', '', strtoupper(trim($v->sku)));
    if (!isset($skuMap[$noSpaceSku])) {
        $skuMap[$noSpaceSku] = $v;
    }
}

function parseSizeTokensFixed($text) {
    $sizes = [];
    if (preg_match_all('/\b(5XL|4XL|3XL|2XL|XXL|XL|L|M|S|XS)\b/i', $text, $m)) {
        foreach ($m[1] as $s) {
            $sz = strtoupper($s);
            $sizes[] = $sz;
            if ($sz === '2XL') $sizes[] = 'XXL';
            if ($sz === 'XXL') $sizes[] = '2XL';
            if ($sz === '3XL') $sizes[] = 'XXXL';
            if ($sz === 'XXXL') $sizes[] = '3XL';
            if ($sz === '4XL') $sizes[] = 'XXXXL';
            if ($sz === 'XXXXL') $sizes[] = '4XL';
        }
    }
    return array_unique($sizes);
}

function resolveVariantSmart($rawSku, $varText, $skuMap) {
    $cleanSku = strtoupper(trim($rawSku));
    $noSpaceSku = str_replace(' ', '', $cleanSku);
    if (!$cleanSku) return null;

    if (isset($skuMap[$cleanSku])) return $skuMap[$cleanSku];
    if (isset($skuMap[$noSpaceSku])) return $skuMap[$noSpaceSku];

    $sizes = parseSizeTokensFixed($varText . ' ' . $rawSku);

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

// Verify SK08-L rows
$sk08l_detail = [];
$sk04xxl_detail = [];

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
        $v = resolveVariantSmart($part, $variantName, $skuMap);
        if ($v && $v->sku === 'SK08-L') {
            $sk08l_detail[] = ['row' => $idx+1, 'rawSku' => $rawSku, 'variant' => $variantName, 'qty' => $qty];
        }
        if ($v && $v->sku === 'SK04-XXL') {
            $sk04xxl_detail[] = ['row' => $idx+1, 'rawSku' => $rawSku, 'variant' => $variantName, 'qty' => $qty];
        }
    }
}

echo "=== SK08-L Breakdown ===\n";
echo "Total Rows: " . count($sk08l_detail) . " | Total Qty: " . array_sum(array_column($sk08l_detail, 'qty')) . " pcs\n";

echo "\n=== SK04-XXL Breakdown ===\n";
echo "Total Rows: " . count($sk04xxl_detail) . " | Total Qty: " . array_sum(array_column($sk04xxl_detail, 'qty')) . " pcs\n";
