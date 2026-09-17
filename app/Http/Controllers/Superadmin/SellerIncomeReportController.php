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

class SellerIncomeReportController extends Controller
{
    public function index(Request $request)
    {
        $sellerId = $request->input('seller_id');
        $periodId = $request->input('period_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $sellers = Seller::select('id', 'seller_name')->orderBy('seller_name')->get();
        $periods = Period::select('id', 'name')->get();

        $sellerQuery = Seller::query()
            ->when($sellerId, fn($q) => $q->where('id', $sellerId))
            ->get();

        $reportData = $sellerQuery->map(function ($seller) use ($periodId, $startDate, $endDate) {
            // Stock Out Items (Terjual)
            $stockOutItems = StockOutItem::query()
                ->whereHas('productVariant.product', fn($pq) => $pq->where('seller_id', $seller->id))
                ->whereHas('stockOut', function ($sq) use ($periodId, $startDate, $endDate) {
                    $sq->when($periodId, fn($q) => $q->where('period_id', $periodId))
                       ->when($startDate, fn($q) => $q->where('date', '>=', $startDate))
                       ->when($endDate, fn($q) => $q->where('date', '<=', $endDate));
                });

            $totalQtySold = (int) $stockOutItems->sum('qty');
            $totalHppSold = (float) $stockOutItems->sum(DB::raw('qty * price'));
            $totalOmsetSold = (float) $stockOutItems->sum(DB::raw('qty * selling_price'));
            $grossProfit = $totalOmsetSold - $totalHppSold;

            // Stock In HPP Total
            $stockInHpp = (float) StockInItem::query()
                ->whereHas('productVariant.product', fn($pq) => $pq->where('seller_id', $seller->id))
                ->whereHas('stockIn', function ($sq) use ($periodId, $startDate, $endDate) {
                    $sq->when($periodId, fn($q) => $q->where('period_id', $periodId))
                       ->when($startDate, fn($q) => $q->where('date', '>=', $startDate))
                       ->when($endDate, fn($q) => $q->where('date', '<=', $endDate));
                })
                ->sum(DB::raw('qty * price'));

            $adjustments = (float) SellerDebtAdjustment::where('seller_id', $seller->id)
                ->when($periodId, fn($q) => $q->where('period_id', $periodId))
                ->sum('amount');

            $netDebt = $stockInHpp - $totalHppSold + $adjustments;

            return [
                'seller_id' => $seller->id,
                'seller_name' => $seller->seller_name,
                'total_qty_sold' => $totalQtySold,
                'total_hpp_sold' => $totalHppSold,
                'total_omset' => $totalOmsetSold,
                'gross_profit' => $grossProfit,
                'margin_percent' => $totalOmsetSold > 0 ? round(($grossProfit / $totalOmsetSold) * 100, 2) : 0,
                'stock_in_hpp' => $stockInHpp,
                'net_debt' => $netDebt,
            ];
        });

        $summary = [
            'total_sellers' => $reportData->count(),
            'total_qty_sold' => $reportData->sum('total_qty_sold'),
            'total_hpp_sold' => $reportData->sum('total_hpp_sold'),
            'total_omset' => $reportData->sum('total_omset'),
            'total_gross_profit' => $reportData->sum('gross_profit'),
        ];

        return Inertia::render('Superadmin/Finance/SellerIncomeReport', [
            'sellers' => $sellers,
            'periods' => $periods,
            'reportData' => $reportData,
            'summary' => $summary,
            'filters' => [
                'seller_id' => $sellerId,
                'period_id' => $periodId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    public function exportPdf(Request $request)
    {
        $sellerId = $request->input('seller_id');
        $periodId = $request->input('period_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $sellerQuery = Seller::query()
            ->when($sellerId, fn($q) => $q->where('id', $sellerId))
            ->get();

        $reportData = $sellerQuery->map(function ($seller) use ($periodId, $startDate, $endDate) {
            $stockOutItems = StockOutItem::query()
                ->whereHas('productVariant.product', fn($pq) => $pq->where('seller_id', $seller->id))
                ->whereHas('stockOut', function ($sq) use ($periodId, $startDate, $endDate) {
                    $sq->when($periodId, fn($q) => $q->where('period_id', $periodId))
                       ->when($startDate, fn($q) => $q->where('date', '>=', $startDate))
                       ->when($endDate, fn($q) => $q->where('date', '<=', $endDate));
                });

            $totalQtySold = (int) $stockOutItems->sum('qty');
            $totalHppSold = (float) $stockOutItems->sum(DB::raw('qty * price'));
            $totalOmsetSold = (float) $stockOutItems->sum(DB::raw('qty * selling_price'));
            $grossProfit = $totalOmsetSold - $totalHppSold;

            return [
                'seller_name' => $seller->seller_name,
                'total_qty_sold' => $totalQtySold,
                'total_hpp_sold' => $totalHppSold,
                'total_omset' => $totalOmsetSold,
                'gross_profit' => $grossProfit,
                'margin_percent' => $totalOmsetSold > 0 ? round(($grossProfit / $totalOmsetSold) * 100, 2) : 0,
            ];
        });

        $pdf = Pdf::loadView('pdf.seller_income_report', compact('reportData', 'startDate', 'endDate'));

        return $pdf->stream('Laporan_Penghasilan_HPP_Seller.pdf');
    }
}
