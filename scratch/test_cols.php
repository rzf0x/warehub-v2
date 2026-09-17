<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;

echo "product_variants columns: " . implode(', ', Schema::getColumnListing('product_variants')) . "\n";
echo "products columns: " . implode(', ', Schema::getColumnListing('products')) . "\n";
