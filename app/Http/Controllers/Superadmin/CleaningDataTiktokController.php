<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CleaningDataTiktokController extends Controller
{
    public function index()
    {
        return Inertia::render('Superadmin/Settings/CleaningDataTiktok');
    }

    public function process(Request $request)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(120);

        $request->validate([
            'master_json' => 'nullable',
            'raw_jsons' => 'nullable|array',
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

        // 1. Ekstraksi Master Data TikTok (Bilingual & Multi-Key)
        $masterRows = $this->unwrapDataArray($masterRaw, ['Order details', 'Rincian Pesanan', 'Detail Pesanan', 'Detail pesanan', 'OrderSKUList', 'orders', 'data', 'items', 'rows']);
        
        $masterMapping = [];
        $totalOrderAmount = 0;
        $totalGmvAdsAmount = 0;

        foreach ($masterRows as $row) {
            if (!is_array($row)) continue;

            $orderId = $this->getFieldVal($row, ['Order/adjustment ID', 'Order/Adjustment ID', 'ID Pesanan/Penyesuaian', 'ID Pesanan/penyesuaian', 'ID pesanan/penyesuaian', 'Order ID', 'ID Pesanan']);
            $orderIdStr = strtoupper(trim((string)$orderId));

            $transType = strtoupper(trim((string)($this->getFieldVal($row, ['Transaction type', 'Tipe transaksi', 'Tipe Transaksi', 'Jenis Transaksi']) ?? 'ORDER')));

            $rawSettlement = $this->getFieldVal($row, ['Total settlement amount', 'Total dana settlement', 'Total jumlah penyelesaian', 'Total penyelesaian', 'Settlement Amount', 'Pencairan']);
            $settlement = $this->parseNum($rawSettlement);

            $isGmvAds = str_contains($transType, 'GMV') || str_contains($transType, 'ADS') || str_contains($transType, 'IKLAN') || str_contains($transType, 'ADJUSTMENT');

            if ($isGmvAds) {
                $totalGmvAdsAmount += abs($settlement);
            } else {
                if (strlen($orderIdStr) >= 10) {
                    if (!isset($masterMapping[$orderIdStr])) {
                        $masterMapping[$orderIdStr] = 0;
                    }
                    $masterMapping[$orderIdStr] += $settlement;
                    $totalOrderAmount += $settlement;
                }
            }
        }

        $totalMasterAmount = $totalOrderAmount + $totalGmvAdsAmount;

        // Ekstraksi Bruto
        $bruto = $this->extractBruto($masterRaw, $masterRows);

        // 2. Process & Deduplicate Raw TikTok Data
        $dedupSet = [];
        $orderRawItemsMap = [];

        foreach ($rawInputs as $rawInput) {
            $rawPayload = is_string($rawInput) ? (json_decode($rawInput, true) ?? []) : $rawInput;
            $items = $this->unwrapDataArray($rawPayload, ['OrderSKUList', 'OrderList', 'Order details', 'orders', 'data', 'items', 'rows']);
            if (!is_array($items)) continue;

            foreach ($items as $item) {
                if (!is_array($item)) continue;

                $orderId = trim((string)($this->getFieldVal($item, ['Order ID', 'ID Pesanan', 'No. Pesanan', 'order_id']) ?? ''));
                if (!$orderId) continue;

                // VALIDASI KHUSUS TIKTOK: Order ID HARUS HANYA NUMERIK
                if (preg_match('/[^0-9]/', $orderId)) {
                    continue;
                }

                $productName = trim((string)($this->getFieldVal($item, ['Product Name', 'Nama Produk', 'product_name']) ?? ''));
                $variantName = trim((string)($this->getFieldVal($item, ['Variation', 'Nama Variasi', 'Variasi', 'variant']) ?? ''));

                // Deduplikasi Composite Key: Order ID + '||' + Product Name + '||' + Variation
                $dedupKey = $orderId . '||' . $productName . '||' . $variantName;
                if (isset($dedupSet[$dedupKey])) continue;
                $dedupSet[$dedupKey] = true;

                // Cek ketersediaan di Master Data
                if (!isset($masterMapping[$orderId])) continue;

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
            $masterAmt = $masterMapping[$orderId] ?? 0;
            $itemCount = count($rawItems);

            $rawPaySums = 0;
            $rawPays = [];
            foreach ($rawItems as $idx => $rawItem) {
                $rawP = $this->parseNum($this->getFieldVal($rawItem, ['Total Pembayaran', 'Subtotal Pesanan', 'Harga Setelah Diskon', 'Harga Awal']) ?? 0);
                $rawPays[$idx] = $rawP;
                $rawPaySums += max(0, $rawP);
            }

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

                // Abaikan baris dengan Total Pembayaran 0 atau minus
                if ($itemPembayaran <= 0) {
                    continue;
                }

                $orderMatchedAny = true;

                $productName = trim((string)($this->getFieldVal($item, ['Product Name', 'Nama Produk', 'product_name']) ?? ''));
                $variantName = trim((string)($this->getFieldVal($item, ['Variation', 'Nama Variasi', 'Variasi', 'variant']) ?? ''));
                $sku = trim((string)($this->getFieldVal($item, ['Seller SKU', 'Nomor Referensi SKU', 'SKU Induk', 'sku']) ?? 'NO-SKU'));
                $resi = trim((string)($this->getFieldVal($item, ['Tracking ID', 'No. Resi', 'No Resi', 'resi']) ?? '-'));
                $qty = max(1, intval($this->getFieldVal($item, ['Quantity', 'Jumlah', 'Qty', 'quantity']) ?? 1));

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

        $missingList = [];
        foreach ($masterMapping as $orderId => $amt) {
            if (!isset($matchedOrdersMap[$orderId])) {
                $missingList[] = [
                    'order_id' => $orderId,
                    'amount' => $amt,
                ];
            }
        }

        $skuBreakdown = array_values($skuBreakdownMap);
        usort($skuBreakdown, fn($a, $b) => $b['total_qty'] <=> $a['total_qty']);

        $exportPayload = [
            'total_master' => count($masterMapping),
            'total_matched' => count($matchedDataItems),
            'total_payment' => round($totalPembayaranKalkulasi),
            'total_quantity' => $totalQtySatuan,
            'total_produk_ditemukan' => $totalQtySatuan,
            'total_qty_product_ditemukan' => $totalQtyProductDitemukan,
            'total_qty_baris_tiktok' => count($matchedDataItems),
            'total_pesanan_ditemukan' => count($matchedOrdersMap),
            'total_master_amount' => round($totalMasterAmount),
            'matched_master_amount' => round(array_sum($matchedOrdersMap)),
            'total_gmv_ads_amount' => round($totalGmvAdsAmount),
            'missing_count' => count($missingList),
            'bruto' => round($bruto),
            'data' => $matchedDataItems,
        ];

        return response()->json([
            'success' => true,
            'export_payload' => $exportPayload,
            'sku_breakdown' => array_slice($skuBreakdown, 0, 150),
            'sku_breakdown_more' => max(0, count($skuBreakdown) - 150),
            'missing_order_ids' => $missingList,
        ]);
    }

    private function getFieldVal(array $row, array $candidates)
    {
        foreach ($candidates as $cand) {
            if (array_key_exists($cand, $row) && $row[$cand] !== null) {
                return $row[$cand];
            }
        }
        foreach ($row as $k => $v) {
            $cleanK = strtolower(trim((string)$k));
            foreach ($candidates as $cand) {
                if ($cleanK === strtolower(trim($cand)) && $v !== null) {
                    return $v;
                }
            }
        }
        return null;
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

        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
        return is_numeric($s) ? (float)$s : 0.0;
    }

    private function extractBruto($masterRaw, array $masterRows): float
    {
        $reports = [];
        if (is_array($masterRaw)) {
            if (isset($masterRaw['Reports']) && is_array($masterRaw['Reports'])) {
                $reports = $masterRaw['Reports'];
            } elseif (isset($masterRaw['Summary']) && is_array($masterRaw['Summary'])) {
                $reports = $masterRaw['Summary'];
            }
        }

        foreach ($reports as $item) {
            if (!is_array($item)) continue;
            $vals = array_map('strval', array_values($item));
            foreach ($vals as $v) {
                if (str_contains(strtolower($v), 'total pendapatan') || str_contains(strtolower($v), 'subtotal after seller discounts')) {
                    $lastVal = end($vals);
                    return abs($this->parseNum($lastVal));
                }
            }
        }

        $fallbackBruto = 0;
        foreach ($masterRows as $row) {
            if (!is_array($row)) continue;
            $rev = $this->getFieldVal($row, ['Subtotal after seller discounts', 'Subtotal after sales discounts', 'Subtotal setelah diskon penjual', 'Total Revenue', 'Total revenue', 'Subtotal sebelum diskon']);
            $fallbackBruto += $this->parseNum($rev);
        }

        return $fallbackBruto;
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

        $sku = preg_replace('/^(SET|PAKET\d*|BDL|BND|BUNDLE)[-\s]+/i', '', $sku);

        if (preg_match('/^BDL-/i', $sku)) {
            $sku = preg_replace('/^BDL-/i', '', $sku);
        }

        if (strtoupper($sku) === 'KAOS POLOS' && preg_match('/^polos\s+/i', $variation)) {
            $variation = preg_replace('/^polos\s+/i', 'POLOS - ', $variation);
        }

        if (preg_match_all('/POLOS\s*-\s*[A-Z]+/i', $sku, $matches) && count($matches[0]) > 1) {
            return array_map('trim', $matches[0]);
        }

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
