<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MergerSkuController extends Controller
{
    public function index(Request $request)
    {
        $variants = ProductVariant::with(['product.seller'])
            ->select('id', 'product_id', 'sku', 'size', 'color', 'price')
            ->latest()
            ->paginate(15);

        return Inertia::render('Superadmin/Warehouse/Mapping/MergerSku', [
            'variants' => $variants,
        ]);
    }

    public function merge(Request $request)
    {
        $validated = $request->validate([
            'target_sku' => ['required', 'string', 'max:100'],
            'source_variant_ids' => ['required', 'array', 'min:1'],
            'source_variant_ids.*' => ['exists:product_variants,id'],
        ]);

        DB::transaction(function () use ($validated) {
            ProductVariant::whereIn('id', $validated['source_variant_ids'])
                ->update(['sku' => $validated['target_sku']]);
        });

        return redirect()->back()->with('success', 'SKU Varian berhasil digabungkan / diselaraskan!');
    }
}
