<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Models\Stock;
use App\Models\StockIn;
use App\Models\StockOut;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        if ($user && !$user->hasAnyRole(['super-admin', 'superadmin']) && ($user->hasRole('seller') || $user->sellers()->exists())) {
            return redirect()->route('seller.dashboard');
        }

        $allPeriods = Period::orderBy('start_date', 'desc')->get();

        $selectedPeriodId = $request->query('period_id');
        if ($selectedPeriodId) {
            $selectedPeriod = $allPeriods->firstWhere('id', (int) $selectedPeriodId);
        } else {
            $selectedPeriod = Period::where('is_public', true)->latest()->first() ?? $allPeriods->first();
        }

        $activePeriodId = $selectedPeriod?->id;

        // --- Stock In Metrics for Selected Period ---
        $stockInsQuery = StockIn::where('period_id', $activePeriodId);
        $stockIns = $stockInsQuery->with('items')->get();
        
        $stockInQty = 0;
        $stockInHpp = 0;
        foreach ($stockIns as $si) {
            foreach ($si->items as $item) {
                $stockInQty += (int) $item->qty;
                $stockInHpp += (float) ($item->qty * $item->price);
            }
        }
        $stockInNota = $stockIns->count();

        // --- Stock Out Metrics for Selected Period ---
        $stockOutsQuery = StockOut::where('period_id', $activePeriodId);
        $stockOuts = $stockOutsQuery->with('items')->get();

        $stockOutQty = 0;
        $stockOutHpp = 0;
        $stockOutOmset = 0;
        foreach ($stockOuts as $so) {
            $stockOutHpp += (float) $so->total_amount;
            $stockOutOmset += (float) $so->total_selling_price;
            foreach ($so->items as $item) {
                $stockOutQty += (int) $item->qty;
            }
        }
        $stockOutNota = $stockOuts->count();

        // Net movement & total physical stock
        $netMovementQty = $stockInQty - $stockOutQty;
        $totalPhysicalStock = (int) Stock::sum('qty');

        // --- Recent Stock Ins (Selected Period or Latest 5) ---
        $recentStockInsQuery = StockIn::with(['warehouse', 'items']);
        if ($activePeriodId) {
            $recentStockInsQuery->where('period_id', $activePeriodId);
        }
        $recentStockIns = $recentStockInsQuery->latest('date')
            ->latest('id')
            ->take(5)
            ->get()
            ->map(function ($item) {
                $qty = $item->items->sum('qty');
                $hpp = $item->items->sum(fn($i) => $i->qty * $i->price);
                return [
                    'id' => $item->id,
                    'invoice_number' => $item->invoice_number ?? 'IN-'.$item->id,
                    'date' => $item->date ? $item->date->format('d M Y') : '-',
                    'warehouse_name' => $item->warehouse?->name ?? '-',
                    'qty' => $qty,
                    'total_hpp' => (float) $hpp,
                ];
            });

        // --- Recent Stock Outs (Selected Period or Latest 5) ---
        $recentStockOutsQuery = StockOut::with(['warehouse', 'store', 'items']);
        if ($activePeriodId) {
            $recentStockOutsQuery->where('period_id', $activePeriodId);
        }
        $recentStockOuts = $recentStockOutsQuery->latest('date')
            ->latest('id')
            ->take(5)
            ->get()
            ->map(function ($item) {
                $qty = $item->items->sum('qty');
                return [
                    'id' => $item->id,
                    'store_name' => $item->store?->store_name ?? 'Penjualan Direct',
                    'marketplace' => strtoupper($item->store?->marketplace ?? 'OFFLINE'),
                    'date' => $item->date ? $item->date->format('d M Y') : '-',
                    'warehouse_name' => $item->warehouse?->name ?? '-',
                    'qty' => $qty,
                    'total_omset' => (float) $item->total_selling_price,
                ];
            });

        // --- Financial Trend per Period (Last 8 Periods) ---
        $financialCharts = Period::orderBy('start_date', 'asc')
            ->take(8)
            ->get()
            ->map(function ($p) {
                $soHpp = StockOut::where('period_id', $p->id)->sum('total_amount');
                $soOmset = StockOut::where('period_id', $p->id)->sum('total_selling_price');
                return [
                    'id' => $p->id,
                    'period_name' => $p->name,
                    'total_hpp' => (float) $soHpp,
                    'total_omset' => (float) $soOmset,
                    'est_profit' => (float) ($soOmset - $soHpp),
                ];
            });

        return Inertia::render('Superadmin/Dashboard', [
            'allPeriods' => $allPeriods->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'start_date' => $p->start_date ? $p->start_date->format('d M Y') : '',
                'end_date' => $p->end_date ? $p->end_date->format('d M Y') : '',
                'is_public' => (bool) $p->is_public,
            ]),
            'selectedPeriod' => $selectedPeriod ? [
                'id' => $selectedPeriod->id,
                'name' => $selectedPeriod->name,
                'start_date' => $selectedPeriod->start_date ? $selectedPeriod->start_date->format('d M Y') : '',
                'end_date' => $selectedPeriod->end_date ? $selectedPeriod->end_date->format('d M Y') : '',
                'is_public' => (bool) $selectedPeriod->is_public,
            ] : null,
            'metrics' => [
                'stock_in_qty' => $stockInQty,
                'stock_in_hpp' => $stockInHpp,
                'stock_in_nota' => $stockInNota,
                'stock_out_qty' => $stockOutQty,
                'stock_out_hpp' => $stockOutHpp,
                'stock_out_omset' => $stockOutOmset,
                'stock_out_nota' => $stockOutNota,
                'net_movement_qty' => $netMovementQty,
                'total_physical_stock' => $totalPhysicalStock,
            ],
            'recentStockIns' => $recentStockIns,
            'recentStockOuts' => $recentStockOuts,
            'financialCharts' => $financialCharts,
        ]);
    }
}
