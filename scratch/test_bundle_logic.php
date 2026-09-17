<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProductVariant;

$dbVariants = ProductVariant::with('product')->get();
$skuMap = [];
foreach ($dbVariants as $v) {
    $skuMap[strtoupper(trim($v->sku))] = $v;
}

function parseSizeFromVariasiText($text) {
    if (preg_match('/\{?\s*(5XL|4XL|3XL|2XL|XXL|XL|L|M|S|XS)\b/i', $text, $m)) {
        return strtoupper($m[1]);
    }
    if (preg_match('/\b(5XL|4XL|3XL|2XL|XXL|XL|L|M|S|XS)\b/i', $text, $m)) {
        return strtoupper($m[1]);
    }
    return '';
}

function resolveVariantFromSkuAndSize($rawSku, $size, $skuMap) {
    $cleanSku = strtoupper(trim($rawSku));
    if (!$cleanSku) return null;

    if (isset($skuMap[$cleanSku])) {
        return $skuMap[$cleanSku];
    }
    if ($size && isset($skuMap[$cleanSku . '-' . $size])) {
        return $skuMap[$cleanSku . '-' . $size];
    }
    if ($size && isset($skuMap[$cleanSku . $size])) {
        return $skuMap[$cleanSku . $size];
    }
    if ($size && isset($skuMap[$cleanSku . '-' . $size . '-1'])) {
        return $skuMap[$cleanSku . '-' . $size . '-1'];
    }
    if ($size) {
        $prefix = $cleanSku . '-';
        foreach ($skuMap as $dbSku => $variant) {
            if (str_starts_with($dbSku, $prefix) && strtoupper($variant->size) === $size) {
                return $variant;
            }
        }
    }
    return null;
}

$rawSku = "SK04-SK03-SK02";
$variantName = "Warna Kombinasi, L";
$size = parseSizeFromVariasiText($variantName);

echo "Testing resolution for '$rawSku' (Size: '$size'):\n";

// Check if rawSku is a bundle (contains -, +, , or space)
$parts = preg_split('/[\-+\,\s]+/', $rawSku);
$resolvedComponents = [];
$allPartsMatched = true;

if (count($parts) > 1) {
    foreach ($parts as $p) {
        $pClean = trim($p);
        if (!$pClean) continue;
        $v = resolveVariantFromSkuAndSize($pClean, $size, $skuMap);
        if ($v) {
            $resolvedComponents[] = $v;
        } else {
            $allPartsMatched = false;
        }
    }
}

if (count($parts) > 1 && $allPartsMatched && count($resolvedComponents) === count($parts)) {
    echo "SUCCESS: Bundle '$rawSku' resolved to " . count($resolvedComponents) . " individual products:\n";
    foreach ($resolvedComponents as $v) {
        echo "  - Product: {$v->product->product_name} | SKU: {$v->sku}\n";
    }
} else {
    echo "Single product or failed bundle split\n";
}
