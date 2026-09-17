<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Konveksi;
use App\Models\KonveksiPayment;
use App\Models\StockInItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class KonveksiDebtController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $konveksis = Konveksi::query()
            ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->get();

        $konveksiDebts = $konveksis->map(function ($k) {
            $totalTagihan = (float) StockInItem::where('konveksi_id', $k->id)
                ->sum(DB::raw('qty * price'));

            $totalPaid = (float) KonveksiPayment::where('konveksi_id', $k->id)
                ->sum('amount');

            $sisaTagihan = $totalTagihan - $totalPaid;

            $status = 'Lunas';
            if ($sisaTagihan > 0) {
                $status = $totalPaid > 0 ? 'Sebagian / DP' : 'Belum Bayar';
            }

            return [
                'id' => $k->id,
                'name' => $k->name,
                'contact' => $k->contact,
                'color' => $k->color,
                'total_tagihan' => $totalTagihan,
                'total_paid' => $totalPaid,
                'sisa_tagihan' => max(0, $sisaTagihan),
                'status' => $status,
            ];
        });

        $summary = [
            'total_konveksis' => $konveksiDebts->count(),
            'total_tagihan' => $konveksiDebts->sum('total_tagihan'),
            'total_paid' => $konveksiDebts->sum('total_paid'),
            'total_sisa_tagihan' => $konveksiDebts->sum('sisa_tagihan'),
        ];

        return Inertia::render('Superadmin/Finance/KonveksiDebt/Index', [
            'konveksiDebts' => $konveksiDebts,
            'summary' => $summary,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function show(Konveksi $konveksi, Request $request)
    {
        $stockInItems = StockInItem::with(['stockIn.warehouse', 'productVariant.product'])
            ->where('konveksi_id', $konveksi->id)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $payments = KonveksiPayment::where('konveksi_id', $konveksi->id)
            ->latest()
            ->get();

        $totalTagihan = (float) StockInItem::where('konveksi_id', $konveksi->id)->sum(DB::raw('qty * price'));
        $totalPaid = (float) $payments->sum('amount');
        $sisaTagihan = max(0, $totalTagihan - $totalPaid);

        return Inertia::render('Superadmin/Finance/KonveksiDebt/PaymentShow', [
            'konveksi' => $konveksi,
            'stockInItems' => $stockInItems,
            'payments' => $payments,
            'stats' => [
                'total_tagihan' => $totalTagihan,
                'total_paid' => $totalPaid,
                'sisa_tagihan' => $sisaTagihan,
            ],
        ]);
    }

    public function storePayment(Request $request, Konveksi $konveksi)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string'],
            'proof_image' => ['nullable', 'image', 'max:5120'],
        ]);

        $proofImagePath = null;
        if ($request->hasFile('proof_image')) {
            $proofImagePath = $request->file('proof_image')->store('konveksi-proofs', 'public');
        }

        KonveksiPayment::create([
            'konveksi_id' => $konveksi->id,
            'amount' => $validated['amount'],
            'payment_date' => $validated['payment_date'],
            'payment_method' => $validated['payment_method'] ?? 'Transfer Bank',
            'reference_number' => $validated['reference_number'] ?? null,
            'note' => $validated['note'] ?? null,
            'proof_image' => $proofImagePath,
        ]);

        return redirect()->back()->with('success', 'Pembayaran tagihan konveksi berhasil dicatat.');
    }

    public function destroyPayment(KonveksiPayment $payment)
    {
        if ($payment->proof_image) {
            Storage::disk('public')->delete($payment->proof_image);
        }

        $payment->delete();

        return redirect()->back()->with('success', 'Catatan pembayaran konveksi berhasil dihapus.');
    }
}
