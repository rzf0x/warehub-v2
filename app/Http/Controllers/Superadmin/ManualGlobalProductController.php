<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\ManualGlobalProduct;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ManualGlobalProductController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $sellerId = $request->input('seller_id');

        $mappings = ManualGlobalProduct::query()
            ->with(['seller', 'warehouse', 'productVariant.product', 'user'])
            ->when($sellerId, fn($q) => $q->where('seller_id', $sellerId))
            ->when($search, function ($query, $s) {
                $query->where(function ($q) use ($s) {
                    $q->where('note', 'like', "%{$s}%")
                        ->orWhereHas('seller', fn($sel) => $sel->where('seller_name', 'like', "%{$s}%"))
                        ->orWhereHas('warehouse', fn($wh) => $wh->where('name', 'like', "%{$s}%"))
                        ->orWhereHas('productVariant', function ($pv) use ($s) {
                            $pv->where('sku', 'like', "%{$s}%")
                                ->orWhereHas('product', fn($p) => $p->where('product_name', 'like', "%{$s}%"));
                        });
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $sellers = Seller::select('id', 'seller_name as name')->get();
        $warehouses = Warehouse::select('id', 'name')->get();
        $warehouseVariants = ProductVariant::with('product:id,product_name')
            ->select('id', 'product_id', 'sku', 'size', 'color')
            ->get();

        return Inertia::render('Superadmin/Warehouse/Mapping/ManualGlobalProductIndex', [
            'mappings' => $mappings,
            'sellers' => $sellers,
            'warehouses' => $warehouses,
            'warehouseVariants' => $warehouseVariants,
            'filters' => [
                'search' => $search,
                'seller_id' => $sellerId,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'seller_id' => ['required', 'exists:sellers,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'product_variant_id' => ['required', 'exists:product_variants,id'],
            'qty' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['user_id'] = $request->user()?->id;

        ManualGlobalProduct::create($validated);

        return redirect()->back()->with('success', 'Data pengambilan produk manual berhasil disimpan.');
    }

    public function update(Request $request, ManualGlobalProduct $manualGlobalProduct)
    {
        $validated = $request->validate([
            'seller_id' => ['required', 'exists:sellers,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'product_variant_id' => ['required', 'exists:product_variants,id'],
            'qty' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $manualGlobalProduct->update($validated);

        return redirect()->back()->with('success', 'Data pengambilan produk manual berhasil diperbarui.');
    }

    public function destroy(ManualGlobalProduct $manualGlobalProduct)
    {
        $manualGlobalProduct->delete();

        return redirect()->back()->with('success', 'Data pengambilan produk manual berhasil dihapus.');
    }
}
