<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Models\Seller;
use App\Models\SellerDebtAdjustment;
use App\Models\StockInItem;
use App\Models\StockOutItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SellerDebtController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $periodId = $request->input('period_id');

        $sellersQuery = Seller::query()
            ->when($search, fn($q) => $q->where('seller_name', 'like', "%{$search}%"))
            ->orderBy('seller_name')
            ->get();

        $periods = Period::select('id', 'name')->get();

        $sellerDebts = $sellersQuery->map(function ($seller) use ($periodId) {
            // Stock In HPP Total
            $stockInHpp = (float) StockInItem::query()
                ->whereHas('productVariant.product', fn($pq) => $pq->where('seller_id', $seller->id))
                ->when($periodId, fn($sq) => $sq->whereHas('stockIn', fn($q) => $q->where('period_id', $periodId)))
                ->sum(DB::raw('qty * price'));

            // Stock Out HPP Total
            $stockOutHpp = (float) StockOutItem::query()
                ->whereHas('productVariant.product', fn($pq) => $pq->where('seller_id', $seller->id))
                ->when($periodId, fn($sq) => $sq->whereHas('stockOut', fn($q) => $q->where('period_id', $periodId)))
                ->sum(DB::raw('qty * price'));

            // Debt Adjustments Total
            $adjustments = (float) SellerDebtAdjustment::query()
                ->where('seller_id', $seller->id)
                ->when($periodId, fn($q) => $q->where('period_id', $periodId))
                ->sum('amount');

            $netDebt = $stockInHpp - $stockOutHpp + $adjustments;

            return [
                'id' => $seller->id,
                'seller_name' => $seller->seller_name,
                'phone' => $seller->phone,
                'stock_in_hpp' => $stockInHpp,
                'stock_out_hpp' => $stockOutHpp,
                'adjustments' => $adjustments,
                'net_debt' => $netDebt,
            ];
        });

        $summary = [
            'total_sellers' => $sellerDebts->count(),
            'total_stock_in_hpp' => $sellerDebts->sum('stock_in_hpp'),
            'total_stock_out_hpp' => $sellerDebts->sum('stock_out_hpp'),
            'total_net_debt' => $sellerDebts->sum('net_debt'),
        ];

        return Inertia::render('Superadmin/Finance/SellerDebt/Index', [
            'sellerDebts' => $sellerDebts,
            'periods' => $periods,
            'summary' => $summary,
            'filters' => [
                'search' => $search,
                'period_id' => $periodId,
            ],
        ]);
    }

    public function show(Seller $seller, Request $request)
    {
        $periodId = $request->input('period_id');
        $search = $request->input('search');

        $stockInItems = StockInItem::with(['stockIn.warehouse', 'productVariant.product'])
            ->whereHas('productVariant.product', fn($q) => $q->where('seller_id', $seller->id))
            ->when($periodId, fn($q) => $q->whereHas('stockIn', fn($sq) => $sq->where('period_id', $periodId)))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->whereHas('productVariant', function ($vq) use ($search) {
                        $vq->where('sku', 'like', "%{$search}%")
                            ->orWhereHas('product', fn($pq) => $pq->where('product_name', 'like', "%{$search}%"));
                    })->orWhereHas('stockIn', fn($siq) => $siq->where('invoice_number', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(10, ['*'], 'stock_in_page')
            ->withQueryString();

        $stockOutItems = StockOutItem::with(['stockOut.store', 'productVariant.product'])
            ->whereHas('productVariant.product', fn($q) => $q->where('seller_id', $seller->id))
            ->when($periodId, fn($q) => $q->whereHas('stockOut', fn($sq) => $sq->where('period_id', $periodId)))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->whereHas('productVariant', function ($vq) use ($search) {
                        $vq->where('sku', 'like', "%{$search}%")
                            ->orWhereHas('product', fn($pq) => $pq->where('product_name', 'like', "%{$search}%"));
                    })->orWhereHas('stockOut.store', fn($stq) => $stq->where('store_name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(10, ['*'], 'stock_out_page')
            ->withQueryString();

        $adjustments = SellerDebtAdjustment::with('period')
            ->where('seller_id', $seller->id)
            ->when($periodId, fn($q) => $q->where('period_id', $periodId))
            ->latest()
            ->get();

        $stockInHpp = (float) StockInItem::whereHas('productVariant.product', fn($q) => $q->where('seller_id', $seller->id))
            ->when($periodId, fn($q) => $q->whereHas('stockIn', fn($sq) => $sq->where('period_id', $periodId)))
            ->sum(DB::raw('qty * price'));

        $stockOutHpp = (float) StockOutItem::whereHas('productVariant.product', fn($q) => $q->where('seller_id', $seller->id))
            ->when($periodId, fn($q) => $q->whereHas('stockOut', fn($sq) => $sq->where('period_id', $periodId)))
            ->sum(DB::raw('qty * price'));

        $stockInQty = (int) StockInItem::whereHas('productVariant.product', fn($q) => $q->where('seller_id', $seller->id))
            ->when($periodId, fn($q) => $q->whereHas('stockIn', fn($sq) => $sq->where('period_id', $periodId)))
            ->sum('qty');

        $stockOutQty = (int) StockOutItem::whereHas('productVariant.product', fn($q) => $q->where('seller_id', $seller->id))
            ->when($periodId, fn($q) => $q->whereHas('stockOut', fn($sq) => $sq->where('period_id', $periodId)))
            ->sum('qty');

        $totalAdjustment = (float) $adjustments->sum('amount');
        $netDebt = $stockInHpp - $stockOutHpp + $totalAdjustment;
        $liquidationRate = $stockInHpp > 0 ? round(($stockOutHpp / $stockInHpp) * 100, 1) : 0;

        $seller->load('brand:id,name');
        $seller->loadCount(['products', 'stores']);

        return Inertia::render('Superadmin/Finance/SellerDebt/Show', [
            'seller' => $seller,
            'stockInItems' => $stockInItems,
            'stockOutItems' => $stockOutItems,
            'adjustments' => $adjustments,
            'periods' => Period::select('id', 'name')->get(),
            'stats' => [
                'stock_in_hpp' => $stockInHpp,
                'stock_out_hpp' => $stockOutHpp,
                'total_adjustment' => $totalAdjustment,
                'net_debt' => $netDebt,
                'stock_in_qty' => $stockInQty,
                'stock_out_qty' => $stockOutQty,
                'liquidation_rate' => $liquidationRate,
                'active_products_count' => $seller->products_count,
                'stores_count' => $seller->stores_count,
            ],
            'filters' => [
                'period_id' => $periodId,
                'search' => $search,
            ],
        ]);
    }

    public function exportPdf(Seller $seller, Request $request)
    {
        $periodId = $request->input('period_id');
        $selectedPeriod = $periodId ? Period::find($periodId) : null;

        $stockInItems = StockInItem::with(['stockIn.warehouse', 'productVariant.product'])
            ->whereHas('productVariant.product', fn($q) => $q->where('seller_id', $seller->id))
            ->when($periodId, fn($q) => $q->whereHas('stockIn', fn($sq) => $sq->where('period_id', $periodId)))
            ->latest()
            ->get();

        $stockOutItems = StockOutItem::with(['stockOut.store', 'productVariant.product'])
            ->whereHas('productVariant.product', fn($q) => $q->where('seller_id', $seller->id))
            ->when($periodId, fn($q) => $q->whereHas('stockOut', fn($sq) => $sq->where('period_id', $periodId)))
            ->latest()
            ->get();

        $adjustments = SellerDebtAdjustment::with('period')
            ->where('seller_id', $seller->id)
            ->when($periodId, fn($q) => $q->where('period_id', $periodId))
            ->latest()
            ->get();

        $stockInHpp = (float) $stockInItems->sum(fn($it) => $it->qty * $it->price);
        $stockOutHpp = (float) $stockOutItems->sum(fn($it) => $it->qty * $it->price);
        $totalAdjustment = (float) $adjustments->sum('amount');
        $netDebt = $stockInHpp - $stockOutHpp + $totalAdjustment;

        $stockInQty = (int) $stockInItems->sum('qty');
        $stockOutQty = (int) $stockOutItems->sum('qty');
        $liquidationRate = $stockInHpp > 0 ? round(($stockOutHpp / $stockInHpp) * 100, 1) : 0;

        $seller->load('brand:id,name');

        $stats = [
            'stock_in_hpp' => $stockInHpp,
            'stock_out_hpp' => $stockOutHpp,
            'total_adjustment' => $totalAdjustment,
            'net_debt' => $netDebt,
            'stock_in_qty' => $stockInQty,
            'stock_out_qty' => $stockOutQty,
            'liquidation_rate' => $liquidationRate,
        ];

        $pdf = Pdf::loadView('pdf.seller_debt_statement', compact(
            'seller',
            'selectedPeriod',
            'stats',
            'stockInItems',
            'stockOutItems',
            'adjustments'
        ));

        $filename = 'Rekening_Koran_Modal_' . str_replace(' ', '_', $seller->seller_name) . '.pdf';
        return $pdf->stream($filename);
    }

    public function chart(Request $request)
    {
        $sellerId = $request->input('seller_id');

        $sellers = Seller::select('id', 'seller_name')->orderBy('seller_name')->get();

        $querySellers = $sellerId ? Seller::where('id', $sellerId)->get() : $sellers;

        $chartData = $querySellers->map(function ($seller) {
            $stockInHpp = (float) StockInItem::whereHas('productVariant.product', fn($q) => $q->where('seller_id', $seller->id))
                ->sum(DB::raw('qty * price'));
            $stockOutHpp = (float) StockOutItem::whereHas('productVariant.product', fn($q) => $q->where('seller_id', $seller->id))
                ->sum(DB::raw('qty * price'));
            $adjustments = (float) SellerDebtAdjustment::where('seller_id', $seller->id)->sum('amount');

            return [
                'seller_id' => $seller->id,
                'seller_name' => $seller->seller_name,
                'stock_in_hpp' => $stockInHpp,
                'stock_out_hpp' => $stockOutHpp,
                'net_debt' => $stockInHpp - $stockOutHpp + $adjustments,
            ];
        });

        return Inertia::render('Superadmin/Finance/SellerDebt/Chart', [
            'sellers' => $sellers,
            'chartData' => $chartData,
            'selectedSellerId' => $sellerId,
        ]);
    }

    public function konveksiSimulation(Request $request)
    {
        $sellers = Seller::select('id', 'seller_name')->orderBy('seller_name')->get();

        return Inertia::render('Superadmin/Finance/SellerDebt/KonveksiSimulation', [
            'sellers' => $sellers,
        ]);
    }

    public function storeAdjustment(Request $request)
    {
        $validated = $request->validate([
            'seller_id' => ['required', 'exists:sellers,id'],
            'period_id' => ['nullable', 'exists:periods,id'],
            'amount' => ['required', 'numeric'],
            'adjustment_date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        SellerDebtAdjustment::create([
            'seller_id' => $validated['seller_id'],
            'period_id' => $validated['period_id'] ?? null,
            'created_by' => $request->user()?->id,
            'amount' => $validated['amount'],
            'adjustment_date' => $validated['adjustment_date'],
            'note' => $validated['note'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Penyesuaian saldo hutang seller berhasil disimpan.');
    }

    public function destroyAdjustment(SellerDebtAdjustment $adjustment)
    {
        $adjustment->delete();
        return redirect()->back()->with('success', 'Penyesuaian saldo hutang seller berhasil dihapus.');
    }
}
