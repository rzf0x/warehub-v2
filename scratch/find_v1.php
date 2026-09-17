<?php

$v1Paths = [
    'd:\\Warehouse & AA Gym Konveksi\\System\\V1',
    'd:\\Warehouse & AA Gym Konveksi\\System\\warehub',
    'c:\\laragon\\www\\warehub',
    'd:\\laragon\\www\\warehub',
];

foreach ($v1Paths as $path) {
    if (file_exists($path)) {
        echo "Found V1 path: $path\n";
    }
}
