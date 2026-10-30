<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Konveksi;
use App\Models\Period;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\StockIn;
use App\Models\StockInItem;
use App\Models\StockLog;
use App\Models\Warehouse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

use App\Models\Product;
use App\Models\Seller;

class StockInController extends Controller
{
    private function applyStockInFilters($query, Request $request)
    {
        $search = $request->input('search');
        $warehouseId = $request->input('warehouse_id');
        $periodId = $request->input('period_id');
        $sellerId = $request->input('seller_id');
        $konveksiId = $request->input('konveksi_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        return $query
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))
            ->when($periodId, fn($q) => $q->where('period_id', $periodId))
            ->when($startDate, fn($q) => $q->whereDate('date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('date', '<=', $endDate))
            ->when($sellerId, function ($q) use ($sellerId) {
                $q->whereHas('items.productVariant.product', fn($pq) => $pq->where('seller_id', $sellerId));
            })
            ->when($konveksiId, function ($q) use ($konveksiId) {
                $q->whereHas('items', fn($iq) => $iq->where('konveksi_id', $konveksiId));
            })
            ->when($search, function ($query, $s) {
                $query->where(function ($q) use ($s) {
                    $q->where('invoice_number', 'like', "%{$s}%")
                        ->orWhere('note', 'like', "%{$s}%")
                        ->orWhereHas('warehouse', fn($wh) => $wh->where('name', 'like', "%{$s}%"))
                        ->orWhereHas('items.konveksi', fn($kv) => $kv->where('name', 'like', "%{$s}%"))
                        ->orWhereHas('items.productVariant.product.seller', fn($sel) => $sel->where('seller_name', 'like', "%{$s}%"))
                        ->orWhereHas('items.productVariant', fn($pv) => $pv->where('sku', 'like', "%{$s}%")->orWhereHas('product', fn($p) => $p->where('product_name', 'like', "%{$s}%")));
                });
            });
    }

    public function index(Request $request)
    {
        $search = $request->input('search');
        $warehouseId = $request->input('warehouse_id');
        $periodId = $request->input('period_id');
        $sellerId = $request->input('seller_id');
        $konveksiId = $request->input('konveksi_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $baseQuery = $this->applyStockInFilters(StockIn::query(), $request);

        $stockIns = (clone $baseQuery)
            ->select('id', 'warehouse_id', 'user_id', 'period_id', 'invoice_number', 'date', 'note')
            ->with([
                'warehouse:id,name',
                'user:id,name',
                'period:id,name',
                'items:id,stock_in_id,product_variant_id,konveksi_id,qty,price',
                'items.konveksi:id,name',
                'items.productVariant:id,product_id',
                'items.productVariant.product:id,seller_id',
                'items.productVariant.product.seller:id,seller_name',
            ])
            ->latest('date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $warehouses = Warehouse::select('id', 'name')->get();
        $periods = Period::select('id', 'name')->get();
        $sellers = Seller::select('id', 'seller_name')->orderBy('seller_name')->get();
        $konveksis = Konveksi::select('id', 'name')->orderBy('name')->get();

        // Gunakan JOIN langsung (jauh lebih cepat di MySQL dibanding WHERE IN subquery)
        $itemsSummaryQuery = DB::table('stock_in_items')
            ->join('stock_ins', 'stock_ins.id', '=', 'stock_in_items.stock_in_id')
            ->when($warehouseId, fn($q) => $q->where('stock_ins.warehouse_id', $warehouseId))
            ->when($periodId, fn($q) => $q->where('stock_ins.period_id', $periodId))
            ->when($startDate, fn($q) => $q->whereDate('stock_ins.date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('stock_ins.date', '<=', $endDate))
            ->when($konveksiId, fn($q) => $q->where('stock_in_items.konveksi_id', $konveksiId))
            ->when($sellerId, function ($q) use ($sellerId) {
                $q->join('product_variants as pv_s', 'pv_s.id', '=', 'stock_in_items.product_variant_id')
                    ->join('products as p_s', 'p_s.id', '=', 'pv_s.product_id')
                    ->where('p_s.seller_id', $sellerId);
            })
            ->when($search, function ($q) use ($baseQuery) {
                $q->whereIn('stock_in_items.stock_in_id', (clone $baseQuery)->select('id'));
            });

        $aggregates = $itemsSummaryQuery
            ->selectRaw('COALESCE(SUM(stock_in_items.qty), 0) as total_qty, COALESCE(SUM(stock_in_items.qty * stock_in_items.price), 0) as total_nominal')
            ->first();

        $summary = [
            'total_transactions' => $stockIns->total(),
            'total_qty' => (int) ($aggregates->total_qty ?? 0),
            'total_nominal' => (float) ($aggregates->total_nominal ?? 0),
            'total_sellers' => $sellers->count(),
        ];

        return Inertia::render('Superadmin/Warehouse/StockIn/Index', [
            'stockIns' => $stockIns,
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
                    ->orWhereHas('product', fn($pq) => $pq->where('product_name', 'like', "%{$s}%"));
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
            'periods' => Period::select('id', 'name')->get(),
            'sellers' => Seller::select('id', 'seller_name')->orderBy('seller_name')->get(),
            'konveksis' => Konveksi::select('id', 'name')->get(),
            'defaultInvoiceNumber' => $this->generateUniqueInvoiceNumber(),
        ]);
    }

    public function edit(StockIn $stockIn)
    {
        $stockIn->load(['items.productVariant.product']);

        $sellerIds = $stockIn->items
            ->map(fn($item) => $item->productVariant?->product?->seller_id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $initialSellerProducts = [];
        foreach ($sellerIds as $sid) {
            $initialSellerProducts[$sid] = Product::where('seller_id', $sid)
                ->with(['variants:id,product_id,sku,size,color,price'])
                ->select('id', 'seller_id', 'product_name', 'sku')
                ->orderBy('product_name')
                ->get();
        }

        return Inertia::render('Superadmin/Warehouse/StockIn/Form', [
            'warehouses' => Warehouse::select('id', 'name')->get(),
            'periods' => Period::select('id', 'name')->get(),
            'sellers' => Seller::select('id', 'seller_name')->orderBy('seller_name')->get(),
            'konveksis' => Konveksi::select('id', 'name')->get(),
            'stockIn' => [
                'id' => $stockIn->id,
                'warehouse_id' => $stockIn->warehouse_id,
                'period_id' => $stockIn->period_id,
                'invoice_number' => $stockIn->invoice_number,
                'date' => $stockIn->date ? $stockIn->date->format('Y-m-d') : now()->format('Y-m-d'),
                'note' => $stockIn->note,
                'items' => $stockIn->items->map(fn($item) => [
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'period_id' => ['nullable', 'exists:periods,id'],
            'invoice_number' => ['nullable', 'string', 'max:100', 'unique:stock_ins,invoice_number'],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'exists:product_variants,id'],
            'items.*.konveksi_id' => ['nullable', 'exists:konveksis,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ]);

        $invoiceNumber = !empty($validated['invoice_number'])
            ? $validated['invoice_number']
            : $this->generateUniqueInvoiceNumber($validated['date']);

        DB::transaction(function () use ($validated, $request, $invoiceNumber) {
            $stockIn = StockIn::create([
                'warehouse_id' => $validated['warehouse_id'],
                'user_id' => $request->user()->id,
                'period_id' => $validated['period_id'] ?? null,
                'invoice_number' => $invoiceNumber,
                'date' => $validated['date'],
                'note' => $validated['note'] ?? null,
            ]);

            foreach ($validated['items'] as $itemData) {
                StockInItem::create([
                    'stock_in_id' => $stockIn->id,
                    'product_variant_id' => $itemData['product_variant_id'],
                    'konveksi_id' => $itemData['konveksi_id'] ?? null,
                    'qty' => $itemData['qty'],
                    'price' => $itemData['price'],
                ]);

                // Update / Increment physical stock
                $stockRecord = Stock::firstOrCreate(
                    [
                        'warehouse_id' => $validated['warehouse_id'],
                        'product_variant_id' => $itemData['product_variant_id'],
                    ],
                    ['qty' => 0, 'allocated_qty' => 0]
                );

                $qtyBefore = $stockRecord->qty;
                $stockRecord->increment('qty', $itemData['qty']);
                $qtyAfter = $stockRecord->fresh()->qty;

                // Log audit trail
                StockLog::create([
                    'product_variant_id' => $itemData['product_variant_id'],
                    'warehouse_id' => $validated['warehouse_id'],
                    'reference_type' => StockIn::class,
                    'reference_id' => $stockIn->id,
                    'qty_before' => $qtyBefore,
                    'qty_change' => $itemData['qty'],
                    'qty_after' => $qtyAfter,
                    'note' => "Stock In / Receipt Invoice #{$stockIn->invoice_number}",
                ]);
            }
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
            'items.*.product_variant_id' => ['required', 'exists:product_variants,id'],
            'items.*.konveksi_id' => ['nullable', 'exists:konveksis,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($validated, $stockIn) {
            $stockIn->load('items');
            $oldWarehouseId = $stockIn->warehouse_id;

            // 1. Revert stok dari item lama
            foreach ($stockIn->items as $oldItem) {
                $stock = Stock::where('warehouse_id', $oldWarehouseId)
                    ->where('product_variant_id', $oldItem->product_variant_id)
                    ->first();

                if ($stock) {
                    $qtyBefore = $stock->qty;
                    $stock->decrement('qty', min($stock->qty, $oldItem->qty));
                    $qtyAfter = $stock->fresh()->qty;

                    StockLog::create([
                        'product_variant_id' => $oldItem->product_variant_id,
                        'warehouse_id' => $oldWarehouseId,
                        'reference_type' => StockIn::class,
                        'reference_id' => $stockIn->id,
                        'qty_before' => $qtyBefore,
                        'qty_change' => -$oldItem->qty,
                        'qty_after' => $qtyAfter,
                        'note' => "Revisi (Revert) Stock In Invoice #{$stockIn->invoice_number}",
                    ]);
                }
            }

            $stockIn->items()->delete();

            // 2. Update header Stock In (invoice_number tetap tidak berubah)
            $stockIn->update([
                'warehouse_id' => $validated['warehouse_id'],
                'period_id' => $validated['period_id'] ?? null,
                'date' => $validated['date'],
                'note' => $validated['note'] ?? null,
            ]);

            // 3. Simpan item baru dan tambahkan stok baru
            foreach ($validated['items'] as $itemData) {
                StockInItem::create([
                    'stock_in_id' => $stockIn->id,
                    'product_variant_id' => $itemData['product_variant_id'],
                    'konveksi_id' => $itemData['konveksi_id'] ?? null,
                    'qty' => $itemData['qty'],
                    'price' => $itemData['price'],
                ]);

                $stockRecord = Stock::firstOrCreate(
                    [
                        'warehouse_id' => $validated['warehouse_id'],
                        'product_variant_id' => $itemData['product_variant_id'],
                    ],
                    ['qty' => 0, 'allocated_qty' => 0]
                );

                $qtyBefore = $stockRecord->qty;
                $stockRecord->increment('qty', $itemData['qty']);
                $qtyAfter = $stockRecord->fresh()->qty;

                StockLog::create([
                    'product_variant_id' => $itemData['product_variant_id'],
                    'warehouse_id' => $validated['warehouse_id'],
                    'reference_type' => StockIn::class,
                    'reference_id' => $stockIn->id,
                    'qty_before' => $qtyBefore,
                    'qty_change' => $itemData['qty'],
                    'qty_after' => $qtyAfter,
                    'note' => "Update Stock In Invoice #{$stockIn->invoice_number}",
                ]);
            }
        });

        return redirect()->route('superadmin.warehouse.stock-in.index')->with('success', 'Transaksi Stok Masuk berhasil diperbarui.');
    }

    public function destroy(StockIn $stockIn)
    {
        DB::transaction(function () use ($stockIn) {
            $stockIn->load('items');
            foreach ($stockIn->items as $item) {
                $stock = Stock::where('warehouse_id', $stockIn->warehouse_id)
                    ->where('product_variant_id', $item->product_variant_id)
                    ->first();

                if ($stock) {
                    $qtyBefore = $stock->qty;
                    $stock->decrement('qty', min($stock->qty, $item->qty));
                    $qtyAfter = $stock->fresh()->qty;

                    StockLog::create([
                        'product_variant_id' => $item->product_variant_id,
                        'warehouse_id' => $stockIn->warehouse_id,
                        'reference_type' => StockIn::class,
                        'reference_id' => $stockIn->id,
                        'qty_before' => $qtyBefore,
                        'qty_change' => -$item->qty,
                        'qty_after' => $qtyAfter,
                        'note' => "Pembatalan Stock In Invoice #{$stockIn->invoice_number}",
                    ]);
                }
            }

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
