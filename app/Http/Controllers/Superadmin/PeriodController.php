<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Period;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PeriodController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $periods = Period::query()
            ->withCount(['stockIns', 'stockOuts'])
            ->when($search, function ($query, $s) {
                $query->where('name', 'like', "%{$s}%");
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Superadmin/Warehouse/Master/PeriodIndex', [
            'periods' => $periods,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function stockIn(Period $period, Request $request)
    {
        $search = $request->input('search');

        $stockIns = $period->stockIns()
            ->with(['warehouse', 'user', 'items.productVariant.product.seller'])
            ->when($search, function ($query, $s) {
                $query->where(function ($q) use ($s) {
                    $q->where('invoice_number', 'like', "%{$s}%")
                      ->orWhere('note', 'like', "%{$s}%")
                      ->orWhereHas('warehouse', function ($wh) use ($s) {
                          $wh->where('name', 'like', "%{$s}%");
                      })
                      ->orWhereHas('items.productVariant.product.seller', function ($sel) use ($s) {
                          $sel->where('seller_name', 'like', "%{$s}%");
                      });
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Superadmin/Warehouse/Master/PeriodStockIn', [
            'period' => $period,
            'stockIns' => $stockIns,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function stockOut(Period $period, Request $request)
    {
        $search = $request->input('search');

        $stockOuts = $period->stockOuts()
            ->with(['warehouse', 'store', 'user', 'items.productVariant.product.seller'])
            ->when($search, function ($query, $s) {
                $query->where(function ($q) use ($s) {
                    $q->where('note', 'like', "%{$s}%")
                      ->orWhereHas('warehouse', function ($wh) use ($s) {
                          $wh->where('name', 'like', "%{$s}%");
                      })
                      ->orWhereHas('store', function ($st) use ($s) {
                          $st->where('name', 'like', "%{$s}%");
                      })
                      ->orWhereHas('items.productVariant.product.seller', function ($sel) use ($s) {
                          $sel->where('seller_name', 'like', "%{$s}%");
                      });
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Superadmin/Warehouse/Master/PeriodStockOut', [
            'period' => $period,
            'stockOuts' => $stockOuts,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_public' => ['boolean'],
        ]);

        Period::create($validated);

        return redirect()->back()->with('success', 'Periode pembukuan berhasil ditambahkan.');
    }

    public function update(Request $request, Period $period)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_public' => ['boolean'],
        ]);

        $period->update($validated);

        return redirect()->back()->with('success', 'Periode pembukuan berhasil diperbarui.');
    }

    public function destroy(Period $period)
    {
        $period->delete();

        return redirect()->back()->with('success', 'Periode pembukuan berhasil dihapus.');
    }
}
