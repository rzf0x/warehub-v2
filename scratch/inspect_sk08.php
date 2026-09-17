<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$jsonPath = 'C:\\Users\\Rizky\\Downloads\\CD_Tiktok_HENDRY .json';
$raw = file_get_contents($jsonPath);
$json = json_decode($raw, true);
$rows = $json['data'] ?? (is_array($json) ? $json : []);

echo "Total JSON order rows: " . count($rows) . "\n\n";

$sk08l_count = 0;
$sk08l_items = [];

foreach ($rows as $idx => $item) {
    $rawSku = trim((string)($item['Nomor Referensi SKU'] ?? $item['Seller SKU'] ?? $item['SKU Induk'] ?? $item['sku'] ?? ''));
    $variantName = trim((string)($item['Nama Variasi'] ?? $item['Variation'] ?? ''));
    $productName = trim((string)($item['Nama Produk'] ?? $item['Product Name'] ?? ''));
    $qty = intval($item['Jumlah'] ?? $item['Quantity'] ?? $item['Qty'] ?? 1);
    $resi = trim((string)($item['No. Resi'] ?? $item['Tracking ID'] ?? $item['resi'] ?? ''));

    // Check single or bundle SKU
    $parts = preg_split('/[\+,\,]+/', $rawSku);
    if (count($parts) === 1 && preg_match('/^([A-Z0-9\s]+)-([A-Z0-9\s]+)/i', $rawSku)) {
        $parts = explode('-', $rawSku);
    }
    $parts = array_filter(array_map('trim', $parts));

    $hasSk08L = false;
    foreach ($parts as $part) {
        $cPart = strtoupper(str_replace(' ', '', $part));
        // Check if SK08 and variant contains L
        if ($cPart === 'SK08' || $cPart === 'SK08-L' || strpos($rawSku, 'SK08') !== false || strpos($rawSku, 'SK-08') !== false) {
            // check size
            if (preg_match('/\bL\b/i', $variantName) || strpos(strtoupper($rawSku), 'SK08-L') !== false || strpos(strtoupper($rawSku), 'SK-08-L') !== false || preg_match('/\bL\b/i', $rawSku)) {
                $hasSk08L = true;
            }
        }
    }

    if ($hasSk08L) {
        $sk08l_count += $qty;
        $sk08l_items[] = [
            'row' => $idx + 1,
            'resi' => $resi,
            'sku' => $rawSku,
            'variant' => $variantName,
            'product' => $productName,
            'qty' => $qty,
            'parts' => implode(', ', $parts),
        ];
    }
}

echo "=== SK08 Size L Analysis ===\n";
echo "Total Rows Matched: " . count($sk08l_items) . "\n";
echo "Total Qty (pcs): " . $sk08l_count . "\n\n";

foreach ($sk08l_items as $i) {
    echo "Row {$i['row']}: Qty={$i['qty']} | SKU: {$i['sku']} | Variant: {$i['variant']} | Resi: {$i['resi']}\n";
}
