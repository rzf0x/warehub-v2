<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\StockLog;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Warehouse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StockOpnameController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $warehouseId = $request->input('warehouse_id');

        $stockOpnames = StockOpname::query()
            ->with(['warehouse', 'user', 'items.productVariant.product'])
            ->when($search, function ($query, $s) {
                $query->where('note', 'like', "%{$s}%");
            })
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $warehouses = Warehouse::select('id', 'name')->get();

        return Inertia::render('Superadmin/Warehouse/StockOpname/Index', [
            'stockOpnames' => $stockOpnames,
            'warehouses' => $warehouses,
            'filters' => [
                'search' => $search,
                'warehouse_id' => $warehouseId,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Superadmin/Warehouse/StockOpname/Form', [
            'warehouses' => Warehouse::select('id', 'name')->get(),
            'variants' => ProductVariant::with(['product:id,product_name', 'stocks'])
                ->select('id', 'product_id', 'sku', 'size', 'color')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'exists:product_variants,id'],
            'items.*.system_qty' => ['required', 'integer', 'min:0'],
            'items.*.physical_qty' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($validated, $request) {
            $stockOpname = StockOpname::create([
                'warehouse_id' => $validated['warehouse_id'],
                'user_id' => $request->user()->id,
                'date' => $validated['date'],
                'note' => $validated['note'] ?? null,
            ]);

            foreach ($validated['items'] as $itemData) {
                $difference = $itemData['physical_qty'] - $itemData['system_qty'];

                StockOpnameItem::create([
                    'stock_opname_id' => $stockOpname->id,
                    'product_variant_id' => $itemData['product_variant_id'],
                    'system_qty' => $itemData['system_qty'],
                    'physical_qty' => $itemData['physical_qty'],
                    'difference' => $difference,
                ]);

                // Update physical stock in Stock table
                $stockRecord = Stock::firstOrCreate(
                    ['warehouse_id' => $validated['warehouse_id'], 'product_variant_id' => $itemData['product_variant_id']],
                    ['qty' => 0, 'allocated_qty' => 0]
                );

                $qtyBefore = $stockRecord->qty;
                $stockRecord->update(['qty' => $itemData['physical_qty']]);
                $qtyAfter = $stockRecord->fresh()->qty;

                // Log opname adjustment
                StockLog::create([
                    'product_variant_id' => $itemData['product_variant_id'],
                    'warehouse_id' => $validated['warehouse_id'],
                    'reference_type' => StockOpname::class,
                    'reference_id' => $stockOpname->id,
                    'qty_before' => $qtyBefore,
                    'qty_change' => $difference,
                    'qty_after' => $qtyAfter,
                    'note' => "Penyesuaian Stock Opname Audit #{$stockOpname->id} (Diff: {$difference})",
                ]);
            }
        });

        return redirect()->route('superadmin.warehouse.stock-opname.index')->with('success', 'Hasil Audit Stock Opname berhasil disimpan dan stok telah disesuaikan.');
    }

    public function streamReport(StockOpname $stockOpname)
    {
        $stockOpname->load(['warehouse', 'user', 'items.productVariant.product']);
        $pdf = Pdf::loadView('pdf.stock_opname_report', compact('stockOpname'));

        return $pdf->stream("Laporan_Stock_Opname_{$stockOpname->id}.pdf");
    }
}
