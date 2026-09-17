<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Models\StockOut;
use App\Models\StockOutItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProfitReportController extends Controller
{
    public function index(Request $request)
    {
        $periodId = $request->input('period_id');
        $search = $request->input('search');

        $periodsQuery = Period::query()
            ->when($periodId, fn($q) => $q->where('id', $periodId))
            ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('start_date', 'desc')
            ->get();

        $allPeriods = Period::select('id', 'name')->orderBy('start_date', 'desc')->get();

        $reportData = $periodsQuery->map(function ($period) {
            $stockOutIds = StockOut::where('period_id', $period->id)->pluck('id');

            $qtyKeluar = (int) StockOutItem::whereIn('stock_out_id', $stockOutIds)->sum('qty');

            $hppModal = (float) StockOut::where('period_id', $period->id)->sum('total_amount');
            if ($hppModal == 0 && count($stockOutIds) > 0) {
                $hppModal = (float) StockOutItem::whereIn('stock_out_id', $stockOutIds)->sum(DB::raw('qty * price'));
            }

            $bruto = (float) StockOut::where('period_id', $period->id)->sum('bruto');
            if ($bruto == 0 && count($stockOutIds) > 0) {
                $bruto = (float) StockOut::where('period_id', $period->id)->sum('total_selling_price');
            }

            $penghasilanBersih = (float) StockOut::where('period_id', $period->id)->sum('total_selling_price');
            if ($penghasilanBersih == 0 && count($stockOutIds) > 0) {
                $penghasilanBersih = (float) StockOutItem::whereIn('stock_out_id', $stockOutIds)->sum(DB::raw('qty * selling_price'));
            }

            $estUntung = $penghasilanBersih - $hppModal;

            $startDateFormatted = $period->start_date ? Carbon::parse($period->start_date)->format('Y-m-d') : null;
            $endDateFormatted = $period->end_date ? Carbon::parse($period->end_date)->format('Y-m-d') : null;

            return [
                'period_id' => $period->id,
                'period_name' => $period->name,
                'start_date' => $startDateFormatted,
                'end_date' => $endDateFormatted,
                'is_public' => (bool) $period->is_public,
                'qty_keluar' => $qtyKeluar,
                'hpp_modal' => $hppModal,
                'bruto' => $bruto,
                'penghasilan_bersih' => $penghasilanBersih,
                'est_untung' => $estUntung,
            ];
        });

        $summary = [
            'total_periods' => $reportData->count(),
            'total_qty_keluar' => (int) $reportData->sum('qty_keluar'),
            'total_hpp_modal' => (float) $reportData->sum('hpp_modal'),
            'total_bruto' => (float) $reportData->sum('bruto'),
            'total_penghasilan_bersih' => (float) $reportData->sum('penghasilan_bersih'),
            'total_est_untung' => (float) $reportData->sum('est_untung'),
        ];

        return Inertia::render('Superadmin/Finance/ProfitReport', [
            'periods' => $allPeriods,
            'reportData' => $reportData,
            'summary' => $summary,
            'filters' => [
                'period_id' => $periodId,
                'search' => $search,
            ],
        ]);
    }

    public function exportPdf(Request $request)
    {
        $periodId = $request->input('period_id');
        $periodIdsParam = $request->input('period_ids');
        $search = $request->input('search');

        $periodIds = [];
        if (!empty($periodIdsParam)) {
            if (is_array($periodIdsParam)) {
                $periodIds = array_map('intval', $periodIdsParam);
            } else if (is_string($periodIdsParam)) {
                $periodIds = array_map('intval', array_filter(explode(',', $periodIdsParam)));
            }
        }

        $periodsQuery = Period::query()
            ->when(count($periodIds) > 0, fn($q) => $q->whereIn('id', $periodIds))
            ->when(count($periodIds) === 0 && $periodId, fn($q) => $q->where('id', $periodId))
            ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('start_date', 'desc')
            ->get();

        $selectedPeriodName = null;
        if (count($periodIds) === 1) {
            $selectedPeriod = Period::find($periodIds[0]);
            $selectedPeriodName = $selectedPeriod ? $selectedPeriod->name : null;
        } elseif ($periodId && count($periodIds) === 0) {
            $selectedPeriod = Period::find($periodId);
            $selectedPeriodName = $selectedPeriod ? $selectedPeriod->name : null;
        } elseif (count($periodIds) > 1) {
            $selectedPeriodName = count($periodIds) . ' Periode Terpilih (Bulk Selection)';
        }

        $reportData = $periodsQuery->map(function ($period) {
            $stockOutIds = StockOut::where('period_id', $period->id)->pluck('id');

            $qtyKeluar = (int) StockOutItem::whereIn('stock_out_id', $stockOutIds)->sum('qty');

            $hppModal = (float) StockOut::where('period_id', $period->id)->sum('total_amount');
            if ($hppModal == 0 && count($stockOutIds) > 0) {
                $hppModal = (float) StockOutItem::whereIn('stock_out_id', $stockOutIds)->sum(DB::raw('qty * price'));
            }

            $bruto = (float) StockOut::where('period_id', $period->id)->sum('bruto');
            if ($bruto == 0 && count($stockOutIds) > 0) {
                $bruto = (float) StockOut::where('period_id', $period->id)->sum('total_selling_price');
            }

            $penghasilanBersih = (float) StockOut::where('period_id', $period->id)->sum('total_selling_price');
            if ($penghasilanBersih == 0 && count($stockOutIds) > 0) {
                $penghasilanBersih = (float) StockOutItem::whereIn('stock_out_id', $stockOutIds)->sum(DB::raw('qty * selling_price'));
            }

            $estUntung = $penghasilanBersih - $hppModal;

            return [
                'period_name' => $period->name,
                'start_date' => $period->start_date ? Carbon::parse($period->start_date)->format('d/m/Y') : '-',
                'end_date' => $period->end_date ? Carbon::parse($period->end_date)->format('d/m/Y') : '-',
                'qty_keluar' => $qtyKeluar,
                'hpp_modal' => $hppModal,
                'bruto' => $bruto,
                'penghasilan_bersih' => $penghasilanBersih,
                'est_untung' => $estUntung,
            ];
        });

        $summary = [
            'total_qty_keluar' => (int) $reportData->sum('qty_keluar'),
            'total_hpp_modal' => (float) $reportData->sum('hpp_modal'),
            'total_bruto' => (float) $reportData->sum('bruto'),
            'total_penghasilan_bersih' => (float) $reportData->sum('penghasilan_bersih'),
            'total_est_untung' => (float) $reportData->sum('est_untung'),
        ];

        $pdf = Pdf::loadView('pdf.profit_report', compact('reportData', 'summary', 'selectedPeriodName'));

        $filename = $selectedPeriodName
            ? 'Laporan_Keuntungan_' . str_replace(' ', '_', $selectedPeriodName) . '.pdf'
            : 'Laporan_Keuntungan_Semua_Periode.pdf';

        return $pdf->stream($filename);
    }
}
