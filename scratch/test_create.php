<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Superadmin\StockOutController;
use Illuminate\Http\Request;

try {
    $controller = new StockOutController();
    $response = $controller->create(new Request());
    echo "Create status: Success\n";
    echo "Component: " . $response->toResponse(request())->getData()->component . "\n";
    echo "Props keys: " . implode(', ', array_keys((array)$response->toResponse(request())->getData()->props)) . "\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
