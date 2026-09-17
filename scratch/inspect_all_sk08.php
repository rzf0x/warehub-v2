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
ProductVariant::with('product.seller')->get()->each(function ($v) use (&$skuMap) {
    $cleanSku = strtoupper(str_replace(' ', '', $v->sku));
    $skuMap[$cleanSku] = $v;
});

// Helper size token parser exactly like StockOutController
function parseSizeTokens($text) {
    $sizes = [];
    if (preg_match_all('/\b(5XL|4XL|3XL|2XL|XXL|XL|L|M|S|XS|\d{1-[0-9]}T|\d{1,2}T|\d{1,2}\s*TAHUN)\b/i', $text, $m)) {
        foreach ($m[0] as $s) {
            $sz = strtoupper(trim($s));
            if ($sz === '2XL') $sz = 'XXL';
            if ($sz === '3XL') $sz = 'XXXL';
            if ($sz === '4XL') $sz = 'XXXXL';
            $sizes[] = $sz;
        }
    }
    return array_unique($sizes);
}

$summary = [];

foreach ($rows as $idx => $item) {
    $rawSku = trim((string)($item['Nomor Referensi SKU'] ?? $item['Seller SKU'] ?? $item['SKU Induk'] ?? $item['sku'] ?? ''));
    $variantName = trim((string)($item['Nama Variasi'] ?? $item['Variation'] ?? ''));
    $productName = trim((string)($item['Nama Produk'] ?? $item['Product Name'] ?? ''));
    $qty = intval($item['Jumlah'] ?? $item['Quantity'] ?? $item['Qty'] ?? 1);

    if (strpos(strtoupper($rawSku), 'SK08') === false && strpos(strtoupper($rawSku), 'SK-08') === false) {
        continue;
    }

    $parts = preg_split('/[\+,\,]+/', $rawSku);
    if (count($parts) === 1 && preg_match('/^([A-Z0-9\s]+)-([A-Z0-9\s]+)/i', $rawSku)) {
        $parts = explode('-', $rawSku);
    }
    $parts = array_filter(array_map('trim', $parts));

    foreach ($parts as $part) {
        $c = strtoupper(str_replace(' ', '', $part));
        if ($c === 'SK08' || $c === 'SK-08' || strpos($c, 'SK08') !== false) {
            // Find resolved variant
            $sizeTokens = [];
            // Extract size from variantName
            // In bundle like "PAKET L, 2XL USIA 9-12 TAHUN", PAKET L is Paket 3 Pcs (SK07-SK08-SK06) and size is 2XL!
            // Let's check size tokens
            if (preg_match('/\b(5XL|4XL|3XL|2XL|XXL|XL|L|M|S|XS)\b/i', $variantName, $m)) {
                $sz = strtoupper($m[1]);
                if ($sz === '2XL') $sz = 'XXL';
                if ($sz === '3XL') $sz = 'XXXL';
                if ($sz === '4XL') $sz = 'XXXXL';
                $sizeTokens[] = $sz;
            }

            $szStr = implode(',', $sizeTokens) ?: 'Unknown';
            $key = "SK08 (" . $szStr . ")";
            if (!isset($summary[$key])) {
                $summary[$key] = [
                    'count' => 0,
                    'qty' => 0,
                    'samples' => []
                ];
            }
            $summary[$key]['count']++;
            $summary[$key]['qty'] += $qty;
            $summary[$key]['samples'][] = [
                'rawSku' => $rawSku,
                'variantName' => $variantName,
                'qty' => $qty
            ];
        }
    }
}

echo "=== ALL SK08 IN CD_Tiktok_HENDRY.json ===\n";
print_r($summary);
