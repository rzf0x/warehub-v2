<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Stock;
use App\Models\StockLog;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Warehouse;
use App\Services\SellerStockReconciliationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class StockController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $warehouseId = $request->input('warehouse_id');

        $stocks = Stock::query()
            ->with(['warehouse', 'productVariant.product.seller'])
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))
            ->when($search, function ($query, $s) {
                $query->whereHas('productVariant', function ($vq) use ($s) {
                    $vq->where('sku', 'like', "%{$s}%")
                        ->orWhereHas('product', fn($pq) => $pq->where('product_name', 'like', "%{$s}%"));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $warehouses = Warehouse::select('id', 'name')->get();

        $summary = [
            'total_qty' => (int) Stock::when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))->sum('qty'),
            'total_items' => Stock::when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))->count(),
            'total_warehouses' => $warehouseId ? 1 : Warehouse::count(),
            'low_stock_count' => Stock::when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))->where('qty', '>', 0)->where('qty', '<=', 5)->count(),
            'out_of_stock_count' => Stock::when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))->where('qty', '<=', 0)->count(),
        ];

        return Inertia::render('Superadmin/Warehouse/Stock/Index', [
            'stocks' => $stocks,
            'warehouses' => $warehouses,
            'summary' => $summary,
            'lowStockCount' => $summary['low_stock_count'],
            'filters' => [
                'search' => $search,
                'warehouse_id' => $warehouseId,
            ],
        ]);
    }

    public function logs(Request $request)
    {
        $search = $request->input('search');
        $warehouseId = $request->input('warehouse_id');

        $logs = StockLog::query()
            ->with(['warehouse', 'productVariant.product'])
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))
            ->when($search, function ($query, $s) {
                $query->where('note', 'like', "%{$s}%")
                    ->orWhereHas('productVariant', fn($vq) => $vq->where('sku', 'like', "%{$s}%"));
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $warehouses = Warehouse::select('id', 'name')->get();

        $summary = [
            'total_qty' => (int) Stock::when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))->sum('qty'),
            'total_items' => Stock::when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))->count(),
            'total_warehouses' => $warehouseId ? 1 : Warehouse::count(),
            'low_stock_count' => Stock::when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))->where('qty', '>', 0)->where('qty', '<=', 5)->count(),
            'out_of_stock_count' => Stock::when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))->where('qty', '<=', 0)->count(),
        ];

        return Inertia::render('Superadmin/Warehouse/Stock/Index', [
            'activeTab' => 'logs',
            'logs' => $logs,
            'warehouses' => $warehouses,
            'summary' => $summary,
            'lowStockCount' => $summary['low_stock_count'],
            'filters' => [
                'search' => $search,
                'warehouse_id' => $warehouseId,
            ],
        ]);
    }

    public function printSku(Request $request)
    {
        $productCountsBySeller = DB::table('products')
            ->selectRaw('seller_id, COUNT(*) as total')
            ->groupBy('seller_id')
            ->pluck('total', 'seller_id');

        $sellers = DB::table('sellers as s')
            ->leftJoin('brands as b', 'b.id', '=', 's.brand_id')
            ->select('s.id', 's.seller_name', 's.phone', 'b.name as brand_name')
            ->orderBy('s.seller_name')
            ->get()
            ->map(fn ($s) => [
                'id' => (int) $s->id,
                'seller_name' => (string) ($s->seller_name ?: 'Seller #' . $s->id),
                'phone' => $s->phone,
                'brand_name' => $s->brand_name,
                'products_count' => (int) ($productCountsBySeller[$s->id] ?? 0),
            ]);

        $warehouses = DB::table('warehouses')
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn ($w) => [
                'id' => (int) $w->id,
                'name' => (string) $w->name,
            ]);

        $sellerId = $request->filled('seller_id') ? (int) $request->input('seller_id') : null;
        $warehouseId = $request->filled('warehouse_id') ? (int) $request->input('warehouse_id') : null;
        $search = trim((string) $request->input('search', ''));

        $selectedSeller = $sellerId ? $sellers->firstWhere('id', $sellerId) : null;
        $selectedWarehouse = $warehouseId ? $warehouses->firstWhere('id', $warehouseId) : null;

        $products = [];
        if ($selectedSeller) {
            $like = "%{$search}%";

            $stockSubquery = DB::table('stocks as st')
                ->join('product_variants as spv', 'spv.id', '=', 'st.product_variant_id')
                ->join('products as sp', 'sp.id', '=', 'spv.product_id')
                ->where('sp.seller_id', $sellerId)
                ->when($warehouseId, fn ($q) => $q->where('st.warehouse_id', $warehouseId))
                ->groupBy('st.product_variant_id')
                ->selectRaw('st.product_variant_id, COALESCE(SUM(st.qty), 0) as total_qty');

            $rows = DB::table('products as p')
                ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
                ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
                ->leftJoin('product_variants as pv', 'pv.product_id', '=', 'p.id')
                ->leftJoinSub($stockSubquery, 'st_sum', fn ($join) => $join->on('st_sum.product_variant_id', '=', 'pv.id'))
                ->where('p.seller_id', $sellerId)
                ->when($search !== '', function ($q) use ($like) {
                    $q->where(function ($sub) use ($like) {
                        $sub->where('p.product_name', 'like', $like)
                            ->orWhere('p.sku', 'like', $like)
                            ->orWhere('pv.sku', 'like', $like);
                    });
                })
                ->select([
                    'p.id as product_id',
                    'p.product_name',
                    'p.sku as product_sku',
                    'p.product_type',
                    'b.name as brand_name',
                    'c.name as category_name',
                    'pv.id as variant_id',
                    'pv.sku as variant_sku',
                    'pv.size',
                    'pv.color',
                    DB::raw('COALESCE(st_sum.total_qty, 0) as variant_qty'),
                ])
                ->orderBy('p.product_name')
                ->orderBy('pv.sku')
                ->get();

            $grouped = [];
            foreach ($rows as $row) {
                $pid = (int) $row->product_id;
                if (! isset($grouped[$pid])) {
                    $grouped[$pid] = [
                        'id' => $pid,
                        'product_name' => (string) $row->product_name,
                        'sku' => (string) ($row->product_sku ?: '-'),
                        'product_type' => (string) ($row->product_type ?: 'single'),
                        'brand_name' => (string) ($row->brand_name ?: '-'),
                        'category_name' => (string) ($row->category_name ?: '-'),
                        'total_qty' => 0,
                        'variants_count' => 0,
                        'variants' => [],
                    ];
                }

                if ($row->variant_id !== null) {
                    $qty = (int) $row->variant_qty;
                    $grouped[$pid]['variants'][] = [
                        'id' => (int) $row->variant_id,
                        'sku' => (string) $row->variant_sku,
                        'size' => (string) ($row->size ?: '-'),
                        'color' => (string) ($row->color ?: '-'),
                        'qty' => $qty,
                    ];
                    $grouped[$pid]['total_qty'] += $qty;
                    $grouped[$pid]['variants_count']++;
                }
            }

            $products = array_values($grouped);
        }

        return Inertia::render('Superadmin/Warehouse/Stock/PrintSku', [
            'sellers' => $sellers,
            'warehouses' => $warehouses,
            'selectedSeller' => $selectedSeller,
            'selectedWarehouse' => $selectedWarehouse,
            'products' => $products,
            'filters' => [
                'seller_id' => $selectedSeller ? (string) $selectedSeller['id'] : '',
                'warehouse_id' => $warehouseId ? (string) $warehouseId : '',
                'search' => $search,
            ],
        ]);
    }

    public function exportPrintSkuPdf(Request $request)
    {
        $validated = $request->validate([
            'seller_id' => ['required', 'integer', 'exists:sellers,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'variant_ids' => ['nullable'],
            'product_ids' => ['nullable'],
        ]);

        $seller = Seller::with('brand:id,name')->findOrFail((int) $validated['seller_id']);
        $warehouseId = ! empty($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null;
        $warehouse = $warehouseId ? Warehouse::select('id', 'name')->find($warehouseId) : null;

        $rawVariantIds = $request->input('variant_ids', []);
        if (is_string($rawVariantIds)) {
            $rawVariantIds = explode(',', $rawVariantIds);
        }
        $variantIdsFilter = array_values(array_unique(array_filter(array_map('intval', (array) $rawVariantIds))));

        $rawProductIds = $request->input('product_ids', []);
        if (is_string($rawProductIds)) {
            $rawProductIds = explode(',', $rawProductIds);
        }
        $productIdsFilter = array_values(array_unique(array_filter(array_map('intval', (array) $rawProductIds))));

        $stockSubquery = DB::table('stocks as st')
            ->join('product_variants as spv', 'spv.id', '=', 'st.product_variant_id')
            ->join('products as sp', 'sp.id', '=', 'spv.product_id')
            ->where('sp.seller_id', $seller->id)
            ->when($warehouseId, fn ($q) => $q->where('st.warehouse_id', $warehouseId))
            ->groupBy('st.product_variant_id')
            ->selectRaw('st.product_variant_id, COALESCE(SUM(st.qty), 0) as total_qty');

        $query = DB::table('product_variants as pv')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoinSub($stockSubquery, 'st_sum', fn ($join) => $join->on('st_sum.product_variant_id', '=', 'pv.id'))
            ->where('p.seller_id', $seller->id);

        if (! empty($variantIdsFilter)) {
            $query->whereIn('pv.id', $variantIdsFilter);
        } elseif (! empty($productIdsFilter)) {
            $query->whereIn('p.id', $productIdsFilter);
        }

        $rows = $query
            ->select([
                'p.id as product_id',
                'p.product_name',
                'p.sku as product_sku',
                'p.product_type',
                'c.name as category_name',
                'pv.id as variant_id',
                'pv.sku as variant_sku',
                'pv.size',
                'pv.color',
                DB::raw('COALESCE(st_sum.total_qty, 0) as variant_qty'),
            ])
            ->orderBy('p.product_name')
            ->orderBy('pv.sku')
            ->get();

        $grouped = [];
        $totalVariantsCount = 0;
        $grandTotalQty = 0;

        foreach ($rows as $row) {
            $pid = (int) $row->product_id;
            if (! isset($grouped[$pid])) {
                $grouped[$pid] = [
                    'id' => $pid,
                    'product_name' => (string) $row->product_name,
                    'sku' => (string) ($row->product_sku ?: '-'),
                    'product_type' => (string) ($row->product_type ?: 'single'),
                    'category_name' => (string) ($row->category_name ?: '-'),
                    'total_qty' => 0,
                    'variants' => [],
                ];
            }

            $qty = (int) $row->variant_qty;
            $grouped[$pid]['variants'][] = [
                'id' => (int) $row->variant_id,
                'sku' => (string) $row->variant_sku,
                'size' => (string) ($row->size ?: '-'),
                'color' => (string) ($row->color ?: '-'),
                'qty' => $qty,
            ];
            $grouped[$pid]['total_qty'] += $qty;
            $totalVariantsCount++;
            $grandTotalQty += $qty;
        }

        $productsData = array_values($grouped);
        $totalProductsCount = count($productsData);

        $slug = Str::slug($seller->seller_name ?: 'mitra');
        $filename = "SKU_Stock_{$slug}_" . date('Ymd_His') . '.pdf';

        $pdf = Pdf::loadView('pdf.sku_stock_report', [
            'seller' => $seller,
            'warehouse' => $warehouse,
            'products' => $productsData,
            'summary' => [
                'total_products' => $totalProductsCount,
                'total_variants' => $totalVariantsCount,
                'grand_total_qty' => $grandTotalQty,
            ],
        ])->setPaper('a4', 'portrait');

        if ($request->boolean('download')) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    public function reconciliation(Request $request, SellerStockReconciliationService $service)
    {
        $sellers = Seller::select('id', 'seller_name', 'phone')
            ->orderBy('seller_name')
            ->get();
        $warehouses = Warehouse::select('id', 'name')
            ->orderBy('name')
            ->get();
        $periods = Period::select('id', 'name')
            ->orderBy('start_date', 'desc')
            ->get();

        $sellerId = $request->filled('seller_id') ? (int) $request->input('seller_id') : null;
        $selectedSeller = $sellerId ? Seller::select('id', 'seller_name', 'phone')->find($sellerId) : null;

        $warehouseId = $request->filled('warehouse_id') ? (int) $request->input('warehouse_id') : null;
        if ($selectedSeller && ! $warehouseId && $warehouses->isNotEmpty()) {
            $warehouseId = (int) $warehouses->first()->id;
        }

        $periodId = $request->filled('period_id') ? (int) $request->input('period_id') : null;
        $prefix = trim((string) $request->input('prefix', ''));
        $search = trim((string) $request->input('search', ''));

        $selectedWarehouse = $warehouseId ? $warehouses->firstWhere('id', $warehouseId) : null;

        $report = $selectedSeller
            ? $service->buildReport(
                $selectedSeller,
                $warehouseId,
                $periodId,
                $prefix !== '' ? $prefix : null,
                $search !== '' ? $search : null
            )
            : null;

        return Inertia::render('Superadmin/Warehouse/Stock/Reconciliation', [
            'sellers' => $sellers,
            'warehouses' => $warehouses,
            'periods' => $periods,
            'selectedSeller' => $selectedSeller,
            'selectedWarehouse' => $selectedWarehouse,
            'report' => $report,
            'filters' => [
                'seller_id' => $selectedSeller ? (string) $selectedSeller->id : '',
                'warehouse_id' => $warehouseId ? (string) $warehouseId : '',
                'period_id' => $periodId ? (string) $periodId : '',
                'prefix' => $prefix,
                'search' => $search,
            ],
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
                'warning' => session('warning'),
            ],
        ]);
    }

    public function exportReconciliationCsv(Request $request, SellerStockReconciliationService $service)
    {
        $validated = $request->validate([
            'seller_id' => ['required', 'exists:sellers,id'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'period_id' => ['nullable', 'exists:periods,id'],
            'prefix' => ['nullable', 'string', 'max:50'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $seller = Seller::findOrFail((int) $validated['seller_id']);
        $warehouseId = ! empty($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null;
        $periodId = ! empty($validated['period_id']) ? (int) $validated['period_id'] : null;
        $prefix = isset($validated['prefix']) && trim($validated['prefix']) !== '' ? trim($validated['prefix']) : null;
        $search = isset($validated['search']) && trim($validated['search']) !== '' ? trim($validated['search']) : null;

        $report = $service->buildReport($seller, $warehouseId, $periodId, $prefix, $search);
        $slug = Str::slug($seller->seller_name ?: 'mitra');
        $filename = "rekonsiliasi-hutang-stok-{$slug}-" . date('Ymd') . '.csv';

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');
            if (! $handle) {
                return;
            }

            // UTF-8 BOM so Indonesian Excel opens UTF-8 cleanly
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Prefix SKU',
                'SKU',
                'Nama Produk',
                'Size',
                'Color',
                'Konveksi',
                'HPP Satuan',
                'Qty Masuk',
                'HPP Masuk',
                'Qty Keluar Single',
                'Qty Keluar Bundle',
                'Total Qty Keluar',
                'HPP Keluar',
                'Sisa Hutang Qty',
                'Sisa Hutang HPP',
                'Stok Fisik Gudang',
                'Nilai Stok Fisik',
                'Selisih Qty (Fisik - Hutang)',
            ]);

            foreach ($report['groups'] as $group) {
                foreach ($group['variants'] as $v) {
                    fputcsv($handle, [
                        $v['prefix'],
                        $v['sku'],
                        $v['product_name'],
                        $v['size'],
                        $v['color'],
                        $v['konveksi_name'],
                        $v['hpp_price'],
                        $v['in_qty'],
                        $v['in_hpp'],
                        $v['single_out_qty'],
                        $v['bundle_out_qty'],
                        $v['out_qty'],
                        $v['out_hpp'],
                        $v['sisa_debt_qty'],
                        $v['sisa_debt_hpp'],
                        $v['warehouse_stock_qty'],
                        $v['stock_hpp_value'],
                        $v['qty_gap'],
                    ]);
                }

                fputcsv($handle, [
                    "SUBTOTAL {$group['prefix']}",
                    '',
                    "{$group['variant_count']} Varian",
                    '',
                    '',
                    '',
                    '',
                    $group['in_qty'],
                    $group['in_hpp'],
                    $group['single_out_qty'],
                    $group['bundle_out_qty'],
                    $group['out_qty'],
                    $group['out_hpp'],
                    $group['sisa_debt_qty'],
                    $group['sisa_debt_hpp'],
                    $group['warehouse_stock_qty'],
                    $group['stock_hpp_value'],
                    $group['qty_gap'],
                ]);
            }

            $summary = $report['summary'];
            fputcsv($handle, [
                'GRAND TOTAL',
                '',
                "{$summary['total_variants']} Varian",
                '',
                '',
                '',
                '',
                $summary['in_qty'],
                $summary['in_hpp'],
                $summary['single_out_qty'],
                $summary['bundle_out_qty'],
                $summary['out_qty'],
                $summary['out_hpp'],
                $summary['sisa_debt_qty'],
                $summary['sisa_debt_hpp'],
                $summary['warehouse_stock_qty'],
                $summary['stock_hpp_value'],
                $summary['qty_gap'],
            ]);

            fputcsv($handle, [
                'ADJUSTMENT SELLER',
                '',
                'Penyesuaian Saldo SellerDebtAdjustment',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                $summary['total_adjustment'],
                '',
                '',
                '',
            ]);

            fputcsv($handle, [
                'NET HUTANG AKHIR',
                '',
                'Sisa Hutang Varian + Adjustment',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                $summary['net_debt_with_adjustment'],
                '',
                '',
                '',
            ]);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportReconciliationPdf(Request $request, SellerStockReconciliationService $service)
    {
        $validated = $request->validate([
            'seller_id' => ['required', 'exists:sellers,id'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'period_id' => ['nullable', 'exists:periods,id'],
            'prefix' => ['nullable', 'string', 'max:50'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $seller = Seller::findOrFail((int) $validated['seller_id']);
        $warehouseId = ! empty($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null;
        $periodId = ! empty($validated['period_id']) ? (int) $validated['period_id'] : null;
        $prefixFilter = isset($validated['prefix']) && trim($validated['prefix']) !== '' ? trim($validated['prefix']) : null;
        $search = isset($validated['search']) && trim($validated['search']) !== '' ? trim($validated['search']) : null;

        $warehouse = $warehouseId ? Warehouse::find($warehouseId) : null;
        $period = $periodId ? Period::find($periodId) : null;
        $report = $service->buildReport($seller, $warehouseId, $periodId, $prefixFilter, $search);

        $slug = Str::slug($seller->seller_name ?: 'mitra');
        $pdf = Pdf::loadView('pdf.seller_stock_reconciliation', [
            'seller' => $seller,
            'warehouse' => $warehouse,
            'period' => $period,
            'prefixFilter' => $prefixFilter,
            'search' => $search,
            'report' => $report,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream("Rekonsiliasi_Hutang_Stok_{$slug}_" . date('Ymd') . '.pdf');
    }

    public function downloadOpnameTemplateCsv(Request $request, SellerStockReconciliationService $service)
    {
        $validated = $request->validate([
            'seller_id' => ['required', 'exists:sellers,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'prefix' => ['nullable', 'string', 'max:50'],
        ]);

        $seller = Seller::findOrFail((int) $validated['seller_id']);
        $warehouseId = (int) $validated['warehouse_id'];
        $prefix = isset($validated['prefix']) && trim($validated['prefix']) !== '' ? trim($validated['prefix']) : null;

        $report = $service->buildReport($seller, $warehouseId, null, $prefix, null);
        $slug = Str::slug($seller->seller_name ?: 'mitra');
        $filename = "template-opname-{$slug}-" . date('Ymd') . '.csv';

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');
            if (! $handle) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'sku',
                'prefix',
                'product_name',
                'size',
                'color',
                'stok_sistem_sekarang',
                'sisa_hutang_qty',
                'stok_fisik_opname',
            ]);

            foreach ($report['groups'] as $group) {
                foreach ($group['variants'] as $v) {
                    fputcsv($handle, [
                        $v['sku'],
                        $v['prefix'],
                        $v['product_name'],
                        $v['size'],
                        $v['color'],
                        $v['warehouse_stock_qty'],
                        $v['sisa_debt_qty'],
                        '',
                    ]);
                }
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function resetSellerStockToZero(Request $request)
    {
        $validated = $request->validate([
            'seller_id' => ['required', 'exists:sellers,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
            'prefixes' => ['nullable', 'array'],
            'prefixes.*' => ['string', 'max:50'],
        ]);

        $seller = Seller::findOrFail((int) $validated['seller_id']);
        $warehouse = Warehouse::findOrFail((int) $validated['warehouse_id']);
        $prefixes = array_values(array_filter(array_map(
            fn ($p) => strtoupper(trim((string) $p)),
            $validated['prefixes'] ?? []
        )));

        $variants = DB::table('product_variants as pv')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('p.seller_id', $seller->id)
            ->where(function ($q) {
                $q->where('p.product_type', '!=', 'bundle')
                    ->orWhereNull('p.product_type');
            })
            ->select('pv.id', 'pv.sku')
            ->get();

        if (! empty($prefixes)) {
            $variants = $variants->filter(function ($v) use ($prefixes) {
                return in_array(SellerStockReconciliationService::extractSkuPrefix($v->sku), $prefixes, true);
            });
        }

        $variantIds = $variants->pluck('id')->all();
        if (empty($variantIds)) {
            return redirect()->back()->with('warning', "Tidak ada varian produk untuk mitra {$seller->seller_name} pada filter yang dipilih.");
        }

        $result = DB::transaction(function () use ($validated, $request, $seller, $warehouse, $variantIds, $prefixes) {
            $stocksToReset = Stock::where('warehouse_id', $warehouse->id)
                ->whereIn('product_variant_id', $variantIds)
                ->where('qty', '!=', 0)
                ->lockForUpdate()
                ->get();

            if ($stocksToReset->isEmpty()) {
                return null;
            }

            $prefixLabel = ! empty($prefixes) ? ' [Prefix: ' . implode(', ', $prefixes) . ']' : '';
            $rawNote = trim((string) ($validated['note'] ?? ''));
            $opnameNote = $rawNote !== '' ? $rawNote : "Reset Nol Pra-Opname Mitra: {$seller->seller_name}{$prefixLabel}";

            $stockOpname = StockOpname::create([
                'warehouse_id' => $warehouse->id,
                'user_id' => $request->user()?->id,
                'date' => $validated['date'],
                'note' => $opnameNote,
            ]);

            $now = now();
            $opnameItems = [];
            $logEntries = [];

            foreach ($stocksToReset as $stock) {
                $qtyBefore = (int) $stock->qty;
                $difference = 0 - $qtyBefore;

                $opnameItems[] = [
                    'stock_opname_id' => $stockOpname->id,
                    'product_variant_id' => $stock->product_variant_id,
                    'system_qty' => $qtyBefore,
                    'physical_qty' => 0,
                    'difference' => $difference,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $stock->update(['qty' => 0]);

                $logEntries[] = [
                    'stock_id' => $stock->id,
                    'product_variant_id' => $stock->product_variant_id,
                    'warehouse_id' => $warehouse->id,
                    'user_id' => $request->user()?->id,
                    'reference_type' => StockOpname::class,
                    'reference_id' => $stockOpname->id,
                    'qty_before' => $qtyBefore,
                    'qty_change' => $difference,
                    'qty_after' => 0,
                    'note' => "Reset Nol Pra-Opname {$seller->seller_name} (#OPN-{$stockOpname->id}, Diff: {$difference})",
                ];
            }

            foreach (array_chunk($opnameItems, 500) as $chunk) {
                StockOpnameItem::insert($chunk);
            }

            $this->insertSafeStockLogs($logEntries);

            return [
                'opname_id' => $stockOpname->id,
                'count' => count($opnameItems),
            ];
        });

        if (! $result) {
            return redirect()->back()->with('warning', "Semua stok varian yang dipilih untuk mitra {$seller->seller_name} di gudang {$warehouse->name} sudah bernilai 0.");
        }

        return redirect()->back()->with(
            'success',
            "Berhasil men-nol-kan {$result['count']} varian stok mitra {$seller->seller_name} di {$warehouse->name} (Audit #OPN-{$result['opname_id']}). Hutang modal lama tidak berubah."
        );
    }

    public function applySellerOpname(Request $request)
    {
        $validated = $request->validate([
            'seller_id' => ['required', 'exists:sellers,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.physical_qty' => ['required', 'integer', 'min:0'],
        ]);

        $seller = Seller::findOrFail((int) $validated['seller_id']);
        $warehouse = Warehouse::findOrFail((int) $validated['warehouse_id']);

        $validVariantMap = DB::table('product_variants as pv')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('p.seller_id', $seller->id)
            ->where(function ($q) {
                $q->where('p.product_type', '!=', 'bundle')
                    ->orWhereNull('p.product_type');
            })
            ->pluck('pv.id')
            ->flip()
            ->all();

        $dedupedItems = [];
        foreach ($validated['items'] as $item) {
            $variantId = (int) $item['product_variant_id'];
            if (! isset($validVariantMap[$variantId])) {
                throw ValidationException::withMessages([
                    'items' => 'Terdapat varian yang bukan milik mitra terpilih atau bertipe bundle.',
                ]);
            }
            $dedupedItems[$variantId] = (int) $item['physical_qty'];
        }

        $result = DB::transaction(function () use ($validated, $request, $seller, $warehouse, $dedupedItems) {
            $variantIds = array_keys($dedupedItems);

            $existingStocks = Stock::where('warehouse_id', $warehouse->id)
                ->whereIn('product_variant_id', $variantIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('product_variant_id');

            $changedEntries = [];
            foreach ($dedupedItems as $variantId => $physicalQty) {
                $stock = $existingStocks->get($variantId);
                if (! $stock) {
                    $stock = Stock::firstOrCreate(
                        ['warehouse_id' => $warehouse->id, 'product_variant_id' => $variantId],
                        ['qty' => 0, 'allocated_qty' => 0]
                    );
                }

                $qtyBefore = (int) $stock->qty;
                if ($physicalQty === $qtyBefore) {
                    continue;
                }

                $changedEntries[] = [
                    'stock' => $stock,
                    'variant_id' => $variantId,
                    'qty_before' => $qtyBefore,
                    'physical_qty' => $physicalQty,
                    'difference' => $physicalQty - $qtyBefore,
                ];
            }

            if (empty($changedEntries)) {
                return null;
            }

            $rawNote = trim((string) ($validated['note'] ?? ''));
            $opnameNote = $rawNote !== '' ? $rawNote : "Input Hasil Stock Opname Mitra: {$seller->seller_name}";

            $stockOpname = StockOpname::create([
                'warehouse_id' => $warehouse->id,
                'user_id' => $request->user()?->id,
                'date' => $validated['date'],
                'note' => $opnameNote,
            ]);

            $now = now();
            $opnameItems = [];
            $logEntries = [];

            foreach ($changedEntries as $entry) {
                /** @var Stock $stock */
                $stock = $entry['stock'];
                $qtyBefore = $entry['qty_before'];
                $physicalQty = $entry['physical_qty'];
                $difference = $entry['difference'];
                $diffStr = $difference > 0 ? "+{$difference}" : (string) $difference;

                $opnameItems[] = [
                    'stock_opname_id' => $stockOpname->id,
                    'product_variant_id' => $entry['variant_id'],
                    'system_qty' => $qtyBefore,
                    'physical_qty' => $physicalQty,
                    'difference' => $difference,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $stock->update(['qty' => $physicalQty]);

                $logEntries[] = [
                    'stock_id' => $stock->id,
                    'product_variant_id' => $entry['variant_id'],
                    'warehouse_id' => $warehouse->id,
                    'user_id' => $request->user()?->id,
                    'reference_type' => StockOpname::class,
                    'reference_id' => $stockOpname->id,
                    'qty_before' => $qtyBefore,
                    'qty_change' => $difference,
                    'qty_after' => $physicalQty,
                    'note' => "Hasil Opname Mitra {$seller->seller_name} (#OPN-{$stockOpname->id}, Diff: {$diffStr})",
                ];
            }

            foreach (array_chunk($opnameItems, 500) as $chunk) {
                StockOpnameItem::insert($chunk);
            }

            $this->insertSafeStockLogs($logEntries);

            return [
                'opname_id' => $stockOpname->id,
                'count' => count($opnameItems),
            ];
        });

        if (! $result) {
            return redirect()->back()->with('warning', 'Tidak ada perubahan stok untuk disimpan (semua nilai sama dengan stok sistem saat ini).');
        }

        return redirect()->back()->with(
            'success',
            "Berhasil menyimpan hasil Stock Opname untuk {$result['count']} varian mitra {$seller->seller_name} (Audit #OPN-{$result['opname_id']}). Hutang modal lama tidak berubah."
        );
    }

    /**
     * Insert stock logs defensively matching whichever schema columns exist on stock_logs.
     *
     * @param array<int, array<string, mixed>> $entries
     */
    private function insertSafeStockLogs(array $entries): void
    {
        if (empty($entries)) {
            return;
        }

        $columns = array_flip(Schema::getColumnListing('stock_logs'));
        $now = now();
        $rows = [];

        foreach ($entries as $entry) {
            $candidate = [
                'stock_id' => $entry['stock_id'] ?? null,
                'product_variant_id' => $entry['product_variant_id'] ?? null,
                'warehouse_id' => $entry['warehouse_id'] ?? null,
                'user_id' => $entry['user_id'] ?? auth()->id(),
                'type' => 'OPNAME',
                'reference_type' => $entry['reference_type'] ?? null,
                'reference_id' => $entry['reference_id'] ?? null,
                'qty_before' => $entry['qty_before'] ?? 0,
                'qty_change' => $entry['qty_change'] ?? 0,
                'qty_after' => $entry['qty_after'] ?? 0,
                'quantity_before' => $entry['qty_before'] ?? 0,
                'change_amount' => $entry['qty_change'] ?? 0,
                'quantity_after' => $entry['qty_after'] ?? 0,
                'note' => Str::limit((string) ($entry['note'] ?? ''), 190),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $row = [];
            foreach ($candidate as $col => $val) {
                if (isset($columns[$col])) {
                    $row[$col] = $val;
                }
            }

            if (! empty($row)) {
                $rows[] = $row;
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('stock_logs')->insert($chunk);
        }
    }
}
