<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\BundleItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProductBundleController extends Controller
{
    public function create()
    {
        $sellers = Seller::select('id', 'seller_name as name')->get();
        $availableVariants = ProductVariant::with(['product'])
            ->select('id', 'product_id', 'sku', 'size', 'color', 'price', 'selling_price')
            ->get();

        return Inertia::render('Superadmin/Warehouse/Products/ProductBundleForm', [
            'sellers' => $sellers,
            'availableVariants' => $availableVariants,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'seller_id' => ['required', 'exists:sellers,id'],
            'bundle_name' => ['required', 'string', 'max:255'],
            'parent_sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.component_variant_id' => ['required', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($validated) {
            $product = Product::create([
                'seller_id' => $validated['seller_id'],
                'product_name' => $validated['bundle_name'],
                'sku' => $validated['parent_sku'],
                'product_type' => 'bundle',
            ]);

            $bundleVariant = ProductVariant::create([
                'product_id' => $product->id,
                'sku' => $validated['parent_sku'],
                'size' => 'BUNDLE',
                'color' => 'BUNDLE',
                'price' => $validated['price'],
                'selling_price' => $validated['selling_price'],
            ]);

            foreach ($validated['items'] as $item) {
                BundleItem::create([
                    'bundle_variant_id' => $bundleVariant->id,
                    'component_variant_id' => $item['component_variant_id'],
                    'quantity' => $item['quantity'],
                ]);
            }
        });

        return redirect()->route('superadmin.warehouse.products.index')->with('success', 'Paket Bundle Multi-SKU berhasil dirakit!');
    }
}
