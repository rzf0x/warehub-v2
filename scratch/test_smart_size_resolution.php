<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProductVariant;

$jsonPath = 'C:\\Users\\Rizky\\Downloads\\CD_Tiktok_BABAN 01 - 07 JUNI 2026.json';
$raw = file_get_contents($jsonPath);
$json = json_decode($raw, true);
$rows = $json['data'] ?? (is_array($json) ? $json : []);

$dbVariants = ProductVariant::with('product')->get();
$dbSkuMap = [];
foreach ($dbVariants as $v) {
    $skuClean = strtoupper(trim($v->sku));
    $dbSkuMap[$skuClean] = $v;
}

function parseSizeFromVariasi($variasi) {
    // Check pattern like {XL 7-8Tahun or XL:11-12Tahun or XL 7-8Tahun or {M 3-4Tahun
    if (preg_match('/(?:\{|\b)(5XL|4XL|3XL|XXL|XL|L|M|S|XS|2XL)\b/i', $variasi, $m)) {
        return strtoupper($m[1]);
    }
    return '';
}

echo "Testing Smart Size Resolution Engine on " . count($rows) . " rows:\n\n";

$matchedCount = 0;
$totalComponents = 0;

foreach ($rows as $idx => $r) {
    $skuStr = trim($r['Nomor Referensi SKU'] ?? '');
    $variasi = trim($r['Nama Variasi'] ?? '');
    $size = parseSizeFromVariasi($variasi);

    echo "Row [$idx] SKU Str: '$skuStr' | Variasi: '$variasi' => Size: '$size'\n";

    // Split SKU by - or + or space
    $parts = preg_split('/[\-+\,\s]+/', strtoupper($skuStr));
    foreach ($parts as $p) {
        $pClean = trim($p);
        if ($pClean === '') continue;
        $totalComponents++;

        // 1. Direct match
        if (isset($dbSkuMap[$pClean])) {
            echo "   -> Component '$pClean': DIRECT MATCH ({$dbSkuMap[$pClean]->sku})\n";
            $matchedCount++;
            continue;
        }

        // 2. Combine with size (e.g. BKA42 + XL => BKA42-XL)
        $withDash = $pClean . '-' . $size;
        if ($size && isset($dbSkuMap[$withDash])) {
            echo "   -> Component '$pClean': MATCH WITH DASH ({$dbSkuMap[$withDash]->sku})\n";
            $matchedCount++;
            continue;
        }

        // 3. Combine without dash (e.g. BKA42 + XL => BKA42XL)
        $noDash = $pClean . $size;
        if ($size && isset($dbSkuMap[$noDash])) {
            echo "   -> Component '$pClean': MATCH NO DASH ({$dbSkuMap[$noDash]->sku})\n";
            $matchedCount++;
            continue;
        }

        // 4. Check if pClean starts with BKA and has no size suffix, try adding size
        echo "   -> Component '$pClean' (size '$size'): NOT FOUND in DB ❌\n";
    }
    echo "---------------------------------------------------------\n";
}

echo "\nFINAL RESULT: $matchedCount / $totalComponents components matched!\n";
