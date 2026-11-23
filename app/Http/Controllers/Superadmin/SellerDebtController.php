<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Models\Seller;
use App\Models\SellerDebtAdjustment;
use App\Models\StockInItem;
use App\Models\StockOutItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SellerDebtController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $periodId = $request->input('period_id');

        $sellersQuery = Seller::query()
            ->when($search, fn($q) => $q->where('seller_name', 'like', "%{$search}%"))
            ->orderBy('seller_name')
            ->get();

        $periods = Period::select('id', 'name')->get();

        $sellerDebts = $sellersQuery->map(function ($seller) use ($periodId) {
            // Stock In HPP Total
            $stockInHpp = (float) StockInItem::query()
                ->whereHas('productVariant.product', fn($pq) => $pq->where('seller_id', $seller->id))
                ->when($periodId, fn($sq) => $sq->whereHas('stockIn', fn($q) => $q->where('period_id', $periodId)))
                ->sum(DB::raw('qty * price'));

            // Stock Out HPP Total
            $stockOutHpp = (float) StockOutItem::query()
                ->whereHas('productVariant.product', fn($pq) => $pq->where('seller_id', $seller->id))
                ->when($periodId, fn($sq) => $sq->whereHas('stockOut', fn($q) => $q->where('period_id', $periodId)))
                ->sum(DB::raw('qty * price'));

            // Debt Adjustments Total
            $adjustments = (float) SellerDebtAdjustment::query()
                ->where('seller_id', $seller->id)
                ->when($periodId, fn($q) => $q->where('period_id', $periodId))
                ->sum('amount');

            $netDebt = $stockInHpp - $stockOutHpp + $adjustments;

            return [
                'id' => $seller->id,
                'uuid' => $seller->uuid,
                'seller_name' => $seller->seller_name,
                'phone' => $seller->phone,
                'stock_in_hpp' => $stockInHpp,
                'stock_out_hpp' => $stockOutHpp,
                'adjustments' => $adjustments,
                'net_debt' => $netDebt,
            ];
        });

        $summary = [
            'total_sellers' => $sellerDebts->count(),
            'total_stock_in_hpp' => $sellerDebts->sum('stock_in_hpp'),
            'total_stock_out_hpp' => $sellerDebts->sum('stock_out_hpp'),
            'total_net_debt' => $sellerDebts->sum('net_debt'),
        ];

        return Inertia::render('Superadmin/Finance/SellerDebt/Index', [
            'sellerDebts' => $sellerDebts,
            'periods' => $periods,
            'summary' => $summary,
            'filters' => [
                'search' => $search,
                'period_id' => $periodId,
            ],
        ]);
    }

    public function show(Seller $seller, Request $request)
    {
        // Enforce UUID in URL for security (only if uuid column exists and is populated)
        if (! empty($seller->uuid) && $request->segment(4) === (string) $seller->id) {
            return redirect()->to(str_replace('/seller-debt/' . $seller->id, '/seller-debt/' . $seller->uuid, $request->getRequestUri()));
        }

        $periodId = $request->input('period_id');
        $search = $request->input('search');

        $stockInItems = StockInItem::with(['stockIn.warehouse', 'productVariant.product'])
            ->whereHas('productVariant.product', fn($q) => $q->where('seller_id', $seller->id))
            ->when($periodId, fn($q) => $q->whereHas('stockIn', fn($sq) => $sq->where('period_id', $periodId)))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->whereHas('productVariant', function ($vq) use ($search) {
                        $vq->where('sku', 'like', "%{$search}%")
                            ->orWhereHas('product', fn($pq) => $pq->where('product_name', 'like', "%{$search}%"));
                    })->orWhereHas('stockIn', fn($siq) => $siq->where('invoice_number', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(10, ['*'], 'stock_in_page')
            ->withQueryString();

        $stockOutItems = StockOutItem::with(['stockOut.store', 'productVariant.product'])
            ->whereHas('productVariant.product', fn($q) => $q->where('seller_id', $seller->id))
            ->when($periodId, fn($q) => $q->whereHas('stockOut', fn($sq) => $sq->where('period_id', $periodId)))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->whereHas('productVariant', function ($vq) use ($search) {
                        $vq->where('sku', 'like', "%{$search}%")
                            ->orWhereHas('product', fn($pq) => $pq->where('product_name', 'like', "%{$search}%"));
                    })->orWhereHas('stockOut.store', fn($stq) => $stq->where('store_name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(10, ['*'], 'stock_out_page')
            ->withQueryString();

        $adjustments = SellerDebtAdjustment::with('period')
            ->where('seller_id', $seller->id)
            ->when($periodId, fn($q) => $q->where('period_id', $periodId))
            ->latest()
            ->get();

        $stockInTotals = StockInItem::whereHas('productVariant.product', fn($q) => $q->where('seller_id', $seller->id))
            ->when($periodId, fn($q) => $q->whereHas('stockIn', fn($sq) => $sq->where('period_id', $periodId)))
            ->selectRaw('COALESCE(SUM(qty), 0) as total_qty, COALESCE(SUM(qty * price), 0) as total_hpp')
            ->first();

        $stockOutTotals = StockOutItem::whereHas('productVariant.product', fn($q) => $q->where('seller_id', $seller->id))
            ->when($periodId, fn($q) => $q->whereHas('stockOut', fn($sq) => $sq->where('period_id', $periodId)))
            ->selectRaw('COALESCE(SUM(qty), 0) as total_qty, COALESCE(SUM(qty * price), 0) as total_hpp')
            ->first();

        $stockInHpp = (float) ($stockInTotals->total_hpp ?? 0);
        $stockOutHpp = (float) ($stockOutTotals->total_hpp ?? 0);
        $stockInQty = (int) ($stockInTotals->total_qty ?? 0);
        $stockOutQty = (int) ($stockOutTotals->total_qty ?? 0);

        $totalAdjustment = (float) $adjustments->sum('amount');
        $netDebt = $stockInHpp - $stockOutHpp + $totalAdjustment;
        $liquidationRate = $stockInHpp > 0 ? round(($stockOutHpp / $stockInHpp) * 100, 1) : 0;

        $seller->load('brand:id,name');
        $seller->loadCount(['products', 'stores']);

        return Inertia::render('Superadmin/Finance/SellerDebt/Show', [
            'seller' => $seller,
            'stockInItems' => $stockInItems,
            'stockOutItems' => $stockOutItems,
            'adjustments' => $adjustments,
            'periods' => Period::select('id', 'name')->get(),
            'stats' => [
                'stock_in_hpp' => $stockInHpp,
                'stock_out_hpp' => $stockOutHpp,
                'total_adjustment' => $totalAdjustment,
                'net_debt' => $netDebt,
                'stock_in_qty' => $stockInQty,
                'stock_out_qty' => $stockOutQty,
                'liquidation_rate' => $liquidationRate,
                'active_products_count' => $seller->products_count,
                'stores_count' => $seller->stores_count,
            ],
            'filters' => [
                'period_id' => $periodId,
                'search' => $search,
            ],
        ]);
    }

    public function exportPdf(Seller $seller, Request $request)
    {
        // Enforce UUID in URL for security (only if uuid column exists and is populated)
        if (! empty($seller->uuid) && $request->segment(4) === (string) $seller->id) {
            return redirect()->to(str_replace('/seller-debt/' . $seller->id, '/seller-debt/' . $seller->uuid, $request->getRequestUri()));
        }

        $periodId = $request->input('period_id');
        $selectedPeriod = $periodId ? Period::find($periodId) : null;

        $stockInItems = StockInItem::with(['stockIn.warehouse', 'productVariant.product'])
            ->whereHas('productVariant.product', fn($q) => $q->where('seller_id', $seller->id))
            ->when($periodId, fn($q) => $q->whereHas('stockIn', fn($sq) => $sq->where('period_id', $periodId)))
            ->latest()
            ->get();

        $stockOutItems = StockOutItem::with(['stockOut.store', 'productVariant.product'])
            ->whereHas('productVariant.product', fn($q) => $q->where('seller_id', $seller->id))
            ->when($periodId, fn($q) => $q->whereHas('stockOut', fn($sq) => $sq->where('period_id', $periodId)))
            ->latest()
            ->get();

        $adjustments = SellerDebtAdjustment::with('period')
            ->where('seller_id', $seller->id)
            ->when($periodId, fn($q) => $q->where('period_id', $periodId))
            ->latest()
            ->get();

        $stockInHpp = (float) $stockInItems->sum(fn($it) => $it->qty * $it->price);
        $stockOutHpp = (float) $stockOutItems->sum(fn($it) => $it->qty * $it->price);
        $totalAdjustment = (float) $adjustments->sum('amount');
        $netDebt = $stockInHpp - $stockOutHpp + $totalAdjustment;

        $stockInQty = (int) $stockInItems->sum('qty');
        $stockOutQty = (int) $stockOutItems->sum('qty');
        $liquidationRate = $stockInHpp > 0 ? round(($stockOutHpp / $stockInHpp) * 100, 1) : 0;

        $seller->load('brand:id,name');

        $stats = [
            'stock_in_hpp' => $stockInHpp,
            'stock_out_hpp' => $stockOutHpp,
            'total_adjustment' => $totalAdjustment,
            'net_debt' => $netDebt,
            'stock_in_qty' => $stockInQty,
            'stock_out_qty' => $stockOutQty,
            'liquidation_rate' => $liquidationRate,
        ];

        $pdf = Pdf::loadView('pdf.seller_debt_statement', compact(
            'seller',
            'selectedPeriod',
            'stats',
            'stockInItems',
            'stockOutItems',
            'adjustments'
        ));

        $filename = 'Rekening_Koran_Modal_' . str_replace(' ', '_', $seller->seller_name) . '.pdf';
        return $pdf->stream($filename);
    }

    public function chart(Request $request)
    {
        $sellerId = $request->input('seller_id');

        $sellers = Seller::select('id', 'seller_name')->orderBy('seller_name')->get();

        $querySellers = $sellerId ? Seller::where('id', $sellerId)->get() : $sellers;

        $chartData = $querySellers->map(function ($seller) {
            $stockInHpp = (float) StockInItem::whereHas('productVariant.product', fn($q) => $q->where('seller_id', $seller->id))
                ->sum(DB::raw('qty * price'));
            $stockOutHpp = (float) StockOutItem::whereHas('productVariant.product', fn($q) => $q->where('seller_id', $seller->id))
                ->sum(DB::raw('qty * price'));
            $adjustments = (float) SellerDebtAdjustment::where('seller_id', $seller->id)->sum('amount');

            return [
                'seller_id' => $seller->id,
                'seller_name' => $seller->seller_name,
                'stock_in_hpp' => $stockInHpp,
                'stock_out_hpp' => $stockOutHpp,
                'net_debt' => $stockInHpp - $stockOutHpp + $adjustments,
            ];
        });

        return Inertia::render('Superadmin/Finance/SellerDebt/Chart', [
            'sellers' => $sellers,
            'chartData' => $chartData,
            'selectedSellerId' => $sellerId,
        ]);
    }

    public function konveksiSimulation(Request $request)
    {
        $sellers = Seller::select('id', 'seller_name')->orderBy('seller_name')->get();

        return Inertia::render('Superadmin/Finance/SellerDebt/KonveksiSimulation', [
            'sellers' => $sellers,
        ]);
    }

    public function konveksiDetail(Seller $seller, Request $request)
    {
        // Enforce UUID in URL for security (only if uuid column exists and is populated)
        if (! empty($seller->uuid) && $request->segment(4) === (string) $seller->id) {
            return redirect()->to(str_replace('/seller-debt/' . $seller->id, '/seller-debt/' . $seller->uuid, $request->getRequestUri()));
        }

        $periodId = $request->input('period_id');
        $search = $request->input('search');

        // 1. Stock In items aggregated directly in DB (1 optimized query)
        $stockInGrouped = DB::table('stock_in_items as sii')
            ->join('stock_ins as si', 'si.id', '=', 'sii.stock_in_id')
            ->join('product_variants as pv', 'pv.id', '=', 'sii.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('p.seller_id', $seller->id)
            ->when($periodId, fn($q) => $q->where('si.period_id', $periodId))
            ->selectRaw('
                COALESCE(sii.konveksi_id, p.konveksi_id, 0) as konveksi_id,
                pv.id as variant_id,
                pv.sku,
                p.product_name,
                pv.size,
                pv.color,
                AVG(sii.price) as unit_price,
                SUM(sii.qty) as in_qty,
                SUM(sii.qty * sii.price) as in_hpp
            ')
            ->groupBy(
                DB::raw('COALESCE(sii.konveksi_id, p.konveksi_id, 0)'),
                'pv.id',
                'pv.sku',
                'p.product_name',
                'pv.size',
                'pv.color'
            )
            ->get();

        // 2. Stock Out items (Single Products & Bundle Items) aggregated directly in DB (1 optimized query using UNION ALL)
        $stockOutSingleQuery = DB::table('stock_out_items as soi')
            ->join('stock_outs as so', 'so.id', '=', 'soi.stock_out_id')
            ->join('product_variants as pv', 'pv.id', '=', 'soi.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('p.seller_id', $seller->id)
            ->where(function ($q) {
                $q->where('p.product_type', '!=', 'bundle')
                  ->orWhereNull('p.product_type');
            })
            ->when($periodId, fn($q) => $q->where('so.period_id', $periodId))
            ->selectRaw('
                COALESCE(soi.konveksi_id, p.konveksi_id, 0) as konveksi_id,
                pv.id as variant_id,
                pv.sku,
                p.product_name,
                pv.size,
                pv.color,
                CASE WHEN soi.price > 0 THEN soi.price ELSE pv.price END as unit_price,
                soi.qty as out_qty,
                (soi.qty * soi.price) as out_hpp
            ');

        $stockOutBundleQuery = DB::table('stock_out_items as soi')
            ->join('stock_outs as so', 'so.id', '=', 'soi.stock_out_id')
            ->join('product_variants as pv', 'pv.id', '=', 'soi.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->join('bundle_items as bi', 'bi.bundle_variant_id', '=', 'pv.id')
            ->join('product_variants as cpv', 'cpv.id', '=', 'bi.component_variant_id')
            ->join('products as cp', 'cp.id', '=', 'cpv.product_id')
            ->where('p.seller_id', $seller->id)
            ->where('p.product_type', '=', 'bundle')
            ->when($periodId, fn($q) => $q->where('so.period_id', $periodId))
            ->selectRaw('
                COALESCE(cp.konveksi_id, 0) as konveksi_id,
                cpv.id as variant_id,
                cpv.sku,
                cp.product_name,
                cpv.size,
                cpv.color,
                cpv.price as unit_price,
                (soi.qty * bi.qty) as out_qty,
                (soi.qty * bi.qty * cpv.price) as out_hpp
            ');

        $combinedOutQuery = $stockOutSingleQuery->unionAll($stockOutBundleQuery);

        $stockOutGrouped = DB::query()
            ->fromSub($combinedOutQuery, 'combined_out')
            ->selectRaw('
                konveksi_id,
                variant_id,
                sku,
                product_name,
                size,
                color,
                AVG(unit_price) as unit_price,
                SUM(out_qty) as out_qty,
                SUM(out_hpp) as out_hpp
            ')
            ->groupBy('konveksi_id', 'variant_id', 'sku', 'product_name', 'size', 'color')
            ->get();

        // 3. Collect unique konveksi IDs from results
        $konveksiIds = array_values(array_filter(array_unique(array_merge(
            $stockInGrouped->pluck('konveksi_id')->map(fn($v) => (int)$v)->all(),
            $stockOutGrouped->pluck('konveksi_id')->map(fn($v) => (int)$v)->all()
        ))));

        // 4. Fetch only the required konveksis in 1 query
        $konveksiModels = empty($konveksiIds)
            ? collect()
            : \App\Models\Konveksi::whereIn('id', $konveksiIds)
                ->select('id', 'name', 'contact', 'color')
                ->get()
                ->keyBy('id');

        // 5. Structure data into konveksiMap
        $konveksiMap = [];

        $ensureKonveksi = function ($kId) use (&$konveksiMap, $konveksiModels) {
            if (!isset($konveksiMap[$kId])) {
                $konveksiObj = $konveksiModels->get($kId);
                $konveksiMap[$kId] = [
                    'konveksi_id' => $kId,
                    'name' => $konveksiObj?->name ?: 'Tanpa Konveksi / Belum Terdata',
                    'contact' => $konveksiObj?->contact ?: null,
                    'color' => $konveksiObj?->color ?: '#6366f1',
                    'stock_in_qty' => 0,
                    'stock_in_hpp' => 0,
                    'stock_out_qty' => 0,
                    'stock_out_hpp' => 0,
                    'sisa_hutang' => 0,
                    'sisa_qty' => 0,
                    'liquidation_rate' => 0,
                    'products' => [],
                ];
            }
        };

        foreach ($stockInGrouped as $row) {
            $kId = (int) $row->konveksi_id;
            $ensureKonveksi($kId);

            $qty = (int) $row->in_qty;
            $hpp = (float) $row->in_hpp;

            $konveksiMap[$kId]['stock_in_qty'] += $qty;
            $konveksiMap[$kId]['stock_in_hpp'] += $hpp;

            $vId = (int) $row->variant_id;
            $variantDesc = trim(($row->size ?? '') . ' / ' . ($row->color ?? ''), ' /');
            if (!isset($konveksiMap[$kId]['products'][$vId])) {
                $konveksiMap[$kId]['products'][$vId] = [
                    'variant_id' => $vId,
                    'sku' => $row->sku,
                    'product_name' => $row->product_name,
                    'variant' => $variantDesc ?: '-',
                    'unit_price' => (float) $row->unit_price,
                    'in_qty' => 0,
                    'in_hpp' => 0,
                    'out_qty' => 0,
                    'out_hpp' => 0,
                    'sisa_qty' => 0,
                    'sisa_hpp' => 0,
                ];
            }

            $konveksiMap[$kId]['products'][$vId]['in_qty'] += $qty;
            $konveksiMap[$kId]['products'][$vId]['in_hpp'] += $hpp;
            if ($row->unit_price > 0) {
                $konveksiMap[$kId]['products'][$vId]['unit_price'] = (float) $row->unit_price;
            }
        }

        foreach ($stockOutGrouped as $row) {
            $kId = (int) $row->konveksi_id;
            $ensureKonveksi($kId);

            $qty = (int) $row->out_qty;
            $hpp = (float) $row->out_hpp;

            $konveksiMap[$kId]['stock_out_qty'] += $qty;
            $konveksiMap[$kId]['stock_out_hpp'] += $hpp;

            $vId = (int) $row->variant_id;
            $variantDesc = trim(($row->size ?? '') . ' / ' . ($row->color ?? ''), ' /');
            if (!isset($konveksiMap[$kId]['products'][$vId])) {
                $konveksiMap[$kId]['products'][$vId] = [
                    'variant_id' => $vId,
                    'sku' => $row->sku,
                    'product_name' => $row->product_name,
                    'variant' => $variantDesc ?: '-',
                    'unit_price' => (float) $row->unit_price,
                    'in_qty' => 0,
                    'in_hpp' => 0,
                    'out_qty' => 0,
                    'out_hpp' => 0,
                    'sisa_qty' => 0,
                    'sisa_hpp' => 0,
                ];
            }

            $konveksiMap[$kId]['products'][$vId]['out_qty'] += $qty;
            $konveksiMap[$kId]['products'][$vId]['out_hpp'] += $hpp;
        }

        // Calculate Sisa & Liquidation
        foreach ($konveksiMap as $kId => &$kData) {
            $kData['sisa_hutang'] = (float) ($kData['stock_in_hpp'] - $kData['stock_out_hpp']);
            $kData['sisa_qty'] = (int) ($kData['stock_in_qty'] - $kData['stock_out_qty']);
            $kData['liquidation_rate'] = $kData['stock_in_hpp'] > 0
                ? round(($kData['stock_out_hpp'] / $kData['stock_in_hpp']) * 100, 1)
                : 0;

            foreach ($kData['products'] as &$p) {
                $p['sisa_qty'] = (int) ($p['in_qty'] - $p['out_qty']);
                $p['sisa_hpp'] = (float) ($p['in_hpp'] - $p['out_hpp']);
            }
            unset($p);

            uasort($kData['products'], fn($a, $b) => $b['sisa_hpp'] <=> $a['sisa_hpp']);
            $kData['products'] = array_values($kData['products']);
        }
        unset($kData);

        // Search filtering
        $konveksiList = array_values($konveksiMap);
        if ($search) {
            $s = strtolower($search);
            $konveksiList = array_filter($konveksiList, function ($k) use ($s) {
                if (str_contains(strtolower($k['name']), $s)) return true;
                foreach ($k['products'] as $prod) {
                    if (str_contains(strtolower($prod['sku']), $s) || str_contains(strtolower($prod['product_name']), $s)) {
                        return true;
                    }
                }
                return false;
            });
            $konveksiList = array_values($konveksiList);
        }

        // Sort: Konveksi with highest unpaid debt first
        usort($konveksiList, fn($a, $b) => $b['sisa_hutang'] <=> $a['sisa_hutang']);

        $totalInHpp = array_sum(array_column($konveksiList, 'stock_in_hpp'));
        $totalOutHpp = array_sum(array_column($konveksiList, 'stock_out_hpp'));
        $totalSisaHutang = array_sum(array_column($konveksiList, 'sisa_hutang'));
        $totalInQty = array_sum(array_column($konveksiList, 'stock_in_qty'));
        $totalOutQty = array_sum(array_column($konveksiList, 'stock_out_qty'));
        $totalSisaQty = array_sum(array_column($konveksiList, 'sisa_qty'));
        $overallLiquidation = $totalInHpp > 0 ? round(($totalOutHpp / $totalInHpp) * 100, 1) : 0;

        $summary = [
            'total_konveksi' => count($konveksiList),
            'total_stock_in_hpp' => (float) $totalInHpp,
            'total_stock_out_hpp' => (float) $totalOutHpp,
            'total_sisa_hutang' => (float) $totalSisaHutang,
            'total_stock_in_qty' => (int) $totalInQty,
            'total_stock_out_qty' => (int) $totalOutQty,
            'total_sisa_qty' => (int) $totalSisaQty,
            'overall_liquidation' => (float) $overallLiquidation,
        ];

        $seller->loadMissing('brand:id,name');

        return Inertia::render('Superadmin/Finance/SellerDebt/KonveksiDetail', [
            'seller' => $seller,
            'konveksis' => $konveksiList,
            'summary' => $summary,
            'periods' => Period::select('id', 'name')->get(),
            'filters' => [
                'period_id' => $periodId ? (string) $periodId : '',
                'search' => $search ?? '',
            ],
        ]);
    }

    public function storeAdjustment(Request $request)
    {
        $validated = $request->validate([
            'seller_id' => ['required', 'exists:sellers,id'],
            'period_id' => ['nullable', 'exists:periods,id'],
            'amount' => ['required', 'numeric'],
            'adjustment_date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        SellerDebtAdjustment::create([
            'seller_id' => $validated['seller_id'],
            'period_id' => $validated['period_id'] ?? null,
            'amount' => $validated['amount'],
            'date' => $validated['adjustment_date'],
            'reason' => $validated['note'] ?? 'Penyesuaian Saldo Manual',
        ]);

        return redirect()->back()->with('success', 'Penyesuaian saldo hutang seller berhasil disimpan.');
    }

    public function destroyAdjustment(SellerDebtAdjustment $adjustment)
    {
        $adjustment->delete();
        return redirect()->back()->with('success', 'Penyesuaian saldo hutang seller berhasil dihapus.');
    }
}
