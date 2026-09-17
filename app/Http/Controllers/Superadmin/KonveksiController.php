<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Konveksi;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KonveksiController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $konveksis = Konveksi::query()
            ->when($search, function ($query, $s) {
                $query->where('name', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%")
                    ->orWhere('address', 'like', "%{$s}%");
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Superadmin/Warehouse/Master/KonveksiIndex', [
            'konveksis' => $konveksis,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
        ]);

        Konveksi::create($validated);

        return redirect()->back()->with('success', 'Vendor Konveksi berhasil ditambahkan.');
    }

    public function update(Request $request, Konveksi $konveksi)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
        ]);

        $konveksi->update($validated);

        return redirect()->back()->with('success', 'Vendor Konveksi berhasil diperbarui.');
    }

    public function destroy(Konveksi $konveksi)
    {
        $konveksi->delete();

        return redirect()->back()->with('success', 'Vendor Konveksi berhasil dihapus.');
    }
}
