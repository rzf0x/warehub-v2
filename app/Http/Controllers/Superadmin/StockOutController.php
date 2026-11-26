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
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class StockOutController extends Controller
{
    /** @var array<string, bool> */
    private static array $hasTotalAmountColCache = [];

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
            'stocks',
            'bundleAsBundle.componentVariant.stocks',
            'bundleAsBundle.componentVariant.product'
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
            ->map(function ($v) use ($warehouseId) {
                $stockInfo = $this->getVariantStockInfo($v, $warehouseId);
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
                    'stocks' => $stockInfo['is_bundle']
                        ? $this->calculateBundleStocksMap($v)
                        : $v->stocks->pluck('qty', 'warehouse_id'),
                    'is_bundle' => $stockInfo['is_bundle'],
                    'bundle_components' => $stockInfo['bundle_components'],
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
            'bruto' => ['nullable', 'numeric'],
            'total_selling_price' => ['nullable', 'numeric'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer'],
            'items.*.konveksi_id' => ['nullable', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.selling_price' => ['required', 'numeric'],
        ]);

        $this->validateBatchReferences($validated['items']);

        $this->persistStockOutBulk(
            header: [
                'warehouse_id' => (int) $validated['warehouse_id'],
                'store_id' => $validated['store_id'] ?? null,
                'period_id' => $validated['period_id'] ?? null,
                'resi_summary' => $validated['resi_summary'],
                'date' => $validated['date'],
                'note' => $validated['note'] ?? null,
                'bruto' => $validated['bruto'] ?? null,
                'total_selling_price' => $request->filled('total_selling_price') ? (float) $request->input('total_selling_price') : null,
            ],
            items: $validated['items'],
            userId: (int) $request->user()->id,
        );

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
            'bruto' => ['nullable', 'numeric'],
            'total_selling_price' => ['nullable', 'numeric'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer'],
            'items.*.konveksi_id' => ['nullable', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.selling_price' => ['required', 'numeric'],
        ]);

        $this->validateBatchReferences($validated['items']);

        $this->persistStockOutBulk(
            header: [
                'warehouse_id' => (int) $validated['warehouse_id'],
                'store_id' => $validated['store_id'] ?? null,
                'period_id' => $validated['period_id'] ?? null,
                'resi_summary' => $validated['resi_summary'],
                'date' => $validated['date'],
                'note' => $validated['note'] ?? null,
                'bruto' => $validated['bruto'] ?? null,
                'total_selling_price' => $request->filled('total_selling_price') ? (float) $request->input('total_selling_price') : null,
            ],
            items: $validated['items'],
            userId: (int) $request->user()->id,
            existingStockOut: $stockOut,
        );

        return redirect()->route('superadmin.warehouse.stock-out.index')->with('success', 'Transaksi Stok Keluar berhasil diperbarui.');
    }

    public function initChunkedSave(Request $request)
    {
        $validated = $request->validate([
            'stock_out_id' => ['nullable', 'integer', 'exists:stock_outs,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'store_id' => ['nullable', 'exists:stores,id'],
            'period_id' => ['nullable', 'exists:periods,id'],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
            'bruto' => ['nullable', 'numeric'],
            'total_selling_price' => ['nullable', 'numeric'],
            'fallback_resi_summary' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->cleanupStaleStagingDirs();

        $token = (string) Str::uuid();
        $dir = $this->getStagingDir($token);
        File::ensureDirectoryExists($dir);

        $meta = [
            'user_id' => (int) $request->user()->id,
            'stock_out_id' => isset($validated['stock_out_id']) ? (int) $validated['stock_out_id'] : null,
            'warehouse_id' => (int) $validated['warehouse_id'],
            'store_id' => $validated['store_id'] ?? null,
            'period_id' => $validated['period_id'] ?? null,
            'date' => $validated['date'],
            'note' => $validated['note'] ?? null,
            'bruto' => $validated['bruto'] ?? null,
            'total_selling_price' => $request->filled('total_selling_price') ? (float) $request->input('total_selling_price') : null,
            'fallback_resi_summary' => $validated['fallback_resi_summary'] ?? null,
            'created_at' => now()->timestamp,
        ];

        File::put($dir . DIRECTORY_SEPARATOR . 'meta.json', json_encode($meta));

        return response()->json([
            'success' => true,
            'token' => $token,
        ]);
    }

    public function appendChunkedSave(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'uuid'],
            'type' => ['required', 'in:items,resis'],
            'chunk' => ['required', 'array', 'min:1'],
        ]);

        $token = $validated['token'];
        $dir = $this->getStagingDir($token);
        $meta = $this->readStagingMeta($dir, (int) $request->user()->id);

        if (!$meta) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi penyimpanan tidak ditemukan atau sudah kedaluwarsa. Silakan ulangi simpan.',
            ], 404);
        }

        $type = $validated['type'];
        $filePath = $dir . DIRECTORY_SEPARATOR . ($type === 'items' ? 'items.jsonl' : 'resis.jsonl');

        $lines = [];
        foreach ($validated['chunk'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $lines[] = json_encode($row, JSON_UNESCAPED_UNICODE);
        }

        if (!empty($lines)) {
            file_put_contents($filePath, implode("\n", $lines) . "\n", FILE_APPEND | LOCK_EX);
        }

        return response()->json([
            'success' => true,
            'received' => count($lines),
        ]);
    }

    public function commitChunkedSave(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'uuid'],
        ]);

        $token = $validated['token'];
        $dir = $this->getStagingDir($token);
        $meta = $this->readStagingMeta($dir, (int) $request->user()->id);

        if (!$meta) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi penyimpanan tidak ditemukan atau sudah kedaluwarsa. Silakan ulangi simpan.',
            ], 404);
        }

        $items = $this->readJsonLines($dir . DIRECTORY_SEPARATOR . 'items.jsonl');
        $resis = $this->readJsonLines($dir . DIRECTORY_SEPARATOR . 'resis.jsonl');

        if (empty($items)) {
            File::deleteDirectory($dir);
            return response()->json([
                'success' => false,
                'message' => 'Daftar item barang keluar kosong.',
            ], 422);
        }

        $normalizedItems = [];
        foreach ($items as $idx => $item) {
            $variantId = isset($item['product_variant_id']) ? (int) $item['product_variant_id'] : 0;
            $qty = isset($item['qty']) ? (int) $item['qty'] : 0;
            if ($variantId <= 0 || $qty < 1) {
                File::deleteDirectory($dir);
                return response()->json([
                    'success' => false,
                    'message' => 'Data item ke-' . ($idx + 1) . ' tidak valid (varian/qty).',
                ], 422);
            }

            $normalizedItems[] = [
                'product_variant_id' => $variantId,
                'konveksi_id' => !empty($item['konveksi_id']) ? (int) $item['konveksi_id'] : null,
                'qty' => $qty,
                'price' => max(0.0, (float) ($item['price'] ?? 0)),
                'selling_price' => (float) ($item['selling_price'] ?? 0),
            ];
        }

        try {
            $this->validateBatchReferences($normalizedItems);
        } catch (ValidationException $e) {
            File::deleteDirectory($dir);
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Validasi referensi item gagal.',
                'errors' => $e->errors(),
            ], 422);
        }

        $resiSummary = !empty($resis)
            ? json_encode($resis, JSON_UNESCAPED_UNICODE)
            : (string) ($meta['fallback_resi_summary'] ?: ($meta['note'] ?: ('Manual Out ' . $meta['date'])));

        $existingStockOut = null;
        if (!empty($meta['stock_out_id'])) {
            $existingStockOut = StockOut::with('items')->findOrFail((int) $meta['stock_out_id']);
        }

        $stockOut = $this->persistStockOutBulk(
            header: [
                'warehouse_id' => (int) $meta['warehouse_id'],
                'store_id' => $meta['store_id'] ?? null,
                'period_id' => $meta['period_id'] ?? null,
                'resi_summary' => $resiSummary,
                'date' => $meta['date'],
                'note' => $meta['note'] ?? null,
                'bruto' => $meta['bruto'] ?? null,
                'total_selling_price' => $meta['total_selling_price'] ?? null,
            ],
            items: $normalizedItems,
            userId: (int) $request->user()->id,
            existingStockOut: $existingStockOut,
            preParsedResis: !empty($resis) ? $resis : null,
        );

        File::deleteDirectory($dir);

        $message = $existingStockOut
            ? 'Transaksi Stok Keluar berhasil diperbarui.'
            : 'Transaksi Stok Keluar berhasil disimpan.';

        session()->flash('success', $message);

        return response()->json([
            'success' => true,
            'message' => $message,
            'stock_out_id' => $stockOut->id,
            'redirect_url' => route('superadmin.warehouse.stock-out.index'),
        ]);
    }

    public function abortChunkedSave(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'uuid'],
        ]);

        $dir = $this->getStagingDir($validated['token']);
        $meta = $this->readStagingMeta($dir, (int) $request->user()->id);

        if ($meta) {
            File::deleteDirectory($dir);
        }

        return response()->json(['success' => true]);
    }

    private function getStagingBaseDir(): string
    {
        return storage_path('app/private/stock-out-staging');
    }

    private function getStagingDir(string $token): string
    {
        return $this->getStagingBaseDir() . DIRECTORY_SEPARATOR . $token;
    }

    private function readStagingMeta(string $dir, int $userId): ?array
    {
        $metaPath = $dir . DIRECTORY_SEPARATOR . 'meta.json';
        if (!File::exists($metaPath)) {
            return null;
        }
        $meta = json_decode((string) File::get($metaPath), true);
        if (!is_array($meta) || (int) ($meta['user_id'] ?? 0) !== $userId) {
            return null;
        }
        return $meta;
    }

    private function readJsonLines(string $filePath): array
    {
        if (!File::exists($filePath)) {
            return [];
        }

        $rows = [];
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return [];
        }

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $rows[] = $decoded;
            }
        }
        fclose($handle);

        return $rows;
    }

    private function cleanupStaleStagingDirs(): void
    {
        $baseDir = $this->getStagingBaseDir();
        if (!File::isDirectory($baseDir)) {
            return;
        }

        $cutoff = now()->subHours(2)->timestamp;
        foreach (File::directories($baseDir) as $dir) {
            $metaPath = $dir . DIRECTORY_SEPARATOR . 'meta.json';
            $mtime = File::exists($metaPath) ? File::lastModified($metaPath) : File::lastModified($dir);
            if ($mtime < $cutoff) {
                File::deleteDirectory($dir);
            }
        }
    }

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

    private function getStockLogColumns(): array
    {
        return $this->getTableColumns('stock_logs');
    }

    private function stocksHasAllocatedQty(): bool
    {
        return isset($this->getTableColumns('stocks')['allocated_qty']);
    }

    private function validateBatchReferences(array $items): void
    {
        $variantIds = array_values(array_unique(array_map(fn ($i) => (int) $i['product_variant_id'], $items)));
        $existingVariantCount = ProductVariant::whereIn('id', $variantIds)->count();
        if ($existingVariantCount !== count($variantIds)) {
            throw ValidationException::withMessages([
                'items' => ['Terdapat varian produk yang tidak ditemukan di database.'],
            ]);
        }

        $konveksiIds = array_values(array_unique(array_filter(array_map(
            fn ($i) => !empty($i['konveksi_id']) ? (int) $i['konveksi_id'] : null,
            $items
        ))));
        if (!empty($konveksiIds)) {
            $existingKonveksiCount = Konveksi::whereIn('id', $konveksiIds)->count();
            if ($existingKonveksiCount !== count($konveksiIds)) {
                throw ValidationException::withMessages([
                    'items' => ['Terdapat data konveksi yang tidak ditemukan di database.'],
                ]);
            }
        }
    }

    private function bulkAdjustStockColumn(int $warehouseId, array $deltaByVariantId): void
    {
        if (empty($deltaByVariantId)) {
            return;
        }

        $now = now()->toDateTimeString();

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

            $bindings[] = $now;
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

    private function persistStockOutBulk(
        array $header,
        array $items,
        int $userId,
        ?StockOut $existingStockOut = null,
        ?array $preParsedResis = null
    ): StockOut {
        return DB::transaction(function () use ($header, $items, $userId, $existingStockOut, $preParsedResis) {
            $now = now();
            $nowStr = $now->toDateTimeString();

            // 1. If updating an existing StockOut, restore old physical stocks in bulk
            if ($existingStockOut) {
                $oldItems = $existingStockOut->items;
                if ($oldItems->isNotEmpty()) {
                    $oldVariantIds = $oldItems->pluck('product_variant_id')->filter()->unique()->values()->all();
                    $oldVariants = ProductVariant::with('product:id,product_type')
                        ->whereIn('id', $oldVariantIds)
                        ->get()
                        ->keyBy('id');

                    $oldBundleIds = $oldVariants
                        ->filter(fn ($v) => $v->product && $v->product->product_type === 'bundle')
                        ->keys()
                        ->all();

                    $oldBundleMap = !empty($oldBundleIds)
                        ? BundleItem::whereIn('bundle_variant_id', $oldBundleIds)->get()->groupBy('bundle_variant_id')
                        : collect();

                    $restoreDeltas = [];
                    foreach ($oldItems as $oldItem) {
                        $oldVar = $oldVariants->get($oldItem->product_variant_id);
                        if ($oldVar && $oldVar->product && $oldVar->product->product_type === 'bundle') {
                            foreach ($oldBundleMap->get($oldVar->id, []) as $bItem) {
                                $compId = (int) $bItem->component_variant_id;
                                $compQty = (int) ($bItem->qty ?? $bItem->quantity ?? 1) * (int) $oldItem->qty;
                                $restoreDeltas[$compId] = ($restoreDeltas[$compId] ?? 0) + $compQty;
                            }
                        } else {
                            $vId = (int) $oldItem->product_variant_id;
                            $restoreDeltas[$vId] = ($restoreDeltas[$vId] ?? 0) + (int) $oldItem->qty;
                        }
                    }

                    $this->bulkAdjustStockColumn((int) $existingStockOut->warehouse_id, $restoreDeltas);
                }

                $existingStockOut->items()->delete();
            }

            // 2. Compute totals once
            $totalHpp = 0.0;
            $rawItemsSellingSum = 0.0;
            foreach ($items as $item) {
                $totalHpp += (float) $item['price'] * (int) $item['qty'];
                $rawItemsSellingSum += (float) $item['selling_price'] * (int) $item['qty'];
            }
            $totalSelling = $rawItemsSellingSum;

            $parsedResis = $preParsedResis ?? json_decode((string) $header['resi_summary'], true);
            $hasResiPayments = false;
            $resiPaymentSum = 0.0;
            if (is_array($parsedResis)) {
                foreach ($parsedResis as $r) {
                    if (is_array($r) && isset($r['payment']) && is_numeric($r['payment'])) {
                        $hasResiPayments = true;
                        $resiPaymentSum += (float) $r['payment'];
                    }
                }
            }

            if ($hasResiPayments) {
                $totalSelling = $resiPaymentSum;
            } elseif ($header['total_selling_price'] !== null) {
                $totalSelling = (float) $header['total_selling_price'];
            }

            $brutoVal = (float) ($header['bruto'] ?? $totalSelling);
            if ($brutoVal <= 0) {
                $brutoVal = $totalSelling;
            }

            $stockOutColumns = $this->getTableColumns('stock_outs');
            $stockOutPayload = [
                'warehouse_id' => (int) $header['warehouse_id'],
                'user_id' => $userId,
                'store_id' => $header['store_id'] ?? null,
                'period_id' => $header['period_id'] ?? null,
                'resi_summary' => $header['resi_summary'],
                'date' => $header['date'],
                'note' => $header['note'] ?? null,
                'total_selling_price' => $totalSelling,
                'bruto' => $brutoVal,
            ];
            if (isset($stockOutColumns['total_amount'])) {
                $stockOutPayload['total_amount'] = $totalHpp;
            }

            if ($existingStockOut) {
                $existingStockOut->update($stockOutPayload);
                $stockOut = $existingStockOut;
            } else {
                $stockOut = StockOut::create($stockOutPayload);
            }

            $scaleRatio = ($rawItemsSellingSum > 0 && abs($rawItemsSellingSum - $totalSelling) > 0.01)
                ? ($totalSelling / $rawItemsSellingSum)
                : 1.0;

            // 3. Preload all variants & bundle components in 2 queries total
            $variantIds = array_values(array_unique(array_map(fn ($i) => (int) $i['product_variant_id'], $items)));
            $variants = ProductVariant::with('product:id,product_type')
                ->whereIn('id', $variantIds)
                ->select('id', 'product_id')
                ->get()
                ->keyBy('id');

            $bundleVariantIds = $variants
                ->filter(fn ($v) => $v->product && $v->product->product_type === 'bundle')
                ->keys()
                ->all();

            $bundleMap = !empty($bundleVariantIds)
                ? BundleItem::whereIn('bundle_variant_id', $bundleVariantIds)->get()->groupBy('bundle_variant_id')
                : collect();

            // 4. Compute StockLog notes ONCE (no repeated json_decode in loop!)
            $notePrefix = $existingStockOut ? 'Update Stock Out Resi' : 'Penjualan Resi';
            $bundleNotePrefix = $existingStockOut ? 'Update Stock Out Bundle' : 'Penjualan Bundle';
            $logNote = Str::limit($this->formatStockLogNoteFromParsed($stockOut, $parsedResis, $notePrefix), 190);
            $bundleLogNote = Str::limit($this->formatStockLogNoteFromParsed($stockOut, $parsedResis, $bundleNotePrefix), 190);

            // 5. Build StockOutItem rows & physical deduction entries in memory
            $stockOutItemColumns = $this->getTableColumns('stock_out_items');
            $hasItemSubtotalCol = isset($stockOutItemColumns['subtotal']);
            $stockOutItemRows = [];
            $deductionEntries = [];
            $physicalVariantIdsMap = [];

            foreach ($items as $itemData) {
                $vId = (int) $itemData['product_variant_id'];
                $qty = (int) $itemData['qty'];
                $price = (float) $itemData['price'];
                $adjustedSellingPrice = round((float) $itemData['selling_price'] * $scaleRatio, 2);

                $itemRow = [
                    'stock_out_id' => $stockOut->id,
                    'product_variant_id' => $vId,
                    'konveksi_id' => $itemData['konveksi_id'] ?? null,
                    'qty' => $qty,
                    'price' => $price,
                    'selling_price' => $adjustedSellingPrice,
                    'created_at' => $nowStr,
                    'updated_at' => $nowStr,
                ];
                if ($hasItemSubtotalCol) {
                    $itemRow['subtotal'] = $price * $qty;
                }
                $stockOutItemRows[] = $itemRow;

                $variant = $variants->get($vId);
                if ($variant && $variant->product && $variant->product->product_type === 'bundle') {
                    foreach ($bundleMap->get($variant->id, []) as $bItem) {
                        $compId = (int) $bItem->component_variant_id;
                        $deductQty = (int) ($bItem->qty ?? $bItem->quantity ?? 1) * $qty;
                        $deductionEntries[] = [
                            'variant_id' => $compId,
                            'qty' => $deductQty,
                            'note' => $bundleLogNote,
                        ];
                        $physicalVariantIdsMap[$compId] = true;
                    }
                } else {
                    $deductionEntries[] = [
                        'variant_id' => $vId,
                        'qty' => $qty,
                        'note' => $logNote,
                    ];
                    $physicalVariantIdsMap[$vId] = true;
                }
            }

            // Bulk insert StockOutItem in chunks
            foreach (array_chunk($stockOutItemRows, 300) as $chunk) {
                StockOutItem::insert($chunk);
            }

            // 6. Bulk preload / create missing Stock rows, then bulk update & bulk insert StockLog
            $warehouseId = (int) $header['warehouse_id'];
            $physicalVariantIds = array_keys($physicalVariantIdsMap);

            if (!empty($physicalVariantIds)) {
                $existingStocks = Stock::where('warehouse_id', $warehouseId)
                    ->whereIn('product_variant_id', $physicalVariantIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('product_variant_id');

                $missingStockRows = [];
                $hasAllocatedQty = $this->stocksHasAllocatedQty();
                foreach ($physicalVariantIds as $pvId) {
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
                        ->whereIn('product_variant_id', $physicalVariantIds)
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

                $logColumns = $this->getStockLogColumns();
                $deductDeltas = [];
                $stockLogRows = [];

                foreach ($deductionEntries as $entry) {
                    $pvId = (int) $entry['variant_id'];
                    $deductQty = (int) $entry['qty'];
                    $qtyBefore = $runningQty[$pvId] ?? 0;
                    $qtyAfter = $qtyBefore - $deductQty;
                    $runningQty[$pvId] = $qtyAfter;
                    $deductDeltas[$pvId] = ($deductDeltas[$pvId] ?? 0) - $deductQty;

                    $logRow = [
                        'reference_type' => StockOut::class,
                        'reference_id' => $stockOut->id,
                        'note' => $entry['note'],
                        'created_at' => $nowStr,
                        'updated_at' => $nowStr,
                    ];

                    if (isset($logColumns['stock_id'])) {
                        $logRow['stock_id'] = $stockIdMap[$pvId] ?? null;
                    }
                    if (isset($logColumns['product_variant_id'])) {
                        $logRow['product_variant_id'] = $pvId;
                    }
                    if (isset($logColumns['warehouse_id'])) {
                        $logRow['warehouse_id'] = $warehouseId;
                    }
                    if (isset($logColumns['user_id'])) {
                        $logRow['user_id'] = $userId;
                    }
                    if (isset($logColumns['type'])) {
                        $logRow['type'] = 'STOCK_OUT';
                    }
                    if (isset($logColumns['qty_before'])) {
                        $logRow['qty_before'] = $qtyBefore;
                    }
                    if (isset($logColumns['qty_change'])) {
                        $logRow['qty_change'] = -$deductQty;
                    }
                    if (isset($logColumns['qty_after'])) {
                        $logRow['qty_after'] = $qtyAfter;
                    }
                    if (isset($logColumns['quantity_before'])) {
                        $logRow['quantity_before'] = $qtyBefore;
                    }
                    if (isset($logColumns['quantity_after'])) {
                        $logRow['quantity_after'] = $qtyAfter;
                    }
                    if (isset($logColumns['change_amount'])) {
                        $logRow['change_amount'] = -$deductQty;
                    }

                    $stockLogRows[] = $logRow;
                }

                $this->bulkAdjustStockColumn($warehouseId, $deductDeltas);

                try {
                    foreach (array_chunk($stockLogRows, 300) as $chunk) {
                        StockLog::insert($chunk);
                    }
                } catch (\Throwable $e) {
                    \Log::warning('Gagal mencatat bulk StockLog: ' . $e->getMessage());
                }
            }

            return $stockOut;
        });
    }

    private function formatStockLogNoteFromParsed(StockOut $stockOut, mixed $parsedResis, string $prefix = 'Penjualan Resi'): string
    {
        if (is_array($parsedResis)) {
            $count = count($parsedResis);
            $first = $parsedResis[0] ?? [];
            $firstCode = is_array($first) ? ($first['no_resi'] ?? $first['order_id'] ?? null) : null;
            if ($firstCode && $count > 1) {
                return Str::limit("{$prefix} #{$firstCode} (+ " . ($count - 1) . " resi) [ID #{$stockOut->id}]", 190);
            } elseif ($firstCode) {
                return Str::limit("{$prefix} #{$firstCode} [ID #{$stockOut->id}]", 190);
            }
            return "{$prefix} [ID #{$stockOut->id}] ({$count} pesanan)";
        }

        $resiStr = trim((string) ($stockOut->resi_summary ?? ''));
        if ($resiStr === '') {
            return "{$prefix} [ID #{$stockOut->id}]";
        }

        return Str::limit("{$prefix} #{$resiStr} [ID #{$stockOut->id}]", 190);
    }

    public function processOrders(Request $request)
    {
        $warehouseId = $request->input('warehouse_id');
        $rawItems = [];

        $suggestedBruto = null;
        $suggestedPencairan = null;
        $expectedFileQty = null;

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
                $expectedFileQty = $decoded['total_qty_satuan'] ?? $decoded['total_qty_product_ditemukan'] ?? null;
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

        $variants = ProductVariant::with([
            'product:id,seller_id,product_name,product_type,sku',
            'product.seller:id,seller_name',
            'stocks' => function ($q) use ($warehouseId) {
                if ($warehouseId) $q->where('warehouse_id', $warehouseId);
            },
            'bundleAsBundle.componentVariant:id,product_id,sku,size,color,price',
            'bundleAsBundle.componentVariant.stocks' => function ($q) use ($warehouseId) {
                if ($warehouseId) $q->where('warehouse_id', $warehouseId);
            },
            'bundleAsBundle.componentVariant.product:id,product_name'
        ])->select('id', 'product_id', 'sku', 'size', 'color', 'price')->get();

        $skuMap = [];
        $sizeGroups = [];
        foreach ($variants as $v) {
            $skuClean = strtoupper(trim($v->sku));
            $skuMap[$skuClean] = $v;
            $noSpaceSku = str_replace(' ', '', $skuClean);
            if (!isset($skuMap[$noSpaceSku])) {
                $skuMap[$noSpaceSku] = $v;
            }

            $vSize = strtoupper(trim((string)$v->size));
            $canonicalSize = match ($vSize) {
                'XXL' => '2XL',
                'XXXL' => '3XL',
                'XXXXL' => '4XL',
                'XXXXXL' => '5XL',
                default => $vSize,
            };
            if ($canonicalSize !== '') {
                $entry = [
                    'dbSku' => $skuClean,
                    'noSpaceSku' => $noSpaceSku,
                    'variant' => $v,
                ];
                $sizeGroups[$canonicalSize][] = $entry;
                if ($canonicalSize !== $vSize) {
                    $sizeGroups[$vSize][] = $entry;
                }
            }
        }

        $stockInfoCache = [];
        $getCachedStockInfo = function ($v) use ($warehouseId, &$stockInfoCache) {
            if (!isset($stockInfoCache[$v->id])) {
                $stockInfoCache[$v->id] = $this->getVariantStockInfo($v, $warehouseId);
            }
            return $stockInfoCache[$v->id];
        };

        $resolveCache = [];
        $resolveCached = function (string $testSku, string $varText, string $fullSkuText) use (&$resolveCache, $skuMap, $sizeGroups) {
            $cacheKey = strtoupper(trim($testSku)) . '|' . strtoupper(trim($varText)) . '|' . strtoupper(trim($fullSkuText));
            if (!array_key_exists($cacheKey, $resolveCache)) {
                $resolveCache[$cacheKey] = $this->resolveVariantFromSkuAndSize($testSku, $varText, $fullSkuText, $skuMap, $sizeGroups);
            }
            return $resolveCache[$cacheKey];
        };

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
                if (!isset($resisSet[$rKey])) {
                    $resisSet[$rKey] = [
                        'order_id' => $orderId,
                        'no_resi' => $resi,
                        'payment' => 0.0,
                    ];
                }
                $resisSet[$rKey]['payment'] = round($resisSet[$rKey]['payment'] + $paymentAmt, 2);
                if ($resi && empty($resisSet[$rKey]['no_resi'])) {
                    $resisSet[$rKey]['no_resi'] = $resi;
                }
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
                    $partV = $resolveCached($testPart, $variantName, $rawSku);

                    // If not resolved and subsequent part is just number/short code, try with prefix
                    if (!$partV && $idx > 0 && $prefix && !preg_match('/^[A-Za-z]/', $part)) {
                        $pTrim = rtrim($prefix);
                        $testPart = $pTrim . ' ' . $part;
                        $partV = $resolveCached($testPart, $variantName, $rawSku);
                        if (!$partV) {
                            $testPart = rtrim($prefix, " \t\n\r\0\x0B-_") . $part;
                            $partV = $resolveCached($testPart, $variantName, $rawSku);
                        }
                    }

                    if ($partV) {
                        $stockInfo = $getCachedStockInfo($partV);
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
                            'available_stock' => $stockInfo['available_stock'],
                            'is_bundle' => $stockInfo['is_bundle'],
                            'bundle_components' => $stockInfo['bundle_components'],
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
                    $this->recordMissingSku($missingSkus, $rawSku, $productName, $variantName, $qty, null, $paymentAmt, $orderId);
                    continue;
                }
            }

            // 2. Direct resolve on single raw SKU
            $directV = $resolveCached($rawSku, $variantName, $rawSku);
            if ($directV) {
                $stockInfo = $getCachedStockInfo($directV);
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
                    'available_stock' => $stockInfo['available_stock'],
                    'is_bundle' => $stockInfo['is_bundle'],
                    'bundle_components' => $stockInfo['bundle_components'],
                ];
                continue;
            }

            // 3. Fallback missing SKU recording
            $this->recordMissingSku($missingSkus, $rawSku, $productName, $variantName, $qty, null, $paymentAmt, $orderId);
        }

        $totalMissingAmount = 0;
        $totalMissingQty = 0;
        foreach ($missingSkus as $m) {
            $totalMissingAmount += (float)($m['total_payment'] ?? 0);
            $totalMissingQty += (int)($m['total_qty'] ?? 0);
        }

        return response()->json([
            'success' => true,
            'matched_items' => $matchedItems,
            'missing_skus' => array_values($missingSkus),
            'resis' => array_values($resisSet),
            'suggested_bruto' => $suggestedBruto ? (float)$suggestedBruto : null,
            'suggested_pencairan' => $suggestedPencairan ? (float)$suggestedPencairan : null,
            'expected_file_qty' => isset($expectedFileQty) && $expectedFileQty ? (int)$expectedFileQty : null,
            'total_missing_amount' => (float)round($totalMissingAmount, 2),
            'total_missing_qty' => (int)$totalMissingQty,
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
     * Record missing SKU with aggregated sizes, variations, quantities, and payments
     */
    private function recordMissingSku(
        array &$missingSkus,
        string $rawSku,
        string $productName,
        string $variantName,
        int $qty = 1,
        ?string $missingPart = null,
        float $payment = 0,
        ?string $orderId = null
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
                'total_payment' => 0,
                'order_count' => 0,
                'orders' => [],
                'missing_parts' => [],
            ];
        }

        if ($productName && $missingSkus[$skuKey]['product_name'] === $rawSku) {
            $missingSkus[$skuKey]['product_name'] = $productName;
        }

        if ($variantName && !in_array($variantName, $missingSkus[$skuKey]['variations'])) {
            $missingSkus[$skuKey]['variations'][] = $variantName;
        }

        if ($orderId && !in_array($orderId, $missingSkus[$skuKey]['orders'])) {
            $missingSkus[$skuKey]['orders'][] = $orderId;
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
        $missingSkus[$skuKey]['total_payment'] += $payment;
        $missingSkus[$skuKey]['order_count'] += 1;

        // Legacy compatibility fields
        $missingSkus[$skuKey]['variation'] = implode(', ', $missingSkus[$skuKey]['variations']);
        $missingSkus[$skuKey]['sizes_count'] = count($missingSkus[$skuKey]['sizes']);
        $missingSkus[$skuKey]['has_multiple_sizes'] = count($missingSkus[$skuKey]['sizes']) > 1;
    }

    private function parseSizeTokens(string $text): array
    {
        $sizes = [];
        if (preg_match_all('/\b(5XL|4XL|3XL|2XL|XXXXXL|XXXXL|XXXL|XXL|XL|L|M|S|XS)\b/i', $text, $m)) {
            foreach ($m[1] as $s) {
                $sz = strtoupper($s);
                $sizes[] = $sz;
                if ($sz === '2XL') $sizes[] = 'XXL';
                if ($sz === 'XXL') $sizes[] = '2XL';
                if ($sz === '3XL') $sizes[] = 'XXXL';
                if ($sz === 'XXXL') $sizes[] = '3XL';
                if ($sz === '4XL') $sizes[] = 'XXXXL';
                if ($sz === 'XXXXL') $sizes[] = '4XL';
                if ($sz === '5XL') $sizes[] = 'XXXXXL';
                if ($sz === 'XXXXXL') $sizes[] = '5XL';
            }
        }

        // Also check explicit size prefixes (e.g. "Size 30", "Ukuran 32", "Size: 28")
        if (preg_match_all('/(?<!all\s)(?<!plus\s)(?<!big\s)(?<!free\s)\b(?:size|ukuran|sz|no\.?)\s*[:\-]?\s*(\d{1,3}|[A-Za-z0-9]{1,4})\b/i', $text, $m)) {
            foreach ($m[1] as $s) {
                $sz = strtoupper(trim($s));
                if (!in_array($sz, ['ANAK', 'DEWASA', 'PRIA', 'WANITA', 'COWOK', 'CEWEK', 'BAYI', 'KIDS', 'MAN', 'WOMAN'])) {
                    $sizes[] = $sz;
                }
            }
        }

        return array_values(array_unique($sizes));
    }

    private function resolveVariantFromSkuAndSize(string $rawSku, string $varText, string $fullSkuText, array $skuMap, array $sizeGroups = [])
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

            // Prefix match (optimized via pre-grouped $sizeGroups if available)
            $prefix1 = $cleanSku . '-';
            $prefix2 = $noSpaceSku . '-';
            $prefix3 = $cleanSku . $size;
            $prefix4 = $noSpaceSku . $size;
            $regex = '/^' . preg_quote($cleanSku, '/') . '(?![0-9])/i';

            if (!empty($sizeGroups)) {
                $candidates = $sizeGroups[$size] ?? [];
                foreach ($candidates as $cand) {
                    $dbSku = $cand['dbSku'];
                    $dbNoSpace = $cand['noSpaceSku'];
                    if (
                        str_starts_with($dbSku, $prefix1) ||
                        str_starts_with($dbNoSpace, $prefix2) ||
                        str_starts_with($dbSku, $prefix3) ||
                        str_starts_with($dbNoSpace, $prefix4)
                    ) {
                        return $cand['variant'];
                    }
                    if (preg_match($regex, $dbSku) && str_contains($dbSku, $size)) {
                        return $cand['variant'];
                    }
                }
            } else {
                foreach ($skuMap as $dbSku => $variant) {
                    $vSize = strtoupper(trim((string)$variant->size));
                    $sizeMatched = ($vSize === $size ||
                        ($size === 'XXL' && $vSize === '2XL') || ($size === '2XL' && $vSize === 'XXL') ||
                        ($size === 'XXXL' && $vSize === '3XL') || ($size === '3XL' && $vSize === 'XXXL') ||
                        ($size === 'XXXXL' && $vSize === '4XL') || ($size === '4XL' && $vSize === 'XXXXL') ||
                        ($size === 'XXXXXL' && $vSize === '5XL') || ($size === '5XL' && $vSize === 'XXXXXL')
                    );
                    if ($sizeMatched) {
                        if (str_starts_with($dbSku, $prefix1) || str_starts_with($dbSku, $prefix2) || str_starts_with($dbSku, $prefix3) || str_starts_with($dbSku, $prefix4)) {
                            return $variant;
                        }
                        if (preg_match($regex, $dbSku) && str_contains($dbSku, $size)) {
                            return $variant;
                        }
                    }
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

        $variant = ProductVariant::with(['product', 'bundleAsBundle.componentVariant.stocks', 'bundleAsBundle.componentVariant.product'])->findOrFail($variantId);
        $isBundle = ($variant->product && $variant->product->product_type === 'bundle') || $variant->bundleAsBundle->isNotEmpty();

        $updatedComponents = [];
        $newStockQty = 0;

        DB::transaction(function () use ($warehouseId, $variant, $isBundle, $addQty, $note, &$newStockQty, &$updatedComponents) {
            if ($isBundle && $variant->bundleAsBundle->isNotEmpty()) {
                foreach ($variant->bundleAsBundle as $bItem) {
                    $compV = $bItem->componentVariant;
                    if (!$compV) continue;
                    $compReqQty = (int)($bItem->quantity ?? $bItem->qty ?? 1);
                    $compAddQty = $addQty * max(1, $compReqQty);

                    $this->adjustSingleVariantStock($warehouseId, $compV->id, $compAddQty, $note . " (Komponen Bundle: {$variant->sku})");
                }

                $freshVariant = ProductVariant::with(['product', 'bundleAsBundle.componentVariant.stocks', 'bundleAsBundle.componentVariant.product'])->find($variant->id);
                $stockInfo = $this->getVariantStockInfo($freshVariant, $warehouseId);
                $newStockQty = $stockInfo['available_stock'];
                $updatedComponents = $stockInfo['bundle_components'];
            } else {
                $newStockQty = $this->adjustSingleVariantStock($warehouseId, $variant->id, $addQty, $note);
            }
        });

        return response()->json([
            'success' => true,
            'message' => $isBundle
                ? "Berhasil menambahkan stok komponen bundle ({$addQty} set) ke gudang."
                : "Berhasil menambahkan {$addQty} pcs stok ke gudang.",
            'new_stock' => $newStockQty,
            'variant_id' => $variantId,
            'is_bundle' => $isBundle,
            'bundle_components' => $updatedComponents,
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
        $bundleUpdates = [];

        DB::transaction(function () use ($warehouseId, $items, &$updatedList, &$bundleUpdates) {
            foreach ($items as $item) {
                $variantId = (int)$item['variant_id'];
                $addQty = (int)$item['add_qty'];

                $variant = ProductVariant::with(['product', 'bundleAsBundle.componentVariant.stocks', 'bundleAsBundle.componentVariant.product'])->find($variantId);
                if (!$variant) continue;

                $isBundle = ($variant->product && $variant->product->product_type === 'bundle') || $variant->bundleAsBundle->isNotEmpty();

                if ($isBundle && $variant->bundleAsBundle->isNotEmpty()) {
                    foreach ($variant->bundleAsBundle as $bItem) {
                        $compV = $bItem->componentVariant;
                        if (!$compV) continue;
                        $compReqQty = (int)($bItem->quantity ?? $bItem->qty ?? 1);
                        $compAddQty = $addQty * max(1, $compReqQty);
                        $newCompStock = $this->adjustSingleVariantStock($warehouseId, $compV->id, $compAddQty, "Penyesuaian stok massal komponen bundle: {$variant->sku}");
                        $updatedList[$compV->id] = $newCompStock;
                    }

                    $freshVariant = ProductVariant::with(['product', 'bundleAsBundle.componentVariant.stocks', 'bundleAsBundle.componentVariant.product'])->find($variant->id);
                    $stockInfo = $this->getVariantStockInfo($freshVariant, $warehouseId);
                    $updatedList[$variant->id] = $stockInfo['available_stock'];
                    $bundleUpdates[$variant->id] = $stockInfo['bundle_components'];
                } else {
                    $newStockQty = $this->adjustSingleVariantStock($warehouseId, $variant->id, $addQty, 'Penyesuaian stok massal dari form Stock Out');
                    $updatedList[$variant->id] = $newStockQty;
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Berhasil menyesuaikan stok untuk ' . count($items) . ' produk ke gudang.',
            'updated_stocks' => $updatedList,
            'bundle_updates' => $bundleUpdates,
        ]);
    }

    private function adjustSingleVariantStock(int $warehouseId, int $variantId, int $addQty, string $note): int
    {
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

        return $newStockQty;
    }

    private function getVariantStockInfo($v, $warehouseId = null): array
    {
        $isBundle = ($v->product && $v->product->product_type === 'bundle') || ($v->bundleAsBundle && $v->bundleAsBundle->isNotEmpty());

        if ($isBundle && $v->bundleAsBundle && $v->bundleAsBundle->isNotEmpty()) {
            $possibleList = [];
            $components = [];
            foreach ($v->bundleAsBundle as $bItem) {
                $compV = $bItem->componentVariant;
                $reqQty = (int)($bItem->quantity ?? $bItem->qty ?? 1);
                $compStock = 0;
                if ($compV) {
                    $compStock = $warehouseId
                        ? ($compV->stocks->firstWhere('warehouse_id', $warehouseId)->qty ?? 0)
                        : ($compV->stocks->sum('qty') ?? 0);
                }
                $possibleList[] = $reqQty > 0 ? (int)floor($compStock / $reqQty) : 0;
                $components[] = [
                    'component_variant_id' => $bItem->component_variant_id,
                    'sku' => $compV->sku ?? '',
                    'product_name' => $compV->product->product_name ?? '',
                    'size' => $compV->size ?? '',
                    'color' => $compV->color ?? '',
                    'quantity' => $reqQty,
                    'available_stock' => (int)$compStock,
                ];
            }
            $availableStock = !empty($possibleList) ? min($possibleList) : 0;
            return [
                'is_bundle' => true,
                'available_stock' => (int)$availableStock,
                'bundle_components' => $components,
            ];
        }

        $stk = $warehouseId
            ? ($v->stocks->firstWhere('warehouse_id', $warehouseId)->qty ?? 0)
            : ($v->stocks->sum('qty') ?? 0);

        return [
            'is_bundle' => false,
            'available_stock' => (int)$stk,
            'bundle_components' => [],
        ];
    }

    private function calculateBundleStocksMap($v): array
    {
        $warehouses = Warehouse::pluck('id');
        $stocksMap = [];
        foreach ($warehouses as $wId) {
            $possibleList = [];
            foreach ($v->bundleAsBundle as $bItem) {
                $compV = $bItem->componentVariant;
                $reqQty = (int)($bItem->quantity ?? $bItem->qty ?? 1);
                $compStock = $compV ? ($compV->stocks->firstWhere('warehouse_id', $wId)->qty ?? 0) : 0;
                $possibleList[] = $reqQty > 0 ? (int)floor($compStock / $reqQty) : 0;
            }
            $stocksMap[$wId] = !empty($possibleList) ? min($possibleList) : 0;
        }
        return $stocksMap;
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
        $requestedPeriodId = $request->input('period_id');
        $sellerId = $request->input('seller_id');
        $konveksiId = $request->input('konveksi_id');

        // Langsung ambil semua kolom ringan dari periods tanpa query information_schema (menghindari duplicate query Debugbar)
        $periods = Period::query()
            ->orderBy('start_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
        $sellers = Seller::select('id', 'seller_name')
            ->orderBy('seller_name', 'asc')
            ->get();
        $konveksis = Konveksi::select('id', 'name')
            ->orderBy('name', 'asc')
            ->get();

        // Selalu scope ke 1 periode saja (default ke periode aktif / publik terbaru) agar ringan & tidak render semua periode
        $selectedPeriod = null;
        if ($requestedPeriodId) {
            $selectedPeriod = $periods->firstWhere('id', (int) $requestedPeriodId);
        }
        if (!$selectedPeriod) {
            $selectedPeriod = $periods->first(fn ($p) => !empty($p->getAttributes()['is_active']))
                ?? $periods->first(fn ($p) => !empty($p->getAttributes()['is_public']))
                ?? $periods->first();
        }
        $periodId = $selectedPeriod?->id;

        $periodsMap = $periods->keyBy('id');
        $sellersMap = $sellers->pluck('seller_name', 'id')->all();
        $konveksisMap = $konveksis->pluck('name', 'id')->all();
        $storesMap = DB::table('stores')
            ->select('id', 'store_name', 'marketplace', 'seller_id')
            ->get()
            ->keyBy('id');

        $applyStockOutFilters = function ($query) use ($periodId, $sellerId, $konveksiId) {
            $query->when(
                $periodId !== null,
                fn ($q) => $q->where('stock_outs.period_id', $periodId),
                fn ($q) => $q->whereRaw('1 = 0')
            )
            ->when($sellerId, function ($q) use ($sellerId) {
                $q->where(function ($sq) use ($sellerId) {
                    $sq->whereExists(function ($st) use ($sellerId) {
                        $st->select(DB::raw(1))
                            ->from('stores')
                            ->whereColumn('stores.id', 'stock_outs.store_id')
                            ->where('stores.seller_id', $sellerId);
                    })->orWhereExists(function ($it) use ($sellerId) {
                        $it->select(DB::raw(1))
                            ->from('stock_out_items as soi')
                            ->join('product_variants as pv', 'pv.id', '=', 'soi.product_variant_id')
                            ->join('products as p', 'p.id', '=', 'pv.product_id')
                            ->whereColumn('soi.stock_out_id', 'stock_outs.id')
                            ->where('p.seller_id', $sellerId);
                    });
                });
            })
            ->when($konveksiId, function ($q) use ($konveksiId) {
                $q->whereExists(function ($it) use ($konveksiId) {
                    $it->select(DB::raw(1))
                        ->from('stock_out_items as soi')
                        ->leftJoin('product_variants as pv', 'pv.id', '=', 'soi.product_variant_id')
                        ->leftJoin('products as p', 'p.id', '=', 'pv.product_id')
                        ->whereColumn('soi.stock_out_id', 'stock_outs.id')
                        ->where(function ($kq) use ($konveksiId) {
                            $kq->where('soi.konveksi_id', $konveksiId)
                                ->orWhere('p.konveksi_id', $konveksiId);
                        });
                });
            });
        };

        $filteredIdsQuery = DB::table('stock_outs')->select('stock_outs.id');
        $applyStockOutFilters($filteredIdsQuery);

        // Agregasi qty & HPP per (stock_out_id, konveksi) langsung di SQL tanpa hidrasi ribuan model Eloquent
        $effectiveKonveksiSql = 'COALESCE(ki.id, kp.id, 0)';
        $itemAggRows = DB::table('stock_out_items as soi')
            ->leftJoin('product_variants as pv', 'pv.id', '=', 'soi.product_variant_id')
            ->leftJoin('products as p', 'p.id', '=', 'pv.product_id')
            ->leftJoin('konveksis as ki', 'ki.id', '=', 'soi.konveksi_id')
            ->leftJoin('konveksis as kp', 'kp.id', '=', 'p.konveksi_id')
            ->whereIn('soi.stock_out_id', $filteredIdsQuery)
            ->when($konveksiId, function ($q) use ($konveksiId) {
                $q->where(function ($kq) use ($konveksiId) {
                    $kq->where('soi.konveksi_id', $konveksiId)
                        ->orWhere('p.konveksi_id', $konveksiId);
                });
            })
            ->selectRaw("
                soi.stock_out_id,
                {$effectiveKonveksiSql} as eff_konveksi_id,
                MIN(soi.id) as first_item_id,
                COALESCE(SUM(soi.qty), 0) as total_pcs,
                COALESCE(SUM(soi.qty * soi.price), 0) as raw_nominal,
                MIN(CASE WHEN p.seller_id IS NOT NULL THEN soi.id END) as first_seller_item_id
            ")
            ->groupByRaw("soi.stock_out_id, {$effectiveKonveksiSql}")
            ->orderBy('soi.stock_out_id')
            ->orderBy('first_item_id')
            ->get();

        $itemsByStockOut = [];
        $earliestSellerItemBySo = [];
        foreach ($itemAggRows as $aggRow) {
            $soId = (int) $aggRow->stock_out_id;
            $itemsByStockOut[$soId][] = [
                'konveksi_id' => (int) $aggRow->eff_konveksi_id,
                'pcs' => (int) $aggRow->total_pcs,
                'nominal' => (float) $aggRow->raw_nominal,
            ];

            if ($aggRow->first_seller_item_id !== null) {
                $candItemId = (int) $aggRow->first_seller_item_id;
                if (!isset($earliestSellerItemBySo[$soId]) || $candItemId < $earliestSellerItemBySo[$soId]) {
                    $earliestSellerItemBySo[$soId] = $candItemId;
                }
            }
        }
        unset($itemAggRows);

        $connName = DB::connection()->getName() ?? 'default';
        $driver = DB::connection()->getDriverName();
        $jsonArrayLenSql = match ($driver) {
            'sqlite' => 'CASE WHEN stock_outs.resi_summary IS NOT NULL AND LENGTH(stock_outs.resi_summary) > 4096 AND json_valid(stock_outs.resi_summary) THEN json_array_length(stock_outs.resi_summary) ELSE NULL END',
            'mysql', 'mariadb' => 'CASE WHEN stock_outs.resi_summary IS NOT NULL AND LENGTH(stock_outs.resi_summary) > 4096 AND JSON_VALID(stock_outs.resi_summary) THEN JSON_LENGTH(stock_outs.resi_summary) ELSE NULL END',
            default => 'NULL',
        };
        if (!array_key_exists($connName, self::$hasTotalAmountColCache)) {
            self::$hasTotalAmountColCache[$connName] = \Illuminate\Support\Facades\Schema::hasColumn('stock_outs', 'total_amount');
        }
        $hasTotalAmountCol = self::$hasTotalAmountColCache[$connName];

        // Ambil header stock_outs langsung tanpa subquery berulang & potongan pendek resi_summary (maks 4KB)
        $stockOutsQuery = DB::table('stock_outs');
        $applyStockOutFilters($stockOutsQuery);
        $stockOuts = $stockOutsQuery
            ->select([
                'stock_outs.id',
                'stock_outs.store_id',
                'stock_outs.period_id',
                'stock_outs.date',
                'stock_outs.note',
                $hasTotalAmountCol ? 'stock_outs.total_amount' : DB::raw('0 as total_amount'),
                DB::raw('LENGTH(stock_outs.resi_summary) as resi_raw_len'),
                DB::raw('SUBSTR(stock_outs.resi_summary, 1, 4096) as resi_head'),
                DB::raw('SUBSTR(RTRIM(stock_outs.resi_summary), -1, 1) as resi_last_char'),
                DB::raw("CASE
                    WHEN stock_outs.resi_summary IS NULL OR LENGTH(stock_outs.resi_summary) <= 4096 THEN NULL
                    ELSE (
                        (LENGTH(stock_outs.resi_summary) - LENGTH(REPLACE(stock_outs.resi_summary, '\"no_resi\"', ''))) / 9
                        - (LENGTH(stock_outs.resi_summary) - LENGTH(REPLACE(stock_outs.resi_summary, '\"no_resi\":\"\"', ''))) / 12
                        - (LENGTH(stock_outs.resi_summary) - LENGTH(REPLACE(stock_outs.resi_summary, '\"no_resi\": \"\"', ''))) / 13
                        - (LENGTH(stock_outs.resi_summary) - LENGTH(REPLACE(stock_outs.resi_summary, '\"no_resi\":null', ''))) / 14
                        - (LENGTH(stock_outs.resi_summary) - LENGTH(REPLACE(stock_outs.resi_summary, '\"no_resi\": null', ''))) / 15
                    )
                END as resi_obj_count"),
                DB::raw("{$jsonArrayLenSql} as resi_json_len"),
            ])
            ->orderBy('stock_outs.date', 'desc')
            ->orderBy('stock_outs.id', 'desc')
            ->get();

        // Fallback seller dari item hanya untuk stock_outs yang tidak punya store->seller_id
        $fallbackItemIdsToResolve = [];
        foreach ($stockOuts as $so) {
            $storeObj = $so->store_id ? ($storesMap[$so->store_id] ?? null) : null;
            $hasStoreSeller = $storeObj && $storeObj->seller_id && isset($sellersMap[$storeObj->seller_id]);
            if (!$hasStoreSeller && isset($earliestSellerItemBySo[$so->id])) {
                $fallbackItemIdsToResolve[$so->id] = $earliestSellerItemBySo[$so->id];
            }
        }

        $fallbackSellerBySoId = [];
        if (!empty($fallbackItemIdsToResolve)) {
            $itemSellerMap = DB::table('stock_out_items as soi')
                ->join('product_variants as pv', 'pv.id', '=', 'soi.product_variant_id')
                ->join('products as p', 'p.id', '=', 'pv.product_id')
                ->whereIn('soi.id', array_values($fallbackItemIdsToResolve))
                ->pluck('p.seller_id', 'soi.id')
                ->all();

            foreach ($fallbackItemIdsToResolve as $soId => $itemId) {
                if (isset($itemSellerMap[$itemId])) {
                    $fallbackSellerBySoId[$soId] = (int) $itemSellerMap[$itemId];
                }
            }
        }

        $pdfUrlTemplate = route('superadmin.warehouse.stock-out.pdf', ['stockOut' => '__SO_ID__']);

        // Hierarchical grouping: Period -> Seller -> Store -> Fakturs (with Konveksi Breakdown)
        $periodsGrouped = [];
        $grandTotalPcs = 0;
        $grandTotalNominal = 0;
        $grandTotalFakturs = 0;

        foreach ($stockOuts as $so) {
            $soId = (int) $so->id;
            $itemGroups = $itemsByStockOut[$soId] ?? [];

            $soPcs = 0;
            $rawItemsNominal = 0.0;
            foreach ($itemGroups as $ig) {
                $soPcs += $ig['pcs'];
                $rawItemsNominal += $ig['nominal'];
            }

            if ($konveksiId) {
                $soNominal = $rawItemsNominal;
            } else {
                $headerAmount = (float) ($so->total_amount ?? 0);
                $soNominal = $headerAmount > 0 ? $headerAmount : $rawItemsNominal;
            }

            if ($soPcs <= 0 && $konveksiId) {
                continue;
            }

            // Seller group resolution (before initializing period bucket to avoid empty period cards)
            $sId = null;
            $sName = 'Umum / Non-Seller';
            $storeObj = $so->store_id ? ($storesMap[$so->store_id] ?? null) : null;
            if ($storeObj && $storeObj->seller_id && isset($sellersMap[$storeObj->seller_id])) {
                $sId = (int) $storeObj->seller_id;
                $sName = $sellersMap[$sId];
            } elseif (isset($fallbackSellerBySoId[$soId]) && isset($sellersMap[$fallbackSellerBySoId[$soId]])) {
                $sId = $fallbackSellerBySoId[$soId];
                $sName = $sellersMap[$sId];
            }

            if ($sellerId && $sId != $sellerId) {
                continue;
            }

            // Calculate per-konveksi breakdown for this stock out based on HPP (Modal)
            $soKonveksi = [];
            foreach ($itemGroups as $ig) {
                $kId = $ig['konveksi_id'];
                $kName = ($kId > 0 && isset($konveksisMap[$kId])) ? $konveksisMap[$kId] : 'Tanpa Konveksi';

                $soKonveksi[$kId] = [
                    'konveksi_id' => $kId,
                    'name' => $kName,
                    'pcs' => $ig['pcs'],
                    'nominal' => $ig['nominal'],
                ];
            }

            // Reconcile each konveksi's nominal proportionally so their sum matches $soNominal exactly
            if (!empty($soKonveksi) && $soNominal != 0.0) {
                $rawKonveksiSum = (float) array_sum(array_column($soKonveksi, 'nominal'));
                $isIntegerNominal = (floor($soNominal) == $soNominal);
                $precision = $isIntegerNominal ? 0 : 2;

                $kKeys = array_keys($soKonveksi);
                $lastKey = end($kKeys);
                $accumulatedNominal = 0.0;

                if ($rawKonveksiSum > 0) {
                    $ratioScale = $soNominal / $rawKonveksiSum;
                    foreach ($soKonveksi as $kId => &$kVal) {
                        if ($kId === $lastKey) {
                            $kVal['nominal'] = round($soNominal - $accumulatedNominal, $precision);
                        } else {
                            $scaledVal = round((float) $kVal['nominal'] * $ratioScale, $precision);
                            $kVal['nominal'] = $scaledVal;
                            $accumulatedNominal += $scaledVal;
                        }
                    }
                    unset($kVal);
                } elseif ($soPcs > 0) {
                    foreach ($soKonveksi as $kId => &$kVal) {
                        if ($kId === $lastKey) {
                            $kVal['nominal'] = round($soNominal - $accumulatedNominal, $precision);
                        } else {
                            $pcsRatio = (float) $kVal['pcs'] / (float) $soPcs;
                            $scaledVal = round($soNominal * $pcsRatio, $precision);
                            $kVal['nominal'] = $scaledVal;
                            $accumulatedNominal += $scaledVal;
                        }
                    }
                    unset($kVal);
                }
            } elseif ($soNominal == 0.0) {
                foreach ($soKonveksi as &$kVal) {
                    $kVal['nominal'] = 0.0;
                }
                unset($kVal);
            }

            // Period group
            $pId = $so->period_id ?? 0;
            $periodObj = $so->period_id ? ($periodsMap[$so->period_id] ?? null) : null;
            $pName = $periodObj?->name ?? 'Tanpa Periode / Umum';
            $pStartDate = $periodObj?->start_date ? $periodObj->start_date->format('d M Y') : null;
            $pEndDate = $periodObj?->end_date ? $periodObj->end_date->format('d M Y') : null;

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
            $storeName = $storeObj?->store_name ?? 'Penjualan Direct / Tanpa Toko';
            $marketplace = $storeObj?->marketplace ?? 'OFFLINE';

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

            // Format Resi tanpa full json_decode pada LONGTEXT besar
            $rawLen = (int) ($so->resi_raw_len ?? 0);
            $precomputedTotal = null;
            if ($rawLen > 4096) {
                $objCount = (int) round((float) ($so->resi_obj_count ?? 0));
                $jsonLen = $so->resi_json_len !== null ? (int) $so->resi_json_len : 0;
                $precomputedTotal = $objCount > 0 ? $objCount : $jsonLen;
            }
            $resiInfo = $this->formatResiSummaryHead(
                $so->resi_head,
                $rawLen,
                $so->resi_last_char,
                $precomputedTotal,
                false
            );

            $noFaktur = 'OUT-' . str_pad((string) $soId, 5, '0', STR_PAD_LEFT);
            $formattedDate = $so->date ? date('d M Y', strtotime((string) $so->date)) : '-';
            $fakturItem = [
                'id' => $soId,
                'no_faktur' => $noFaktur,
                'date' => $formattedDate,
                'resi_info' => $resiInfo,
                'pcs' => $soPcs,
                'nominal' => $soNominal,
                'note' => $so->note,
                'konveksis' => array_values($soKonveksi),
                'pdf_url' => str_replace('__SO_ID__', (string) $soId, $pdfUrlTemplate),
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
        unset($stockOuts, $itemsByStockOut);

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
                'period_id' => $periodId ? (int) $periodId : null,
                'seller_id' => $sellerId ? (int) $sellerId : null,
                'konveksi_id' => $konveksiId ? (int) $konveksiId : null,
            ],
        ]);
    }

    private function formatResiSummary(?string $raw): array
    {
        if ($raw === null || $raw === '') {
            return [
                'total_resi' => 0,
                'label' => '0 Resi',
                'preview' => '-',
                'sample_resis' => [],
            ];
        }

        $rawLen = strlen($raw);
        if ($rawLen <= 4096) {
            return $this->formatResiSummaryHead($raw, $rawLen, null, null, true);
        }

        // Memory-safe path untuk string JSON LONGTEXT besar (tanpa json_decode seluruh payload)
        $end = $rawLen - 1;
        while ($end >= 0 && in_array($raw[$end], [' ', "\t", "\n", "\r", "\0", "\x0B"], true)) {
            $end--;
        }
        $lastChar = $end >= 0 ? $raw[$end] : '';

        $totalKeys = substr_count($raw, '"no_resi"');
        if ($totalKeys > 0) {
            $emptyKeys = substr_count($raw, '"no_resi":""')
                + substr_count($raw, '"no_resi": ""')
                + substr_count($raw, '"no_resi":null')
                + substr_count($raw, '"no_resi": null');
            $precomputedTotal = max(0, $totalKeys - $emptyKeys);
        } else {
            $precomputedTotal = (int) preg_match_all('/"(?:[^"\\\\]|\\\\.)+"/', $raw);
        }

        return $this->formatResiSummaryHead(
            substr($raw, 0, 4096),
            $rawLen,
            $lastChar,
            $precomputedTotal,
            true
        );
    }

    private function formatResiSummaryHead(
        ?string $head,
        int $rawLen,
        ?string $lastChar = null,
        ?int $precomputedTotal = null,
        bool $includeSamples = true
    ): array {
        if ($head === null || trim($head) === '') {
            return $this->buildResiSummaryResult(0, '0 Resi', '-', [], $includeSamples);
        }

        $ltrimmed = ltrim($head);
        $startsWithBracket = str_starts_with($ltrimmed, '[');
        $endsWithBracket = $lastChar !== null
            ? ($lastChar === ']')
            : str_ends_with(rtrim($ltrimmed), ']');

        if ($startsWithBracket && $endsWithBracket) {
            // Jika ukuran penuh <= 4KB, aman menggunakan json_decode langsung
            if ($rawLen <= 4096 && $precomputedTotal === null) {
                $decoded = json_decode(trim($head), true);
                if (is_array($decoded)) {
                    $resis = [];
                    foreach ($decoded as $entry) {
                        if (is_array($entry) && !empty($entry['no_resi'])) {
                            $resis[] = trim((string) $entry['no_resi']);
                        } elseif (is_string($entry) && trim($entry) !== '') {
                            $resis[] = trim($entry);
                        }
                    }

                    $total = count($resis);
                    $firstFew = array_slice($resis, 0, 2);
                    $remaining = max(0, $total - count($firstFew));
                    $preview = implode(', ', $firstFew);
                    if ($remaining > 0) {
                        $preview .= $preview !== '' ? " (+{$remaining} resi)" : "({$remaining} resi)";
                    }

                    return $this->buildResiSummaryResult(
                        $total,
                        "{$total} Resi",
                        $preview ?: '-',
                        array_slice($resis, 0, 10),
                        $includeSamples
                    );
                }
            } else {
                // Ekstrak sample resi dari potongan 4KB pertama tanpa decode seluruh JSON
                $sampleResis = [];
                $maxSamples = $includeSamples ? 10 : 2;

                if (str_contains($ltrimmed, '"no_resi"')) {
                    if (preg_match_all('/"no_resi"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/', $ltrimmed, $matches)) {
                        foreach ($matches[1] as $rawVal) {
                            $decodedVal = json_decode('"' . $rawVal . '"');
                            $val = trim(is_string($decodedVal) ? $decodedVal : $rawVal);
                            if (!empty($val)) {
                                $sampleResis[] = $val;
                                if (count($sampleResis) >= $maxSamples) {
                                    break;
                                }
                            }
                        }
                    }
                } elseif (preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"/', $ltrimmed, $matches)) {
                    foreach ($matches[1] as $rawVal) {
                        $decodedVal = json_decode('"' . $rawVal . '"');
                        $val = trim(is_string($decodedVal) ? $decodedVal : $rawVal);
                        if ($val !== '') {
                            $sampleResis[] = $val;
                            if (count($sampleResis) >= $maxSamples) {
                                break;
                            }
                        }
                    }
                }

                $total = max(count($sampleResis), (int) ($precomputedTotal ?? 0));
                $firstFew = array_slice($sampleResis, 0, 2);
                $remaining = max(0, $total - count($firstFew));
                $preview = implode(', ', $firstFew);
                if ($remaining > 0) {
                    $preview .= $preview !== '' ? " (+{$remaining} resi)" : "({$remaining} resi)";
                }

                return $this->buildResiSummaryResult(
                    $total,
                    "{$total} Resi",
                    $preview ?: '-',
                    $sampleResis,
                    $includeSamples
                );
            }
        }

        $clean = \Illuminate\Support\Str::limit(trim($ltrimmed), 80);

        return $this->buildResiSummaryResult(1, 'Manual', $clean, [$clean], $includeSamples);
    }

    private function buildResiSummaryResult(
        int $total,
        string $label,
        string $preview,
        array $samples,
        bool $includeSamples
    ): array {
        $result = [
            'total_resi' => $total,
            'label' => $label,
            'preview' => $preview,
        ];

        if ($includeSamples) {
            $result['sample_resis'] = $samples;
        }

        return $result;
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
        $stockOut->load(['warehouse:id,name', 'user:id,name', 'store:id,store_name', 'items.productVariant.product:id,product_name']);
        $resiInfo = $this->formatResiSummary($stockOut->resi_summary);
        $pdf = Pdf::loadView('pdf.stock_out_surat_jalan', compact('stockOut', 'resiInfo'));

        return $pdf->stream("Surat_Jalan_StockOut_{$stockOut->id}.pdf");
    }

    public function exportSummary(Request $request)
    {
        $search = $request->input('search');
        $warehouseId = $request->input('warehouse_id');
        $storeId = $request->input('store_id');
        $periodId = $request->input('period_id');
        $userId = $request->input('user_id');

        // Subquery agregasi item yang di-scope hanya ke stock_outs yang cocok dengan filter
        $filteredIdsQuery = DB::table('stock_outs')
            ->select('stock_outs.id')
            ->when($warehouseId, fn($q) => $q->where('stock_outs.warehouse_id', $warehouseId))
            ->when($storeId, fn($q) => $q->where('stock_outs.store_id', $storeId))
            ->when($periodId, fn($q) => $q->where('stock_outs.period_id', $periodId))
            ->when($userId, fn($q) => $q->where('stock_outs.user_id', $userId))
            ->when($search, function ($query, $s) {
                $query->where(function ($q) use ($s) {
                    $q->where('stock_outs.note', 'like', "%{$s}%")
                        ->orWhere('stock_outs.resi_summary', 'like', "%{$s}%")
                        ->orWhereExists(function ($sq) use ($s) {
                            $sq->select(DB::raw(1))
                                ->from('stores')
                                ->whereColumn('stores.id', 'stock_outs.store_id')
                                ->where('stores.store_name', 'like', "%{$s}%");
                        });
                });
            });

        $itemsAggSub = DB::table('stock_out_items')
            ->selectRaw('stock_out_id, COALESCE(SUM(qty), 0) as total_qty, COALESCE(SUM(qty * price), 0) as calc_hpp')
            ->whereIn('stock_out_id', $filteredIdsQuery)
            ->groupBy('stock_out_id');

        // Query ringan tanpa kolom LONGTEXT resi_summary & tanpa hydrasi ribuan Eloquent model
        $rows = DB::table('stock_outs')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'stock_outs.warehouse_id')
            ->leftJoin('stores', 'stores.id', '=', 'stock_outs.store_id')
            ->leftJoin('sellers', 'sellers.id', '=', 'stores.seller_id')
            ->leftJoin('periods', 'periods.id', '=', 'stock_outs.period_id')
            ->leftJoin('users', 'users.id', '=', 'stock_outs.user_id')
            ->leftJoinSub($itemsAggSub, 'item_agg', function ($join) {
                $join->on('item_agg.stock_out_id', '=', 'stock_outs.id');
            })
            ->whereIn('stock_outs.id', $filteredIdsQuery)
            ->select([
                'stock_outs.id',
                'stock_outs.date',
                'stock_outs.note',
                'stock_outs.total_amount',
                'stock_outs.total_selling_price',
                'stock_outs.bruto',
                'warehouses.name as warehouse_name',
                'stores.store_name',
                'sellers.seller_name',
                'periods.name as period_name',
                'users.name as admin_name',
                DB::raw('COALESCE(item_agg.total_qty, 0) as total_qty'),
                DB::raw('CASE WHEN stock_outs.total_amount > 0 THEN stock_outs.total_amount ELSE COALESCE(item_agg.calc_hpp, 0) END as row_hpp'),
                DB::raw('CASE WHEN stock_outs.bruto > 0 THEN stock_outs.bruto ELSE COALESCE(stock_outs.total_selling_price, 0) END as row_bruto'),
                DB::raw('COALESCE(stock_outs.total_selling_price, 0) as row_bersih'),
            ])
            ->orderByDesc('stock_outs.date')
            ->orderByDesc('stock_outs.id')
            ->get();

        $summary = [
            'total_transactions' => $rows->count(),
            'total_qty' => (int) $rows->sum('total_qty'),
            'total_hpp' => (float) $rows->sum('row_hpp'),
            'total_bruto' => (float) $rows->sum('row_bruto'),
            'total_bersih' => (float) $rows->sum('row_bersih'),
            'total_untung' => (float) ($rows->sum('row_bersih') - $rows->sum('row_hpp')),
        ];

        $filterInfo = [
            'period' => $periodId ? (Period::where('id', $periodId)->value('name') ?? 'Semua Periode') : 'Semua Periode',
            'warehouse' => $warehouseId ? (Warehouse::where('id', $warehouseId)->value('name') ?? 'Semua Gudang') : 'Semua Gudang',
            'store' => $storeId ? (Store::where('id', $storeId)->value('store_name') ?? 'Semua Toko') : 'Semua Toko',
        ];

        $pdf = Pdf::loadView('pdf.stock_out_summary', compact('rows', 'summary', 'filterInfo'))
            ->setPaper('a4', 'landscape');

        return $pdf->stream('Summary_Pengeluaran_Barang_StockOut.pdf');
    }

    public function bajuCelana(Request $request)
    {
        $periodId = $request->input('period_id');
        $warehouseId = $request->input('warehouse_id');
        $storeId = $request->input('store_id');
        $sellerId = $request->input('seller_id');
        $search = $request->input('search');
        $typeFilter = $request->input('type', 'all'); // 'all', 'baju', 'celana', 'other'

        $periods = Period::select('id', 'name', 'start_date', 'end_date')->latest()->get();
        $warehouses = Warehouse::select('id', 'name')->get();
        $stores = Store::select('id', 'store_name')->get();
        $sellers = Seller::select('id', 'seller_name')->orderBy('seller_name')->get();

        // Query stock outs based on filters
        $stockOutQuery = StockOut::query()
            ->when($periodId, fn($q) => $q->where('period_id', $periodId))
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))
            ->when($storeId, fn($q) => $q->where('store_id', $storeId));

        if ($sellerId) {
            $stockOutQuery->where(function ($q) use ($sellerId) {
                $q->whereHas('store', fn($st) => $st->where('seller_id', $sellerId))
                  ->orWhereHas('items.productVariant.product', fn($pq) => $pq->where('seller_id', $sellerId));
            });
        }

        $stockOutIds = $stockOutQuery->pluck('id');

        // Fetch stock out items within these stock outs with bundle component relationships
        $items = StockOutItem::whereIn('stock_out_id', $stockOutIds)
            ->with([
                'stockOut.store',
                'stockOut.warehouse',
                'stockOut.period',
                'productVariant.product.category',
                'productVariant.product.seller',
                'productVariant.bundleAsBundle.componentVariant.product.category',
                'productVariant.bundleAsBundle.componentVariant.product.seller',
            ])
            ->get();

        $bajuCount = 0;
        $celanaCount = 0;
        $otherCount = 0;
        $bundleUnpackedPcs = 0;
        $totalPcs = 0;

        $categorizedVariants = [];

        foreach ($items as $item) {
            $variant = $item->productVariant;
            if (!$variant) continue;
            $product = $variant->product;
            if (!$product) continue;

            $isBundle = ($product->product_type === 'bundle') && $variant->bundleAsBundle->isNotEmpty();

            if ($isBundle) {
                $bundleUnpackedPcs += (int)$item->qty;
                foreach ($variant->bundleAsBundle as $bItem) {
                    $compVariant = $bItem->componentVariant;
                    if (!$compVariant) continue;
                    $compProduct = $compVariant->product;
                    if (!$compProduct) continue;

                    $compQty = (int)($bItem->quantity * $item->qty);
                    $compCategory = $compProduct->category?->name ?? '';
                    $compType = $this->categorizeProductOrVariant($compCategory, $compProduct->product_name, $compVariant->sku);

                    $vId = $compVariant->id;
                    if (!isset($categorizedVariants[$vId])) {
                        $categorizedVariants[$vId] = [
                            'variant_id' => $vId,
                            'product_id' => $compProduct->id,
                            'product_name' => $compProduct->product_name,
                            'sku' => $compVariant->sku,
                            'size' => $compVariant->size,
                            'color' => $compVariant->color,
                            'barcode' => $compVariant->barcode,
                            'category_name' => $compCategory ?: 'Tanpa Kategori',
                            'seller_name' => $compProduct->seller?->seller_name ?? ($product->seller?->seller_name ?? '-'),
                            'type' => $compType,
                            'single_qty' => 0,
                            'bundle_qty' => 0,
                            'qty' => 0,
                            'bundle_sources' => [],
                            'price' => (float)$compVariant->price,
                            'total_amount' => 0,
                        ];
                    }

                    $categorizedVariants[$vId]['bundle_qty'] += $compQty;
                    $categorizedVariants[$vId]['qty'] += $compQty;
                    $categorizedVariants[$vId]['total_amount'] += ($compQty * (float)$compVariant->price);
                    if (!in_array($product->product_name, $categorizedVariants[$vId]['bundle_sources'])) {
                        $categorizedVariants[$vId]['bundle_sources'][] = $product->product_name;
                    }

                    $totalPcs += $compQty;
                    if ($compType === 'baju') $bajuCount += $compQty;
                    elseif ($compType === 'celana') $celanaCount += $compQty;
                    else $otherCount += $compQty;
                }
            } else {
                $categoryName = $product->category?->name ?? '';
                $itemType = $this->categorizeProductOrVariant($categoryName, $product->product_name, $variant->sku);
                $qty = (int)$item->qty;
                $vId = $variant->id;

                if (!isset($categorizedVariants[$vId])) {
                    $categorizedVariants[$vId] = [
                        'variant_id' => $vId,
                        'product_id' => $product->id,
                        'product_name' => $product->product_name,
                        'sku' => $variant->sku,
                        'size' => $variant->size,
                        'color' => $variant->color,
                        'barcode' => $variant->barcode,
                        'category_name' => $categoryName ?: 'Tanpa Kategori',
                        'seller_name' => $product->seller?->seller_name ?? '-',
                        'type' => $itemType,
                        'single_qty' => 0,
                        'bundle_qty' => 0,
                        'qty' => 0,
                        'bundle_sources' => [],
                        'price' => (float)($item->price ?: $variant->price),
                        'total_amount' => 0,
                    ];
                }

                $categorizedVariants[$vId]['single_qty'] += $qty;
                $categorizedVariants[$vId]['qty'] += $qty;
                $categorizedVariants[$vId]['total_amount'] += ($qty * (float)($item->price ?: $variant->price));

                $totalPcs += $qty;
                if ($itemType === 'baju') $bajuCount += $qty;
                elseif ($itemType === 'celana') $celanaCount += $qty;
                else $otherCount += $qty;
            }
        }

        $resultList = array_values($categorizedVariants);

        // Apply filter type if selected
        if ($typeFilter && in_array($typeFilter, ['baju', 'celana', 'other'])) {
            $resultList = array_filter($resultList, fn($i) => $i['type'] === $typeFilter);
        }

        // Apply search keyword
        if ($search) {
            $s = strtolower($search);
            $resultList = array_filter($resultList, function ($i) use ($s) {
                return str_contains(strtolower($i['sku']), $s)
                    || str_contains(strtolower($i['product_name']), $s)
                    || str_contains(strtolower($i['category_name']), $s)
                    || str_contains(strtolower($i['seller_name']), $s)
                    || str_contains(strtolower($i['size'] ?? ''), $s)
                    || str_contains(strtolower($i['color'] ?? ''), $s)
                    || str_contains(strtolower(implode(' ', $i['bundle_sources'])), $s);
            });
        }

        // Sort descending by total QTY sold
        usort($resultList, fn($a, $b) => $b['qty'] <=> $a['qty']);

        return Inertia::render('Superadmin/Warehouse/StockOut/StockOutBajuCelana', [
            'items' => array_values($resultList),
            'summary' => [
                'total_pcs' => $totalPcs,
                'baju_count' => $bajuCount,
                'celana_count' => $celanaCount,
                'other_count' => $otherCount,
                'bundle_unpacked_pcs' => $bundleUnpackedPcs,
            ],
            'periods' => $periods,
            'warehouses' => $warehouses,
            'stores' => $stores,
            'sellers' => $sellers,
            'filters' => [
                'period_id' => $periodId,
                'warehouse_id' => $warehouseId,
                'store_id' => $storeId,
                'seller_id' => $sellerId,
                'search' => $search,
                'type' => $typeFilter ?? 'all',
            ],
        ]);
    }

    private function categorizeProductOrVariant(string $categoryName = '', string $productName = '', string $sku = ''): string
    {
        $text = strtolower($categoryName . ' ' . $productName . ' ' . $sku);

        // Celana / Bawahan
        $celanaKeywords = [
            'celana', 'pants', 'pant', 'kulot', 'cullote', 'culotte', 'jeans', 
            'jogger', 'bawahan', 'short', 'shorts', 'rok', 'skirt', 'chino', 
            'cargo', 'legging', 'kolor', 'boxer', 'sweatpants', 'hotpants', 'slacks', 'training'
        ];
        foreach ($celanaKeywords as $kw) {
            if (str_contains($text, $kw)) {
                return 'celana';
            }
        }

        // Baju / Atasan
        $bajuKeywords = [
            'baju', 'kaos', 'shirt', 'tshirt', 't-shirt', 'kemeja', 'atasan', 
            'top', 'hoodie', 'sweater', 'jaket', 'jacket', 'tunik', 'blouse', 
            'dress', 'gamis', 'polo', 'outer', 'cardigan', 'blazer', 'vest', 
            'singlet', 'jersey', 'crewneck', 'oversize'
        ];
        foreach ($bajuKeywords as $kw) {
            if (str_contains($text, $kw)) {
                return 'baju';
            }
        }

        return 'other';
    }
}
