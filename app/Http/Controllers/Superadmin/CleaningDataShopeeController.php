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
        $request->validate([
            'master_json' => 'nullable|string',
            'raw_jsons' => 'nullable|array',
        ]);

        $masterRaw = json_decode($request->input('master_json', '[]'), true) ?? [];
        $rawJsons = $request->input('raw_jsons', []);

        $masterData = $this->unwrapDataArray($masterRaw, ['Income', 'orders', 'data', 'items', 'rows']);

        $masterOrders = [];
        $totalMasterAmount = 0;

        foreach ($masterData as $row) {
            if (!is_array($row)) continue;
            $orderId = null;
            $amount = 0;

            foreach ($row as $k => $v) {
                if (is_array($v)) continue;
                $vStr = trim((string)$v);
                if (preg_match('/^[0-9A-Z]{14,16}$/i', $vStr) && !is_numeric($vStr)) {
                    $orderId = $vStr;
                }
                if (in_array(strtolower($k), ['total penghasilan', 'column33', 'jumlah pencairan', 'pencairan', 'total_pembayaran', 'total no. pesanan', 'amount'])) {
                    $val = str_replace(['Rp', '.', ' '], '', $vStr);
                    $val = str_replace(',', '.', $val);
                    if (is_numeric($val)) {
                        $amount = floatval($val);
                    }
                }
            }

            if ($orderId) {
                if (!isset($masterOrders[$orderId])) {
                    $masterOrders[$orderId] = $amount;
                    $totalMasterAmount += $amount;
                }
            }
        }

        $totalOrderIdsMaster = count($masterOrders);

        // Deduplication & Order Matching
        $dedupKeys = [];
        $matchedOrdersMap = [];
        $matchedDataItems = [];
        $skuBreakdown = [];
        $totalQtySatuan = 0;
        $totalPembayaranKalkulasi = 0;
        $totalQtyProductDitemukan = 0;

        foreach ($rawJsons as $rawJsonStr) {
            $rawPayload = is_array($rawJsonStr) ? $rawJsonStr : (json_decode($rawJsonStr, true) ?? []);
            $items = $this->unwrapDataArray($rawPayload, ['orders', 'data', 'items', 'rows']);
            if (!is_array($items)) continue;

            foreach ($items as $item) {
                if (!is_array($item)) continue;
                $orderId = trim((string)($item['No. Pesanan'] ?? $item['Order ID'] ?? $item['order_id'] ?? ''));
                $resi = trim((string)($item['No. Resi'] ?? $item['Tracking No'] ?? $item['resi'] ?? '-'));
                $productName = trim((string)($item['Nama Produk'] ?? $item['product_name'] ?? ''));
                $variantName = trim((string)($item['Nama Variasi'] ?? $item['variant_name'] ?? ''));
                $sku = trim((string)($item['Nomor Referensi SKU'] ?? $item['SKU Penjual'] ?? $item['SKU Induk'] ?? $item['sku'] ?? 'NO-SKU'));
                $qty = intval($item['Jumlah'] ?? $item['Qty'] ?? $item['quantity'] ?? 1);
                
                $totalPembayaranRaw = $item['Total Pembayaran'] ?? $item['Subtotal Pesanan'] ?? 0;
                $totalPembayaran = floatval(str_replace(['Rp', '.', ' '], '', (string)$totalPembayaranRaw));

                if (!$orderId) continue;

                $key = "{$orderId}_{$productName}_{$variantName}";
                if (isset($dedupKeys[$key])) continue;
                $dedupKeys[$key] = true;

                if (isset($masterOrders[$orderId])) {
                    $matchedOrdersMap[$orderId] = $masterOrders[$orderId];
                    $masterAmount = $masterOrders[$orderId];
                    $itemPembayaran = $masterAmount > 0 ? $masterAmount : $totalPembayaran;
                    
                    $matchedDataItems[] = [
                        'No. Pesanan' => $orderId,
                        'No. Resi' => $resi,
                        'Nomor Referensi SKU' => $sku,
                        'Nama Variasi' => $variantName,
                        'Jumlah' => $qty,
                        'Total Pembayaran' => $itemPembayaran,
                        'Nama Produk' => $productName
                    ];

                    $totalPembayaranKalkulasi += $itemPembayaran;
                    $totalQtyProductDitemukan += $qty;

                    // Parse Shopee Bundle
                    $parsedItems = $this->parseShopeeBundle($sku, $variantName, $qty);
                    foreach ($parsedItems as $p) {
                        $itemSku = $p['sku'];
                        $itemSize = $p['size'];
                        $itemQty = $p['qty'];

                        $totalQtySatuan += $itemQty;
                        $bKey = "{$itemSku}_{$itemSize}";

                        if (!isset($skuBreakdown[$bKey])) {
                            $skuBreakdown[$bKey] = [
                                'sku' => $itemSku,
                                'size' => $itemSize,
                                'total_qty' => 0,
                                'order_count' => 0
                            ];
                        }
                        $skuBreakdown[$bKey]['total_qty'] += $itemQty;
                        $skuBreakdown[$bKey]['order_count'] += 1;
                    }
                }
            }
        }

        $totalPesananDitemukan = count($matchedOrdersMap);
        $matchedMasterAmount = array_sum($matchedOrdersMap);
        $totalDataCocok = count($matchedDataItems);
        $totalProdukDitemukan = $totalDataCocok;

        $missingOrderIds = [];
        foreach ($masterOrders as $orderId => $amt) {
            if (!isset($matchedOrdersMap[$orderId])) {
                $missingOrderIds[] = [
                    'order_id' => $orderId,
                    'amount' => $amt
                ];
            }
        }

        return response()->json([
            'success' => true,
            'export_payload' => [
                'total_order_ids_master' => $totalOrderIdsMaster,
                'total_master_amount' => $totalMasterAmount,
                'matched_master_amount' => $matchedMasterAmount,
                'total_data_cocok' => $totalDataCocok,
                'total_pembayaran_kalkulasi' => $totalPembayaranKalkulasi,
                'total_produk_ditemukan' => $totalProdukDitemukan,
                'total_qty_product_ditemukan' => $totalQtyProductDitemukan,
                'total_qty_satuan' => $totalQtySatuan,
                'total_pesanan_ditemukan' => $totalPesananDitemukan,
                'data' => $matchedDataItems
            ],
            'sku_breakdown' => array_values($skuBreakdown),
            'missing_order_ids' => $missingOrderIds
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

    private function parseShopeeBundle($skuStr, $variationStr, $qty)
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
