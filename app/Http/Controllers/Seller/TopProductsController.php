<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Seller;
use App\Models\StockOutItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TopProductsController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $seller = $user->sellers()->first() ?? Seller::first();

        if (!$seller) {
            return Inertia::render('Seller/Sales/TopProducts', [
                'topProducts' => [],
                'grandTotalOmset' => 0,
                'filters' => [],
            ]);
        }

        $preset = $request->input('preset', 'this_month'); // 'this_month', '7_days', 'all_time'

        $now = Carbon::now();
        if ($preset === '7_days') {
            $startDate = $now->copy()->subDays(6)->startOfDay();
            $endDate = $now->copy()->endOfDay();
        } elseif ($preset === 'this_month') {
            $startDate = $now->copy()->startOfMonth();
            $endDate = $now->copy()->endOfMonth();
        } else {
            $startDate = null;
            $endDate = null;
        }

        // Aggregate top sales grouped by product
        $query = StockOutItem::whereHas('productVariant.product', function ($q) use ($seller) {
            $q->where('seller_id', $seller->id);
        });

        if ($startDate && $endDate) {
            $query->whereHas('stockOut', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('date', [$startDate, $endDate]);
            });
        }

        $grandTotalOmset = (float) (clone $query)->sum(DB::raw('qty * selling_price'));

        $topSales = $query->select(
            'product_variants.product_id',
            DB::raw('SUM(stock_out_items.qty) as total_qty'),
            DB::raw('SUM(stock_out_items.qty * stock_out_items.selling_price) as total_omset'),
            DB::raw('SUM(stock_out_items.qty * stock_out_items.price) as total_hpp')
        )
        ->join('product_variants', 'stock_out_items.product_variant_id', '=', 'product_variants.id')
        ->groupBy('product_variants.product_id')
        ->orderBy('total_omset', 'desc')
        ->limit(20)
        ->get();

        $productIds = $topSales->pluck('product_id');
        $productsMap = Product::whereIn('id', $productIds)->with(['category', 'brand'])->get()->keyBy('id');

        $topProducts = $topSales->map(function ($item, $index) use ($productsMap, $grandTotalOmset) {
            $prod = $productsMap[$item->product_id] ?? null;
            $omset = (float) $item->total_omset;
            $hpp = (float) $item->total_hpp;
            $qty = (int) $item->total_qty;

            $contributionPercent = $grandTotalOmset > 0 ? round(($omset / $grandTotalOmset) * 100, 1) : 0;

            $crown = null;
            if ($index === 0) $crown = 'gold';
            elseif ($index === 1) $crown = 'silver';
            elseif ($index === 2) $crown = 'bronze';

            return [
                'rank' => $index + 1,
                'crown' => $crown,
                'product_id' => $item->product_id,
                'product_name' => $prod->product_name ?? 'Produk',
                'sku' => $prod->sku ?? '-',
                'category_name' => $prod->category->name ?? 'Tanpa Kategori',
                'brand_name' => $prod->brand->name ?? 'Tanpa Brand',
                'image' => $prod->image ?? null,
                'total_qty' => $qty,
                'total_omset' => $omset,
                'total_hpp' => $hpp,
                'gross_profit' => $omset - $hpp,
                'contribution_percent' => $contributionPercent,
            ];
        });

        return Inertia::render('Seller/Sales/TopProducts', [
            'topProducts' => $topProducts,
            'grandTotalOmset' => $grandTotalOmset,
            'filters' => [
                'preset' => $preset,
            ],
        ]);
    }
}
