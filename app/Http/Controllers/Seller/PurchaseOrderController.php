<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\PoType;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Seller;
use App\Models\Store;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $seller = $user->sellers()->first() ?? Seller::first();

        if (!$seller) {
            return Inertia::render('Seller/PurchaseOrders/Index', [
                'purchaseOrders' => [],
                'summary' => [
                    'total_po' => 0,
                    'pending_count' => 0,
                    'in_production_count' => 0,
                    'completed_count' => 0,
                ],
                'filters' => [],
            ]);
        }

        $search = $request->input('search');
        $status = $request->input('status'); // 'all', 'pending', 'in_production', 'completed'

        $query = PurchaseOrder::where('seller_id', $seller->id)
            ->with(['store', 'poType', 'items.productVariant.product', 'items.konveksi']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('po_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('store', fn($sq) => $sq->where('store_name', 'like', "%{$search}%"));
            });
        }

        if ($status === 'pending') {
            $query->whereIn('production_status', ['pending', 'approved']);
        } elseif ($status === 'in_production') {
            $query->where('production_status', 'in_production');
        } elseif ($status === 'completed') {
            $query->where('production_status', 'completed');
        }

        $purchaseOrders = $query->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(function ($po) {
                $totalQty = (int) $po->items->sum('qty');
                $totalEstimatedCost = (float) $po->items->sum(DB::raw('qty * price'));

                $statusProgress = 25;
                $statusLabel = 'Menunggu Persetujuan';
                if ($po->production_status === 'approved') {
                    $statusProgress = 50;
                    $statusLabel = 'Disetujui Admin';
                } elseif ($po->production_status === 'in_production') {
                    $statusProgress = 75;
                    $statusLabel = 'Proses Produksi Konveksi';
                } elseif ($po->production_status === 'completed') {
                    $statusProgress = 100;
                    $statusLabel = 'Produksi Selesai';
                }

                return [
                    'id' => $po->id,
                    'po_number' => $po->po_number,
                    'created_at' => $po->created_at ? $po->created_at->format('d M Y - H:i') : '-',
                    'store_name' => $po->store->store_name ?? 'Umum',
                    'po_type_name' => $po->poType->name ?? 'Reguler',
                    'priority' => strtoupper($po->priority ?? 'NORMAL'),
                    'production_status' => $po->production_status ?? 'pending',
                    'status_progress' => $statusProgress,
                    'status_label' => $statusLabel,
                    'notes' => $po->notes,
                    'konveksi_notes' => $po->konveksi_notes,
                    'total_qty' => $totalQty,
                    'total_estimated_cost' => $totalEstimatedCost,
                    'items' => $po->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'product_name' => $item->productVariant->product->product_name ?? 'Produk',
                            'variant_info' => trim(($item->productVariant->color ?? '') . ' ' . ($item->productVariant->size ?? '')),
                            'sku' => $item->productVariant->sku ?? '-',
                            'qty' => (int) $item->qty,
                            'price' => (float) $item->price,
                            'subtotal' => (float) ($item->qty * $item->price),
                            'konveksi_name' => $item->konveksi->name ?? 'Tim Konveksi',
                        ];
                    }),
                ];
            });

        $allPos = PurchaseOrder::where('seller_id', $seller->id)->get();

        return Inertia::render('Seller/PurchaseOrders/Index', [
            'purchaseOrders' => $purchaseOrders,
            'summary' => [
                'total_po' => $allPos->count(),
                'pending_count' => $allPos->whereIn('production_status', ['pending', 'approved'])->count(),
                'in_production_count' => $allPos->where('production_status', 'in_production')->count(),
                'completed_count' => $allPos->where('production_status', 'completed')->count(),
            ],
            'filters' => [
                'search' => $search,
                'status' => $status ?? 'all',
            ],
        ]);
    }

    public function create()
    {
        $user = auth()->user();
        $seller = $user->sellers()->first() ?? Seller::first();

        if (!$seller) {
            return redirect()->route('seller.purchase-orders.index')->with('error', 'Profil seller tidak ditemukan.');
        }

        $stores = Store::where('seller_id', $seller->id)->select('id', 'store_name', 'marketplace')->get();
        $poTypes = PoType::select('id', 'name')->get();

        if ($poTypes->isEmpty()) {
            $poTypes = collect([
                ['id' => 1, 'name' => 'Restock Reguler'],
                ['id' => 2, 'name' => 'Restock Prioritas (Urgent)'],
            ]);
        }

        $variants = ProductVariant::whereHas('product', function ($q) use ($seller) {
            $q->where('seller_id', $seller->id);
        })
        ->with(['product.category'])
        ->get()
        ->map(function ($v) {
            return [
                'id' => $v->id,
                'product_name' => $v->product->product_name ?? 'Produk',
                'category_name' => $v->product->category->name ?? 'Kategori',
                'sku' => $v->sku,
                'size' => $v->size,
                'color' => $v->color,
                'price' => (float) $v->price,
                'selling_price' => (float) $v->selling_price,
            ];
        });

        return Inertia::render('Seller/PurchaseOrders/Form', [
            'stores' => $stores,
            'poTypes' => $poTypes,
            'variants' => $variants,
            'seller' => [
                'id' => $seller->id,
                'seller_name' => $seller->seller_name,
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
            'po_type_id' => ['nullable', 'exists:po_types,id'],
            'priority' => ['required', 'string', 'in:normal,high,urgent'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'exists:product_variants,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($seller, $user, $validated) {
            $poNumber = 'PO-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

            $po = PurchaseOrder::create([
                'po_number' => $poNumber,
                'seller_id' => $seller->id,
                'store_id' => $validated['store_id'] ?? null,
                'po_type_id' => $validated['po_type_id'] ?? null,
                'user_id' => $user->id,
                'priority' => strtolower($validated['priority']),
                'production_status' => 'pending',
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_variant_id' => $item['product_variant_id'],
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'status' => 'pending',
                ]);
            }
        });

        return redirect()->route('seller.purchase-orders.index')->with('success', 'Pengajuan PO Restock berhasil dikirim.');
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        $user = auth()->user();
        $seller = $user->sellers()->first() ?? Seller::first();

        if ($seller && $purchaseOrder->seller_id !== $seller->id) {
            abort(403, 'Akses tidak diizinkan.');
        }

        if ($purchaseOrder->production_status !== 'pending') {
            return redirect()->back()->with('error', 'PO yang sudah diproses konveksi tidak dapat dibatalkan.');
        }

        $purchaseOrder->items()->delete();
        $purchaseOrder->delete();

        return redirect()->back()->with('success', 'Pengajuan PO berhasil dibatalkan.');
    }

    public function pdf(PurchaseOrder $purchaseOrder)
    {
        $user = auth()->user();
        $seller = $user->sellers()->first() ?? Seller::first();

        if ($seller && $purchaseOrder->seller_id !== $seller->id) {
            abort(403, 'Akses tidak diizinkan.');
        }

        $purchaseOrder->load(['seller', 'store', 'poType', 'items.productVariant.product', 'items.konveksi']);

        $totalQty = (int) $purchaseOrder->items->sum('qty');
        $totalCost = (float) $purchaseOrder->items->sum(DB::raw('qty * price'));

        $pdf = Pdf::loadView('pdf.seller_po_invoice', compact('purchaseOrder', 'totalQty', 'totalCost'));

        return $pdf->stream('Invoice_' . $purchaseOrder->po_number . '.pdf');
    }
}
