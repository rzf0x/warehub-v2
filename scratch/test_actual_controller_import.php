<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Superadmin\StockOutController;
use Illuminate\Http\Request;

$jsonPath = 'C:\\Users\\Rizky\\Downloads\\CD_Tiktok_HENDRY .json';
$raw = file_get_contents($jsonPath);
$json = json_decode($raw, true);
$rows = $json['data'] ?? (is_array($json) ? $json : []);

$controller = new StockOutController();

$req = new Request();
$req->replace([
    'warehouse_id' => 1,
    'items' => $rows,
]);

$response = $controller->importChunked($req);
$resData = json_decode($response->getContent(), true);

$matched = $resData['matched_items'] ?? [];

$skuQty = [];
foreach ($matched as $m) {
    $sku = $m['sku'];
    if (!isset($skuQty[$sku])) $skuQty[$sku] = 0;
    $skuQty[$sku] += $m['qty'];
}

arsort($skuQty);

echo "=== ACTUAL CONTROLLER IMPORTCHUNKED OUTPUT ===\n";
foreach ($skuQty as $sku => $qty) {
    echo "{$sku}: {$qty} pcs\n";
}
