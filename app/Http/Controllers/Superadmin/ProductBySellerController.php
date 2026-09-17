<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductBySellerController extends Controller
{
    public function index(Request $request)
    {
        $sellerId = $request->input('seller_id');
        $search = $request->input('search');

        $sellers = Seller::withCount('products')
            ->with('brand')
            ->get()
            ->map(function ($s) {
                return [
                    'id' => $s->id,
                    'name' => $s->seller_name ?? $s->name,
                    'brand_name' => $s->brand->name ?? null,
                    'products_count' => $s->products_count ?? 0,
                ];
            });

        $selectedSeller = null;
        if ($sellerId) {
            $sellerObj = Seller::with('brand')->find($sellerId);
            if ($sellerObj) {
                $selectedSeller = [
                    'id' => $sellerObj->id,
                    'name' => $sellerObj->seller_name ?? $sellerObj->name,
                    'brand_name' => $sellerObj->brand->name ?? null,
                ];
            }
        }

        $products = null;
        if ($sellerId) {
            $products = Product::query()
                ->with(['seller', 'brand', 'category', 'konveksi', 'variants'])
                ->where('seller_id', $sellerId)
                ->when($search, function ($q) use ($search) {
                    $q->where('product_name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhereHas('variants', function ($vq) use ($search) {
                            $vq->where('sku', 'like', "%{$search}%")
                                ->orWhere('barcode', 'like', "%{$search}%");
                        });
                })
                ->latest()
                ->paginate(12)
                ->withQueryString();
        }

        return Inertia::render('Superadmin/Warehouse/Products/ProductBySeller', [
            'sellers' => $sellers,
            'selectedSeller' => $selectedSeller,
            'products' => $products,
            'filters' => [
                'seller_id' => $sellerId,
                'search' => $search,
            ],
        ]);
    }
}
