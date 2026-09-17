<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $seller = $user->sellers()->first() ?? Seller::first();

        if (!$seller) {
            return Inertia::render('Seller/Products/Index', [
                'products' => [],
                'categories' => [],
                'brands' => [],
                'filters' => [],
            ]);
        }

        $search = $request->input('search');
        $categoryId = $request->input('category_id');
        $brandId = $request->input('brand_id');
        $productType = $request->input('product_type');

        $products = Product::where('seller_id', $seller->id)
            ->with(['category', 'brand', 'variants.stocks.warehouse'])
            ->when($search, function ($q, $s) {
                $q->where(function ($sub) use ($s) {
                    $sub->where('product_name', 'like', "%{$s}%")
                        ->orWhere('sku', 'like', "%{$s}%")
                        ->orWhereHas('variants', function ($vq) use ($s) {
                            $vq->where('sku', 'like', "%{$s}%")
                               ->orWhere('color', 'like', "%{$s}%")
                               ->orWhere('size', 'like', "%{$s}%");
                        });
                });
            })
            ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
            ->when($brandId, fn($q) => $q->where('brand_id', $brandId))
            ->when($productType, fn($q) => $q->where('product_type', $productType))
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(function ($p) {
                $totalStock = $p->variants->sum(function ($v) {
                    return $v->stocks->sum('qty');
                });

                $minHpp = $p->variants->min('price') ?? 0;
                $maxHpp = $p->variants->max('price') ?? 0;

                $minSelling = $p->variants->min('selling_price') ?? 0;
                $maxSelling = $p->variants->max('selling_price') ?? 0;

                return [
                    'id' => $p->id,
                    'product_name' => $p->product_name,
                    'sku' => $p->sku,
                    'product_type' => $p->product_type ?? 'Single',
                    'image' => $p->image,
                    'category_name' => $p->category->name ?? 'Tanpa Kategori',
                    'brand_name' => $p->brand->name ?? 'Tanpa Brand',
                    'total_stock' => (int) $totalStock,
                    'variant_count' => $p->variants->count(),
                    'hpp_range' => [
                        'min' => (float) $minHpp,
                        'max' => (float) $maxHpp,
                    ],
                    'selling_range' => [
                        'min' => (float) $minSelling,
                        'max' => (float) $maxSelling,
                    ],
                    'variants' => $p->variants->map(function ($v) {
                        $vStock = $v->stocks->sum('qty');
                        return [
                            'id' => $v->id,
                            'sku' => $v->sku,
                            'size' => $v->size,
                            'color' => $v->color,
                            'price' => (float) $v->price,
                            'selling_price' => (float) $v->selling_price,
                            'stock' => (int) $vStock,
                            'stocks_per_warehouse' => $v->stocks->map(function ($st) {
                                return [
                                    'warehouse_name' => $st->warehouse->name ?? 'Gudang',
                                    'qty' => (int) $st->qty,
                                ];
                            }),
                        ];
                    }),
                ];
            });

        $categories = Category::select('id', 'name')->get();
        $brands = Brand::select('id', 'name')->get();

        return Inertia::render('Seller/Products/Index', [
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'filters' => [
                'search' => $search,
                'category_id' => $categoryId,
                'brand_id' => $brandId,
                'product_type' => $productType,
            ],
        ]);
    }
}
