<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\Store;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StoreController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $seller = $user->sellers()->first() ?? Seller::first();

        if (!$seller) {
            return Inertia::render('Seller/StoreManagement/Index', [
                'stores' => [],
                'seller' => null,
            ]);
        }

        $search = $request->input('search');

        $stores = Store::where('seller_id', $seller->id)
            ->withCount(['stockOuts', 'purchaseOrders'])
            ->when($search, function ($q, $s) {
                $q->where('store_name', 'like', "%{$s}%")
                  ->orWhere('marketplace', 'like', "%{$s}%");
            })
            ->latest()
            ->get()
            ->map(function ($store) {
                return [
                    'id' => $store->id,
                    'store_name' => $store->store_name,
                    'marketplace' => $store->marketplace ?? 'Manual',
                    'stock_outs_count' => $store->stock_outs_count,
                    'purchase_orders_count' => $store->purchase_orders_count,
                    'created_at' => $store->created_at ? $store->created_at->format('d M Y') : '-',
                ];
            });

        return Inertia::render('Seller/StoreManagement/Index', [
            'stores' => $stores,
            'seller' => [
                'id' => $seller->id,
                'seller_name' => $seller->seller_name,
            ],
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $seller = $user->sellers()->first() ?? Seller::first();

        if (!$seller) {
            return redirect()->back()->with('error', 'Profil seller tidak ditemukan.');
        }

        $validated = $request->validate([
            'store_name' => ['required', 'string', 'max:255'],
            'marketplace' => ['required', 'string', 'max:255'],
        ]);

        Store::create([
            'seller_id' => $seller->id,
            'store_name' => $validated['store_name'],
            'marketplace' => $validated['marketplace'],
        ]);

        return redirect()->back()->with('success', 'Toko marketplace berhasil ditambahkan.');
    }

    public function update(Request $request, Store $store)
    {
        $user = auth()->user();
        $seller = $user->sellers()->first() ?? Seller::first();

        if ($seller && $store->seller_id !== $seller->id) {
            abort(403, 'Akses tidak diizinkan.');
        }

        $validated = $request->validate([
            'store_name' => ['required', 'string', 'max:255'],
            'marketplace' => ['required', 'string', 'max:255'],
        ]);

        $store->update($validated);

        return redirect()->back()->with('success', 'Data toko berhasil diperbarui.');
    }

    public function destroy(Store $store)
    {
        $user = auth()->user();
        $seller = $user->sellers()->first() ?? Seller::first();

        if ($seller && $store->seller_id !== $seller->id) {
            abort(403, 'Akses tidak diizinkan.');
        }

        $store->delete();

        return redirect()->back()->with('success', 'Toko berhasil dihapus.');
    }
}
