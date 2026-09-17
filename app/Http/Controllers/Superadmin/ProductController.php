<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Konveksi;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\VariantTemplate;
use App\Services\VariantPricePropagationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function __construct(
        protected VariantPricePropagationService $pricePropagationService
    ) {}

    public function index(Request $request)
    {
        $search = $request->input('search');
        $sellerId = $request->input('seller_id');
        $brandId = $request->input('brand_id');

        $products = Product::query()
            ->with(['seller', 'brand', 'category', 'konveksi', 'variants'])
            ->when($search, function ($query, $s) {
                $query->where('product_name', 'like', "%{$s}%")
                    ->orWhere('sku', 'like', "%{$s}%")
                    ->orWhereHas('variants', function ($vq) use ($s) {
                        $vq->where('sku', 'like', "%{$s}%")
                            ->orWhere('barcode', 'like', "%{$s}%");
                    });
            })
            ->when($sellerId, fn($q) => $q->where('seller_id', $sellerId))
            ->when($brandId, fn($q) => $q->where('brand_id', $brandId))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $sellers = Seller::select('id', 'seller_name as name')->get();
        $brands = Brand::select('id', 'name')->get();

        return Inertia::render('Superadmin/Warehouse/Products/Index', [
            'products' => $products,
            'sellers' => $sellers,
            'brands' => $brands,
            'filters' => [
                'search' => $search,
                'seller_id' => $sellerId,
                'brand_id' => $brandId,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Superadmin/Warehouse/Products/ProductForm', [
            'product' => null,
            'sellers' => Seller::select('id', 'seller_name')->get(),
            'brands' => Brand::select('id', 'name')->get(),
            'categories' => Category::select('id', 'name')->get(),
            'konveksis' => Konveksi::select('id', 'name')->get(),
            'variantTemplates' => VariantTemplate::with('items')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'seller_id' => ['required', 'exists:sellers,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'konveksi_id' => ['nullable', 'exists:konveksis,id'],
            'product_name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'product_type' => ['required', 'string', 'in:single,bundle'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.sku' => ['required', 'string', 'max:100', 'unique:product_variants,sku'],
            'variants.*.size' => ['required', 'string', 'max:50'],
            'variants.*.color' => ['required', 'string', 'max:50'],
            'variants.*.price' => ['required', 'numeric', 'min:0'],
            'variants.*.selling_price' => ['required', 'numeric', 'min:0'],
            'variants.*.barcode' => ['nullable', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($validated) {
            $product = Product::create([
                'seller_id' => $validated['seller_id'],
                'brand_id' => $validated['brand_id'] ?? null,
                'category_id' => $validated['category_id'] ?? null,
                'konveksi_id' => $validated['konveksi_id'] ?? null,
                'product_name' => $validated['product_name'],
                'sku' => $validated['sku'],
                'product_type' => $validated['product_type'],
            ]);

            foreach ($validated['variants'] as $vData) {
                ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $vData['sku'],
                    'size' => $vData['size'],
                    'color' => $vData['color'],
                    'price' => $vData['price'],
                    'selling_price' => $vData['selling_price'],
                    'barcode' => $vData['barcode'] ?? null,
                ]);
            }
        });

        return redirect()->route('superadmin.warehouse.products.index')->with('success', 'Produk & Varian berhasil dibuat.');
    }

    public function edit(Product $product)
    {
        $product->load(['variants']);

        return Inertia::render('Superadmin/Warehouse/Products/ProductForm', [
            'product' => $product,
            'sellers' => Seller::select('id', 'seller_name')->get(),
            'brands' => Brand::select('id', 'name')->get(),
            'categories' => Category::select('id', 'name')->get(),
            'konveksis' => Konveksi::select('id', 'name')->get(),
            'variantTemplates' => VariantTemplate::with('items')->get(),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'seller_id' => ['required', 'exists:sellers,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'konveksi_id' => ['nullable', 'exists:konveksis,id'],
            'product_name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku,'.$product->id],
            'product_type' => ['required', 'string', 'in:single,bundle'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'variants.*.sku' => ['required', 'string', 'max:100'],
            'variants.*.size' => ['required', 'string', 'max:50'],
            'variants.*.color' => ['required', 'string', 'max:50'],
            'variants.*.price' => ['required', 'numeric', 'min:0'],
            'variants.*.selling_price' => ['required', 'numeric', 'min:0'],
            'variants.*.barcode' => ['nullable', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($product, $validated) {
            $product->update([
                'seller_id' => $validated['seller_id'],
                'brand_id' => $validated['brand_id'] ?? null,
                'category_id' => $validated['category_id'] ?? null,
                'konveksi_id' => $validated['konveksi_id'] ?? null,
                'product_name' => $validated['product_name'],
                'sku' => $validated['sku'],
                'product_type' => $validated['product_type'],
            ]);

            $existingIds = $product->variants()->pluck('id')->toArray();
            $keptIds = [];

            foreach ($validated['variants'] as $vData) {
                if (!empty($vData['id'])) {
                    $variant = ProductVariant::find($vData['id']);
                    if ($variant) {
                        $oldPrice = (float) $variant->price;
                        $oldSellingPrice = (float) $variant->selling_price;

                        $variant->update([
                            'sku' => $vData['sku'],
                            'size' => $vData['size'],
                            'color' => $vData['color'],
                            'price' => $vData['price'],
                            'selling_price' => $vData['selling_price'],
                            'barcode' => $vData['barcode'] ?? null,
                        ]);

                        $this->pricePropagationService->propagatePriceChange($variant, $oldPrice, $oldSellingPrice);
                        $keptIds[] = $variant->id;
                    }
                } else {
                    $newVariant = ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => $vData['sku'],
                        'size' => $vData['size'],
                        'color' => $vData['color'],
                        'price' => $vData['price'],
                        'selling_price' => $vData['selling_price'],
                        'barcode' => $vData['barcode'] ?? null,
                    ]);
                    $keptIds[] = $newVariant->id;
                }
            }

            // Remove deleted variants
            $toDelete = array_diff($existingIds, $keptIds);
            if (!empty($toDelete)) {
                ProductVariant::whereIn('id', $toDelete)->delete();
            }
        });

        return redirect()->route('superadmin.warehouse.products.index')->with('success', 'Produk & Varian berhasil diperbarui.');
    }

    public function destroy(Product $product)
    {
        DB::transaction(function () use ($product) {
            $product->variants()->delete();
            $product->delete();
        });

        return redirect()->back()->with('success', 'Produk berhasil dihapus.');
    }

    public function exportPdf(Request $request)
    {
        $products = Product::with(['seller', 'brand', 'category', 'konveksi', 'variants'])->get();
        $pdf = Pdf::loadView('pdf.products', compact('products'));

        return $pdf->stream('Katalog_Produk_Warehub_v2.pdf');
    }
}
