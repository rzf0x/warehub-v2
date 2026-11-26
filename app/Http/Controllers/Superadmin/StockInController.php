<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Konveksi;
use App\Models\Period;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Stock;
use App\Models\StockIn;
use App\Models\StockInItem;
use App\Models\StockLog;
use App\Models\Warehouse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class StockInController extends Controller
{
    /** @var array<string, array<string, int>> */
    private static array $tableColumnsCache = [];

    private function getTableColumns(string $table): array
    {
        $conn = DB::getDefaultConnection();
        $cacheKey = $conn . ':' . $table;
        if (!isset(self::$tableColumnsCache[$cacheKey])) {
            self::$tableColumnsCache[$cacheKey] = array_flip(Schema::getColumnListing($table));
        }

        return self::$tableColumnsCache[$cacheKey];
    }

    private function applyStockInFilters($query, Request $request, ?string $effectivePeriodId = null)
    {
        $search = $request->input('search');
        $warehouseId = $request->input('warehouse_id');
        $periodId = $effectivePeriodId ?? $request->input('period_id');
        if ($periodId === 'all') {
            $periodId = null;
        }
        $sellerId = $request->input('seller_id');
        $konveksiId = $request->input('konveksi_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        return $query
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when($periodId, fn ($q) => $q->where('period_id', $periodId))
            ->when($startDate, fn ($q) => $q->whereDate('date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->whereDate('date', '<=', $endDate))
            ->when($sellerId, function ($q) use ($sellerId) {
                $q->whereHas('items.productVariant.product', fn ($pq) => $pq->where('seller_id', $sellerId));
            })
            ->when($konveksiId, function ($q) use ($konveksiId) {
                $q->whereHas('items', fn ($iq) => $iq->where('konveksi_id', $konveksiId));
            })
            ->when($search, function ($query, $s) {
                $query->where(function ($q) use ($s) {
                    $q->where('invoice_number', 'like', "%{$s}%")
                        ->orWhere('note', 'like', "%{$s}%")
                        ->orWhereHas('warehouse', fn ($wh) => $wh->where('name', 'like', "%{$s}%"))
                        ->orWhereHas('items.konveksi', fn ($kv) => $kv->where('name', 'like', "%{$s}%"))
                        ->orWhereHas('items.productVariant.product.seller', fn ($sel) => $sel->where('seller_name', 'like', "%{$s}%"))
                        ->orWhereHas('items.productVariant', fn ($pv) => $pv->where('sku', 'like', "%{$s}%")->orWhereHas('product', fn ($p) => $p->where('product_name', 'like', "%{$s}%")));
                });
            });
    }

    public function index(Request $request)
    {
        $search = $request->input('search');
        $warehouseId = $request->input('warehouse_id');
        $sellerId = $request->input('seller_id');
        $konveksiId = $request->input('konveksi_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $periodCols = $this->getTableColumns('periods');
        $periodSelect = ['id', 'name'];
        if (isset($periodCols['is_active'])) {
            $periodSelect[] = 'is_active';
        }
        if (isset($periodCols['is_public'])) {
            $periodSelect[] = 'is_public';
        }
        $periods = Period::select($periodSelect)->latest('id')->get();

        // Default to active period when period_id is not explicitly provided
        $requestedPeriodId = $request->input('period_id');
        if ($requestedPeriodId === null || $requestedPeriodId === '') {
            $activePeriod = $periods->first(function ($p) {
                return (bool) ($p->is_active ?? $p->is_public ?? false);
            }) ?? $periods->first();
            $periodId = $activePeriod ? (string) $activePeriod->id : 'all';
        } else {
            $periodId = (string) $requestedPeriodId;
        }

        $effectivePeriodId = ($periodId !== '' && $periodId !== 'all') ? $periodId : null;

        $baseQuery = $this->applyStockInFilters(StockIn::query(), $request, $effectivePeriodId);

        // Lightweight query: no deep Eloquent item hydration
        $stockIns = $baseQuery
            ->select('id', 'warehouse_id', 'user_id', 'period_id', 'invoice_number', 'date', 'note')
            ->with([
                'warehouse:id,name',
                'user:id,name',
                'period:id,name',
            ])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        $stockInIds = $stockIns->pluck('id')->all();

        $itemStatsMap = [];
        $sellersMap = [];
        $konveksisMap = [];

        if (!empty($stockInIds)) {
            $itemStatsMap = DB::table('stock_in_items')
                ->whereIn('stock_in_id', $stockInIds)
                ->selectRaw('stock_in_id, COUNT(*) as item_count, COALESCE(SUM(qty), 0) as total_qty, COALESCE(SUM(qty * price), 0) as total_nominal')
                ->groupBy('stock_in_id')
                ->get()
                ->keyBy('stock_in_id')
                ->all();

            $sellerRows = DB::table('stock_in_items as sii')
                ->join('product_variants as pv', 'pv.id', '=', 'sii.product_variant_id')
                ->join('products as p', 'p.id', '=', 'pv.product_id')
                ->join('sellers as s', 's.id', '=', 'p.seller_id')
                ->whereIn('sii.stock_in_id', $stockInIds)
                ->select('sii.stock_in_id', 's.seller_name')
                ->distinct()
                ->orderBy('s.seller_name')
                ->get();

            foreach ($sellerRows as $row) {
                if ($row->seller_name) {
                    $sellersMap[$row->stock_in_id][] = $row->seller_name;
                }
            }

            $konveksiRows = DB::table('stock_in_items as sii')
                ->join('konveksis as k', 'k.id', '=', 'sii.konveksi_id')
                ->whereIn('sii.stock_in_id', $stockInIds)
                ->select('sii.stock_in_id', 'k.name')
                ->distinct()
                ->orderBy('k.name')
                ->get();

            foreach ($konveksiRows as $row) {
                if ($row->name) {
                    $konveksisMap[$row->stock_in_id][] = $row->name;
                }
            }
        }

        $totalQty = 0;
        $totalNominal = 0.0;

        $stockInsPayload = $stockIns->map(function (StockIn $si) use ($itemStatsMap, $sellersMap, $konveksisMap, &$totalQty, &$totalNominal) {
            $stat = $itemStatsMap[$si->id] ?? null;
            $rowQty = (int) ($stat->total_qty ?? 0);
            $rowNominal = (float) ($stat->total_nominal ?? 0);
            $rowCount = (int) ($stat->item_count ?? 0);

            $totalQty += $rowQty;
            $totalNominal += $rowNominal;

            return [
                'id' => $si->id,
                'detail_key' => StockIn::encodeRouteKey((int) $si->id),
                'invoice_number' => $si->invoice_number,
                'date' => $si->date ? $si->date->format('Y-m-d') : '',
                'note' => $si->note,
                'warehouse' => $si->warehouse ? ['id' => $si->warehouse->id, 'name' => $si->warehouse->name] : null,
                'period' => $si->period ? ['id' => $si->period->id, 'name' => $si->period->name] : null,
                'user' => $si->user ? ['id' => $si->user->id, 'name' => $si->user->name] : null,
                'sellers' => $sellersMap[$si->id] ?? [],
                'konveksis' => $konveksisMap[$si->id] ?? [],
                'total_qty' => $rowQty,
                'total_nominal' => $rowNominal,
                'item_count' => $rowCount,
            ];
        })->values();

        $warehouses = Warehouse::select('id', 'name')->get();
        $sellers = Seller::select('id', 'seller_name')->orderBy('seller_name')->get();
        $konveksis = Konveksi::select('id', 'name')->orderBy('name')->get();

        $summary = [
            'total_transactions' => $stockInsPayload->count(),
            'total_qty' => (int) $totalQty,
            'total_nominal' => (float) $totalNominal,
            'total_sellers' => $sellers->count(),
        ];

        return Inertia::render('Superadmin/Warehouse/StockIn/Index', [
            'stockIns' => $stockInsPayload,
            'warehouses' => $warehouses,
            'periods' => $periods,
            'sellers' => $sellers,
            'konveksis' => $konveksis,
            'summary' => $summary,
            'filters' => [
                'search' => $search,
                'warehouse_id' => $warehouseId,
                'period_id' => $periodId,
                'seller_id' => $sellerId,
                'konveksi_id' => $konveksiId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    public function show(string $token)
    {
        $id = StockIn::decodeRouteKey($token);
        if ($id === null) {
            abort(404);
        }

        $stockIn = StockIn::with([
            'warehouse:id,name',
            'user:id,name',
            'period:id,name',
            'items' => fn ($q) => $q->orderBy('id'),
            'items.konveksi:id,name',
            'items.productVariant:id,product_id,sku,size,color,price',
            'items.productVariant.product:id,product_name,product_type,seller_id',
            'items.productVariant.product.seller:id,seller_name',
        ])->findOrFail($id);

        $items = $stockIn->items->map(function ($item) {
            $variant = $item->productVariant;
            $product = $variant?->product;
            $qty = (int) ($item->qty ?? 0);
            $price = (float) ($item->price ?? 0);

            return [
                'id' => $item->id,
                'sku' => $variant->sku ?? '-',
                'product_name' => $product->product_name ?? '-',
                'product_type' => $product->product_type ?? 'single',
                'size' => $variant->size ?? '-',
                'color' => $variant->color ?? '-',
                'seller_name' => $product?->seller?->seller_name ?? '-',
                'konveksi_name' => $item->konveksi?->name ?? '-',
                'qty' => $qty,
                'price' => $price,
                'subtotal_hpp' => round($qty * $price, 2),
            ];
        })->sort(function (array $a, array $b) {
            $baseA = $this->extractBaseSkuForSort($a['sku'], $a['size']);
            $baseB = $this->extractBaseSkuForSort($b['sku'], $b['size']);

            $baseCmp = strnatcasecmp($baseA, $baseB);
            if ($baseCmp !== 0) {
                return $baseCmp;
            }

            $rankA = $this->getApparelSizeRank($a['size'], $a['sku']);
            $rankB = $this->getApparelSizeRank($b['size'], $b['sku']);
            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }

            return strnatcasecmp($a['sku'], $b['sku']);
        })->values();

        $uniqueSellers = $items->pluck('seller_name')->filter(fn ($s) => $s && $s !== '-')->unique()->values()->all();
        $uniqueKonveksis = $items->pluck('konveksi_name')->filter(fn ($k) => $k && $k !== '-')->unique()->values()->all();

        return Inertia::render('Superadmin/Warehouse/StockIn/Show', [
            'stockIn' => [
                'id' => $stockIn->id,
                'detail_key' => StockIn::encodeRouteKey((int) $stockIn->id),
                'invoice_number' => $stockIn->invoice_number,
                'date' => $stockIn->date ? $stockIn->date->format('Y-m-d') : '',
                'note' => $stockIn->note,
                'warehouse' => $stockIn->warehouse ? ['id' => $stockIn->warehouse->id, 'name' => $stockIn->warehouse->name] : null,
                'user' => $stockIn->user ? ['id' => $stockIn->user->id, 'name' => $stockIn->user->name] : null,
                'period' => $stockIn->period ? ['id' => $stockIn->period->id, 'name' => $stockIn->period->name] : null,
                'sellers' => $uniqueSellers,
                'konveksis' => $uniqueKonveksis,
                'created_at' => $stockIn->created_at?->format('Y-m-d H:i'),
            ],
            'items' => $items,
            'metrics' => [
                'total_qty' => (int) $items->sum('qty'),
                'total_hpp' => (float) $items->sum('subtotal_hpp'),
                'total_variants' => $items->count(),
                'total_sellers' => count($uniqueSellers),
                'total_konveksis' => count($uniqueKonveksis),
            ],
        ]);
    }

    private function getApparelSizeRank(?string $sizeStr, ?string $skuStr = null): float
    {
        static $order = [
            '3XS' => 1, 'XXXS' => 1,
            '2XS' => 2, 'XXS' => 2,
            'XS' => 3,
            'S' => 4,
            'M' => 5,
            'ML' => 6, 'M-L' => 6,
            'L' => 7,
            'L-XL' => 8,
            'XL' => 9,
            '2XL' => 10, 'XXL' => 10,
            '3XL' => 11, 'XXXL' => 11,
            '4XL' => 12, 'XXXXL' => 12,
            '5XL' => 13, 'XXXXXL' => 13,
            '6XL' => 14, 'XXXXXXL' => 14,
            '7XL' => 15,
            'ALL SIZE' => 50, 'ALLSIZE' => 50,
            'STANDAR' => 51, 'STANDARD' => 51,
            'JUMBO' => 52,
            '-' => 99, '' => 100,
        ];

        $s = strtoupper(trim((string) preg_replace('/\s+/', ' ', (string) $sizeStr)));
        if (($s === '' || $s === '-') && $skuStr) {
            if (preg_match('/[\-\s_](7XL|6XL|5XL|4XL|3XL|2XL|XXXXXXL|XXXXXL|XXXXL|XXXL|XXL|XL|L|M|S|XS|\d{1,3})(?:\-1)?$/i', strtoupper($skuStr), $m)) {
                $s = $m[1];
            }
        }

        if (isset($order[$s])) {
            return (float) $order[$s];
        }

        if (preg_match('/^\d+(\.\d+)?$/', $s)) {
            return 20.0 + ((float) $s * 0.1);
        }

        if (preg_match('/\b(7XL|6XL|5XL|4XL|3XL|2XL|XXXXXXL|XXXXXL|XXXXL|XXXL|XXL|XL|L|M|S|XS)\b/', $s, $m) && isset($order[$m[1]])) {
            return (float) $order[$m[1]];
        }

        if (preg_match('/\b(\d{1,3})\b/', $s, $m)) {
            return 20.0 + ((float) $m[1] * 0.1);
        }

        return 80.0;
    }

    private function extractBaseSkuForSort(?string $skuStr, ?string $sizeStr = null): string
    {
        $raw = strtoupper(trim((string) $skuStr));
        if ($raw === '') {
            return '';
        }

        $sz = strtoupper(trim((string) $sizeStr));
        if ($sz !== '' && $sz !== '-') {
            $escaped = preg_quote($sz, '/');
            $stripped = preg_replace('/[\-\s_]*' . $escaped . '(?:\-1)?$/i', '', $raw);
            if ($stripped !== null && trim($stripped) !== '') {
                return trim($stripped);
            }
        }

        $stripped = preg_replace('/[\-\s_]+(7XL|6XL|5XL|4XL|3XL|2XL|XXXXXXL|XXXXXL|XXXXL|XXXL|XXL|XL|L|M|S|XS|ALL\s*SIZE|JUMBO|STANDAR)(?:\-1)?$/i', '', $raw);

        return trim($stripped ?? $raw);
    }

    public function getSellerProducts(Request $request)
    {
        $sellerId = $request->input('seller_id');

        if (!$sellerId) {
            return response()->json([]);
        }

        $products = Product::where('seller_id', $sellerId)
            ->with(['variants:id,product_id,sku,size,color,price'])
            ->select('id', 'seller_id', 'product_name', 'sku')
            ->orderBy('product_name')
            ->get();

        return response()->json($products);
    }

    public function searchVariants(Request $request)
    {
        $q = $request->input('q');
        $variants = ProductVariant::with('product:id,product_name')
            ->select('id', 'product_id', 'sku', 'size', 'color', 'price')
            ->when($q, function ($query, $s) {
                $query->where('sku', 'like', "%{$s}%")
                    ->orWhereHas('product', fn ($pq) => $pq->where('product_name', 'like', "%{$s}%"));
            })
            ->latest()
            ->limit(50)
            ->get();

        return response()->json($variants);
    }

    private function generateUniqueInvoiceNumber(?string $date = null): string
    {
        $datePart = $date ? date('Ymd', strtotime($date)) : now()->format('Ymd');
        do {
            $randomDigits = str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
            $invoiceNumber = "WHB/IN/{$datePart}/{$randomDigits}";
        } while (StockIn::where('invoice_number', $invoiceNumber)->exists());

        return $invoiceNumber;
    }

    public function create()
    {
        return Inertia::render('Superadmin/Warehouse/StockIn/Form', [
            'warehouses' => Warehouse::select('id', 'name')->get(),
            'periods' => Period::select('id', 'name')->latest('id')->get(),
            'sellers' => Seller::select('id', 'seller_name')->orderBy('seller_name')->get(),
            'konveksis' => Konveksi::select('id', 'name')->orderBy('name')->get(),
            'defaultInvoiceNumber' => $this->generateUniqueInvoiceNumber(),
        ]);
    }

    public function edit(StockIn $stockIn)
    {
        $stockIn->load(['items.productVariant.product']);

        $sellerIds = $stockIn->items
            ->map(fn ($item) => $item->productVariant?->product?->seller_id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $initialSellerProducts = [];
        if (!empty($sellerIds)) {
            $grouped = Product::whereIn('seller_id', $sellerIds)
                ->with(['variants:id,product_id,sku,size,color,price'])
                ->select('id', 'seller_id', 'product_name', 'sku')
                ->orderBy('product_name')
                ->get()
                ->groupBy('seller_id');

            foreach ($sellerIds as $sid) {
                $initialSellerProducts[$sid] = $grouped->get($sid, collect())->values();
            }
        }

        return Inertia::render('Superadmin/Warehouse/StockIn/Form', [
            'warehouses' => Warehouse::select('id', 'name')->get(),
            'periods' => Period::select('id', 'name')->latest('id')->get(),
            'sellers' => Seller::select('id', 'seller_name')->orderBy('seller_name')->get(),
            'konveksis' => Konveksi::select('id', 'name')->orderBy('name')->get(),
            'stockIn' => [
                'id' => $stockIn->id,
                'warehouse_id' => $stockIn->warehouse_id,
                'period_id' => $stockIn->period_id,
                'invoice_number' => $stockIn->invoice_number,
                'date' => $stockIn->date ? $stockIn->date->format('Y-m-d') : now()->format('Y-m-d'),
                'note' => $stockIn->note,
                'items' => $stockIn->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_id' => $item->productVariant?->product_id,
                    'seller_id' => $item->productVariant?->product?->seller_id,
                    'konveksi_id' => $item->konveksi_id,
                    'qty' => (int) $item->qty,
                    'price' => (float) $item->price,
                ])->values(),
            ],
            'initialSellerProducts' => $initialSellerProducts,
        ]);
    }

    private function validateBatchReferences(array $items): void
    {
        $variantIds = array_values(array_unique(array_map(fn ($i) => (int) $i['product_variant_id'], $items)));
        if (ProductVariant::whereIn('id', $variantIds)->count() !== count($variantIds)) {
            throw ValidationException::withMessages([
                'items' => ['Terdapat varian produk yang tidak ditemukan di database.'],
            ]);
        }

        $konveksiIds = array_values(array_unique(array_filter(array_map(
            fn ($i) => !empty($i['konveksi_id']) ? (int) $i['konveksi_id'] : null,
            $items
        ))));
        if (!empty($konveksiIds) && Konveksi::whereIn('id', $konveksiIds)->count() !== count($konveksiIds)) {
            throw ValidationException::withMessages([
                'items' => ['Terdapat data konveksi yang tidak ditemukan di database.'],
            ]);
        }
    }

    private function bulkAdjustStockColumn(int $warehouseId, array $deltaByVariantId): void
    {
        if (empty($deltaByVariantId)) {
            return;
        }

        $nowStr = now()->toDateTimeString();

        foreach (array_chunk($deltaByVariantId, 200, true) as $chunk) {
            $cases = [];
            $bindings = [];
            $variantIds = [];

            foreach ($chunk as $variantId => $delta) {
                $deltaInt = (int) $delta;
                if ($deltaInt === 0) {
                    continue;
                }
                $cases[] = 'WHEN ? THEN ?';
                $bindings[] = (int) $variantId;
                $bindings[] = $deltaInt;
                $variantIds[] = (int) $variantId;
            }

            if (empty($variantIds)) {
                continue;
            }

            $bindings[] = $nowStr;
            $bindings[] = $warehouseId;
            $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
            foreach ($variantIds as $vId) {
                $bindings[] = $vId;
            }

            $sql = 'UPDATE stocks SET qty = qty + CASE product_variant_id '
                . implode(' ', $cases)
                . " ELSE 0 END, updated_at = ? WHERE warehouse_id = ? AND product_variant_id IN ({$placeholders})";

            DB::update($sql, $bindings);
        }
    }

    private function buildStockLogRow(
        array $logColumns,
        int $stockId,
        int $variantId,
        int $warehouseId,
        int $userId,
        int $stockInId,
        int $qtyBefore,
        int $qtyChange,
        int $qtyAfter,
        string $type,
        string $note,
        string $nowStr
    ): array {
        $row = [
            'reference_type' => StockIn::class,
            'reference_id' => $stockInId,
            'note' => Str::limit($note, 190),
            'created_at' => $nowStr,
            'updated_at' => $nowStr,
        ];

        if (isset($logColumns['stock_id'])) {
            $row['stock_id'] = $stockId;
        }
        if (isset($logColumns['product_variant_id'])) {
            $row['product_variant_id'] = $variantId;
        }
        if (isset($logColumns['warehouse_id'])) {
            $row['warehouse_id'] = $warehouseId;
        }
        if (isset($logColumns['user_id'])) {
            $row['user_id'] = $userId;
        }
        if (isset($logColumns['type'])) {
            $row['type'] = $type;
        }
        if (isset($logColumns['qty_before'])) {
            $row['qty_before'] = $qtyBefore;
        }
        if (isset($logColumns['qty_change'])) {
            $row['qty_change'] = $qtyChange;
        }
        if (isset($logColumns['qty_after'])) {
            $row['qty_after'] = $qtyAfter;
        }
        if (isset($logColumns['quantity_before'])) {
            $row['quantity_before'] = $qtyBefore;
        }
        if (isset($logColumns['quantity_after'])) {
            $row['quantity_after'] = $qtyAfter;
        }
        if (isset($logColumns['change_amount'])) {
            $row['change_amount'] = $qtyChange;
        }

        return $row;
    }

    private function bulkRevertStockInItems(StockIn $stockIn, int $userId, string $notePrefix, string $logType): void
    {
        $oldItems = $stockIn->items;
        if ($oldItems->isEmpty()) {
            return;
        }

        $warehouseId = (int) $stockIn->warehouse_id;
        $variantIds = $oldItems->pluck('product_variant_id')->filter()->unique()->values()->all();
        if (empty($variantIds)) {
            return;
        }

        $existingStocks = Stock::where('warehouse_id', $warehouseId)
            ->whereIn('product_variant_id', $variantIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('product_variant_id');

        $runningQty = [];
        $stockIdMap = [];
        foreach ($existingStocks as $pvId => $stk) {
            $runningQty[(int) $pvId] = (int) $stk->qty;
            $stockIdMap[(int) $pvId] = (int) $stk->id;
        }

        $logColumns = $this->getTableColumns('stock_logs');
        $nowStr = now()->toDateTimeString();
        $revertDeltas = [];
        $stockLogRows = [];
        $note = "{$notePrefix} Invoice #{$stockIn->invoice_number}";

        foreach ($oldItems as $oldItem) {
            $pvId = (int) $oldItem->product_variant_id;
            if (!isset($stockIdMap[$pvId])) {
                continue;
            }

            $qtyBefore = $runningQty[$pvId] ?? 0;
            $deductQty = min(max(0, $qtyBefore), (int) $oldItem->qty);
            $qtyAfter = $qtyBefore - $deductQty;
            $runningQty[$pvId] = $qtyAfter;
            $revertDeltas[$pvId] = ($revertDeltas[$pvId] ?? 0) - $deductQty;

            $stockLogRows[] = $this->buildStockLogRow(
                $logColumns,
                $stockIdMap[$pvId],
                $pvId,
                $warehouseId,
                $userId,
                (int) $stockIn->id,
                $qtyBefore,
                -$deductQty,
                $qtyAfter,
                $logType,
                $note,
                $nowStr
            );
        }

        $this->bulkAdjustStockColumn($warehouseId, $revertDeltas);

        if (!empty($stockLogRows)) {
            try {
                foreach (array_chunk($stockLogRows, 300) as $chunk) {
                    StockLog::insert($chunk);
                }
            } catch (\Throwable $e) {
                \Log::warning('Gagal mencatat bulk StockLog revert StockIn: ' . $e->getMessage());
            }
        }
    }

    private function bulkInsertItemsAndIncrementStocks(StockIn $stockIn, array $items, int $userId, string $notePrefix): void
    {
        $warehouseId = (int) $stockIn->warehouse_id;
        $nowStr = now()->toDateTimeString();

        $stockInItemRows = [];
        $variantIdsMap = [];

        foreach ($items as $itemData) {
            $pvId = (int) $itemData['product_variant_id'];
            $stockInItemRows[] = [
                'stock_in_id' => $stockIn->id,
                'product_variant_id' => $pvId,
                'konveksi_id' => !empty($itemData['konveksi_id']) ? (int) $itemData['konveksi_id'] : null,
                'qty' => (int) $itemData['qty'],
                'price' => (float) $itemData['price'],
                'created_at' => $nowStr,
                'updated_at' => $nowStr,
            ];
            $variantIdsMap[$pvId] = true;
        }

        foreach (array_chunk($stockInItemRows, 300) as $chunk) {
            StockInItem::insert($chunk);
        }

        $variantIds = array_keys($variantIdsMap);
        if (empty($variantIds)) {
            return;
        }

        $existingStocks = Stock::where('warehouse_id', $warehouseId)
            ->whereIn('product_variant_id', $variantIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('product_variant_id');

        $hasAllocatedQty = isset($this->getTableColumns('stocks')['allocated_qty']);
        $missingStockRows = [];
        foreach ($variantIds as $pvId) {
            if (!$existingStocks->has($pvId)) {
                $row = [
                    'warehouse_id' => $warehouseId,
                    'product_variant_id' => $pvId,
                    'qty' => 0,
                    'created_at' => $nowStr,
                    'updated_at' => $nowStr,
                ];
                if ($hasAllocatedQty) {
                    $row['allocated_qty'] = 0;
                }
                $missingStockRows[] = $row;
            }
        }

        if (!empty($missingStockRows)) {
            foreach (array_chunk($missingStockRows, 300) as $chunk) {
                Stock::insert($chunk);
            }
            $existingStocks = Stock::where('warehouse_id', $warehouseId)
                ->whereIn('product_variant_id', $variantIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('product_variant_id');
        }

        $runningQty = [];
        $stockIdMap = [];
        foreach ($existingStocks as $pvId => $stk) {
            $runningQty[(int) $pvId] = (int) $stk->qty;
            $stockIdMap[(int) $pvId] = (int) $stk->id;
        }

        $logColumns = $this->getTableColumns('stock_logs');
        $addDeltas = [];
        $stockLogRows = [];
        $note = "{$notePrefix} Invoice #{$stockIn->invoice_number}";

        foreach ($items as $itemData) {
            $pvId = (int) $itemData['product_variant_id'];
            $addQty = (int) $itemData['qty'];
            $qtyBefore = $runningQty[$pvId] ?? 0;
            $qtyAfter = $qtyBefore + $addQty;
            $runningQty[$pvId] = $qtyAfter;
            $addDeltas[$pvId] = ($addDeltas[$pvId] ?? 0) + $addQty;

            $stockLogRows[] = $this->buildStockLogRow(
                $logColumns,
                $stockIdMap[$pvId] ?? 0,
                $pvId,
                $warehouseId,
                $userId,
                (int) $stockIn->id,
                $qtyBefore,
                $addQty,
                $qtyAfter,
                'STOCK_IN',
                $note,
                $nowStr
            );
        }

        $this->bulkAdjustStockColumn($warehouseId, $addDeltas);

        if (!empty($stockLogRows)) {
            try {
                foreach (array_chunk($stockLogRows, 300) as $chunk) {
                    StockLog::insert($chunk);
                }
            } catch (\Throwable $e) {
                \Log::warning('Gagal mencatat bulk StockLog StockIn: ' . $e->getMessage());
            }
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'period_id' => ['nullable', 'exists:periods,id'],
            'invoice_number' => ['nullable', 'string', 'max:100', 'unique:stock_ins,invoice_number'],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer'],
            'items.*.konveksi_id' => ['nullable', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ]);

        $this->validateBatchReferences($validated['items']);

        $invoiceNumber = !empty($validated['invoice_number'])
            ? $validated['invoice_number']
            : $this->generateUniqueInvoiceNumber($validated['date']);

        DB::transaction(function () use ($validated, $request, $invoiceNumber) {
            $stockIn = StockIn::create([
                'warehouse_id' => (int) $validated['warehouse_id'],
                'user_id' => $request->user()->id,
                'period_id' => $validated['period_id'] ?? null,
                'invoice_number' => $invoiceNumber,
                'date' => $validated['date'],
                'note' => $validated['note'] ?? null,
            ]);

            $this->bulkInsertItemsAndIncrementStocks($stockIn, $validated['items'], (int) $request->user()->id, 'Stock In / Receipt');
        });

        return redirect()->route('superadmin.warehouse.stock-in.index')->with('success', 'Transaksi Stok Masuk berhasil disimpan.');
    }

    public function update(Request $request, StockIn $stockIn)
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'period_id' => ['nullable', 'exists:periods,id'],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer'],
            'items.*.konveksi_id' => ['nullable', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ]);

        $this->validateBatchReferences($validated['items']);

        DB::transaction(function () use ($validated, $request, $stockIn) {
            $stockIn->load('items');
            $userId = (int) $request->user()->id;

            // 1. Revert old stocks in bulk
            $this->bulkRevertStockInItems($stockIn, $userId, 'Revisi (Revert) Stock In', 'STOCK_IN_REVERT');
            $stockIn->items()->delete();

            // 2. Update header (invoice_number unchanged)
            $stockIn->update([
                'warehouse_id' => (int) $validated['warehouse_id'],
                'period_id' => $validated['period_id'] ?? null,
                'date' => $validated['date'],
                'note' => $validated['note'] ?? null,
            ]);

            // 3. Bulk insert new items & increment stocks
            $this->bulkInsertItemsAndIncrementStocks($stockIn, $validated['items'], $userId, 'Update Stock In');
        });

        return redirect()->route('superadmin.warehouse.stock-in.index')->with('success', 'Transaksi Stok Masuk berhasil diperbarui.');
    }

    public function destroy(StockIn $stockIn)
    {
        DB::transaction(function () use ($stockIn) {
            $stockIn->load('items');
            $userId = (int) (auth()->id() ?? 1);

            $this->bulkRevertStockInItems($stockIn, $userId, 'Pembatalan Stock In', 'STOCK_IN_CANCEL');

            $stockIn->items()->delete();
            $stockIn->delete();
        });

        return redirect()->back()->with('success', 'Transaksi Stok Masuk berhasil dibatalkan/dihapus.');
    }

    public function streamSuratJalan(StockIn $stockIn)
    {
        $stockIn->load(['warehouse', 'user', 'period', 'items.productVariant.product.seller', 'items.konveksi']);
        $pdf = Pdf::loadView('pdf.stock_in_surat_jalan', compact('stockIn'));
        $safeInvoice = str_replace(['/', '\\'], '-', $stockIn->invoice_number);

        return $pdf->stream("Surat_Jalan_StockIn_{$safeInvoice}.pdf");
    }

    public function exportReport(Request $request)
    {
        $stockIns = $this->applyStockInFilters(
            StockIn::with(['warehouse', 'user', 'period', 'items.productVariant.product.seller', 'items.konveksi']),
            $request
        )
            ->latest('date')
            ->latest('id')
            ->get();

        $pdf = Pdf::loadView('pdf.stock_in_report', compact('stockIns'));

        return $pdf->stream('Laporan_Penerimaan_Barang_StockIn.pdf');
    }
}
