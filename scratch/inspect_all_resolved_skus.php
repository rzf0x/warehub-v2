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

$allResolved = [];
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

    if (count($parts) > 1) {
        foreach ($parts as $part) {
            $v = resolveVariantSmart($part, $variantName, $skuMap);
            if ($v) {
                $sku = $v->sku;
                if (!isset($allResolved[$sku])) $allResolved[$sku] = 0;
                $allResolved[$sku] += $qty;
            }
        }
    } else {
        $v = resolveVariantSmart($rawSku, $variantName, $skuMap);
        if ($v) {
            $sku = $v->sku;
            if (!isset($allResolved[$sku])) $allResolved[$sku] = 0;
            $allResolved[$sku] += $qty;
        }
    }
}

echo "=== ALL RESOLVED SKU QUANTITIES IN HENDRY.JSON ===\n";
arsort($allResolved);
foreach ($allResolved as $sku => $qty) {
    echo "{$sku}: {$qty} pcs\n";
}
