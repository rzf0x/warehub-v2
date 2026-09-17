<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Pengadaan;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PengadaanController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');

        $query = Pengadaan::with(['user', 'sellers']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $pengadaans = $query->latest()->paginate(10)->withQueryString();
        $sellers = Seller::all();
        $users = User::all();

        return Inertia::render('Superadmin/UserManagement/Pengadaan/Index', [
            'pengadaans' => $pengadaans,
            'sellers' => $sellers,
            'users' => $users,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'seller_ids' => ['nullable', 'array'],
            'seller_ids.*' => ['exists:sellers,id'],
        ]);

        $pengadaan = Pengadaan::create([
            'user_id' => $validated['user_id'] ?? null,
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
        ]);

        if (!empty($validated['seller_ids'])) {
            $pengadaan->sellers()->sync($validated['seller_ids']);
        }

        return back()->with('success', 'Tim Pengadaan berhasil ditambahkan.');
    }

    public function update(Request $request, Pengadaan $pengadaan): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'seller_ids' => ['nullable', 'array'],
            'seller_ids.*' => ['exists:sellers,id'],
        ]);

        $pengadaan->update([
            'user_id' => $validated['user_id'] ?? null,
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
        ]);

        $pengadaan->sellers()->sync($validated['seller_ids'] ?? []);

        return back()->with('success', 'Data Tim Pengadaan berhasil diperbarui.');
    }

    public function destroy(Pengadaan $pengadaan): RedirectResponse
    {
        $pengadaan->sellers()->detach();
        $pengadaan->delete();

        return back()->with('success', 'Tim Pengadaan berhasil dihapus.');
    }
}
