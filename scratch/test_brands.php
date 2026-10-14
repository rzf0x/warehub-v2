<?php
$testCases = [
    'BKA 44,47,50,60',
    '3SECOND 01, 02, 03',
    'SK-01, 02, 03',
    'POLO_10/12/14',
    'BABAN 1 + 2 + 3',
    'DISTRO A, B, C',
    'SINGLE_SKU_123',
];

foreach ($testCases as $rawSku) {
    echo "Testing: '$rawSku'\n";
    $parts = preg_split('/[\+,\,\/\&]+/', $rawSku);
    if (count($parts) === 1 && preg_match('/^([A-Z0-9\s]+)-([A-Z0-9\s]+)/i', $rawSku)) {
        $parts = explode('-', $rawSku);
    }
    $parts = array_filter(array_map('trim', $parts));

    if (count($parts) > 1) {
        $firstPart = reset($parts);
        $prefix = '';
        if (preg_match('/^(.*?\D+)\d+$/', $firstPart, $pm)) {
            $prefix = $pm[1];
        } elseif (preg_match('/^([A-Za-z0-9\-_]+[\s\-_]+)/', $firstPart, $pm)) {
            $prefix = $pm[1];
        }
        echo "  Parts: " . json_encode($parts) . "\n";
        echo "  Detected Prefix: '$prefix'\n";
        foreach ($parts as $idx => $p) {
            $inherited = ($idx > 0 && $prefix && !preg_match('/^[A-Za-z]/', $p)) ? trim($prefix . ' ' . $p) : $p;
            echo "    Part $idx: '$p' => Resolved as: '$inherited'\n";
        }
    } else {
        echo "  Single SKU: " . reset($parts) . "\n";
    }
    echo "----------------------------------------\n";
}
