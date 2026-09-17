<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\ProofTransfer;
use App\Models\Seller;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProofTransferController extends Controller
{
    public function index(Request $request)
    {
        $sellerId = $request->input('seller_id');
        $status = $request->input('status');
        $search = $request->input('search');

        $proofTransfers = ProofTransfer::with(['seller', 'store'])
            ->when($sellerId, fn($q) => $q->where('seller_id', $sellerId))
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($search, function ($query, $s) {
                $query->where(function ($q) use ($s) {
                    $q->where('notes', 'like', "%{$s}%")
                      ->orWhereHas('seller', fn($sq) => $sq->where('seller_name', 'like', "%{$s}%"))
                      ->orWhereHas('store', fn($st) => $st->where('store_name', 'like', "%{$s}%"));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $sellers = Seller::select('id', 'seller_name')->orderBy('seller_name')->get();
        $stores = Store::select('id', 'seller_id', 'store_name')->get();

        $summary = [
            'total' => ProofTransfer::count(),
            'pending' => ProofTransfer::where('status', 'pending')->count(),
            'approved' => ProofTransfer::where('status', 'approved')->count(),
            'rejected' => ProofTransfer::where('status', 'rejected')->count(),
            'total_amount_approved' => (float) ProofTransfer::where('status', 'approved')->sum('amount'),
        ];

        return Inertia::render('Superadmin/Finance/ProofTransfers/Index', [
            'proofTransfers' => $proofTransfers,
            'sellers' => $sellers,
            'stores' => $stores,
            'summary' => $summary,
            'filters' => [
                'seller_id' => $sellerId,
                'status' => $status,
                'search' => $search,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'seller_id' => ['required', 'exists:sellers,id'],
            'store_id' => ['nullable', 'exists:stores,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'transfer_date' => ['required', 'date'],
            'proof_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'notes' => ['nullable', 'string'],
        ]);

        $filePath = null;
        if ($request->hasFile('proof_file')) {
            $filePath = $request->file('proof_file')->store('proof-transfers', 'public');
        }

        ProofTransfer::create([
            'seller_id' => $validated['seller_id'],
            'store_id' => $validated['store_id'] ?? null,
            'amount' => $validated['amount'],
            'transfer_date' => $validated['transfer_date'],
            'proof_file' => $filePath,
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Bukti transfer berhasil diunggah & disimpan.');
    }

    public function updateStatus(Request $request, ProofTransfer $proofTransfer)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,approved,rejected'],
            'notes' => ['nullable', 'string'],
        ]);

        $proofTransfer->update([
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? $proofTransfer->notes,
        ]);

        return redirect()->back()->with('success', "Status bukti transfer berhasil diubah menjadi {$validated['status']}.");
    }

    public function destroy(ProofTransfer $proofTransfer)
    {
        if ($proofTransfer->proof_file) {
            Storage::disk('public')->delete($proofTransfer->proof_file);
        }

        $proofTransfer->delete();

        return redirect()->back()->with('success', 'Bukti transfer berhasil dihapus.');
    }
}
