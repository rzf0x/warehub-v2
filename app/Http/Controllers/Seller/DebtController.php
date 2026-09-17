<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\ProofTransfer;
use App\Models\Seller;
use App\Models\SellerDebtAdjustment;
use App\Models\StockInItem;
use App\Models\StockOutItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DebtController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $seller = $user->sellers()->first() ?? Seller::first();

        if (!$seller) {
            return Inertia::render('Seller/Debt/Index', [
                'metrics' => [
                    'stock_in_hpp' => 0,
                    'total_hpp_sold' => 0,
                    'total_adjustments' => 0,
                    'approved_transfers' => 0,
                    'net_debt' => 0,
                ],
                'proofTransfers' => [],
                'adjustments' => [],
            ]);
        }

        // 1. Total HPP Stock In (Barang Masuk)
        $stockInHpp = (float) StockInItem::whereHas('productVariant.product', function ($q) use ($seller) {
            $q->where('seller_id', $seller->id);
        })->sum(DB::raw('qty * price'));

        // 2. Total HPP Sold (Barang Terjual)
        $totalHppSold = (float) StockOutItem::whereHas('productVariant.product', function ($q) use ($seller) {
            $q->where('seller_id', $seller->id);
        })->sum(DB::raw('qty * price'));

        // 3. Debt Adjustments
        $totalAdjustments = (float) SellerDebtAdjustment::where('seller_id', $seller->id)->sum('amount');

        // 4. Approved Proof Transfers
        $approvedTransfers = (float) ProofTransfer::where('seller_id', $seller->id)
            ->where('status', 'approved')
            ->sum('amount');

        // Net Tagihan Modal
        $netDebt = $stockInHpp - $totalHppSold + $totalAdjustments - $approvedTransfers;

        // Proof Transfer Submissions History
        $proofTransfers = ProofTransfer::where('seller_id', $seller->id)
            ->with('store')
            ->latest()
            ->paginate(10)
            ->through(function ($pf) {
                return [
                    'id' => $pf->id,
                    'amount' => (float) $pf->amount,
                    'transfer_date' => $pf->transfer_date ? Carbon::parse($pf->transfer_date)->format('d M Y') : '-',
                    'proof_file' => $pf->proof_file,
                    'status' => $pf->status ?? 'pending',
                    'notes' => $pf->notes,
                    'store_name' => $pf->store->store_name ?? 'Umum',
                    'created_at' => $pf->created_at ? $pf->created_at->format('d M Y - H:i') : '-',
                ];
            });

        // Debt Adjustment History
        $adjustments = SellerDebtAdjustment::where('seller_id', $seller->id)
            ->with('period')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($adj) {
                return [
                    'id' => $adj->id,
                    'amount' => (float) $adj->amount,
                    'reason' => $adj->reason,
                    'period_name' => $adj->period->name ?? 'Umum',
                    'date' => $adj->date ? Carbon::parse($adj->date)->format('d M Y') : '-',
                ];
            });

        return Inertia::render('Seller/Debt/Index', [
            'metrics' => [
                'stock_in_hpp' => $stockInHpp,
                'total_hpp_sold' => $totalHppSold,
                'total_adjustments' => $totalAdjustments,
                'approved_transfers' => $approvedTransfers,
                'net_debt' => $netDebt,
            ],
            'proofTransfers' => $proofTransfers,
            'adjustments' => $adjustments,
            'bankInfo' => [
                'bank_name' => 'BCA (Bank Central Asia)',
                'account_number' => '8410928192',
                'account_holder' => 'AA Gym Konveksi Gudang',
            ],
        ]);
    }
}
