<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\StockLog;
use App\Models\Warehouse;
use Illuminate\Http\Request;
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
}
