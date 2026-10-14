<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CleaningDataShopeeController extends Controller
{
    public function index()
    {
        return Inertia::render('Superadmin/Settings/CleaningDataShopee');
    }

    public function process(Request $request)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(120);

        $request->validate([
            'master_json' => 'nullable',
            'raw_jsons' => 'nullable|array',
            'gmv_ads' => 'nullable',
        ]);

        $masterRaw = $request->input('master_json');
        if (is_string($masterRaw) && trim($masterRaw) !== '') {
            $decodedMaster = json_decode($masterRaw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Master JSON tidak valid: ' . json_last_error_msg()
                ], 422);
            }
            $masterRaw = $decodedMaster;
        }

        $rawInputs = $request->input('raw_jsons', []);
        foreach ($rawInputs as $idx => $rawInput) {
            if (is_string($rawInput) && trim($rawInput) !== '') {
                json_decode($rawInput, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Raw JSON tidak valid (File #' . ($idx + 1) . '): ' . json_last_error_msg()
                    ], 422);
                }
            }
        }

        // 1. Ekstraksi Bruto dari Summary Master Data & GMV Ads
        $bruto = $this->extractBruto($masterRaw);
        $gmvAds = $this->parseNum($request->input('gmv_ads', 0));

        // 2. Unwrap & Process Master Data
        $masterRows = $this->unwrapDataArray($masterRaw, ['Income', 'Penghasilan', 'orders', 'data', 'items', 'rows']);
        
        $masterMapping = [];
        $totalMasterGrossAmount = 0;

        foreach ($masterRows as $row) {
            if (!is_array($row)) continue;

            // STRICT: Hanya proses baris bertipe ORDER jika ada Column2 (format Shopee Penghasilan)
            if (isset($row['Column2']) && strtoupper(trim((string)$row['Column2'])) !== 'ORDER') {
                continue;
            }
            
            $orderId = $this->extractMasterOrderId($row);
            if (!$orderId) continue;

            $amt = $this->extractMasterAmount($row);

            if (!isset($masterMapping[$orderId])) {
                $masterMapping[$orderId] = 0;
            }
            $masterMapping[$orderId] += $amt;
            $totalMasterGrossAmount += $amt;
        }

        $totalOrderIdsMaster = count($masterMapping);
        $netPencairan = max(0, $totalMasterGrossAmount - $gmvAds);

        // Skala nominal master jika ada potongan GMV Ads/Iklan
        $scaledMasterMapping = [];
        if ($gmvAds > 0 && $totalMasterGrossAmount > 0) {
            $ratio = $netPencairan / $totalMasterGrossAmount;
            $runningScaled = 0;
            $oids = array_keys($masterMapping);
            $totalCount = count($oids);
            foreach ($oids as $idx => $oid) {
                if ($idx === $totalCount - 1) {
                    $sAlloc = $netPencairan - $runningScaled;
                } else {
                    $sAlloc = (int)round($masterMapping[$oid] * $ratio);
                }
                $scaledMasterMapping[$oid] = $sAlloc;
                $runningScaled += $sAlloc;
            }
        } else {
            $scaledMasterMapping = $masterMapping;
        }

        // 3. Process Raw Shopee Data
        $dedupSet = [];
        $orderRawItemsMap = [];

        foreach ($rawInputs as $rawInput) {
            $rawPayload = is_string($rawInput) ? (json_decode($rawInput, true) ?? []) : $rawInput;
            $items = $this->unwrapDataArray($rawPayload, ['data', 'orders', 'items', 'rows']);
            if (!is_array($items)) continue;

            foreach ($items as $item) {
                if (!is_array($item)) continue;

                $orderId = strtoupper(trim((string)($item['No. Pesanan'] ?? $item['Order ID'] ?? $item['order_id'] ?? '')));
                if (!$orderId) continue;

                $productName = trim((string)($item['Nama Produk'] ?? $item['product_name'] ?? ''));
                $variantName = trim((string)($item['Nama Variasi'] ?? $item['variant_name'] ?? ''));

                // De-duplikasi Baris Mentah: No. Pesanan + '||' + Nama Produk + '||' + Nama Variasi
                $dedupKey = $orderId . '||' . $productName . '||' . $variantName;
                if (isset($dedupSet[$dedupKey])) continue;
                $dedupSet[$dedupKey] = true;

                // Cek jika Order ID ditemukan di scaledMasterMapping
                if (!isset($scaledMasterMapping[$orderId])) continue;

                if (!isset($orderRawItemsMap[$orderId])) {
                    $orderRawItemsMap[$orderId] = [];
                }
                $orderRawItemsMap[$orderId][] = $item;
            }
        }

        $matchedOrdersMap = [];
        $matchedDataItems = [];
        $skuBreakdownMap = [];

        $totalPembayaranKalkulasi = 0;
        $totalQtyProductDitemukan = 0;
        $totalQtySatuan = 0;

        foreach ($orderRawItemsMap as $orderId => $rawItems) {
            $masterAmt = $scaledMasterMapping[$orderId] ?? 0;
            $itemCount = count($rawItems);

            // Hitung total raw payment per item untuk rasio pembagian
            $rawPaySums = 0;
            $rawPays = [];
            foreach ($rawItems as $idx => $rawItem) {
                $rawP = $this->parseNum($rawItem['Total Pembayaran'] ?? $rawItem['Subtotal Pesanan'] ?? 0);
                $rawPays[$idx] = $rawP;
                $rawPaySums += max(0, $rawP);
            }

            // Alokasikan nominal master secara proporsional ke tiap baris item
            $allocatedPays = [];
            if ($masterAmt > 0) {
                $runningAlloc = 0;
                for ($i = 0; $i < $itemCount; $i++) {
                    if ($i === $itemCount - 1) {
                        $alloc = $masterAmt - $runningAlloc;
                    } else {
                        if ($rawPaySums > 0) {
                            $ratio = max(0, $rawPays[$i]) / $rawPaySums;
                            $alloc = (int)round($masterAmt * $ratio);
                        } else {
                            $alloc = (int)round($masterAmt / $itemCount);
                        }
                    }
                    $allocatedPays[$i] = $alloc;
                    $runningAlloc += $alloc;
                }
            } else {
                foreach ($rawItems as $idx => $rawItem) {
                    $allocatedPays[$idx] = $rawPays[$idx];
                }
            }

            $orderMatchedAny = false;

            foreach ($rawItems as $idx => $item) {
                $itemPembayaran = $allocatedPays[$idx] ?? 0;

                // STRICT FILTERING: Abaikan baris dengan Total Pembayaran 0 atau minus
                if ($itemPembayaran <= 0) {
                    continue;
                }

                $orderMatchedAny = true;

                $productName = trim((string)($item['Nama Produk'] ?? $item['product_name'] ?? ''));
                $variantName = trim((string)($item['Nama Variasi'] ?? $item['variant_name'] ?? ''));
                $sku = trim((string)($item['Nomor Referensi SKU'] ?? $item['SKU Penjual'] ?? $item['SKU Induk'] ?? $item['sku'] ?? 'NO-SKU'));
                $resi = trim((string)($item['No. Resi'] ?? $item['Tracking No'] ?? $item['resi'] ?? '-'));
                $qty = max(1, intval($item['Jumlah'] ?? $item['Qty'] ?? $item['quantity'] ?? 1));

                $matchedDataItems[] = [
                    'No. Pesanan' => $orderId,
                    'No. Resi' => $resi,
                    'Nomor Referensi SKU' => $sku,
                    'Nama Variasi' => $variantName,
                    'Jumlah' => $qty,
                    'Total Pembayaran' => $itemPembayaran,
                    'Nama Produk' => $productName,
                ];

                $totalPembayaranKalkulasi += $itemPembayaran;
                $totalQtyProductDitemukan += $qty;

                // Pemecahan SKU Bundle & Unit Count
                $unitCount = $this->getUnitCount($sku, $variantName);
                $effectiveLineQuantity = $qty * $unitCount;
                $totalQtySatuan += $effectiveLineQuantity;

                // Accumulate SKU Breakdown
                $subCodes = $this->splitBundleSku($sku, $variantName);
                $size = $this->extractSize($variantName, $sku);

                foreach ($subCodes as $code) {
                    $bKey = strtoupper($code) . '||' . $size;
                    if (!isset($skuBreakdownMap[$bKey])) {
                        $skuBreakdownMap[$bKey] = [
                            'sku' => $code,
                            'size' => $size,
                            'total_qty' => 0,
                            'order_count' => 0,
                        ];
                    }
                    $skuBreakdownMap[$bKey]['total_qty'] += $qty;
                    $skuBreakdownMap[$bKey]['order_count'] += 1;
                }
            }

            if ($orderMatchedAny) {
                $matchedOrdersMap[$orderId] = $masterAmt;
            }
        }

        $totalPesananDitemukan = count($matchedOrdersMap);
        $matchedMasterAmount = array_sum($matchedOrdersMap);
        $totalDataCocok = count($matchedDataItems);
        $totalProdukDitemukan = $totalDataCocok;

        // Missing Order IDs
        $missingOrderIds = [];
        foreach ($scaledMasterMapping as $orderId => $amt) {
            if (!isset($matchedOrdersMap[$orderId])) {
                $missingOrderIds[] = [
                    'order_id' => $orderId,
                    'amount' => $amt,
                ];
            }
        }

        // Urutkan SKU Breakdown secara descending berdasarkan total_qty
        $skuBreakdown = array_values($skuBreakdownMap);
        usort($skuBreakdown, fn($a, $b) => $b['total_qty'] <=> $a['total_qty']);

        return response()->json([
            'success' => true,
            'export_payload' => [
                'total_order_ids_master' => $totalOrderIdsMaster,
                'total_master_amount' => round($netPencairan),
                'matched_master_amount' => round($matchedMasterAmount),
                'total_gmv_ads_amount' => round($gmvAds),
                'master_gross_amount' => round($totalMasterGrossAmount),
                'total_data_cocok' => $totalDataCocok,
                'total_pembayaran_kalkulasi' => round($totalPembayaranKalkulasi),
                'total_produk_ditemukan' => $totalProdukDitemukan,
                'total_qty_product_ditemukan' => $totalQtyProductDitemukan,
                'total_qty_satuan' => $totalQtySatuan,
                'total_pesanan_ditemukan' => $totalPesananDitemukan,
                'bruto' => round($bruto),
                'data' => $matchedDataItems,
            ],
            'sku_breakdown' => array_slice($skuBreakdown, 0, 150),
            'sku_breakdown_more' => max(0, count($skuBreakdown) - 150),
            'missing_order_ids' => $missingOrderIds,
        ]);
    }

    private function unwrapDataArray($payload, array $keys)
    {
        if (!is_array($payload)) return [];
        if (array_is_list($payload)) return $payload;

        foreach ($keys as $k) {
            if (isset($payload[$k]) && is_array($payload[$k])) {
                return $payload[$k];
            }
        }

        return $payload;
    }

    private function parseNum($val): float
    {
        if ($val === null || $val === '') return 0.0;
        if (is_numeric($val)) return (float)$val;
        
        $s = trim((string)$val);
        $s = preg_replace('/[Rp\s]/i', '', $s);
        if ($s === '') return 0.0;

        // Indonesian format: 150.000,50 -> 150000.50
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
        return is_numeric($s) ? (float)$s : 0.0;
    }

    private function extractMasterOrderId(array $row): ?string
    {
        $ORDER_ID_REGEX = '/^[A-Z0-9_\-]{8,25}$/i';
        $ignoreValues = ['NO. PESANAN', 'ORDER ID', 'ORDER_ID', 'NO PESANAN', 'NOMOR PESANAN', 'LIHAT BERDASARKAN', 'TOTAL PENGHASILAN', 'TOTAL_PENGHASILAN'];

        $priorityKeys = ['Dari', 'Column3', 'No. Pesanan', 'Order/Adjustment ID', 'order_id', 'Order ID'];
        foreach ($priorityKeys as $k) {
            if (isset($row[$k])) {
                $val = trim((string)$row[$k]);
                if (in_array(strtoupper($val), $ignoreValues)) continue;
                if (preg_match($ORDER_ID_REGEX, $val)) {
                    return strtoupper($val);
                }
            }
        }

        foreach ($row as $k => $v) {
            if (is_array($v)) continue;
            $val = trim((string)$v);
            if (in_array(strtoupper($val), $ignoreValues)) continue;
            if (preg_match($ORDER_ID_REGEX, $val) && !is_numeric($val)) {
                return strtoupper($val);
            }
        }

        return null;
    }

    private function extractMasterAmount(array $row): float
    {
        if (array_key_exists('Rincian Jumlah Pelepasan Dana', $row) || array_key_exists('rincian_jumlah_pelepasan_dana', $row)) {
            $col2 = strtoupper(trim((string)($row['Column2'] ?? $row['column2'] ?? '')));
            if ($col2 === 'ORDER') {
                $val = $row['Rincian Jumlah Pelepasan Dana'] ?? $row['rincian_jumlah_pelepasan_dana'];
                return $this->parseNum($val);
            }
            return 0.0;
        }

        if (array_key_exists('Column33', $row) || array_key_exists('column33', $row)) {
            return $this->parseNum($row['Column33'] ?? $row['column33']);
        }

        foreach (['Total Penghasilan', 'total_penghasilan', 'Jumlah Pencairan', 'pencairan', 'Total Pembayaran', 'amount'] as $k) {
            if (isset($row[$k])) {
                return $this->parseNum($row[$k]);
            }
        }

        return 0.0;
    }

    private function extractBruto($masterRaw): float
    {
        $summary = [];
        if (is_array($masterRaw)) {
            if (isset($masterRaw['Summary']) && is_array($masterRaw['Summary'])) {
                $summary = $masterRaw['Summary'];
            } elseif (isset($masterRaw['summary']) && is_array($masterRaw['summary'])) {
                $summary = $masterRaw['summary'];
            }
        }

        foreach ($summary as $item) {
            if (!is_array($item)) continue;
            $lap = strtolower(trim((string)($item['Laporan Penghasilan'] ?? $item['laporan_penghasilan'] ?? $item['Column1'] ?? '')));
            if (str_contains($lap, 'total pendapatan') || str_contains($lap, '1. total pendapatan')) {
                $col4 = $item['Column4'] ?? $item['column4'] ?? 0;
                return $this->parseNum($col4);
            }
        }

        return 0.0;
    }

    private function extractSize(string $variationStr, string $skuStr): string
    {
        $text = trim($variationStr . ' ' . $skuStr);
        if (preg_match('/(?<![A-Z0-9])(S|M|L|XL|XXL|2XL|3XL|4XL|5XL|XXXL|XXXXL|XXXXXL)(?![A-Z0-9])/i', $text, $m)) {
            return strtoupper($m[1]);
        }
        return '-';
    }

    private function splitBundleSku(string $skuRaw, string $variationRaw): array
    {
        $sku = trim($skuRaw);
        $variation = trim($variationRaw);

        // 1. Remove bundle prefix marker
        $sku = preg_replace('/^(SET|PAKET\d*|BDL|BND|BUNDLE)[-\s]+/i', '', $sku);

        // 2. BDL-XXXX -> XXXX
        if (preg_match('/^BDL-/i', $sku)) {
            $sku = preg_replace('/^BDL-/i', '', $sku);
        }

        // 3. Special handling for KAOS POLOS / POLOS variations
        if (strtoupper($sku) === 'KAOS POLOS' && preg_match('/^polos\s+/i', $variation)) {
            $variation = preg_replace('/^polos\s+/i', 'POLOS - ', $variation);
        }

        // 4. POLOS - COLOR pattern
        if (preg_match_all('/POLOS\s*-\s*[A-Z]+/i', $sku, $matches) && count($matches[0]) > 1) {
            return array_map('trim', $matches[0]);
        }

        // 5. Replace delimiters ['/', '&', ',', '|', '+'] with '-'
        $cleanStr = str_replace(['/', '&', ',', '|', '+'], '-', $sku);
        $parts = array_values(array_filter(array_map('trim', explode('-', $cleanStr))));

        if (count($parts) > 0) {
            return $parts;
        }

        return [$sku !== '' ? $sku : 'NO-SKU'];
    }

    private function getUnitCount(string $skuRaw, string $variationRaw): int
    {
        $codes = $this->splitBundleSku($skuRaw, $variationRaw);
        return max(1, count($codes));
    }
}

