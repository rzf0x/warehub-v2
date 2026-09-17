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
                      ->orWhere('note', 'like', "%{$s}%");
                });
            })
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->when($periodId, fn($q) => $q->where('period_id', $periodId))
            ->when($userId, fn($q) => $q->where('user_id', $userId));

        $stockOuts = (clone $baseQuery)
            ->with(['warehouse', 'user', 'store.seller', 'period', 'items.productVariant.product.seller'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $filteredIds = (clone $baseQuery)->pluck('id');

        $totalQty = StockOutItem::whereIn('stock_out_id', $filteredIds)->sum('qty');

        $totalHpp = (clone $baseQuery)->sum('total_amount');
        if ($totalHpp == 0) {
            $totalHpp = StockOutItem::whereIn('stock_out_id', $filteredIds)->sum(DB::raw('qty * price'));
        }

        $totalBruto = (clone $baseQuery)->sum('bruto');
        if ($totalBruto == 0) {
            $totalBruto = (clone $baseQuery)->sum('total_selling_price');
        }

        $penjualanBersih = (clone $baseQuery)->sum('total_selling_price');
        $estimasiKeuntungan = $penjualanBersih - $totalHpp;

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

        $variants = ProductVariant::with(['product.seller', 'stocks'])
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

        $variants = ProductVariant::with(['product.seller', 'stocks'])
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

                if ($variant && $variant->product && $variant->product->product_type === 'bundle') {
                    $bundleItems = BundleItem::where('bundle_variant_id', $variant->id)->get();
                    foreach ($bundleItems as $bItem) {
                        $deductQty = ($bItem->qty ?? $bItem->quantity ?? 1) * $itemData['qty'];
                        $this->deductPhysicalStock($validated['warehouse_id'], $bItem->component_variant_id, $deductQty, $stockOut->id, "Penjualan Bundle #{$stockOut->resi_summary}");
                    }
                } else {
                    $this->deductPhysicalStock($validated['warehouse_id'], $itemData['product_variant_id'], $itemData['qty'], $stockOut->id, "Penjualan Resi #{$stockOut->resi_summary}");
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
                $stock = Stock::where('warehouse_id', $stockOut->warehouse_id)
                    ->where('product_variant_id', $item->product_variant_id)
                    ->first();

                if ($stock) {
                    $stock->increment('qty', $item->qty);
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

                if ($variant && $variant->product && $variant->product->product_type === 'bundle') {
                    $bundleItems = BundleItem::where('bundle_variant_id', $variant->id)->get();
                    foreach ($bundleItems as $bItem) {
                        $deductQty = ($bItem->qty ?? $bItem->quantity ?? 1) * $itemData['qty'];
                        $this->deductPhysicalStock($validated['warehouse_id'], $bItem->component_variant_id, $deductQty, $stockOut->id, "Update Stock Out Bundle #{$stockOut->resi_summary}");
                    }
                } else {
                    $this->deductPhysicalStock($validated['warehouse_id'], $itemData['product_variant_id'], $itemData['qty'], $stockOut->id, "Update Stock Out Resi #{$stockOut->resi_summary}");
                }
            }
        });

        return redirect()->route('superadmin.warehouse.stock-out.index')->with('success', 'Transaksi Stok Keluar berhasil diperbarui.');
    }

    public function importChunked(Request $request)
    {
        $request->validate([
            'items' => ['required', 'array'],
            'warehouse_id' => ['nullable', 'integer'],
        ]);

        $rawItems = $request->input('items', []);
        $warehouseId = $request->input('warehouse_id');

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

            // 1. Check if rawSku is a multi-component bundle (e.g. SK04-SK03-SK02 or BKA42-BKA43-BKA46-BKA47)
            $parts = preg_split('/[\+,\,]+/', $rawSku);
            if (count($parts) === 1 && preg_match('/^([A-Z0-9\s]+)-([A-Z0-9\s]+)/i', $rawSku)) {
                $parts = explode('-', $rawSku);
            }
            $parts = array_filter(array_map('trim', $parts));

            if (count($parts) > 1) {
                $subMatched = [];
                $hasMissing = false;

                foreach ($parts as $part) {
                    $partV = $this->resolveVariantFromSkuAndSize($part, $variantName, $rawSku, $skuMap);
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
                            'selling_price' => (float)$paymentAmt > 0 ? (float)round($paymentAmt / max(1, count($parts))) : (float)($partV->price ?? 0),
                            'available_stock' => (int)$stk,
                        ];
                    } else {
                        $hasMissing = true;
                        $missingSkus[$part] = [
                            'sku' => $part,
                            'product_name' => $productName,
                            'variation' => $variantName,
                        ];
                    }
                }

                if (!$hasMissing && count($subMatched) > 0) {
                    foreach ($subMatched as $sm) {
                        $matchedItems[] = $sm;
                    }
                    continue;
                }
            }

            // 2. Direct resolve on single raw SKU
            $directV = $this->resolveVariantFromSkuAndSize($rawSku, $variantName, $rawSku, $skuMap);
            if ($directV) {
                $stk = $warehouseId ? ($directV->stocks->firstWhere('warehouse_id', $warehouseId)->qty ?? 0) : ($directV->stocks->sum('qty') ?? 0);
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
                    'selling_price' => (float)$paymentAmt > 0 ? (float)$paymentAmt : (float)($directV->price ?? 0),
                    'available_stock' => (int)$stk,
                ];
                continue;
            }

            // 3. Fallback missing SKU recording
            $missingSkus[strtoupper($rawSku)] = [
                'sku' => $rawSku,
                'product_name' => $productName,
                'variation' => $variantName,
            ];
        }

        return response()->json([
            'success' => true,
            'matched_items' => $matchedItems,
            'missing_skus' => array_values($missingSkus),
            'resis' => array_values($resisSet),
        ]);
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

    private function deductPhysicalStock(int $warehouseId, int $variantId, int $qty, int $stockOutId, string $logNote): void
    {
        $stockRecord = Stock::firstOrCreate(
            ['warehouse_id' => $warehouseId, 'product_variant_id' => $variantId],
            ['qty' => 0, 'allocated_qty' => 0]
        );

        $qtyBefore = $stockRecord->qty;
        $stockRecord->decrement('qty', $qty);
        $qtyAfter = $stockRecord->fresh()->qty;

        StockLog::create([
            'product_variant_id' => $variantId,
            'warehouse_id' => $warehouseId,
            'reference_type' => StockOut::class,
            'reference_id' => $stockOutId,
            'qty_before' => $qtyBefore,
            'qty_change' => -$qty,
            'qty_after' => $qtyAfter,
            'note' => $logNote,
        ]);
    }

    public function destroy(StockOut $stockOut)
    {
        DB::transaction(function () use ($stockOut) {
            $stockOut->load('items');
            foreach ($stockOut->items as $item) {
                $stock = Stock::where('warehouse_id', $stockOut->warehouse_id)
                    ->where('product_variant_id', $item->product_variant_id)
                    ->first();

                if ($stock) {
                    $qtyBefore = $stock->qty;
                    $stock->increment('qty', $item->qty);
                    $qtyAfter = $stock->fresh()->qty;

                    StockLog::create([
                        'product_variant_id' => $item->product_variant_id,
                        'warehouse_id' => $stockOut->warehouse_id,
                        'reference_type' => StockOut::class,
                        'reference_id' => $stockOut->id,
                        'qty_before' => $qtyBefore,
                        'qty_change' => $item->qty,
                        'qty_after' => $qtyAfter,
                        'note' => "Pembatalan Stock Out Resi #{$stockOut->resi_summary}",
                    ]);
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

    public function settlement()
    {
        $settlements = StockOut::with(['store', 'warehouse'])
            ->select('store_id', DB::raw('SUM(total_selling_price) as gross_sales'), DB::raw('COUNT(id) as total_orders'))
            ->groupBy('store_id')
            ->get();

        return Inertia::render('Superadmin/Warehouse/StockOut/StockOutSettlement', [
            'settlements' => $settlements,
        ]);
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
