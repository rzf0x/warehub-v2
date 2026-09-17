<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\StockLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StockDetailController extends Controller
{
    public function showProduct(Product $product, Request $request)
    {
        $user = auth()->user();
        $seller = $user->sellers()->first() ?? Seller::first();

        if ($seller && $product->seller_id !== $seller->id) {
            abort(403, 'Akses tidak diizinkan.');
        }

        $product->load(['category', 'brand', 'variants.stocks.warehouse']);

        $variantIds = $product->variants->pluck('id');

        $logs = StockLog::whereIn('product_variant_id', $variantIds)
            ->with(['productVariant', 'warehouse'])
            ->latest()
            ->paginate(20)
            ->through(function ($log) {
                $refLabel = 'Mutasi Stok';
                if (str_contains($log->reference_type ?? '', 'StockIn')) {
                    $refLabel = 'Stock In (Barang Masuk)';
                } elseif (str_contains($log->reference_type ?? '', 'StockOut')) {
                    $refLabel = 'Stock Out (Penjualan Keluar)';
                } elseif (str_contains($log->reference_type ?? '', 'StockOpname')) {
                    $refLabel = 'Stock Opname (Audit Stok)';
                }

                return [
                    'id' => $log->id,
                    'created_at' => $log->created_at ? $log->created_at->format('d M Y - H:i') : '-',
                    'sku' => $log->productVariant->sku ?? '-',
                    'variant_info' => trim(($log->productVariant->color ?? '') . ' ' . ($log->productVariant->size ?? '')),
                    'warehouse_name' => $log->warehouse->name ?? 'Gudang',
                    'qty_before' => (int) $log->qty_before,
                    'qty_change' => (int) $log->qty_change,
                    'qty_after' => (int) $log->qty_after,
                    'ref_label' => $refLabel,
                    'note' => $log->note,
                ];
            });

        $totalStockPcs = $product->variants->sum(function ($v) {
            return $v->stocks->sum('qty');
        });

        return Inertia::render('Seller/Stocks/StockProductDetail', [
            'product' => [
                'id' => $product->id,
                'product_name' => $product->product_name,
                'sku' => $product->sku,
                'category_name' => $product->category->name ?? 'Tanpa Kategori',
                'brand_name' => $product->brand->name ?? 'Tanpa Brand',
                'total_stock_pcs' => $totalStockPcs,
                'variants' => $product->variants->map(function ($v) {
                    return [
                        'id' => $v->id,
                        'sku' => $v->sku,
                        'size' => $v->size,
                        'color' => $v->color,
                        'price' => (float) $v->price,
                        'selling_price' => (float) $v->selling_price,
                        'qty' => (int) $v->stocks->sum('qty'),
                    ];
                }),
            ],
            'logs' => $logs,
        ]);
    }
}
