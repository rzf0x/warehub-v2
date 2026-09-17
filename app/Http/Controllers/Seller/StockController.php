<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\Seller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StockController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $seller = $user->sellers()->first() ?? Seller::first();

        if (!$seller) {
            return Inertia::render('Seller/Stocks/Index', [
                'variants' => [],
                'summary' => [
                    'total_variants' => 0,
                    'low_stock_count' => 0,
                    'out_of_stock_count' => 0,
                    'safe_stock_count' => 0,
                    'total_stock_pcs' => 0,
                ],
                'filters' => [],
            ]);
        }

        $search = $request->input('search');
        $statusFilter = $request->input('status'); // 'all', 'low_stock', 'out_of_stock', 'safe'
        $threshold = 5;

        // Fetch variants belonging to seller
        $query = ProductVariant::whereHas('product', function ($q) use ($seller) {
            $q->where('seller_id', $seller->id);
        })->with(['product.category', 'stocks.warehouse']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                  ->orWhere('color', 'like', "%{$search}%")
                  ->orWhere('size', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('product_name', 'like', "%{$search}%");
                  });
            });
        }

        $allVariants = $query->get()->map(function ($variant) use ($threshold) {
            $totalQty = (int) $variant->stocks->sum('qty');

            $status = 'safe';
            $statusLabel = 'Stok Aman';
            if ($totalQty === 0) {
                $status = 'out_of_stock';
                $statusLabel = 'Stok Habis';
            } elseif ($totalQty <= $threshold) {
                $status = 'low_stock';
                $statusLabel = 'Stok Menipis';
            }

            return [
                'id' => $variant->id,
                'product_id' => $variant->product_id,
                'product_name' => $variant->product->product_name ?? 'Produk',
                'category_name' => $variant->product->category->name ?? '-',
                'sku' => $variant->sku,
                'size' => $variant->size,
                'color' => $variant->color,
                'price' => (float) $variant->price,
                'selling_price' => (float) $variant->selling_price,
                'total_qty' => $totalQty,
                'status' => $status,
                'status_label' => $statusLabel,
                'stocks' => $variant->stocks->map(function ($st) {
                    return [
                        'warehouse_id' => $st->warehouse_id,
                        'warehouse_name' => $st->warehouse->name ?? 'Gudang',
                        'qty' => (int) $st->qty,
                    ];
                }),
            ];
        });

        $totalVariants = $allVariants->count();
        $lowStockCount = $allVariants->where('status', 'low_stock')->count();
        $outOfStockCount = $allVariants->where('status', 'out_of_stock')->count();
        $safeStockCount = $allVariants->where('status', 'safe')->count();
        $totalStockPcs = $allVariants->sum('total_qty');

        // Apply Status Filter
        $filteredVariants = $allVariants;
        if ($statusFilter === 'low_stock') {
            $filteredVariants = $allVariants->where('status', 'low_stock')->values();
        } elseif ($statusFilter === 'out_of_stock') {
            $filteredVariants = $allVariants->where('status', 'out_of_stock')->values();
        } elseif ($statusFilter === 'safe') {
            $filteredVariants = $allVariants->where('status', 'safe')->values();
        }

        return Inertia::render('Seller/Stocks/Index', [
            'variants' => $filteredVariants,
            'lowStockAlerts' => $allVariants->whereIn('status', ['low_stock', 'out_of_stock'])->values()->take(5),
            'summary' => [
                'total_variants' => $totalVariants,
                'low_stock_count' => $lowStockCount,
                'out_of_stock_count' => $outOfStockCount,
                'safe_stock_count' => $safeStockCount,
                'total_stock_pcs' => $totalStockPcs,
            ],
            'filters' => [
                'search' => $search,
                'status' => $statusFilter ?? 'all',
            ],
        ]);
    }
}
