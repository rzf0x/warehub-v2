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
        $request->validate([
            'master_json' => 'nullable|string',
            'raw_jsons' => 'nullable|array',
        ]);

        $masterRaw = json_decode($request->input('master_json', '[]'), true) ?? [];
        $rawJsons = $request->input('raw_jsons', []);

        $brutoSum = 0;
        $nettoSum = 0;
        $pemakaianGmvSum = 0;
        $totalSamaGmvSum = 0;

        // Extract official summary numbers from TikTok 'Reports' sheet if available
        if (isset($masterRaw['Reports']) && is_array($masterRaw['Reports'])) {
            foreach ($masterRaw['Reports'] as $item) {
                if (!is_array($item)) continue;
                $vals = array_map('strval', array_values($item));
                $valStr = end($vals) ?: '0';
                $numVal = (float)str_replace(['Rp', '.', ' '], '', str_replace(',', '.', $valStr));

                if (in_array('Subtotal after seller discounts', $vals)) {
                    $brutoSum = abs($numVal);
                }
                if (in_array('Total settlement amount', $vals)) {
                    $nettoSum = abs($numVal);
                }
                if (in_array('GMV payment for TikTok Ads', $vals) || in_array('Adjustments', $vals)) {
                    if (in_array('GMV payment for TikTok Ads', $vals)) {
                        $pemakaianGmvSum = abs($numVal);
                    } elseif ($pemakaianGmvSum == 0) {
                        $pemakaianGmvSum = abs($numVal);
                    }
                }
            }
            $totalSamaGmvSum = $nettoSum + $pemakaianGmvSum;
        }

        $masterRows = $this->unwrapDataArray($masterRaw, ['Order details', 'OrderSKUList', 'OrderList', 'orders', 'data', 'items', 'rows', 'Income', 'Sheet1']);

        $masterOrdersMap = [];
        $fallbackBruto = 0;
        $fallbackTotalSamaGmv = 0;
        $fallbackPemakaianGmv = 0;

        foreach ($masterRows as $row) {
            if (!is_array($row)) continue;
            $orderId = trim((string)($row['Order/Adjustment ID'] ?? $row['Order ID'] ?? $row['Order No'] ?? $row['order_id'] ?? ''));
            $transType = trim((string)($row['Transaction type'] ?? 'Order'));

            $rawSettlement = $row['Total settlement amount'] ?? $row['Settlement Amount'] ?? $row['Pencairan'] ?? $row['Total Revenue'] ?? 0;
            $settlement = (float)str_replace(['Rp', '.', ' '], '', str_replace(',', '.', $rawSettlement));

            $rawSubtotal = $row['Subtotal after seller discounts'] ?? $row['Subtotal before discounts'] ?? $row['Subtotal'] ?? 0;
            $subtotal = (float)str_replace(['Rp', '.', ' '], '', str_replace(',', '.', $rawSubtotal));

            $fallbackBruto += $subtotal;

            $isGmvAd = (str_contains(strtolower($transType), 'gmv') || str_contains(strtolower($transType), 'ads'));

            if ($isGmvAd) {
                $fallbackPemakaianGmv += abs($settlement);
            } else {
                if ($orderId && (strtolower($transType) === 'order' || strtolower($transType) === 'pesanan' || $transType === '' || preg_match('/^\d{15,20}$/', $orderId))) {
                    $masterOrdersMap[$orderId] = ($masterOrdersMap[$orderId] ?? 0) + $settlement;
                    $fallbackTotalSamaGmv += $settlement;
                }
            }
        }

        // Exclude orders with non-positive settlement (<= 0, e.g. cancelled/retur 0 or minus)
        foreach ($masterOrdersMap as $orderId => $amt) {
            if ($amt <= 0) {
                unset($masterOrdersMap[$orderId]);
            }
        }

        if ($brutoSum == 0) $brutoSum = $fallbackBruto;
        if ($totalSamaGmvSum == 0) $totalSamaGmvSum = $fallbackTotalSamaGmv;
        if ($pemakaianGmvSum == 0) $pemakaianGmvSum = $fallbackPemakaianGmv;
        if ($nettoSum == 0) $nettoSum = $totalSamaGmvSum - $pemakaianGmvSum;

        $totalMasterAmountSum = array_sum($masterOrdersMap);

        // Process Raw Orders
        $rawAll = [];
        foreach ($rawJsons as $rawJsonStr) {
            $rawPayload = is_array($rawJsonStr) ? $rawJsonStr : (json_decode($rawJsonStr, true) ?? []);
            $items = $this->unwrapDataArray($rawPayload, ['OrderSKUList', 'OrderList', 'Order details', 'orders', 'data', 'items', 'rows', 'Sheet1']);
            if (is_array($items)) {
                $rawAll = array_merge($rawAll, $items);
            }
        }

        $dedupMap = [];
        $matchedOrdersMap = [];
        $matchedDataItems = [];
        $skuBreakdown = [];
        $totalQtySatuanSum = 0;

        foreach ($rawAll as $item) {
            if (!is_array($item)) continue;
            $orderId = trim((string)($item['Order ID'] ?? $item['No. Pesanan'] ?? $item['order_id'] ?? ''));
            $resi = trim((string)($item['Tracking ID'] ?? $item['No. Resi'] ?? $item['No Resi'] ?? $item['resi'] ?? '-'));
            $productName = trim((string)($item['Product Name'] ?? $item['Nama Produk'] ?? $item['product_name'] ?? ''));
            $variantName = trim((string)($item['Variation'] ?? $item['Nama Variasi'] ?? $item['Variasi'] ?? $item['variant_name'] ?? ''));
            $rawSku = trim((string)($item['Seller SKU'] ?? $item['Nomor Referensi SKU'] ?? $item['SKU Induk'] ?? $item['sku'] ?? 'NO-SKU'));
            $rawQty = intval($item['Quantity'] ?? $item['Jumlah'] ?? $item['Qty'] ?? 1);

            if (!$orderId) continue;

            $dedupKey = "{$orderId}_{$productName}_{$variantName}";
            if (isset($dedupMap[$dedupKey])) continue;
            $dedupMap[$dedupKey] = true;

            if (isset($masterOrdersMap[$orderId])) {
                $masterAmt = $masterOrdersMap[$orderId];
                $itemPayment = max(0, $masterAmt);
                $matchedOrdersMap[$orderId] = $itemPayment;

                $matchedDataItems[] = [
                    'No. Pesanan' => $orderId,
                    'No. Resi' => $resi,
                    'Nomor Referensi SKU' => $rawSku,
                    'Nama Variasi' => $variantName,
                    'Jumlah' => $rawQty,
                    'Total Pembayaran' => $itemPayment,
                    'Nama Produk' => $productName
                ];

                $parsedItems = $this->parseBundleSku($rawSku, $variantName, $rawQty);
                foreach ($parsedItems as $p) {
                    $totalQtySatuanSum += $p['qty'];
                    $bKey = "{$p['sku']}_{$p['size']}";
                    if (!isset($skuBreakdown[$bKey])) {
                        $skuBreakdown[$bKey] = [
                            'sku' => $p['sku'],
                            'variation' => $p['size'],
                            'total_qty' => 0,
                            'order_count' => 0
                        ];
                    }
                    $skuBreakdown[$bKey]['total_qty'] += $p['qty'];
                    $skuBreakdown[$bKey]['order_count'] += 1;
                }
            }
        }

        $missingList = [];
        foreach ($masterOrdersMap as $orderId => $amt) {
            if (!isset($matchedOrdersMap[$orderId])) {
                $missingList[] = [
                    'order_id' => $orderId,
                    'amount' => $amt
                ];
            }
        }

        $exportPayload = [
            'bruto' => (int)round($brutoSum),
            'netto' => (int)round($nettoSum),
            'total_sama_gmv' => (int)round($totalSamaGmvSum),
            'pemakaian_gmv' => (int)round($pemakaianGmvSum),
            'total_master' => count($masterOrdersMap),
            'total_matched' => count($matchedDataItems),
            'total_payment' => (int)round($totalMasterAmountSum),
            'total_quantity' => $totalQtySatuanSum,
            'total_produk_ditemukan' => $totalQtySatuanSum,
            'total_qty_product_ditemukan' => $totalQtySatuanSum,
            'total_qty_baris_tiktok' => count($matchedDataItems),
            'total_pesanan_ditemukan' => count($matchedOrdersMap),
            'total_master_amount' => (int)round($totalMasterAmountSum),
            'matched_master_amount' => (int)round(array_sum($matchedOrdersMap)),
            'missing_count' => count($missingList),
            'data' => $matchedDataItems
        ];

        return response()->json([
            'success' => true,
            'export_payload' => $exportPayload,
            'sku_breakdown' => array_values($skuBreakdown),
            'matched_rows' => $matchedDataItems,
            'missing_order_ids' => $missingList
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

    private function parseBundleSku($skuStr, $variationStr, $qty)
    {
        $results = [];
        $cleanSku = trim($skuStr);
        $cleanVar = trim($variationStr);

        $size = 'ALL SIZE';
        if (preg_match('/\b(3XL|2XL|XL|L|M|S|XS|XXL|XXXL)\b/i', $cleanVar . ' ' . $cleanSku, $m)) {
            $size = strtoupper($m[1]);
        }

        $delimiter = null;
        if (str_contains($cleanSku, '+')) $delimiter = '+';
        elseif (str_contains($cleanSku, ',')) $delimiter = ',';
        elseif (preg_match('/^[A-Z0-9]+-[A-Z0-9]+/', $cleanSku)) $delimiter = '-';

        if ($delimiter) {
            $parts = explode($delimiter, $cleanSku);
            $prefix = '';
            foreach ($parts as $idx => $part) {
                $part = trim($part);
                if ($idx === 0 && preg_match('/^([A-Z0-9\s]+)\s+([A-Z0-9]+)$/i', $part, $pm)) {
                    $prefix = trim($pm[1]);
                }
                $itemSku = $part;
                if ($idx > 0 && $prefix && !str_contains($part, ' ') && !preg_match('/^[A-Z]{2,}/i', $part)) {
                    $itemSku = $prefix . ' ' . $part;
                }

                $pQty = $qty;
                if (preg_match('/x(\d+)/i', $itemSku, $qm)) {
                    $pQty *= intval($qm[1]);
                    $itemSku = trim(preg_replace('/x\d+/i', '', $itemSku));
                }
                $results[] = ['sku' => $itemSku, 'size' => $size, 'qty' => $pQty];
            }
        } else {
            $results[] = ['sku' => $cleanSku, 'size' => $size, 'qty' => $qty];
        }

        return $results;
    }
}
