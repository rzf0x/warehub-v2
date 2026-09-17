<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\Seller;
use App\Models\SellerDebtAdjustment;
use App\Models\Stock;
use App\Models\StockInItem;
use App\Models\StockOutItem;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        // Get active seller record linked to user, or fallback to first seller for admin preview
        $seller = $user->sellers()->first() ?? Seller::first();

        if (!$seller) {
            return Inertia::render('Seller/Dashboard', [
                'seller' => null,
                'metrics' => [
                    'total_stock_units' => 0,
                    'monthly_omset' => 0,
                    'net_debt' => 0,
                    'active_po_count' => 0,
                ],
                'recentTransactions' => [],
                'chartData' => [],
                'stores' => [],
            ]);
        }

        // 1. Total Stock Units
        $totalStockUnits = (int) Stock::whereHas('productVariant.product', function ($q) use ($seller) {
            $q->where('seller_id', $seller->id);
        })->sum('qty');

        // 2. Omset Penjualan Bulan Ini
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $monthlyOmset = (float) StockOutItem::whereHas('productVariant.product', function ($q) use ($seller) {
            $q->where('seller_id', $seller->id);
        })->whereHas('stockOut', function ($q) use ($startOfMonth, $endOfMonth) {
            $q->whereBetween('date', [$startOfMonth, $endOfMonth]);
        })->sum(DB::raw('qty * selling_price'));

        // 3. Estimasi Hutang HPP Modal
        $totalStockInHpp = (float) StockInItem::whereHas('productVariant.product', function ($q) use ($seller) {
            $q->where('seller_id', $seller->id);
        })->sum(DB::raw('qty * price'));

        $totalHppSold = (float) StockOutItem::whereHas('productVariant.product', function ($q) use ($seller) {
            $q->where('seller_id', $seller->id);
        })->sum(DB::raw('qty * price'));

        $totalAdjustments = (float) SellerDebtAdjustment::where('seller_id', $seller->id)->sum('amount');

        $netDebt = $totalStockInHpp - $totalHppSold + $totalAdjustments;

        // 4. Total PO Aktif
        $activePoCount = (int) PurchaseOrder::where('seller_id', $seller->id)
            ->whereIn('status', ['pending', 'approved', 'in_production'])
            ->count();

        // 5. Recent 5 Transactions (StockOutItems)
        $recentTransactions = StockOutItem::with(['stockOut.store', 'productVariant.product'])
            ->whereHas('productVariant.product', function ($q) use ($seller) {
                $q->where('seller_id', $seller->id);
            })
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'date' => $item->stockOut->date ? Carbon::parse($item->stockOut->date)->format('d M Y') : '-',
                    'product_name' => $item->productVariant->product->product_name ?? 'Produk',
                    'variant_info' => trim(($item->productVariant->color ?? '') . ' ' . ($item->productVariant->size ?? '')),
                    'store_name' => $item->stockOut->store->store_name ?? 'Marketplace',
                    'qty' => (int) $item->qty,
                    'total_omset' => (float) ($item->qty * $item->selling_price),
                    'total_hpp' => (float) ($item->qty * $item->price),
                ];
            });

        // 6. Chart Data (Past 7 Days Sales Omset)
        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $label = Carbon::now()->subDays($i)->format('d M');

            $dailyOmset = (float) StockOutItem::whereHas('productVariant.product', function ($q) use ($seller) {
                $q->where('seller_id', $seller->id);
            })->whereHas('stockOut', function ($q) use ($date) {
                $q->whereDate('date', $date);
            })->sum(DB::raw('qty * selling_price'));

            $chartData[] = [
                'day' => $label,
                'omset' => $dailyOmset,
            ];
        }

        // 7. Seller Stores
        $stores = Store::where('seller_id', $seller->id)->select('id', 'store_name', 'marketplace')->get();

        return Inertia::render('Seller/Dashboard', [
            'seller' => [
                'id' => $seller->id,
                'seller_name' => $seller->seller_name,
                'address' => $seller->address,
                'phone' => $seller->phone,
            ],
            'metrics' => [
                'total_stock_units' => $totalStockUnits,
                'monthly_omset' => $monthlyOmset,
                'net_debt' => $netDebt,
                'active_po_count' => $activePoCount,
            ],
            'recentTransactions' => $recentTransactions,
            'chartData' => $chartData,
            'stores' => $stores,
        ]);
    }
}
