<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\StockOut;
use App\Models\StockOutItem;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SalesController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $seller = $user->sellers()->first() ?? Seller::first();

        if (!$seller) {
            return Inertia::render('Seller/Sales/Index', [
                'metrics' => [
                    'total_omset' => 0,
                    'total_hpp' => 0,
                    'gross_profit' => 0,
                    'total_qty' => 0,
                ],
                'sales' => [],
                'chartData' => [],
                'stores' => [],
                'filters' => [],
            ]);
        }

        $preset = $request->input('preset', 'this_month'); // 'today', '7_days', 'this_month', 'custom'
        $startDateParam = $request->input('start_date');
        $endDateParam = $request->input('end_date');
        $storeId = $request->input('store_id');
        $search = $request->input('search');

        $now = Carbon::now();
        if ($preset === 'today') {
            $startDate = $now->copy()->startOfDay();
            $endDate = $now->copy()->endOfDay();
        } elseif ($preset === '7_days') {
            $startDate = $now->copy()->subDays(6)->startOfDay();
            $endDate = $now->copy()->endOfDay();
        } elseif ($preset === 'this_month') {
            $startDate = $now->copy()->startOfMonth();
            $endDate = $now->copy()->endOfMonth();
        } elseif ($preset === 'custom' && $startDateParam && $endDateParam) {
            $startDate = Carbon::parse($startDateParam)->startOfDay();
            $endDate = Carbon::parse($endDateParam)->endOfDay();
        } else {
            $startDate = $now->copy()->startOfMonth();
            $endDate = $now->copy()->endOfMonth();
        }

        // Base Query for items
        $itemQuery = StockOutItem::whereHas('productVariant.product', function ($q) use ($seller) {
            $q->where('seller_id', $seller->id);
        })->whereHas('stockOut', function ($q) use ($startDate, $endDate, $storeId) {
            $q->whereBetween('date', [$startDate, $endDate]);
            if ($storeId) {
                $q->where('store_id', $storeId);
            }
        });

        // Summary Metrics
        $totalOmset = (float) (clone $itemQuery)->sum(DB::raw('qty * selling_price'));
        $totalHpp = (float) (clone $itemQuery)->sum(DB::raw('qty * price'));
        $grossProfit = $totalOmset - $totalHpp;
        $totalQty = (int) (clone $itemQuery)->sum('qty');

        // Paginated Sales Transactions (StockOuts)
        $stockOutQuery = StockOut::whereHas('items.productVariant.product', function ($q) use ($seller) {
            $q->where('seller_id', $seller->id);
        })
        ->whereBetween('date', [$startDate, $endDate])
        ->when($storeId, fn($q) => $q->where('store_id', $storeId))
        ->when($search, function ($q) use ($search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('resi_summary', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%")
                    ->orWhereHas('store', fn($sq) => $sq->where('store_name', 'like', "%{$search}%"));
            });
        })
        ->with(['store', 'items.productVariant.product'])
        ->latest('date');

        $sales = $stockOutQuery->paginate(10)
            ->withQueryString()
            ->through(function ($so) use ($seller) {
                $sellerItems = $so->items->filter(function ($i) use ($seller) {
                    return $i->productVariant->product->seller_id === $seller->id;
                });

                $soOmset = $sellerItems->sum(function ($i) {
                    return $i->qty * $i->selling_price;
                });

                $soHpp = $sellerItems->sum(function ($i) {
                    return $i->qty * $i->price;
                });

                $soQty = $sellerItems->sum('qty');

                return [
                    'id' => $so->id,
                    'date' => $so->date ? Carbon::parse($so->date)->format('d M Y') : '-',
                    'store_name' => $so->store->store_name ?? 'Marketplace',
                    'resi_summary' => $so->resi_summary,
                    'total_qty' => (int) $soQty,
                    'total_omset' => (float) $soOmset,
                    'total_hpp' => (float) $soHpp,
                    'profit' => (float) ($soOmset - $soHpp),
                    'items' => $sellerItems->values()->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'product_name' => $item->productVariant->product->product_name ?? 'Produk',
                            'sku' => $item->productVariant->sku ?? '-',
                            'variant_info' => trim(($item->productVariant->color ?? '') . ' ' . ($item->productVariant->size ?? '')),
                            'qty' => (int) $item->qty,
                            'price' => (float) $item->price,
                            'selling_price' => (float) $item->selling_price,
                            'total_omset' => (float) ($item->qty * $item->selling_price),
                        ];
                    }),
                ];
            });

        // Interactive Daily Omset Chart Dataset for Date Range
        $diffDays = max(1, min(31, $startDate->diffInDays($endDate) + 1));
        $chartData = [];
        for ($i = 0; $i < $diffDays; $i++) {
            $curDate = $startDate->copy()->addDays($i);
            $dateStr = $curDate->format('Y-m-d');
            $label = $curDate->format('d M');

            $dayOmset = (float) StockOutItem::whereHas('productVariant.product', function ($q) use ($seller) {
                $q->where('seller_id', $seller->id);
            })->whereHas('stockOut', function ($q) use ($dateStr, $storeId) {
                $q->whereDate('date', $dateStr);
                if ($storeId) {
                    $q->where('store_id', $storeId);
                }
            })->sum(DB::raw('qty * selling_price'));

            $chartData[] = [
                'date' => $dateStr,
                'label' => $label,
                'omset' => $dayOmset,
            ];
        }

        $stores = Store::where('seller_id', $seller->id)->select('id', 'store_name', 'marketplace')->get();

        return Inertia::render('Seller/Sales/Index', [
            'metrics' => [
                'total_omset' => $totalOmset,
                'total_hpp' => $totalHpp,
                'gross_profit' => $grossProfit,
                'total_qty' => $totalQty,
            ],
            'sales' => $sales,
            'chartData' => $chartData,
            'stores' => $stores,
            'filters' => [
                'preset' => $preset,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'store_id' => $storeId,
                'search' => $search,
            ],
        ]);
    }
}
