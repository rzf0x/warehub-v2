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
    public function index(Request $request)
    {
        $search = $request->input('search');
        $warehouseId = $request->input('warehouse_id');
        $periodId = $request->input('period_id');
        $sellerId = $request->input('seller_id');

        $stockIns = StockIn::query()
            ->with(['warehouse', 'user', 'period', 'items.productVariant.product.seller', 'items.konveksi'])
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))
            ->when($periodId, fn($q) => $q->where('period_id', $periodId))
            ->when($sellerId, function ($q) use ($sellerId) {
                $q->whereHas('items.productVariant.product', fn($pq) => $pq->where('seller_id', $sellerId));
            })
            ->when($search, function ($query, $s) {
                $query->where(function ($q) use ($s) {
                    $q->where('invoice_number', 'like', "%{$s}%")
                        ->orWhere('note', 'like', "%{$s}%")
                        ->orWhereHas('warehouse', fn($wh) => $wh->where('name', 'like', "%{$s}%"))
                        ->orWhereHas('items.productVariant.product.seller', fn($sel) => $sel->where('seller_name', 'like', "%{$s}%"))
                        ->orWhereHas('items.productVariant', fn($pv) => $pv->where('sku', 'like', "%{$s}%")->orWhereHas('product', fn($p) => $p->where('product_name', 'like', "%{$s}%")));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $warehouses = Warehouse::select('id', 'name')->get();
        $periods = Period::select('id', 'name')->get();
        $sellers = Seller::select('id', 'seller_name')->orderBy('seller_name')->get();

        $summary = [
            'total_transactions' => StockIn::query()
                ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))
                ->when($periodId, fn($q) => $q->where('period_id', $periodId))
                ->when($sellerId, fn($q) => $q->whereHas('items.productVariant.product', fn($pq) => $pq->where('seller_id', $sellerId)))
                ->count(),
            'total_qty' => (int) StockInItem::query()
                ->whereHas('stockIn', function ($q) use ($warehouseId, $periodId, $sellerId) {
                    $q->when($warehouseId, fn($sq) => $sq->where('warehouse_id', $warehouseId))
                      ->when($periodId, fn($sq) => $sq->where('period_id', $periodId))
                      ->when($sellerId, fn($sq) => $sq->whereHas('items.productVariant.product', fn($pq) => $pq->where('seller_id', $sellerId)));
                })
                ->when($sellerId, fn($iq) => $iq->whereHas('productVariant.product', fn($pq) => $pq->where('seller_id', $sellerId)))
                ->sum('qty'),
            'total_nominal' => (float) StockInItem::query()
                ->whereHas('stockIn', function ($q) use ($warehouseId, $periodId, $sellerId) {
                    $q->when($warehouseId, fn($sq) => $sq->where('warehouse_id', $warehouseId))
                      ->when($periodId, fn($sq) => $sq->where('period_id', $periodId))
                      ->when($sellerId, fn($sq) => $sq->whereHas('items.productVariant.product', fn($pq) => $pq->where('seller_id', $sellerId)));
                })
                ->when($sellerId, fn($iq) => $iq->whereHas('productVariant.product', fn($pq) => $pq->where('seller_id', $sellerId)))
                ->sum(DB::raw('qty * price')),
            'total_sellers' => $sellers->count(),
        ];

        return Inertia::render('Superadmin/Warehouse/StockIn/Index', [
            'stockIns' => $stockIns,
            'warehouses' => $warehouses,
            'periods' => $periods,
            'sellers' => $sellers,
            'summary' => $summary,
            'filters' => [
                'search' => $search,
                'warehouse_id' => $warehouseId,
                'period_id' => $periodId,
                'seller_id' => $sellerId,
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

    public function create()
    {
        return Inertia::render('Superadmin/Warehouse/StockIn/Form', [
            'warehouses' => Warehouse::select('id', 'name')->get(),
            'periods' => Period::select('id', 'name')->get(),
            'sellers' => Seller::select('id', 'seller_name')->orderBy('seller_name')->get(),
            'konveksis' => Konveksi::select('id', 'name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'period_id' => ['nullable', 'exists:periods,id'],
            'invoice_number' => ['required', 'string', 'max:100', 'unique:stock_ins,invoice_number'],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'exists:product_variants,id'],
            'items.*.konveksi_id' => ['nullable', 'exists:konveksis,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($validated, $request) {
            $stockIn = StockIn::create([
                'warehouse_id' => $validated['warehouse_id'],
                'user_id' => $request->user()->id,
                'period_id' => $validated['period_id'] ?? null,
                'invoice_number' => $validated['invoice_number'],
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
        $stockIn->load(['warehouse', 'user', 'period', 'items.productVariant.product', 'items.konveksi']);
        $pdf = Pdf::loadView('pdf.stock_in_surat_jalan', compact('stockIn'));

        return $pdf->stream("Surat_Jalan_StockIn_{$stockIn->invoice_number}.pdf");
    }

    public function exportReport(Request $request)
    {
        $warehouseId = $request->input('warehouse_id');
        $stockIns = StockIn::with(['warehouse', 'user', 'items'])->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))->get();

        $pdf = Pdf::loadView('pdf.stock_in_report', compact('stockIns'));

        return $pdf->stream('Laporan_Penerimaan_Barang_StockIn.pdf');
    }
}
