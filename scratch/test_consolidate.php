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
$rawData = $json['data'] ?? (is_array($json) ? $json : []);

$warehouse = Warehouse::first();
$skuMap = [];
ProductVariant::with('product.seller')->get()->each(function ($v) use (&$skuMap) {
    $cleanSku = strtoupper(str_replace(' ', '', $v->sku));
    $skuMap[$cleanSku] = $v;
});

// Simulate importChunked matching
$matchedItems = [];

foreach ($rawData as $item) {
    $rawSku = trim((string)($item['Nomor Referensi SKU'] ?? $item['Seller SKU'] ?? $item['SKU Induk'] ?? $item['sku'] ?? ''));
    $variantName = trim((string)($item['Nama Variasi'] ?? $item['Variation'] ?? ''));
    $productName = trim((string)($item['Nama Produk'] ?? $item['Product Name'] ?? ''));
    $qty = intval($item['Jumlah'] ?? $item['Quantity'] ?? $item['Qty'] ?? 1);
    $paymentAmt = floatval(str_replace(['Rp', '.', ' '], '', (string)($item['Total Pembayaran'] ?? $item['Subtotal Pesanan'] ?? 0)));

    if (!$rawSku) continue;

    $parts = preg_split('/[\+,\,]+/', $rawSku);
    if (count($parts) === 1 && preg_match('/^([A-Z0-9\s]+)-([A-Z0-9\s]+)/i', $rawSku)) {
        $parts = explode('-', $rawSku);
    }
    $parts = array_filter(array_map('trim', $parts));

    // Simple variant lookup helper
    $resolveV = function($sku, $varName) use ($skuMap) {
        $c = strtoupper(str_replace(' ', '', $sku));
        if (isset($skuMap[$c])) return $skuMap[$c];
        // try size token
        preg_match_all('/\b(S|M|L|XL|XXL|2XL|3XL|4XL|XXXL|XXXXL|2-3T|3-4T|4-5T|5-6T|7-8T|9-10T|11-12T|\d{1,2})\b/i', $varName, $m);
        $sizes = $m[0] ?? [];
        foreach ($sizes as $sz) {
            $szClean = strtoupper($sz);
            if ($szClean === '2XL') $szClean = 'XXL';
            if ($szClean === '3XL') $szClean = 'XXXL';
            if ($szClean === '4XL') $szClean = 'XXXXL';
            $comb = $c . '-' . $szClean;
            if (isset($skuMap[$comb])) return $skuMap[$comb];
        }
        return null;
    };

    if (count($parts) > 1) {
        foreach ($parts as $part) {
            $v = $resolveV($part, $variantName);
            if ($v) {
                $matchedItems[] = [
                    'product_variant_id' => $v->id,
                    'sku' => $v->sku,
                    'qty' => $qty,
                    'price' => (float)$v->price,
                    'selling_price' => $paymentAmt > 0 ? round(($paymentAmt / max(1, $qty)) / max(1, count($parts))) : (float)$v->price,
                    'seller_id' => $v->product->seller_id ?? null,
                ];
            }
        }
    } else {
        $v = $resolveV($rawSku, $variantName);
        if ($v) {
            $matchedItems[] = [
                'product_variant_id' => $v->id,
                'sku' => $v->sku,
                'qty' => $qty,
                'price' => (float)$v->price,
                'selling_price' => $paymentAmt > 0 ? round($paymentAmt / max(1, $qty)) : (float)$v->price,
                'seller_id' => $v->product->seller_id ?? null,
            ];
        }
    }
}

echo "Total Raw Matched Rows: " . count($matchedItems) . "\n";
$totalQtyRaw = array_sum(array_column($matchedItems, 'qty'));
echo "Total Raw Qty: " . $totalQtyRaw . " pcs\n";

// Consolidated
$consolidated = [];
foreach ($matchedItems as $m) {
    $key = $m['product_variant_id'] . '_' . ($m['seller_id'] ?? 0);
    if (!isset($consolidated[$key])) {
        $consolidated[$key] = [
            'product_variant_id' => $m['product_variant_id'],
            'sku' => $m['sku'],
            'qty' => 0,
            'price' => $m['price'],
            'total_selling' => 0,
            'seller_id' => $m['seller_id'],
        ];
    }
    $consolidated[$key]['qty'] += $m['qty'];
    $consolidated[$key]['total_selling'] += ($m['selling_price'] * $m['qty']);
}

foreach ($consolidated as &$c) {
    $c['selling_price'] = round($c['total_selling'] / $c['qty']);
}
unset($c);

echo "Total Consolidated Rows: " . count($consolidated) . "\n";
$totalQtyConsolidated = array_sum(array_column($consolidated, 'qty'));
echo "Total Consolidated Qty: " . $totalQtyConsolidated . " pcs\n";

$totalSellingRaw = 0;
foreach ($matchedItems as $m) {
    $totalSellingRaw += $m['selling_price'] * $m['qty'];
}
$totalSellingCons = 0;
foreach ($consolidated as $c) {
    $totalSellingCons += $c['selling_price'] * $c['qty'];
}

echo "Total Selling Price (Raw): Rp " . number_format($totalSellingRaw, 0, ',', '.') . "\n";
echo "Total Selling Price (Consolidate): Rp " . number_format($totalSellingCons, 0, ',', '.') . "\n";
