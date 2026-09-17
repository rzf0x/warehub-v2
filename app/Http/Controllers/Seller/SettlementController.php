<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\ProofTransfer;
use App\Models\Seller;
use App\Models\SellerDebtAdjustment;
use App\Models\StockInItem;
use App\Models\StockOutItem;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SettlementController extends Controller
{
    public function create()
    {
        $user = auth()->user();
        $seller = $user->sellers()->first() ?? Seller::first();

        if (!$seller) {
            return redirect()->route('seller.debt.index')->with('error', 'Profil seller tidak ditemukan.');
        }

        // Net Debt Calculation for settlement target
        $stockInHpp = (float) StockInItem::whereHas('productVariant.product', function ($q) use ($seller) {
            $q->where('seller_id', $seller->id);
        })->sum(DB::raw('qty * price'));

        $totalHppSold = (float) StockOutItem::whereHas('productVariant.product', function ($q) use ($seller) {
            $q->where('seller_id', $seller->id);
        })->sum(DB::raw('qty * price'));

        $totalAdjustments = (float) SellerDebtAdjustment::where('seller_id', $seller->id)->sum('amount');

        $approvedTransfers = (float) ProofTransfer::where('seller_id', $seller->id)
            ->where('status', 'approved')
            ->sum('amount');

        $netDebt = max(0, $stockInHpp - $totalHppSold + $totalAdjustments - $approvedTransfers);

        $stores = Store::where('seller_id', $seller->id)->select('id', 'store_name', 'marketplace')->get();

        return Inertia::render('Seller/Debt/Settlement', [
            'stores' => $stores,
            'netDebt' => $netDebt,
            'bankInfo' => [
                'bank_name' => 'BCA (Bank Central Asia)',
                'account_number' => '8410928192',
                'account_holder' => 'AA Gym Konveksi Gudang',
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
            'store_id' => ['nullable', 'exists:stores,id'],
            'amount' => ['required', 'numeric', 'min:1000'],
            'transfer_date' => ['required', 'date'],
            'proof_file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'], // Max 5MB
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $filePath = null;
        if ($request->hasFile('proof_file')) {
            $file = $request->file('proof_file');
            $filename = 'transfer_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('storage/proof_transfers'), $filename);
            $filePath = 'storage/proof_transfers/' . $filename;
        }

        ProofTransfer::create([
            'seller_id' => $seller->id,
            'store_id' => $validated['store_id'] ?? null,
            'amount' => $validated['amount'],
            'transfer_date' => $validated['transfer_date'],
            'proof_file' => $filePath,
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('seller.debt.index')->with('success', 'Bukti transfer berhasil di-upload. Menunggu verifikasi admin.');
    }
}
