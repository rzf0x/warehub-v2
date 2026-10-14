<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BundleItem;
use App\Models\Category;
use App\Models\Konveksi;
use App\Models\Period;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Stock;
use App\Models\StockLog;
use App\Models\StockOut;
use App\Models\StockOutItem;
use App\Models\Store;
use App\Models\VariantTemplate;
use App\Models\Warehouse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StockOutController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $warehouseId = $request->input('warehouse_id');
        $storeId = $request->input('store_id');
        $periodId = $request->input('period_id');
        $userId = $request->input('user_id');

        $baseQuery = StockOut::query()
            ->when($search, function ($query, $s) {
                $query->where(function ($q) use ($s) {
                    $q->where('resi_summary', 'like', "%{$s}%")
                      ->orWhere('note', 'like', "%{$s}%")
                      ->orWhereHas('store', fn($sq) => $sq->where('store_name', 'like', "%{$s}%"));
                });
            })
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->when($periodId, fn($q) => $q->where('period_id', $periodId))
            ->when($userId, fn($q) => $q->where('user_id', $userId));

        $stockOuts = (clone $baseQuery)
            ->select('id', 'warehouse_id', 'user_id', 'store_id', 'period_id', 'date', 'note', 'total_amount', 'total_selling_price', 'bruto')
            ->with([
                'warehouse:id,name',
                'user:id,name',
                'store:id,store_name,seller_id',
                'store.seller:id,seller_name',
                'period:id,name',
            ])
            ->withSum('items', 'qty')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $aggregates = (clone $baseQuery)->selectRaw('
            COALESCE(SUM(total_amount), 0) as total_hpp,
            COALESCE(SUM(bruto), 0) as total_bruto,
            COALESCE(SUM(total_selling_price), 0) as penjualan_bersih
        ')->first();

        $totalHpp = (float)($aggregates->total_hpp ?? 0);
        $totalBruto = (float)($aggregates->total_bruto ?? 0);
        $penjualanBersih = (float)($aggregates->penjualan_bersih ?? 0);
        $estimasiKeuntungan = $penjualanBersih - $totalHpp;

        $totalQty = (int)StockOutItem::whereIn('stock_out_id', (clone $baseQuery)->select('id'))->sum('qty');

        $warehouses = Warehouse::select('id', 'name')->get();
        $stores = Store::select('id', 'store_name')->get();
        $periods = Period::select('id', 'name')->latest()->get();
        $users = DB::table('users')->select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Superadmin/Warehouse/StockOut/Index', [
            'stockOuts' => $stockOuts,
            'warehouses' => $warehouses,
            'stores' => $stores,
            'periods' => $periods,
            'users' => $users,
            'metrics' => [
                'total_qty' => (int)$totalQty,
                'total_hpp' => (float)$totalHpp,
                'total_bruto' => (float)$totalBruto,
                'penjualan_bersih' => (float)$penjualanBersih,
                'estimasi_keuntungan' => (float)$estimasiKeuntungan,
            ],
            'filters' => [
                'search' => $search,
                'warehouse_id' => $warehouseId,
                'store_id' => $storeId,
                'period_id' => $periodId,
                'user_id' => $userId,
            ],
        ]);
    }

    public function searchVariants(Request $request)
    {
        $q = trim((string)$request->input('q', ''));
        $sellerId = $request->input('seller_id');
        $warehouseId = $request->input('warehouse_id');

        $query = ProductVariant::with([
            'product.seller:id,seller_name',
            'stocks'
        ])
        ->select('id', 'product_id', 'sku', 'size', 'color', 'price');

        if ($sellerId) {
            $query->whereHas('product', function ($pq) use ($sellerId) {
                $pq->where('seller_id', $sellerId);
            });
        }

        if ($q !== '') {
            $query->where(function ($subQ) use ($q) {
                $subQ->where('sku', 'like', "%{$q}%")
                    ->orWhere('size', 'like', "%{$q}%")
                    ->orWhere('color', 'like', "%{$q}%")
                    ->orWhereHas('product', function ($pq) use ($q) {
                        $pq->where('product_name', 'like', "%{$q}%")
                            ->orWhere('sku', 'like', "%{$q}%");
                    });
            });
        }

        $variants = $query->latest('id')
            ->limit(30)
            ->get()
            ->map(function ($v) {
                return [
                    'id' => $v->id,
                    'product_id' => $v->product_id,
                    'product_name' => $v->product->product_name ?? '',
                    'seller_id' => $v->product->seller_id ?? null,
                    'seller_name' => $v->product->seller->seller_name ?? 'Mitra',
                    'sku' => $v->sku,
                    'size' => $v->size,
                    'color' => $v->color,
                    'price' => (float)$v->price,
                    'selling_price' => (float)$v->price,
                    'stocks' => $v->stocks->pluck('qty', 'warehouse_id'),
                ];
            });

        return response()->json($variants);
    }

    public function create()
    {
        $activePeriod = Period::where('is_active', true)->first();
        $warehouses = Warehouse::select('id', 'name')->get();
        $stores = Store::with('seller:id,seller_name')->select('id', 'seller_id', 'store_name', 'marketplace')->get();
        $periods = Period::select('id', 'name', 'is_active')->latest()->get();
        $sellers = Seller::select('id', 'seller_name')->get();
        $konveksis = Konveksi::select('id', 'name')->get();
        $categories = Category::select('id', 'name')->get();
        $brands = Brand::select('id', 'name')->get();
        $templates = VariantTemplate::with('items')->select('id', 'template_name')->get();

        return Inertia::render('Superadmin/Warehouse/StockOut/StockOutForm', [
            'warehouses' => $warehouses,
            'stores' => $stores,
            'periods' => $periods,
            'sellers' => $sellers,
            'konveksis' => $konveksis,
            'categories' => $categories,
            'brands' => $brands,
            'templates' => $templates,
            'variants' => [],
            'activePeriod' => $activePeriod,
            'stockOut' => null,
            'isEdit' => false,
        ]);
    }

    public function edit($id)
    {
        $stockOut = StockOut::with(['items.productVariant.product.seller', 'store.seller', 'warehouse', 'period'])->findOrFail($id);

        $activePeriod = Period::where('is_active', true)->first();
        $warehouses = Warehouse::select('id', 'name')->get();
        $stores = Store::with('seller:id,seller_name')->select('id', 'seller_id', 'store_name', 'marketplace')->get();
        $periods = Period::select('id', 'name', 'is_active')->latest()->get();
        $sellers = Seller::select('id', 'seller_name')->get();
        $konveksis = Konveksi::select('id', 'name')->get();
        $categories = Category::select('id', 'name')->get();
        $brands = Brand::select('id', 'name')->get();
        $templates = VariantTemplate::with('items')->select('id', 'template_name')->get();

        $variantIds = $stockOut->items->pluck('product_variant_id')->filter()->unique();
        $variants = ProductVariant::with(['product.seller:id,seller_name', 'stocks'])
            ->whereIn('id', $variantIds)
            ->select('id', 'product_id', 'sku', 'size', 'color', 'price')
            ->get()
            ->map(function ($v) {
                return [
                    'id' => $v->id,
                    'product_id' => $v->product_id,
                    'product_name' => $v->product->product_name ?? '',
                    'seller_id' => $v->product->seller_id ?? null,
                    'seller_name' => $v->product->seller->seller_name ?? 'Mitra',
                    'sku' => $v->sku,
                    'size' => $v->size,
                    'color' => $v->color,
                    'price' => (float)$v->price,
                    'selling_price' => (float)$v->price,
                    'stocks' => $v->stocks->pluck('qty', 'warehouse_id'),
                ];
            });

        return Inertia::render('Superadmin/Warehouse/StockOut/StockOutForm', [
            'warehouses' => $warehouses,
            'stores' => $stores,
            'periods' => $periods,
            'sellers' => $sellers,
            'konveksis' => $konveksis,
            'categories' => $categories,
            'brands' => $brands,
            'templates' => $templates,
            'variants' => $variants,
            'activePeriod' => $activePeriod,
            'stockOut' => $stockOut,
            'isEdit' => true,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'store_id' => ['nullable', 'exists:stores,id'],
            'period_id' => ['nullable', 'exists:periods,id'],
            'resi_summary' => ['required', 'string'],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
            'bruto' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'exists:product_variants,id'],
            'items.*.konveksi_id' => ['nullable', 'exists:konveksis,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.selling_price' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($validated, $request) {
            $totalHpp = 0;
            $totalSelling = 0;
            foreach ($validated['items'] as $item) {
                $totalHpp += $item['price'] * $item['qty'];
                $totalSelling += $item['selling_price'] * $item['qty'];
            }

            $brutoVal = floatval($validated['bruto'] ?? $totalSelling);
            if ($brutoVal <= 0) $brutoVal = $totalSelling;

            $stockOut = StockOut::create([
                'warehouse_id' => $validated['warehouse_id'],
                'user_id' => $request->user()->id,
                'store_id' => $validated['store_id'] ?? null,
                'period_id' => $validated['period_id'] ?? null,
                'resi_summary' => $validated['resi_summary'],
                'date' => $validated['date'],
                'note' => $validated['note'] ?? null,
                'total_amount' => $totalHpp,
                'total_selling_price' => $totalSelling,
                'bruto' => $brutoVal,
            ]);

            foreach ($validated['items'] as $itemData) {
                StockOutItem::create([
                    'stock_out_id' => $stockOut->id,
                    'product_variant_id' => $itemData['product_variant_id'],
                    'konveksi_id' => $itemData['konveksi_id'] ?? null,
                    'qty' => $itemData['qty'],
                    'price' => $itemData['price'],
                    'subtotal' => $itemData['price'] * $itemData['qty'],
                    'selling_price' => $itemData['selling_price'],
                ]);

                $variant = ProductVariant::with('product')->find($itemData['product_variant_id']);

                $logNote = $this->formatStockLogNote($stockOut, "Penjualan Resi");
                $bundleLogNote = $this->formatStockLogNote($stockOut, "Penjualan Bundle");

                if ($variant && $variant->product && $variant->product->product_type === 'bundle') {
                    $bundleItems = BundleItem::where('bundle_variant_id', $variant->id)->get();
                    foreach ($bundleItems as $bItem) {
                        $deductQty = ($bItem->qty ?? $bItem->quantity ?? 1) * $itemData['qty'];
                        $this->deductPhysicalStock($validated['warehouse_id'], $bItem->component_variant_id, $deductQty, $stockOut->id, $bundleLogNote);
                    }
                } else {
                    $this->deductPhysicalStock($validated['warehouse_id'], $itemData['product_variant_id'], $itemData['qty'], $stockOut->id, $logNote);
                }
            }
        });

        return redirect()->route('superadmin.warehouse.stock-out.index')->with('success', 'Transaksi Stok Keluar berhasil disimpan.');
    }

    public function update(Request $request, $id)
    {
        $stockOut = StockOut::with('items')->findOrFail($id);

        $validated = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'store_id' => ['nullable', 'exists:stores,id'],
            'period_id' => ['nullable', 'exists:periods,id'],
            'resi_summary' => ['required', 'string'],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
            'bruto' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'exists:product_variants,id'],
            'items.*.konveksi_id' => ['nullable', 'exists:konveksis,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.selling_price' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($validated, $request, $stockOut) {
            foreach ($stockOut->items as $item) {
                $oldVariant = ProductVariant::with('product')->find($item->product_variant_id);
                if ($oldVariant && $oldVariant->product && $oldVariant->product->product_type === 'bundle') {
                    $bundleItems = BundleItem::where('bundle_variant_id', $oldVariant->id)->get();
                    foreach ($bundleItems as $bItem) {
                        $compQty = ($bItem->qty ?? $bItem->quantity ?? 1) * $item->qty;
                        $stock = Stock::where('warehouse_id', $stockOut->warehouse_id)
                            ->where('product_variant_id', $bItem->component_variant_id)
                            ->first();
                        if ($stock) {
                            $stock->increment('qty', $compQty);
                        }
                    }
                } else {
                    $stock = Stock::where('warehouse_id', $stockOut->warehouse_id)
                        ->where('product_variant_id', $item->product_variant_id)
                        ->first();

                    if ($stock) {
                        $stock->increment('qty', $item->qty);
                    }
                }
            }

            $stockOut->items()->delete();

            $totalHpp = 0;
            $totalSelling = 0;
            foreach ($validated['items'] as $item) {
                $totalHpp += $item['price'] * $item['qty'];
                $totalSelling += $item['selling_price'] * $item['qty'];
            }

            $brutoVal = floatval($validated['bruto'] ?? $totalSelling);
            if ($brutoVal <= 0) $brutoVal = $totalSelling;

            $stockOut->update([
                'warehouse_id' => $validated['warehouse_id'],
                'user_id' => $request->user()->id,
                'store_id' => $validated['store_id'] ?? null,
                'period_id' => $validated['period_id'] ?? null,
                'resi_summary' => $validated['resi_summary'],
                'date' => $validated['date'],
                'note' => $validated['note'] ?? null,
                'total_amount' => $totalHpp,
                'total_selling_price' => $totalSelling,
                'bruto' => $brutoVal,
            ]);

            foreach ($validated['items'] as $itemData) {
                StockOutItem::create([
                    'stock_out_id' => $stockOut->id,
                    'product_variant_id' => $itemData['product_variant_id'],
                    'konveksi_id' => $itemData['konveksi_id'] ?? null,
                    'qty' => $itemData['qty'],
                    'price' => $itemData['price'],
                    'subtotal' => $itemData['price'] * $itemData['qty'],
                    'selling_price' => $itemData['selling_price'],
                ]);

                $variant = ProductVariant::with('product')->find($itemData['product_variant_id']);

                $logNote = $this->formatStockLogNote($stockOut, "Update Stock Out Resi");
                $bundleLogNote = $this->formatStockLogNote($stockOut, "Update Stock Out Bundle");

                if ($variant && $variant->product && $variant->product->product_type === 'bundle') {
                    $bundleItems = BundleItem::where('bundle_variant_id', $variant->id)->get();
                    foreach ($bundleItems as $bItem) {
                        $deductQty = ($bItem->qty ?? $bItem->quantity ?? 1) * $itemData['qty'];
                        $this->deductPhysicalStock($validated['warehouse_id'], $bItem->component_variant_id, $deductQty, $stockOut->id, $bundleLogNote);
                    }
                } else {
                    $this->deductPhysicalStock($validated['warehouse_id'], $itemData['product_variant_id'], $itemData['qty'], $stockOut->id, $logNote);
                }
            }
        });

        return redirect()->route('superadmin.warehouse.stock-out.index')->with('success', 'Transaksi Stok Keluar berhasil diperbarui.');
    }

    public function processOrders(Request $request)
    {
        $warehouseId = $request->input('warehouse_id');
        $rawItems = [];

        $suggestedBruto = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $content = file_get_contents($file->getRealPath());
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                if (isset($decoded['data']) && is_array($decoded['data'])) {
                    $rawItems = $decoded['data'];
                } elseif (isset($decoded['orders']) && is_array($decoded['orders'])) {
                    $rawItems = $decoded['orders'];
                } elseif (isset($decoded['items']) && is_array($decoded['items'])) {
                    $rawItems = $decoded['items'];
                } else {
                    $rawItems = $decoded;
                }
                $suggestedBruto = $decoded['bruto'] ?? null;
                $suggestedPencairan = $decoded['total_pembayaran_kalkulasi'] ?? $decoded['matched_master_amount'] ?? $decoded['total_master_amount'] ?? $decoded['total_payment'] ?? null;
                if ($suggestedBruto === null && $suggestedPencairan !== null) {
                    $suggestedBruto = $suggestedPencairan;
                }
            }
        } elseif ($request->filled('items') && is_array($request->input('items'))) {
            $rawItems = $request->input('items');
        } elseif ($request->isJson()) {
            $rawItems = $request->json('items', []);
        }

        if (empty($rawItems)) {
            return response()->json([
                'success' => false,
                'message' => 'Data pesanan kosong atau file JSON tidak valid.',
                'matched_items' => [],
                'missing_skus' => [],
                'resis' => [],
            ], 422);
        }

        $variants = ProductVariant::with(['product.seller', 'stocks' => function ($q) use ($warehouseId) {
            if ($warehouseId) $q->where('warehouse_id', $warehouseId);
        }])->get();

        $skuMap = [];
        foreach ($variants as $v) {
            $skuClean = strtoupper(trim($v->sku));
            $skuMap[$skuClean] = $v;
            $noSpaceSku = str_replace(' ', '', $skuClean);
            if (!isset($skuMap[$noSpaceSku])) {
                $skuMap[$noSpaceSku] = $v;
            }
        }

        $matchedItems = [];
        $missingSkus = [];
        $resisSet = [];

        foreach ($rawItems as $item) {
            if (!is_array($item)) continue;
            $orderId = trim((string)($item['No. Pesanan'] ?? $item['Order ID'] ?? $item['order_id'] ?? ''));
            $resi = trim((string)($item['No. Resi'] ?? $item['Tracking ID'] ?? $item['resi'] ?? ''));
            $rawSku = trim((string)($item['Nomor Referensi SKU'] ?? $item['Seller SKU'] ?? $item['SKU Induk'] ?? $item['sku'] ?? ''));
            $variantName = trim((string)($item['Nama Variasi'] ?? $item['Variation'] ?? ''));
            $productName = trim((string)($item['Nama Produk'] ?? $item['Product Name'] ?? ''));
            $qty = intval($item['Jumlah'] ?? $item['Quantity'] ?? $item['Qty'] ?? 1);
            $paymentAmt = floatval(str_replace(['Rp', '.', ' '], '', (string)($item['Total Pembayaran'] ?? $item['Subtotal Pesanan'] ?? 0)));

            if ($orderId || $resi) {
                $rKey = $orderId ?: $resi;
                $resisSet[$rKey] = [
                    'order_id' => $orderId,
                    'no_resi' => $resi,
                    'payment' => $paymentAmt,
                ];
            }

            if (!$rawSku) continue;

            // 1. Check if rawSku is a multi-component bundle (e.g. SK04-SK03-SK02 or BKA 44,47,50,60)
            $parts = preg_split('/[\+,\,\/\&]+/', $rawSku);
            if (count($parts) === 1 && preg_match('/^([A-Z0-9\s]+)-([A-Z0-9\s]+)/i', $rawSku)) {
                $parts = explode('-', $rawSku);
            }
            $parts = array_filter(array_map('trim', $parts));

            if (count($parts) > 1) {
                // Generic prefix extractor for ANY brand:
                // Examples: "BKA 44" -> "BKA ", "3SECOND 01" -> "3SECOND ", "SK-01" -> "SK-", "POLO 10" -> "POLO "
                $firstPart = reset($parts);
                $prefix = '';
                if (preg_match('/^(.*?\D+)\d+$/', $firstPart, $pm)) {
                    $prefix = $pm[1];
                } elseif (preg_match('/^([A-Za-z0-9\-_]+[\s\-_]+)/', $firstPart, $pm)) {
                    $prefix = $pm[1];
                }

                $subMatched = [];
                $hasMissing = false;

                $partsCount = max(1, count($parts));
                $totalLinePcs = max(1, $qty * $partsCount);
                $unitSelling = $paymentAmt > 0 ? (float)round($paymentAmt / $totalLinePcs, 2) : 0;

                foreach ($parts as $idx => $part) {
                    $testPart = $part;
                    $partV = $this->resolveVariantFromSkuAndSize($testPart, $variantName, $rawSku, $skuMap);

                    // If not resolved and subsequent part is just number/short code, try with prefix
                    if (!$partV && $idx > 0 && $prefix && !preg_match('/^[A-Za-z]/', $part)) {
                        $pTrim = rtrim($prefix);
                        $testPart = $pTrim . ' ' . $part;
                        $partV = $this->resolveVariantFromSkuAndSize($testPart, $variantName, $rawSku, $skuMap);
                        if (!$partV) {
                            $testPart = rtrim($prefix, " \t\n\r\0\x0B-_") . $part;
                            $partV = $this->resolveVariantFromSkuAndSize($testPart, $variantName, $rawSku, $skuMap);
                        }
                    }

                    if ($partV) {
                        $stk = $warehouseId ? ($partV->stocks->firstWhere('warehouse_id', $warehouseId)->qty ?? 0) : ($partV->stocks->sum('qty') ?? 0);
                        $subMatched[] = [
                            'product_variant_id' => $partV->id,
                            'sku' => $partV->sku,
                            'product_name' => $partV->product->product_name ?? $productName,
                            'seller_id' => $partV->product->seller_id ?? null,
                            'seller_name' => $partV->product->seller->seller_name ?? 'Mitra',
                            'size' => $partV->size,
                            'color' => $partV->color,
                            'qty' => $qty,
                            'price' => (float)$partV->price,
                            'selling_price' => $unitSelling > 0 ? $unitSelling : (float)($partV->price ?? 0),
                            'available_stock' => (int)$stk,
                        ];
                    } else {
                        $hasMissing = true;
                    }
                }

                if (!$hasMissing && count($subMatched) === count($parts)) {
                    foreach ($subMatched as $sm) {
                        $matchedItems[] = $sm;
                    }
                    continue;
                } else {
                    $this->recordMissingSku($missingSkus, $rawSku, $productName, $variantName, $qty);
                    continue;
                }
            }

            // 2. Direct resolve on single raw SKU
            $directV = $this->resolveVariantFromSkuAndSize($rawSku, $variantName, $rawSku, $skuMap);
            if ($directV) {
                $stk = $warehouseId ? ($directV->stocks->firstWhere('warehouse_id', $warehouseId)->qty ?? 0) : ($directV->stocks->sum('qty') ?? 0);
                $unitSelling = $paymentAmt > 0 ? (float)round($paymentAmt / max(1, $qty), 2) : (float)($directV->price ?? 0);
                $matchedItems[] = [
                    'product_variant_id' => $directV->id,
                    'sku' => $directV->sku,
                    'product_name' => $directV->product->product_name ?? $productName,
                    'seller_id' => $directV->product->seller_id ?? null,
                    'seller_name' => $directV->product->seller->seller_name ?? 'Mitra',
                    'size' => $directV->size,
                    'color' => $directV->color,
                    'qty' => $qty,
                    'price' => (float)$directV->price,
                    'selling_price' => $unitSelling,
                    'available_stock' => (int)$stk,
                ];
                continue;
            }

            // 3. Fallback missing SKU recording
            $this->recordMissingSku($missingSkus, $rawSku, $productName, $variantName, $qty);
        }

        return response()->json([
            'success' => true,
            'matched_items' => $matchedItems,
            'missing_skus' => array_values($missingSkus),
            'resis' => array_values($resisSet),
            'suggested_bruto' => $suggestedBruto ? (float)$suggestedBruto : null,
            'suggested_pencairan' => $suggestedPencairan ? (float)$suggestedPencairan : null,
        ]);
    }

    public function importChunked(Request $request)
    {
        return $this->processOrders($request);
    }

    /**
     * Extract apparel sizes from variation text, sku, or product name
     */
    private function extractSizesFromText(string $varText, string $skuText = ''): array
    {
        $combined = $varText . ' ' . $skuText;
        $sizes = [];

        // 1. Standard apparel letter sizes
        if (preg_match_all('/\b(5XL|4XL|3XL|2XL|XXXXXL|XXXXL|XXXL|XXL|XL|L|M|S|XS|L\-XL|ML|ALL\s*SIZE|JUMBO|STANDAR)\b/i', $combined, $matches)) {
            foreach ($matches[1] as $m) {
                $val = strtoupper(trim(preg_replace('/\s+/', ' ', $m)));
                if ($val === 'XXL') $val = '2XL';
                if ($val === 'XXXL') $val = '3XL';
                if ($val === 'XXXXL') $val = '4XL';
                if ($val === 'XXXXXL') $val = '5XL';
                $sizes[] = $val;
            }
        }

        // 2. Explicit size/ukuran prefix with number or code (e.g. "Size: 32", "Ukuran 30", "Size M", but ignore "All Size")
        if (preg_match_all('/(?<!all\s)(?<!plus\s)(?<!big\s)(?<!free\s)\b(?:size|ukuran|sz|no\.?)\s*[:\-]?\s*(\d{1,3}|[A-Za-z0-9]{1,4})\b/i', $varText, $matches)) {
            foreach ($matches[1] as $m) {
                $val = strtoupper(trim($m));
                if (!in_array($val, ['ANAK', 'DEWASA', 'PRIA', 'WANITA', 'COWOK', 'CEWEK', 'BAYI', 'KIDS', 'MAN', 'WOMAN'])) {
                    $sizes[] = $val;
                }
            }
        }

        // 3. Fallback: only if no sizes found yet, inspect comma/slash separated variation segments
        if (empty($sizes) && $varText) {
            $segments = preg_split('/[,\|\/]/', $varText);
            foreach ($segments as $seg) {
                $segTrim = trim($seg);
                if (preg_match('/^(\d{1,3}|[A-Z0-9\-]{1,4})$/i', $segTrim)) {
                    $sizes[] = strtoupper($segTrim);
                }
            }
        }

        return array_values(array_unique($sizes));
    }

    /**
     * Record missing SKU with aggregated sizes, variations, and quantities
     */
    private function recordMissingSku(
        array &$missingSkus,
        string $rawSku,
        string $productName,
        string $variantName,
        int $qty = 1,
        ?string $missingPart = null
    ): void {
        $skuKey = strtoupper(trim($rawSku));
        if (!$skuKey) return;

        if (!isset($missingSkus[$skuKey])) {
            $missingSkus[$skuKey] = [
                'sku' => $rawSku,
                'product_name' => $productName ?: $rawSku,
                'variations' => [],
                'sizes' => [],
                'total_qty' => 0,
                'order_count' => 0,
                'missing_parts' => [],
            ];
        }

        if ($productName && $missingSkus[$skuKey]['product_name'] === $rawSku) {
            $missingSkus[$skuKey]['product_name'] = $productName;
        }

        if ($variantName && !in_array($variantName, $missingSkus[$skuKey]['variations'])) {
            $missingSkus[$skuKey]['variations'][] = $variantName;
        }

        $detectedSizes = $this->extractSizesFromText($variantName, $rawSku);
        foreach ($detectedSizes as $sz) {
            if (!in_array($sz, $missingSkus[$skuKey]['sizes'])) {
                $missingSkus[$skuKey]['sizes'][] = $sz;
            }
        }

        if ($missingPart && !in_array($missingPart, $missingSkus[$skuKey]['missing_parts'])) {
            $missingSkus[$skuKey]['missing_parts'][] = $missingPart;
        }

        $missingSkus[$skuKey]['total_qty'] += $qty;
        $missingSkus[$skuKey]['order_count'] += 1;

        // Legacy compatibility fields
        $missingSkus[$skuKey]['variation'] = implode(', ', $missingSkus[$skuKey]['variations']);
        $missingSkus[$skuKey]['sizes_count'] = count($missingSkus[$skuKey]['sizes']);
        $missingSkus[$skuKey]['has_multiple_sizes'] = count($missingSkus[$skuKey]['sizes']) > 1;
    }

    private function parseSizeTokens(string $text): array
    {
        $sizes = [];
        if (preg_match_all('/\b(5XL|4XL|3XL|2XL|XXL|XL|L|M|S|XS)\b/i', $text, $m)) {
            foreach ($m[1] as $s) {
                $sz = strtoupper($s);
                $sizes[] = $sz;
                if ($sz === '2XL') $sizes[] = 'XXL';
                if ($sz === 'XXL') $sizes[] = '2XL';
                if ($sz === '3XL') $sizes[] = 'XXXL';
                if ($sz === 'XXXL') $sizes[] = '3XL';
            }
        }
        return array_unique($sizes);
    }

    private function resolveVariantFromSkuAndSize(string $rawSku, string $varText, string $fullSkuText, array $skuMap)
    {
        $cleanSku = strtoupper(trim($rawSku));
        $noSpaceSku = str_replace(' ', '', $cleanSku);
        if (!$cleanSku) return null;

        // 1. Direct match
        if (isset($skuMap[$cleanSku])) return $skuMap[$cleanSku];
        if (isset($skuMap[$noSpaceSku])) return $skuMap[$noSpaceSku];

        $sizes = $this->parseSizeTokens($varText . ' ' . $fullSkuText);

        foreach ($sizes as $size) {
            if (isset($skuMap[$cleanSku . '-' . $size])) return $skuMap[$cleanSku . '-' . $size];
            if (isset($skuMap[$noSpaceSku . '-' . $size])) return $skuMap[$noSpaceSku . '-' . $size];
            if (isset($skuMap[$cleanSku . $size])) return $skuMap[$cleanSku . $size];
            if (isset($skuMap[$noSpaceSku . $size])) return $skuMap[$noSpaceSku . $size];
            if (isset($skuMap[$cleanSku . '-' . $size . '-1'])) return $skuMap[$cleanSku . '-' . $size . '-1'];
            if (isset($skuMap[$noSpaceSku . '-' . $size . '-1'])) return $skuMap[$noSpaceSku . '-' . $size . '-1'];

            // Prefix match
            $prefix1 = $cleanSku . '-';
            $prefix2 = $noSpaceSku . '-';
            foreach ($skuMap as $dbSku => $variant) {
                $vSize = strtoupper($variant->size);
                if ((str_starts_with($dbSku, $prefix1) || str_starts_with($dbSku, $prefix2)) && 
                    ($vSize === $size || ($size === 'XXL' && $vSize === '2XL') || ($size === '2XL' && $vSize === 'XXL'))) {
                    return $variant;
                }
            }
        }

        return null;
    }

    public function quickCreateProducts(Request $request)
    {
        $validated = $request->validate([
            'seller_id' => ['required', 'exists:sellers,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'konveksi_id' => ['nullable', 'exists:konveksis,id'],
            'template_id' => ['nullable', 'exists:variant_templates,id'],
            'missing_items' => ['required', 'array', 'min:1'],
        ]);

        $createdVariants = [];

        DB::transaction(function () use ($validated, &$createdVariants) {
            $templateItems = [];
            if (!empty($validated['template_id'])) {
                $templateItems = DB::table('variant_template_items')
                    ->where('variant_template_id', $validated['template_id'])
                    ->get();
            }

            foreach ($validated['missing_items'] as $item) {
                $sku = trim((string)($item['sku'] ?? ''));
                $pName = trim((string)($item['product_name'] ?? 'Product ' . $sku));

                if (!$sku) continue;

                $product = Product::create([
                    'seller_id' => $validated['seller_id'],
                    'category_id' => $validated['category_id'] ?? null,
                    'brand_id' => $validated['brand_id'] ?? null,
                    'konveksi_id' => $validated['konveksi_id'] ?? null,
                    'product_type' => 'single',
                    'sku' => $sku,
                    'product_name' => $pName,
                ]);

                if (count($templateItems) > 0) {
                    foreach ($templateItems as $ti) {
                        $vSku = count($templateItems) > 1 ? "{$sku}-{$ti->size}" : $sku;
                        $variant = ProductVariant::create([
                            'product_id' => $product->id,
                            'sku' => $vSku,
                            'size' => $ti->size ?? 'ALL SIZE',
                            'color' => $ti->color ?? '-',
                            'price' => $ti->price ?? 0,
                            'selling_price' => 0,
                        ]);
                        $createdVariants[] = [
                            'id' => $variant->id,
                            'product_id' => $product->id,
                            'product_name' => $product->product_name,
                            'seller_id' => $product->seller_id,
                            'seller_name' => Seller::find($product->seller_id)->seller_name ?? 'Mitra',
                            'sku' => $variant->sku,
                            'size' => $variant->size,
                            'color' => $variant->color,
                            'price' => (float)$variant->price,
                            'selling_price' => (float)$variant->selling_price,
                            'stocks' => [],
                        ];
                    }
                } else {
                    $variant = ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => $sku,
                        'size' => 'ALL SIZE',
                        'color' => '-',
                        'price' => 0,
                        'selling_price' => 0,
                    ]);
                    $createdVariants[] = [
                        'id' => $variant->id,
                        'product_id' => $product->id,
                        'product_name' => $product->product_name,
                        'seller_id' => $product->seller_id,
                        'seller_name' => Seller::find($product->seller_id)->seller_name ?? 'Mitra',
                        'sku' => $variant->sku,
                        'size' => $variant->size,
                        'color' => $variant->color,
                        'price' => (float)$variant->price,
                        'selling_price' => (float)$variant->selling_price,
                        'stocks' => [],
                    ];
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => count($createdVariants) . ' Varian Produk berhasil dibuat otomatis!',
            'created_variants' => $createdVariants,
        ]);
    }

    public function quickAdjustStock(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'variant_id' => ['required', 'exists:product_variants,id'],
            'add_qty' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $warehouseId = (int)$validated['warehouse_id'];
        $variantId = (int)$validated['variant_id'];
        $addQty = (int)$validated['add_qty'];
        $note = $validated['note'] ?? 'Penyesuaian stok manual dari form Stock Out';

        $newStockQty = 0;

        DB::transaction(function () use ($warehouseId, $variantId, $addQty, $note, &$newStockQty) {
            $stockRecord = Stock::where('warehouse_id', $warehouseId)
                ->where('product_variant_id', $variantId)
                ->first();

            if (!$stockRecord) {
                $stockRecord = new Stock();
                $stockRecord->warehouse_id = $warehouseId;
                $stockRecord->product_variant_id = $variantId;
                $stockRecord->qty = 0;
                if (\Illuminate\Support\Facades\Schema::hasColumn('stocks', 'allocated_qty')) {
                    $stockRecord->allocated_qty = 0;
                }
                $stockRecord->save();
            }

            $qtyBefore = (int)$stockRecord->qty;
            $stockRecord->increment('qty', $addQty);
            $newStockQty = (int)$stockRecord->fresh()->qty;

            try {
                $logData = [
                    'stock_id' => $stockRecord->id,
                    'user_id' => auth()->id() ?? 1,
                    'type' => 'MANUAL_ADD',
                    'quantity_before' => $qtyBefore,
                    'quantity_after' => $newStockQty,
                    'change_amount' => $addQty,
                    'note' => $note,
                    'reference_type' => StockOut::class,
                    'reference_id' => null,
                ];

                if (\Illuminate\Support\Facades\Schema::hasColumn('stock_logs', 'product_variant_id')) {
                    $logData['product_variant_id'] = $variantId;
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('stock_logs', 'warehouse_id')) {
                    $logData['warehouse_id'] = $warehouseId;
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('stock_logs', 'qty_before')) {
                    $logData['qty_before'] = $qtyBefore;
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('stock_logs', 'qty_change')) {
                    $logData['qty_change'] = $addQty;
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('stock_logs', 'qty_after')) {
                    $logData['qty_after'] = $newStockQty;
                }

                StockLog::create($logData);
            } catch (\Throwable $e) {
                \Log::warning("Gagal mencatat StockLog: " . $e->getMessage());
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Berhasil menambahkan {$addQty} pcs stok ke gudang.",
            'new_stock' => $newStockQty,
            'variant_id' => $variantId,
        ]);
    }

    public function quickAdjustStockBulk(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'exists:product_variants,id'],
            'items.*.add_qty' => ['required', 'integer', 'min:1'],
        ]);

        $warehouseId = (int)$validated['warehouse_id'];
        $items = $validated['items'];
        $updatedList = [];

        DB::transaction(function () use ($warehouseId, $items, &$updatedList) {
            foreach ($items as $item) {
                $variantId = (int)$item['variant_id'];
                $addQty = (int)$item['add_qty'];

                $stockRecord = Stock::where('warehouse_id', $warehouseId)
                    ->where('product_variant_id', $variantId)
                    ->first();

                if (!$stockRecord) {
                    $stockRecord = new Stock();
                    $stockRecord->warehouse_id = $warehouseId;
                    $stockRecord->product_variant_id = $variantId;
                    $stockRecord->qty = 0;
                    if (\Illuminate\Support\Facades\Schema::hasColumn('stocks', 'allocated_qty')) {
                        $stockRecord->allocated_qty = 0;
                    }
                    $stockRecord->save();
                }

                $qtyBefore = (int)$stockRecord->qty;
                $stockRecord->increment('qty', $addQty);
                $newStockQty = (int)$stockRecord->fresh()->qty;

                try {
                    $logData = [
                        'stock_id' => $stockRecord->id,
                        'user_id' => auth()->id() ?? 1,
                        'type' => 'MANUAL_ADD',
                        'quantity_before' => $qtyBefore,
                        'quantity_after' => $newStockQty,
                        'change_amount' => $addQty,
                        'note' => 'Penyesuaian stok massal dari form Stock Out',
                        'reference_type' => StockOut::class,
                        'reference_id' => null,
                    ];

                    if (\Illuminate\Support\Facades\Schema::hasColumn('stock_logs', 'product_variant_id')) {
                        $logData['product_variant_id'] = $variantId;
                    }
                    if (\Illuminate\Support\Facades\Schema::hasColumn('stock_logs', 'warehouse_id')) {
                        $logData['warehouse_id'] = $warehouseId;
                    }
                    if (\Illuminate\Support\Facades\Schema::hasColumn('stock_logs', 'qty_before')) {
                        $logData['qty_before'] = $qtyBefore;
                    }
                    if (\Illuminate\Support\Facades\Schema::hasColumn('stock_logs', 'qty_change')) {
                        $logData['qty_change'] = $addQty;
                    }
                    if (\Illuminate\Support\Facades\Schema::hasColumn('stock_logs', 'qty_after')) {
                        $logData['qty_after'] = $newStockQty;
                    }

                    StockLog::create($logData);
                } catch (\Throwable $e) {
                    \Log::warning("Gagal mencatat StockLog bulk: " . $e->getMessage());
                }

                $updatedList[$variantId] = $newStockQty;
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Berhasil menambahkan stok untuk " . count($items) . " produk ke gudang.",
            'updated_stocks' => $updatedList,
        ]);
    }

    private function parseBundleSkuStr($skuStr, $variationStr, $qty)
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

    /**
     * Generate a concise summary for StockLog note from resi_summary and stockOut id
     */
    private function formatStockLogNote(StockOut $stockOut, string $prefix = 'Penjualan Resi'): string
    {
        $resiStr = trim((string)($stockOut->resi_summary ?? ''));

        // If resi_summary is a JSON array string
        if (str_starts_with($resiStr, '[')) {
            $decoded = json_decode($resiStr, true);
            if (is_array($decoded)) {
                $count = count($decoded);
                $first = $decoded[0] ?? [];
                $firstCode = $first['no_resi'] ?? $first['order_id'] ?? null;
                if ($firstCode && $count > 1) {
                    return \Illuminate\Support\Str::limit("{$prefix} #{$firstCode} (+ " . ($count - 1) . " resi) [ID #{$stockOut->id}]", 190);
                } elseif ($firstCode) {
                    return \Illuminate\Support\Str::limit("{$prefix} #{$firstCode} [ID #{$stockOut->id}]", 190);
                }
                return "{$prefix} [ID #{$stockOut->id}] ({$count} pesanan)";
            }
        }

        if (!$resiStr) {
            return "{$prefix} [ID #{$stockOut->id}]";
        }

        return \Illuminate\Support\Str::limit("{$prefix} #{$resiStr} [ID #{$stockOut->id}]", 190);
    }

    private function deductPhysicalStock(int $warehouseId, int $variantId, int $qty, int $stockOutId, string $logNote): void
    {
        $stockRecord = Stock::firstOrCreate(
            ['warehouse_id' => $warehouseId, 'product_variant_id' => $variantId],
            ['qty' => 0, 'allocated_qty' => 0]
        );

        $qtyBefore = $stockRecord->qty;
        $stockRecord->decrement('qty', $qty);
        $qtyAfter = $stockRecord->fresh()->qty;

        $safeNote = \Illuminate\Support\Str::limit($logNote, 190);

        try {
            $logData = [
                'reference_type' => StockOut::class,
                'reference_id' => $stockOutId,
                'qty_before' => $qtyBefore,
                'qty_change' => -$qty,
                'qty_after' => $qtyAfter,
                'note' => $safeNote,
            ];

            if (\Illuminate\Support\Facades\Schema::hasColumn('stock_logs', 'product_variant_id')) {
                $logData['product_variant_id'] = $variantId;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('stock_logs', 'warehouse_id')) {
                $logData['warehouse_id'] = $warehouseId;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('stock_logs', 'stock_id')) {
                $logData['stock_id'] = $stockRecord->id;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('stock_logs', 'user_id')) {
                $logData['user_id'] = auth()->id() ?? 1;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('stock_logs', 'type')) {
                $logData['type'] = 'STOCK_OUT';
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('stock_logs', 'quantity_before')) {
                $logData['quantity_before'] = $qtyBefore;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('stock_logs', 'quantity_after')) {
                $logData['quantity_after'] = $qtyAfter;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('stock_logs', 'change_amount')) {
                $logData['change_amount'] = -$qty;
            }

            StockLog::create($logData);
        } catch (\Throwable $e) {
            \Log::warning("Gagal mencatat StockLog deductPhysicalStock: " . $e->getMessage());
        }
    }

    public function destroy(StockOut $stockOut)
    {
        DB::transaction(function () use ($stockOut) {
            $stockOut->load('items');
            $cancelLogNote = $this->formatStockLogNote($stockOut, "Pembatalan Stock Out Resi");

            foreach ($stockOut->items as $item) {
                $stock = Stock::where('warehouse_id', $stockOut->warehouse_id)
                    ->where('product_variant_id', $item->product_variant_id)
                    ->first();

                if ($stock) {
                    $qtyBefore = $stock->qty;
                    $stock->increment('qty', $item->qty);
                    $qtyAfter = $stock->fresh()->qty;

                    try {
                        $logData = [
                            'reference_type' => StockOut::class,
                            'reference_id' => $stockOut->id,
                            'qty_before' => $qtyBefore,
                            'qty_change' => $item->qty,
                            'qty_after' => $qtyAfter,
                            'note' => $cancelLogNote,
                        ];

                        if (\Illuminate\Support\Facades\Schema::hasColumn('stock_logs', 'product_variant_id')) {
                            $logData['product_variant_id'] = $item->product_variant_id;
                        }
                        if (\Illuminate\Support\Facades\Schema::hasColumn('stock_logs', 'warehouse_id')) {
                            $logData['warehouse_id'] = $stockOut->warehouse_id;
                        }
                        if (\Illuminate\Support\Facades\Schema::hasColumn('stock_logs', 'stock_id')) {
                            $logData['stock_id'] = $stock->id;
                        }
                        if (\Illuminate\Support\Facades\Schema::hasColumn('stock_logs', 'user_id')) {
                            $logData['user_id'] = auth()->id() ?? 1;
                        }
                        if (\Illuminate\Support\Facades\Schema::hasColumn('stock_logs', 'type')) {
                            $logData['type'] = 'STOCK_OUT_CANCEL';
                        }
                        if (\Illuminate\Support\Facades\Schema::hasColumn('stock_logs', 'quantity_before')) {
                            $logData['quantity_before'] = $qtyBefore;
                        }
                        if (\Illuminate\Support\Facades\Schema::hasColumn('stock_logs', 'quantity_after')) {
                            $logData['quantity_after'] = $qtyAfter;
                        }
                        if (\Illuminate\Support\Facades\Schema::hasColumn('stock_logs', 'change_amount')) {
                            $logData['change_amount'] = $item->qty;
                        }

                        StockLog::create($logData);
                    } catch (\Throwable $e) {
                        \Log::warning("Gagal mencatat StockLog destroy: " . $e->getMessage());
                    }
                }
            }

            $stockOut->items()->delete();
            $stockOut->delete();
        });

        return redirect()->back()->with('success', 'Transaksi Stok Keluar berhasil dibatalkan.');
    }

    public function chart()
    {
        $monthlyOut = StockOut::select(
            DB::raw("DATE_FORMAT(date, '%Y-%m') as month_key"),
            DB::raw('SUM(total_selling_price) as total_sales'),
            DB::raw('COUNT(id) as total_transactions')
        )
            ->groupBy('month_key')
            ->orderBy('month_key', 'desc')
            ->take(12)
            ->get();

        return Inertia::render('Superadmin/Warehouse/StockOut/StockOutChart', [
            'monthlyOut' => $monthlyOut,
        ]);
    }

    public function settlement(Request $request)
    {
        $periodId = $request->input('period_id');
        $sellerId = $request->input('seller_id');
        $konveksiId = $request->input('konveksi_id');

        $periods = Period::select('id', 'name', 'start_date', 'end_date')
            ->orderBy('start_date', 'desc')
            ->get();
        $sellers = Seller::select('id', 'seller_name')
            ->orderBy('seller_name', 'asc')
            ->get();
        $konveksis = Konveksi::select('id', 'name')
            ->orderBy('name', 'asc')
            ->get();

        $query = StockOut::with([
            'period',
            'store.seller',
            'items.konveksi',
            'items.productVariant.product.seller',
            'items.productVariant.product.konveksi',
        ]);

        if ($periodId) {
            $query->where('period_id', $periodId);
        }

        if ($sellerId) {
            $query->where(function ($sq) use ($sellerId) {
                $sq->whereHas('store', fn($st) => $st->where('seller_id', $sellerId))
                   ->orWhereHas('items.productVariant.product', fn($pq) => $pq->where('seller_id', $sellerId));
            });
        }

        if ($konveksiId) {
            $query->whereHas('items', function ($iq) use ($konveksiId) {
                $iq->where('konveksi_id', $konveksiId)
                   ->orWhereHas('productVariant.product', fn($pq) => $pq->where('konveksi_id', $konveksiId));
            });
        }

        $stockOuts = $query->orderBy('date', 'desc')->orderBy('id', 'desc')->get();

        // Hierarchical grouping: Period -> Seller -> Store -> Fakturs (with Konveksi Breakdown)
        $periodsGrouped = [];
        $grandTotalPcs = 0;
        $grandTotalNominal = 0;
        $grandTotalFakturs = 0;

        foreach ($stockOuts as $so) {
            // Filter items by konveksi if filter is active
            $matchingItems = $so->items;
            if ($konveksiId) {
                $matchingItems = $matchingItems->filter(function ($item) use ($konveksiId) {
                    return $item->konveksi_id == $konveksiId ||
                        ($item->productVariant && $item->productVariant->product && $item->productVariant->product->konveksi_id == $konveksiId);
                });
            }

            $soPcs = (int) $matchingItems->sum('qty');
            if ($konveksiId) {
                $soNominal = (float) $matchingItems->sum(fn($i) => (float)($i->qty * $i->selling_price));
            } else {
                $soNominal = (float) ($so->total_selling_price > 0
                    ? $so->total_selling_price
                    : $matchingItems->sum(fn($i) => (float)($i->qty * $i->selling_price)));
            }

            if ($soPcs <= 0 && $konveksiId) {
                continue;
            }

            // Calculate per-konveksi breakdown for this stock out
            $soKonveksi = [];
            foreach ($matchingItems as $item) {
                $kId = $item->konveksi_id ?: ($item->productVariant?->product?->konveksi_id ?: 0);
                $kName = $item->konveksi?->name ?: ($item->productVariant?->product?->konveksi?->name ?: 'Tanpa Konveksi');

                if (!isset($soKonveksi[$kId])) {
                    $soKonveksi[$kId] = [
                        'konveksi_id' => $kId,
                        'name' => $kName,
                        'pcs' => 0,
                        'nominal' => 0,
                    ];
                }
                $soKonveksi[$kId]['pcs'] += (int) $item->qty;
                $soKonveksi[$kId]['nominal'] += (float) ($item->qty * $item->selling_price);
            }

            // Period group
            $pId = $so->period_id ?? 0;
            $pName = $so->period?->name ?? 'Tanpa Periode / Umum';
            $pStartDate = $so->period?->start_date ? $so->period->start_date->format('d M Y') : null;
            $pEndDate = $so->period?->end_date ? $so->period->end_date->format('d M Y') : null;

            if (!isset($periodsGrouped[$pId])) {
                $periodsGrouped[$pId] = [
                    'period_id' => $so->period_id,
                    'period_name' => $pName,
                    'start_date' => $pStartDate,
                    'end_date' => $pEndDate,
                    'total_pcs' => 0,
                    'total_nominal' => 0,
                    'total_fakturs' => 0,
                    'konveksis' => [],
                    'sellers' => [],
                ];
            }

            // Seller group
            $sId = null;
            $sName = 'Umum / Non-Seller';
            if ($so->store && $so->store->seller) {
                $sId = $so->store->seller->id;
                $sName = $so->store->seller->seller_name;
            } else {
                foreach ($matchingItems as $item) {
                    if ($item->productVariant?->product?->seller) {
                        $sId = $item->productVariant->product->seller->id;
                        $sName = $item->productVariant->product->seller->seller_name;
                        break;
                    }
                }
            }

            if ($sellerId && $sId != $sellerId) {
                continue;
            }

            $sellerKey = $sId ?? 0;
            if (!isset($periodsGrouped[$pId]['sellers'][$sellerKey])) {
                $periodsGrouped[$pId]['sellers'][$sellerKey] = [
                    'seller_id' => $sId,
                    'seller_name' => $sName,
                    'total_pcs' => 0,
                    'total_nominal' => 0,
                    'konveksis' => [],
                    'stores' => [],
                ];
            }

            // Store group
            $storeId = $so->store_id ?? 0;
            $storeName = $so->store?->store_name ?? 'Penjualan Direct / Tanpa Toko';
            $marketplace = $so->store?->marketplace ?? 'OFFLINE';

            if (!isset($periodsGrouped[$pId]['sellers'][$sellerKey]['stores'][$storeId])) {
                $periodsGrouped[$pId]['sellers'][$sellerKey]['stores'][$storeId] = [
                    'store_id' => $so->store_id,
                    'store_name' => $storeName,
                    'marketplace' => strtoupper($marketplace),
                    'total_pcs' => 0,
                    'nominal_pelunasan' => 0,
                    'total_fakturs' => 0,
                    'konveksis' => [],
                    'fakturs' => [],
                ];
            }

            // Format Resi cleanly (handles raw marketplace JSON string)
            $resiInfo = $this->formatResiSummary($so->resi_summary);

            $noFaktur = 'OUT-' . str_pad((string)$so->id, 5, '0', STR_PAD_LEFT);
            $fakturItem = [
                'id' => $so->id,
                'no_faktur' => $noFaktur,
                'date' => $so->date ? $so->date->format('d M Y') : '-',
                'resi_info' => $resiInfo,
                'pcs' => $soPcs,
                'nominal' => $soNominal,
                'note' => $so->note,
                'konveksis' => array_values($soKonveksi),
                'pdf_url' => route('superadmin.warehouse.stock-out.pdf', $so->id),
            ];

            // Accumulate Konveksi breakdown to Store, Seller, and Period
            foreach ($soKonveksi as $kId => $kData) {
                // Store level
                if (!isset($periodsGrouped[$pId]['sellers'][$sellerKey]['stores'][$storeId]['konveksis'][$kId])) {
                    $periodsGrouped[$pId]['sellers'][$sellerKey]['stores'][$storeId]['konveksis'][$kId] = [
                        'konveksi_id' => $kId,
                        'name' => $kData['name'],
                        'pcs' => 0,
                        'nominal' => 0,
                    ];
                }
                $periodsGrouped[$pId]['sellers'][$sellerKey]['stores'][$storeId]['konveksis'][$kId]['pcs'] += $kData['pcs'];
                $periodsGrouped[$pId]['sellers'][$sellerKey]['stores'][$storeId]['konveksis'][$kId]['nominal'] += $kData['nominal'];

                // Seller level
                if (!isset($periodsGrouped[$pId]['sellers'][$sellerKey]['konveksis'][$kId])) {
                    $periodsGrouped[$pId]['sellers'][$sellerKey]['konveksis'][$kId] = [
                        'konveksi_id' => $kId,
                        'name' => $kData['name'],
                        'pcs' => 0,
                        'nominal' => 0,
                    ];
                }
                $periodsGrouped[$pId]['sellers'][$sellerKey]['konveksis'][$kId]['pcs'] += $kData['pcs'];
                $periodsGrouped[$pId]['sellers'][$sellerKey]['konveksis'][$kId]['nominal'] += $kData['nominal'];

                // Period level
                if (!isset($periodsGrouped[$pId]['konveksis'][$kId])) {
                    $periodsGrouped[$pId]['konveksis'][$kId] = [
                        'konveksi_id' => $kId,
                        'name' => $kData['name'],
                        'pcs' => 0,
                        'nominal' => 0,
                    ];
                }
                $periodsGrouped[$pId]['konveksis'][$kId]['pcs'] += $kData['pcs'];
                $periodsGrouped[$pId]['konveksis'][$kId]['nominal'] += $kData['nominal'];
            }

            // Accumulate counts and amounts
            $periodsGrouped[$pId]['sellers'][$sellerKey]['stores'][$storeId]['fakturs'][] = $fakturItem;
            $periodsGrouped[$pId]['sellers'][$sellerKey]['stores'][$storeId]['total_pcs'] += $soPcs;
            $periodsGrouped[$pId]['sellers'][$sellerKey]['stores'][$storeId]['nominal_pelunasan'] += $soNominal;
            $periodsGrouped[$pId]['sellers'][$sellerKey]['stores'][$storeId]['total_fakturs'] += 1;

            $periodsGrouped[$pId]['sellers'][$sellerKey]['total_pcs'] += $soPcs;
            $periodsGrouped[$pId]['sellers'][$sellerKey]['total_nominal'] += $soNominal;

            $periodsGrouped[$pId]['total_pcs'] += $soPcs;
            $periodsGrouped[$pId]['total_nominal'] += $soNominal;
            $periodsGrouped[$pId]['total_fakturs'] += 1;

            $grandTotalPcs += $soPcs;
            $grandTotalNominal += $soNominal;
            $grandTotalFakturs += 1;
        }

        // Convert associative keys to indexed lists
        $resultPeriods = [];
        foreach ($periodsGrouped as $p) {
            $p['konveksis'] = array_values($p['konveksis']);
            $sellersList = [];
            foreach ($p['sellers'] as $s) {
                $s['konveksis'] = array_values($s['konveksis']);
                $storesList = [];
                foreach ($s['stores'] as $st) {
                    $st['konveksis'] = array_values($st['konveksis']);
                    $storesList[] = $st;
                }
                $s['stores'] = $storesList;
                $sellersList[] = $s;
            }
            $p['sellers'] = $sellersList;
            $resultPeriods[] = $p;
        }

        $summary = [
            'total_periods' => count($resultPeriods),
            'grand_total_pcs' => $grandTotalPcs,
            'grand_total_nominal' => $grandTotalNominal,
            'grand_total_fakturs' => $grandTotalFakturs,
        ];

        return Inertia::render('Superadmin/Warehouse/StockOut/StockOutSettlement', [
            'periods' => $periods,
            'sellers' => $sellers,
            'konveksis' => $konveksis,
            'settlementPeriods' => $resultPeriods,
            'summary' => $summary,
            'filters' => [
                'period_id' => $periodId ? (int)$periodId : null,
                'seller_id' => $sellerId ? (int)$sellerId : null,
                'konveksi_id' => $konveksiId ? (int)$konveksiId : null,
            ],
        ]);
    }

    private function formatResiSummary(?string $raw): array
    {
        if (!$raw || trim($raw) === '') {
            return [
                'total_resi' => 0,
                'label' => '0 Resi',
                'preview' => '-',
                'sample_resis' => [],
            ];
        }

        $trimmed = trim($raw);
        if (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $resis = [];
                foreach ($decoded as $entry) {
                    if (is_array($entry) && !empty($entry['no_resi'])) {
                        $resis[] = trim((string)$entry['no_resi']);
                    } elseif (is_string($entry)) {
                        $resis[] = trim($entry);
                    }
                }

                $total = count($resis);
                $firstFew = array_slice($resis, 0, 2);
                $remaining = max(0, $total - count($firstFew));
                $preview = implode(', ', $firstFew);
                if ($remaining > 0) {
                    $preview .= " (+{$remaining} resi)";
                }

                return [
                    'total_resi' => $total,
                    'label' => "{$total} Resi",
                    'preview' => $preview ?: '-',
                    'sample_resis' => array_slice($resis, 0, 10),
                ];
            }
        }

        $clean = \Illuminate\Support\Str::limit($trimmed, 80);
        return [
            'total_resi' => 1,
            'label' => 'Manual',
            'preview' => $clean,
            'sample_resis' => [$clean],
        ];
    }

    public function sellerDetail(Seller $seller)
    {
        $sellerStockOuts = StockOut::with(['items.productVariant.product'])
            ->whereHas('items.productVariant.product', function ($q) use ($seller) {
                $q->where('seller_id', $seller->id);
            })
            ->latest()
            ->paginate(15);

        return Inertia::render('Superadmin/Warehouse/StockOut/StockOutSellerDetail', [
            'seller' => $seller,
            'sellerStockOuts' => $sellerStockOuts,
        ]);
    }

    public function streamSuratJalan(StockOut $stockOut)
    {
        $stockOut->load(['warehouse', 'user', 'store', 'items.productVariant.product']);
        $pdf = Pdf::loadView('pdf.stock_out_surat_jalan', compact('stockOut'));

        return $pdf->stream("Surat_Jalan_StockOut_{$stockOut->id}.pdf");
    }

    public function exportSummary()
    {
        $stockOuts = StockOut::with(['warehouse', 'store', 'items'])->get();
        $pdf = Pdf::loadView('pdf.stock_out_summary', compact('stockOuts'));

        return $pdf->stream('Summary_Pengeluaran_Barang_StockOut.pdf');
    }
}
