<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoreController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $marketplaceFilter = $request->input('marketplace');

        $query = Store::with('seller');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('store_name', 'like', "%{$search}%")
                  ->orWhereHas('seller', function ($sq) use ($search) {
                      $sq->where('seller_name', 'like', "%{$search}%");
                  });
            });
        }

        if ($marketplaceFilter) {
            $query->where('marketplace', $marketplaceFilter);
        }

        $stores = $query->latest()->paginate(10)->withQueryString();
        $sellers = Seller::all();

        return Inertia::render('Superadmin/UserManagement/Stores/Index', [
            'stores' => $stores,
            'sellers' => $sellers,
            'filters' => [
                'search' => $search,
                'marketplace' => $marketplaceFilter,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'seller_id' => ['required', 'exists:sellers,id'],
            'store_name' => ['required', 'string', 'max:255'],
            'marketplace' => ['nullable', 'string', 'max:255'],
        ]);

        Store::create($validated);

        return back()->with('success', 'Toko berhasil ditambahkan.');
    }

    public function update(Request $request, Store $store): RedirectResponse
    {
        $validated = $request->validate([
            'seller_id' => ['required', 'exists:sellers,id'],
            'store_name' => ['required', 'string', 'max:255'],
            'marketplace' => ['nullable', 'string', 'max:255'],
        ]);

        $store->update($validated);

        return back()->with('success', 'Toko berhasil diperbarui.');
    }

    public function destroy(Store $store): RedirectResponse
    {
        $store->delete();

        return back()->with('success', 'Toko berhasil dihapus.');
    }
}
