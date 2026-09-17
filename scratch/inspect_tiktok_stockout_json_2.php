<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProductVariant;

$jsonPath = 'C:\\Users\\Rizky\\Downloads\\CD_Tiktok_BABAN 01 - 07 JUNI 2026.json';
$raw = file_get_contents($jsonPath);
$json = json_decode($raw, true);

$rows = [];
if (isset($json['data']) && is_array($json['data'])) {
    $rows = $json['data'];
} elseif (is_array($json)) {
    $rows = $json;
}

echo "Total rows extracted: " . count($rows) . "\n";

if (count($rows) > 0) {
    echo "First row keys: " . implode(', ', array_keys($rows[0])) . "\n";
    echo "First 5 rows sample:\n";
    foreach (array_slice($rows, 0, 5) as $r) {
        print_r($r);
    }
}
