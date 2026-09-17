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

$dbVariants = ProductVariant::with(['product.seller', 'stocks'])->get();
$skuMap = [];
foreach ($dbVariants as $v) {
    $skuClean = strtoupper(trim($v->sku));
    $skuMap[$skuClean] = $v;
    $noSpaceSku = str_replace(' ', '', $skuClean);
    if (!isset($skuMap[$noSpaceSku])) {
        $skuMap[$noSpaceSku] = $v;
    }
}

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

function resolveVariantSmart($rawSku, $varText, $skuMap) {
    $cleanSku = strtoupper(trim($rawSku));
    $noSpaceSku = str_replace(' ', '', $cleanSku);
    if (!$cleanSku) return null;

    if (isset($skuMap[$cleanSku])) return $skuMap[$cleanSku];
    if (isset($skuMap[$noSpaceSku])) return $skuMap[$noSpaceSku];

    $sizes = parseSizeTokens($varText . ' ' . $rawSku);

    foreach ($sizes as $size) {
        if (isset($skuMap[$cleanSku . '-' . $size])) return $skuMap[$cleanSku . '-' . $size];
        if (isset($skuMap[$noSpaceSku . '-' . $size])) return $skuMap[$noSpaceSku . '-' . $size];
        if (isset($skuMap[$cleanSku . $size])) return $skuMap[$cleanSku . $size];
        if (isset($skuMap[$noSpaceSku . $size])) return $skuMap[$noSpaceSku . $size];
        if (isset($skuMap[$cleanSku . '-' . $size . '-1'])) return $skuMap[$cleanSku . '-' . $size . '-1'];
        if (isset($skuMap[$noSpaceSku . '-' . $size . '-1'])) return $skuMap[$noSpaceSku . '-' . $size . '-1'];

        $prefix1 = $cleanSku . '-';
        $prefix2 = $noSpaceSku . '-';
        foreach ($skuMap as $dbSku => $variant) {
            $vSize = strtoupper($variant->size);
            if ((str_starts_with($dbSku, $prefix1) || str_starts_with($dbSku, $prefix2)) && 
                ($vSize === $size || ($size === 'XXL' && $vSize === '2XL') || ($size === '2XL' && $vSize === 'XXL'))) {
                return $variant;
            }
        }
    }

    return null;
}

echo "=== DETAILED ROW-BY-ROW ANALYSIS ===\n";
foreach ($rows as $idx => $r) {
    $rawSku = trim((string)($r['Nomor Referensi SKU'] ?? $r['Seller SKU'] ?? $r['sku'] ?? ''));
    $varName = trim((string)($r['Nama Variasi'] ?? $r['Variation'] ?? ''));
    $rowQty = intval($r['Jumlah'] ?? $r['Quantity'] ?? $r['Qty'] ?? 1);
    
    $parts = preg_split('/[\+,\,]+/', $rawSku);
    if (count($parts) === 1 && preg_match('/^([A-Z0-9\s]+)-([A-Z0-9\s]+)/i', $rawSku)) {
        $parts = explode('-', $rawSku);
    }
    $parts = array_filter(array_map('trim', $parts));

    $hpps = [];
    foreach ($parts as $part) {
        $v = resolveVariantSmart($part, $varName, $skuMap);
        if ($v) {
            $hpps[] = "{$v->sku}: " . number_format($v->price, 0, ',', '.');
        }
    }

    if ($rowQty > 1 || count($parts) !== 3) {
        echo "Row [$idx]: Qty=$rowQty | SKU: '$rawSku' | Parts count: " . count($parts) . " (" . implode(', ', $hpps) . ")\n";
    }
}
