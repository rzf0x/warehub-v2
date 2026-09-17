<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProductVariant;

$jsonPath = 'C:\\Users\\Rizky\\Downloads\\CD_Tiktok_BABAN 01 - 07 JUNI 2026.json';
$raw = file_get_contents($jsonPath);
$json = json_decode($raw, true);

// Extract items list whether root is object or array
$rawItems = [];
if (isset($json['data']) && is_array($json['data'])) {
    $rawItems = $json['data'];
} elseif (isset($json['items']) && is_array($json['items'])) {
    $rawItems = $json['items'];
} elseif (is_array($json)) {
    $rawItems = $json;
}

echo "Extracted " . count($rawItems) . " raw items.\n";

$variants = ProductVariant::with(['product.seller', 'stocks'])->get();
$skuMap = [];
foreach ($variants as $v) {
    $skuMap[strtoupper(trim($v->sku))] = $v;
}

function parseSizeFromVariasiText($text) {
    // Look for standard size token: 5XL, 4XL, 3XL, 2XL, XXL, XL, L, M, S, XS
    // Prioritize 5XL..2XL over single L/M/S if there are curly braces e.g. {XL 7-8Tahun
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

    // 1. Direct match
    if (isset($skuMap[$cleanSku])) {
        return $skuMap[$cleanSku];
    }

    // 2. Dash size
    if ($size && isset($skuMap[$cleanSku . '-' . $size])) {
        return $skuMap[$cleanSku . '-' . $size];
    }

    // 3. No dash size
    if ($size && isset($skuMap[$cleanSku . $size])) {
        return $skuMap[$cleanSku . $size];
    }

    // 4. Dash size with -1 suffix e.g. BKAJ01-XL-1
    if ($size && isset($skuMap[$cleanSku . '-' . $size . '-1'])) {
        return $skuMap[$cleanSku . '-' . $size . '-1'];
    }

    // 5. Prefix match e.g. cleanSku is BKA42, find any variant starting with BKA42-
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

$matchedItems = [];
$missingSkus = [];

foreach ($rawItems as $item) {
    $rawSku = trim((string)($item['Nomor Referensi SKU'] ?? $item['Seller SKU'] ?? $item['SKU Induk'] ?? $item['sku'] ?? ''));
    $variantName = trim((string)($item['Nama Variasi'] ?? $item['Variation'] ?? ''));
    $productName = trim((string)($item['Nama Produk'] ?? $item['Product Name'] ?? ''));
    $qty = intval($item['Jumlah'] ?? $item['Quantity'] ?? $item['Qty'] ?? 1);

    if (!$rawSku) continue;

    $size = parseSizeFromVariasiText($variantName);

    // Try direct resolve on full SKU string first
    $directV = resolveVariantFromSkuAndSize($rawSku, $size, $skuMap);
    if ($directV) {
        $matchedItems[] = [
            'product_variant_id' => $directV->id,
            'sku' => $directV->sku,
            'size' => $directV->size,
            'qty' => $qty,
        ];
        continue;
    }

    // Split bundle SKUs by - or + or , or space
    $parts = preg_split('/[\-+\,\s]+/', $rawSku);
    $subMatched = [];
    $hasMissing = false;

    foreach ($parts as $part) {
        $pClean = trim($part);
        if (!$pClean) continue;

        $partV = resolveVariantFromSkuAndSize($pClean, $size, $skuMap);
        if ($partV) {
            $subMatched[] = [
                'product_variant_id' => $partV->id,
                'sku' => $partV->sku,
                'size' => $partV->size,
                'qty' => $qty,
            ];
        } else {
            $hasMissing = true;
            $missingSkus[$pClean] = [
                'sku' => $pClean,
                'product_name' => $productName,
                'variation' => $variantName,
            ];
        }
    }

    if (!$hasMissing && count($subMatched) > 0) {
        foreach ($subMatched as $sm) {
            $matchedItems[] = $sm;
        }
    }
}

echo "Total Matched Items: " . count($matchedItems) . "\n";
echo "Total Missing SKUs: " . count($missingSkus) . "\n";
if (count($missingSkus) > 0) {
    echo "Missing SKUs:\n";
    print_r(array_keys($missingSkus));
}
