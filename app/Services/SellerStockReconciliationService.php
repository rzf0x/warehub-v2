<?php

namespace App\Services;

use App\Models\Seller;
use App\Models\SellerDebtAdjustment;
use App\Models\StockOpname;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SellerStockReconciliationService
{
    public static function extractSkuPrefix(?string $sku): string
    {
        $normalized = strtoupper(trim((string) $sku));
        if ($normalized === '') {
            return 'TANPA PREFIX';
        }

        $parts = preg_split('/[-_\/\s]+/', $normalized, 2);
        $head = $parts[0] ?? '';

        if ($head === '') {
            return 'LAINNYA';
        }

        if (preg_match('/^([A-Z]{2,})/', $head, $matches)) {
            return $matches[1];
        }

        return $head;
    }

    /**
     * Build per-variant debt & stock reconciliation grouped by SKU prefix for a seller.
     *
     * @return array{
     *     groups: array<int, array<string, mixed>>,
     *     available_prefixes: array<int, array<string, mixed>>,
     *     summary: array<string, mixed>,
     *     recent_opnames: array<int, array<string, mixed>>
     * }
     */
    public function buildReport(
        Seller $seller,
        ?int $warehouseId = null,
        ?int $periodId = null,
        ?string $prefixFilter = null,
        ?string $search = null
    ): array {
        $variants = DB::table('product_variants as pv')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->leftJoin('konveksis as k', 'k.id', '=', 'p.konveksi_id')
            ->where('p.seller_id', $seller->id)
            ->where(function ($q) {
                $q->where('p.product_type', '!=', 'bundle')
                    ->orWhereNull('p.product_type');
            })
            ->select([
                'pv.id',
                'pv.product_id',
                'pv.sku',
                'pv.size',
                'pv.color',
                'pv.price',
                'p.product_name',
                'k.name as konveksi_name',
            ])
            ->orderBy('pv.sku')
            ->get();

        $totalAdjustment = (float) SellerDebtAdjustment::query()
            ->where('seller_id', $seller->id)
            ->when($periodId, fn ($q) => $q->where('period_id', $periodId))
            ->sum('amount');

        if ($variants->isEmpty()) {
            return [
                'groups' => [],
                'available_prefixes' => [],
                'summary' => $this->emptySummary($totalAdjustment),
                'recent_opnames' => [],
            ];
        }

        $variantIds = $variants->pluck('id')->all();

        $stockInMap = DB::table('stock_in_items as sii')
            ->join('stock_ins as si', 'si.id', '=', 'sii.stock_in_id')
            ->join('product_variants as pv', 'pv.id', '=', 'sii.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('p.seller_id', $seller->id)
            ->where(function ($q) {
                $q->where('p.product_type', '!=', 'bundle')
                    ->orWhereNull('p.product_type');
            })
            ->when($periodId, fn ($q) => $q->where('si.period_id', $periodId))
            ->selectRaw('pv.id as variant_id, COALESCE(SUM(sii.qty), 0) as in_qty, COALESCE(SUM(sii.qty * sii.price), 0) as in_hpp')
            ->groupBy('pv.id')
            ->get()
            ->keyBy('variant_id');

        $singleOutMap = DB::table('stock_out_items as soi')
            ->join('stock_outs as so', 'so.id', '=', 'soi.stock_out_id')
            ->join('product_variants as pv', 'pv.id', '=', 'soi.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('p.seller_id', $seller->id)
            ->where(function ($q) {
                $q->where('p.product_type', '!=', 'bundle')
                    ->orWhereNull('p.product_type');
            })
            ->when($periodId, fn ($q) => $q->where('so.period_id', $periodId))
            ->selectRaw('pv.id as variant_id, COALESCE(SUM(soi.qty), 0) as out_qty, COALESCE(SUM(soi.qty * soi.price), 0) as out_hpp')
            ->groupBy('pv.id')
            ->get()
            ->keyBy('variant_id');

        $bundleQtyCol = Schema::hasColumn('bundle_items', 'qty') ? 'qty' : 'quantity';

        $bundleOutMap = DB::table('stock_out_items as soi')
            ->join('stock_outs as so', 'so.id', '=', 'soi.stock_out_id')
            ->join('product_variants as bpv', 'bpv.id', '=', 'soi.product_variant_id')
            ->join('products as bp', 'bp.id', '=', 'bpv.product_id')
            ->join('bundle_items as bi', 'bi.bundle_variant_id', '=', 'bpv.id')
            ->join('product_variants as cpv', 'cpv.id', '=', 'bi.component_variant_id')
            ->join('products as cp', 'cp.id', '=', 'cpv.product_id')
            ->where('cp.seller_id', $seller->id)
            ->where('bp.product_type', 'bundle')
            ->when($periodId, fn ($q) => $q->where('so.period_id', $periodId))
            ->selectRaw("cpv.id as variant_id, COALESCE(SUM(soi.qty * bi.{$bundleQtyCol}), 0) as bundle_out_qty, COALESCE(SUM(soi.qty * bi.{$bundleQtyCol} * COALESCE(cpv.price, 0)), 0) as bundle_out_hpp")
            ->groupBy('cpv.id')
            ->get()
            ->keyBy('variant_id');

        $warehouseStockMap = DB::table('stocks')
            ->whereIn('product_variant_id', $variantIds)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->groupBy('product_variant_id')
            ->selectRaw('product_variant_id, COALESCE(SUM(qty), 0) as qty')
            ->pluck('qty', 'product_variant_id');

        $allWarehouseStockMap = DB::table('stocks')
            ->whereIn('product_variant_id', $variantIds)
            ->groupBy('product_variant_id')
            ->selectRaw('product_variant_id, COALESCE(SUM(qty), 0) as qty')
            ->pluck('qty', 'product_variant_id');

        $allRows = [];
        $prefixTotals = [];

        foreach ($variants as $v) {
            $prefix = self::extractSkuPrefix($v->sku);
            $inRow = $stockInMap->get($v->id);
            $singleRow = $singleOutMap->get($v->id);
            $bundleRow = $bundleOutMap->get($v->id);

            $inQty = (int) ($inRow->in_qty ?? 0);
            $inHpp = (float) ($inRow->in_hpp ?? 0);
            $singleOutQty = (int) ($singleRow->out_qty ?? 0);
            $singleOutHpp = (float) ($singleRow->out_hpp ?? 0);
            $bundleOutQty = (int) ($bundleRow->bundle_out_qty ?? 0);
            $bundleOutHpp = (float) ($bundleRow->bundle_out_hpp ?? 0);

            $outQty = $singleOutQty + $bundleOutQty;
            $outHpp = $singleOutHpp + $bundleOutHpp;
            $sisaDebtQty = $inQty - $outQty;
            $sisaDebtHpp = $inHpp - $outHpp;

            $hppPrice = (float) $v->price > 0
                ? (float) $v->price
                : ($inQty > 0 ? round($inHpp / $inQty, 2) : 0.0);

            $warehouseStockQty = (int) ($warehouseStockMap[$v->id] ?? 0);
            $allWarehouseStockQty = (int) ($allWarehouseStockMap[$v->id] ?? 0);
            $stockHppValue = $warehouseStockQty * $hppPrice;
            $qtyGap = $warehouseStockQty - $sisaDebtQty;

            $row = [
                'variant_id' => (int) $v->id,
                'product_id' => (int) $v->product_id,
                'prefix' => $prefix,
                'sku' => (string) $v->sku,
                'product_name' => (string) $v->product_name,
                'size' => (string) ($v->size ?? '-'),
                'color' => (string) ($v->color ?? '-'),
                'konveksi_name' => (string) ($v->konveksi_name ?? '-'),
                'hpp_price' => $hppPrice,
                'in_qty' => $inQty,
                'in_hpp' => $inHpp,
                'single_out_qty' => $singleOutQty,
                'single_out_hpp' => $singleOutHpp,
                'bundle_out_qty' => $bundleOutQty,
                'bundle_out_hpp' => $bundleOutHpp,
                'out_qty' => $outQty,
                'out_hpp' => $outHpp,
                'sisa_debt_qty' => $sisaDebtQty,
                'sisa_debt_hpp' => $sisaDebtHpp,
                'warehouse_stock_qty' => $warehouseStockQty,
                'all_warehouse_stock_qty' => $allWarehouseStockQty,
                'stock_hpp_value' => $stockHppValue,
                'qty_gap' => $qtyGap,
            ];

            $allRows[] = $row;

            if (! isset($prefixTotals[$prefix])) {
                $prefixTotals[$prefix] = [
                    'prefix' => $prefix,
                    'variant_count' => 0,
                    'sisa_debt_qty' => 0,
                    'sisa_debt_hpp' => 0.0,
                    'warehouse_stock_qty' => 0,
                    'non_zero_stock_count' => 0,
                ];
            }

            $prefixTotals[$prefix]['variant_count']++;
            $prefixTotals[$prefix]['sisa_debt_qty'] += $sisaDebtQty;
            $prefixTotals[$prefix]['sisa_debt_hpp'] += $sisaDebtHpp;
            $prefixTotals[$prefix]['warehouse_stock_qty'] += $warehouseStockQty;
            if ($warehouseStockQty !== 0) {
                $prefixTotals[$prefix]['non_zero_stock_count']++;
            }
        }

        $availablePrefixes = array_values($prefixTotals);
        usort($availablePrefixes, function ($a, $b) {
            $cmp = $b['sisa_debt_hpp'] <=> $a['sisa_debt_hpp'];

            return $cmp !== 0 ? $cmp : strcmp($a['prefix'], $b['prefix']);
        });

        $overallNonZeroStockCount = count(array_filter($allRows, fn ($r) => $r['warehouse_stock_qty'] !== 0));
        $overallWarehouseStockQty = array_sum(array_column($allRows, 'warehouse_stock_qty'));

        $normalizedPrefixFilter = $prefixFilter !== null ? strtoupper(trim($prefixFilter)) : '';
        $normalizedSearch = $search !== null ? strtolower(trim($search)) : '';

        $filteredRows = array_values(array_filter($allRows, function ($row) use ($normalizedPrefixFilter, $normalizedSearch) {
            if ($normalizedPrefixFilter !== '' && strtoupper($row['prefix']) !== $normalizedPrefixFilter) {
                return false;
            }

            if ($normalizedSearch !== '') {
                $haystack = strtolower(
                    $row['sku'].' '.$row['product_name'].' '.$row['size'].' '.$row['color'].' '.$row['konveksi_name'].' '.$row['prefix']
                );
                if (! str_contains($haystack, $normalizedSearch)) {
                    return false;
                }
            }

            return true;
        }));

        $groupsMap = [];
        $summary = $this->emptySummary($totalAdjustment);
        $summary['overall_non_zero_stock_count'] = $overallNonZeroStockCount;
        $summary['overall_warehouse_stock_qty'] = $overallWarehouseStockQty;

        foreach ($filteredRows as $row) {
            $prefix = $row['prefix'];
            if (! isset($groupsMap[$prefix])) {
                $groupsMap[$prefix] = [
                    'prefix' => $prefix,
                    'variant_count' => 0,
                    'in_qty' => 0,
                    'in_hpp' => 0.0,
                    'single_out_qty' => 0,
                    'bundle_out_qty' => 0,
                    'out_qty' => 0,
                    'out_hpp' => 0.0,
                    'sisa_debt_qty' => 0,
                    'sisa_debt_hpp' => 0.0,
                    'warehouse_stock_qty' => 0,
                    'all_warehouse_stock_qty' => 0,
                    'non_zero_stock_count' => 0,
                    'stock_hpp_value' => 0.0,
                    'qty_gap' => 0,
                    'variants' => [],
                ];
            }

            $groupsMap[$prefix]['variant_count']++;
            $groupsMap[$prefix]['in_qty'] += $row['in_qty'];
            $groupsMap[$prefix]['in_hpp'] += $row['in_hpp'];
            $groupsMap[$prefix]['single_out_qty'] += $row['single_out_qty'];
            $groupsMap[$prefix]['bundle_out_qty'] += $row['bundle_out_qty'];
            $groupsMap[$prefix]['out_qty'] += $row['out_qty'];
            $groupsMap[$prefix]['out_hpp'] += $row['out_hpp'];
            $groupsMap[$prefix]['sisa_debt_qty'] += $row['sisa_debt_qty'];
            $groupsMap[$prefix]['sisa_debt_hpp'] += $row['sisa_debt_hpp'];
            $groupsMap[$prefix]['warehouse_stock_qty'] += $row['warehouse_stock_qty'];
            $groupsMap[$prefix]['all_warehouse_stock_qty'] += $row['all_warehouse_stock_qty'];
            if ($row['warehouse_stock_qty'] !== 0) {
                $groupsMap[$prefix]['non_zero_stock_count']++;
                $summary['non_zero_stock_count']++;
            }
            $groupsMap[$prefix]['stock_hpp_value'] += $row['stock_hpp_value'];
            $groupsMap[$prefix]['qty_gap'] += $row['qty_gap'];
            $groupsMap[$prefix]['variants'][] = $row;

            $summary['total_variants']++;
            $summary['in_qty'] += $row['in_qty'];
            $summary['in_hpp'] += $row['in_hpp'];
            $summary['single_out_qty'] += $row['single_out_qty'];
            $summary['bundle_out_qty'] += $row['bundle_out_qty'];
            $summary['out_qty'] += $row['out_qty'];
            $summary['out_hpp'] += $row['out_hpp'];
            $summary['sisa_debt_qty'] += $row['sisa_debt_qty'];
            $summary['sisa_debt_hpp'] += $row['sisa_debt_hpp'];
            $summary['warehouse_stock_qty'] += $row['warehouse_stock_qty'];
            $summary['all_warehouse_stock_qty'] += $row['all_warehouse_stock_qty'];
            $summary['stock_hpp_value'] += $row['stock_hpp_value'];
            $summary['qty_gap'] += $row['qty_gap'];
        }

        $groups = array_values($groupsMap);
        usort($groups, function ($a, $b) {
            $cmp = $b['sisa_debt_hpp'] <=> $a['sisa_debt_hpp'];

            return $cmp !== 0 ? $cmp : strcmp($a['prefix'], $b['prefix']);
        });

        $summary['total_prefixes'] = count($groups);
        $summary['net_debt_with_adjustment'] = $summary['sisa_debt_hpp'] + $totalAdjustment;

        $recentOpnames = StockOpname::query()
            ->with(['warehouse:id,name', 'user:id,name'])
            ->withCount('items')
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->whereHas('items.productVariant.product', fn ($q) => $q->where('seller_id', $seller->id))
            ->latest('date')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn ($opname) => [
                'id' => $opname->id,
                'date' => $opname->date ? $opname->date->format('Y-m-d') : '-',
                'warehouse_name' => $opname->warehouse?->name ?? '-',
                'user_name' => $opname->user?->name ?? '-',
                'items_count' => (int) $opname->items_count,
                'note' => $opname->note ?? '-',
            ])
            ->values()
            ->all();

        return [
            'groups' => $groups,
            'available_prefixes' => $availablePrefixes,
            'summary' => $summary,
            'recent_opnames' => $recentOpnames,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySummary(float $totalAdjustment): array
    {
        return [
            'total_variants' => 0,
            'total_prefixes' => 0,
            'in_qty' => 0,
            'in_hpp' => 0.0,
            'single_out_qty' => 0,
            'bundle_out_qty' => 0,
            'out_qty' => 0,
            'out_hpp' => 0.0,
            'sisa_debt_qty' => 0,
            'sisa_debt_hpp' => 0.0,
            'total_adjustment' => $totalAdjustment,
            'net_debt_with_adjustment' => $totalAdjustment,
            'warehouse_stock_qty' => 0,
            'all_warehouse_stock_qty' => 0,
            'non_zero_stock_count' => 0,
            'overall_non_zero_stock_count' => 0,
            'overall_warehouse_stock_qty' => 0,
            'stock_hpp_value' => 0.0,
            'qty_gap' => 0,
        ];
    }
}
