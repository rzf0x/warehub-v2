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

$skuMap = [];
ProductVariant::with(['product.seller'])->get()->each(function ($v) use (&$skuMap) {
    $cleanSku = strtoupper(trim($v->sku));
    $skuMap[$cleanSku] = $v;
});

function parseSizeTokensBugged($text) {
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

function resolveVariantBugged($sku, $varName, $skuMap) {
    $cleanSku = strtoupper(trim($sku));
    if (isset($skuMap[$cleanSku])) return $skuMap[$cleanSku];
    $sizes = parseSizeTokensBugged($varName);
    foreach ($sizes as $size) {
        if (isset($skuMap[$cleanSku . '-' . $size])) return $skuMap[$cleanSku . '-' . $size];
    }
    return null;
}

$buggedSK08L_count = 0;
foreach ($rows as $item) {
    $rawSku = trim((string)($item['Nomor Referensi SKU'] ?? $item['Seller SKU'] ?? $item['SKU Induk'] ?? $item['sku'] ?? ''));
    $variantName = trim((string)($item['Nama Variasi'] ?? $item['Variation'] ?? ''));
    $qty = intval($item['Jumlah'] ?? $item['Quantity'] ?? $item['Qty'] ?? 1);

    if (!$rawSku) continue;
    $parts = preg_split('/[\+,\,]+/', $rawSku);
    if (count($parts) === 1 && preg_match('/^([A-Z0-9\s]+)-([A-Z0-9\s]+)/i', $rawSku)) {
        $parts = explode('-', $rawSku);
    }
    $parts = array_filter(array_map('trim', $parts));

    foreach ($parts as $part) {
        $v = resolveVariantBugged($part, $variantName, $skuMap);
        if ($v && $v->sku === 'SK08-L') {
            $buggedSK08L_count += $qty;
        }
    }
}

echo "Bugged SK08-L Qty Result: " . $buggedSK08L_count . " pcs\n";
