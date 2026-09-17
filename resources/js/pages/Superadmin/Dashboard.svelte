<script lang="ts">
    import AdminLayout from '@/Layouts/AdminLayout.svelte';
    import Badge from '@/Components/Badge.svelte';
    import { router } from '@inertiajs/svelte';
    import {
        Calendar,
        ArrowDownLeft,
        ArrowUpRight,
        Boxes,
        FileText,
        Store as StoreIcon,
        TrendingDown,
        TrendingUp,
        Minus,
        ChevronDown,
        Filter,
        DollarSign,
        Activity,
        Sparkles,
        BarChart3,
        Building2,
        Package
    } from '@lucide/svelte';

    interface PeriodItem {
        id: number;
        name: string;
        start_date: string;
        end_date: string;
        is_public: boolean;
    }

    interface Metrics {
        stock_in_qty: number;
        stock_in_hpp: number;
        stock_in_nota: number;
        stock_out_qty: number;
        stock_out_hpp: number;
        stock_out_omset: number;
        stock_out_nota: number;
        net_movement_qty: number;
        total_physical_stock: number;
    }

    interface StockInItem {
        id: number;
        invoice_number: string;
        date: string;
        warehouse_name: string;
        qty: number;
        total_hpp: number;
    }

    interface StockOutItem {
        id: number;
        store_name: string;
        marketplace: string;
        date: string;
        warehouse_name: string;
        qty: number;
        total_omset: number;
    }

    interface FinancialChart {
        id: number;
        period_name: string;
        total_hpp: number;
        total_omset: number;
        est_profit: number;
    }

    let {
        allPeriods = [],
        selectedPeriod = null,
        metrics = {
            stock_in_qty: 0,
            stock_in_hpp: 0,
            stock_in_nota: 0,
            stock_out_qty: 0,
            stock_out_hpp: 0,
            stock_out_omset: 0,
            stock_out_nota: 0,
            net_movement_qty: 0,
            total_physical_stock: 0,
        },
        recentStockIns = [],
        recentStockOuts = [],
        financialCharts = [],
    }: {
        allPeriods?: PeriodItem[];
        selectedPeriod?: PeriodItem | null;
        metrics?: Metrics;
        recentStockIns?: StockInItem[];
        recentStockOuts?: StockOutItem[];
        financialCharts?: FinancialChart[];
    } = $props();

    let currentPeriodId = $state(selectedPeriod?.id ?? (allPeriods[0]?.id ?? ''));

    function handlePeriodChange(event: Event) {
        const target = event.target as HTMLSelectElement;
        const periodId = target.value;
        currentPeriodId = periodId;
        router.get(
            '/superadmin/dashboard',
            { period_id: periodId },
            { preserveState: true, preserveScroll: true }
        );
    }

    function formatRupiah(num: number): string {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(num || 0);
    }

    function formatNumber(num: number): string {
        return new Intl.NumberFormat('id-ID').format(num || 0);
    }

    // Chart scaling
    let maxFinancialVal = $derived(
        Math.max(...financialCharts.flatMap(f => [f.total_omset, f.total_hpp]), 1000000)
    );
</script>

<AdminLayout title="Dashboard Ringkasan Stok" breadcrumbs={[{ name: 'Dashboard' }]}>
    <!-- Page Header & Period Selector Banner -->
    <div class="mb-8 rounded-3xl bg-slate-900 p-6 md:p-8 text-white shadow-xl relative overflow-hidden border border-slate-800">
        <!-- Background Glow Accent -->
        <div class="absolute -top-24 -right-24 h-72 w-72 rounded-full bg-indigo-600/20 blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-20 -left-20 h-64 w-64 rounded-full bg-emerald-600/15 blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="px-3 py-1 rounded-full bg-indigo-500/20 border border-indigo-400/30 text-indigo-300 text-xs font-semibold tracking-wide uppercase">
                        WareHub Logistics V2
                    </span>
                    {#if selectedPeriod?.is_public}
                        <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 text-[11px] font-bold border border-emerald-500/30">
                            [AKTIF]
                        </span>
                    {/if}
                </div>
                <h1 class="text-3xl font-extrabold tracking-tight text-white">Dashboard Ringkasan Stok</h1>
                <p class="text-slate-400 text-sm mt-1">Pusat kontrol logistik & perputaran stok pergudangan WareHub.</p>
            </div>

            <!-- Tampilan Periode Selector -->
            <div class="bg-slate-800/90 backdrop-blur-md p-4 rounded-2xl border border-slate-700/80 shadow-inner flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="flex items-center gap-2 text-slate-300 text-xs font-semibold uppercase tracking-wider">
                    <Calendar class="h-4 w-4 text-indigo-400 shrink-0" />
                    <span>Tampilan Periode:</span>
                </div>
                <select
                    value={currentPeriodId}
                    onchange={handlePeriodChange}
                    class="bg-slate-900 border border-slate-700 text-white text-sm font-semibold rounded-xl px-3.5 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all cursor-pointer min-w-[240px]"
                >
                    {#each allPeriods as p}
                        <option value={p.id}>
                            {p.name} {p.is_public ? '[AKTIF]' : ''}
                        </option>
                    {/each}
                </select>
            </div>
        </div>

        {#if selectedPeriod}
            <div class="mt-4 pt-4 border-t border-slate-800/80 flex flex-wrap items-center gap-4 text-xs text-slate-400">
                <span class="flex items-center gap-1.5 font-medium text-slate-300">
                    <Calendar class="h-3.5 w-3.5 text-indigo-400" />
                    Periode Penarikan: <strong class="text-white">{selectedPeriod.name}</strong> ({selectedPeriod.start_date} - {selectedPeriod.end_date})
                </span>
            </div>
        {/if}
    </div>

    <!-- 4 Key Summary Metric Cards -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        <!-- 1. Barang Masuk (Stock In) -->
        <div class="relative overflow-hidden rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm border border-slate-100 dark:border-slate-800 transition-all hover:shadow-md hover:border-slate-200">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Barang Masuk (Stock In)</span>
                    <h3 class="text-3xl font-extrabold text-slate-900 dark:text-slate-100 mt-2">
                        {formatNumber(metrics.stock_in_qty)} <span class="text-sm font-semibold text-slate-400">pcs</span>
                    </h3>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400">
                    <ArrowDownLeft class="h-6 w-6" />
                </div>
            </div>
            
            <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800/60 flex flex-wrap gap-2 text-xs">
                <span class="px-2.5 py-1 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold">
                    Valuasi HPP: <strong class="text-emerald-600 dark:text-emerald-400">{formatRupiah(metrics.stock_in_hpp)}</strong>
                </span>
                <span class="px-2 py-1 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-medium">
                    {metrics.stock_in_nota} Nota
                </span>
            </div>
        </div>

        <!-- 2. Barang Keluar (Stock Out) -->
        <div class="relative overflow-hidden rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm border border-slate-100 dark:border-slate-800 transition-all hover:shadow-md hover:border-slate-200">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Barang Keluar (Stock Out)</span>
                    <h3 class="text-3xl font-extrabold text-slate-900 dark:text-slate-100 mt-2">
                        {formatNumber(metrics.stock_out_qty)} <span class="text-sm font-semibold text-slate-400">pcs</span>
                    </h3>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-500/10 text-rose-600 dark:bg-rose-950/50 dark:text-rose-400">
                    <ArrowUpRight class="h-6 w-6" />
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800/60 space-y-1.5 text-xs">
                <div class="flex items-center justify-between text-slate-600 dark:text-slate-400 font-medium">
                    <span>HPP: <strong class="text-slate-800 dark:text-slate-200">{formatRupiah(metrics.stock_out_hpp)}</strong></span>
                    <span class="px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-500">{metrics.stock_out_nota} Nota</span>
                </div>
                <div class="flex items-center justify-between text-emerald-600 dark:text-emerald-400 font-semibold">
                    <span>Omset Jual:</span>
                    <span>{formatRupiah(metrics.stock_out_omset)}</span>
                </div>
            </div>
        </div>

        <!-- 3. Net Stock Movement -->
        <div class="relative overflow-hidden rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm border border-slate-100 dark:border-slate-800 transition-all hover:shadow-md hover:border-slate-200">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Net Stock Movement</span>
                    <h3 class="text-3xl font-extrabold mt-2 {metrics.net_movement_qty < 0 ? 'text-rose-600 dark:text-rose-400' : (metrics.net_movement_qty > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-800 dark:text-slate-100')}">
                        {metrics.net_movement_qty > 0 ? '+' : ''}{formatNumber(metrics.net_movement_qty)} <span class="text-sm font-semibold text-slate-400">pcs</span>
                    </h3>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl {metrics.net_movement_qty < 0 ? 'bg-rose-500/10 text-rose-600' : 'bg-emerald-500/10 text-emerald-600'}">
                    {#if metrics.net_movement_qty < 0}
                        <TrendingDown class="h-6 w-6" />
                    {:else if metrics.net_movement_qty > 0}
                        <TrendingUp class="h-6 w-6" />
                    {:else}
                        <Minus class="h-6 w-6" />
                    {/if}
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800/60 flex items-center gap-1.5 text-xs font-medium">
                {#if metrics.net_movement_qty < 0}
                    <span class="inline-flex items-center gap-1 text-rose-600 dark:text-rose-400 font-semibold">
                        <TrendingDown class="h-3.5 w-3.5" /> Stok gudang menyusut periode ini
                    </span>
                {:else if metrics.net_movement_qty > 0}
                    <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-semibold">
                        <TrendingUp class="h-3.5 w-3.5" /> Stok gudang bertambah periode ini
                    </span>
                {:else}
                    <span class="text-slate-500">Stok gudang stabil periode ini</span>
                {/if}
            </div>
        </div>

        <!-- 4. Total Stok Fisik Aktif -->
        <div class="relative overflow-hidden rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm border border-slate-100 dark:border-slate-800 transition-all hover:shadow-md hover:border-slate-200">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Stok Fisik Aktif</span>
                    <h3 class="text-3xl font-extrabold text-indigo-600 dark:text-indigo-400 mt-2">
                        {formatNumber(metrics.total_physical_stock)} <span class="text-sm font-semibold text-slate-400">pcs</span>
                    </h3>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-500/10 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-400">
                    <Boxes class="h-6 w-6" />
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800/60 text-xs text-slate-500 font-medium">
                Total fisik riil tersimpan di semua gudang
            </div>
        </div>
    </div>

    <!-- Recent Stock In & Stock Out Tables Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Barang Masuk Terbaru (Stock In) -->
        <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm border border-slate-100 dark:border-slate-800 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-5 pb-4 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400">
                            <ArrowDownLeft class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Barang Masuk Terbaru (Stock In)</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Riwayat penerimaan stok masuk pergudangan</p>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-400 font-semibold uppercase tracking-wider">
                                <th class="pb-3 px-2">No. Faktur</th>
                                <th class="pb-3 px-2">Tanggal</th>
                                <th class="pb-3 px-2">Gudang</th>
                                <th class="pb-3 px-2 text-right">Qty</th>
                                <th class="pb-3 px-2 text-right">Total HPP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 text-slate-700 dark:text-slate-300">
                            {#each recentStockIns as item}
                                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                                    <td class="py-3 px-2 font-bold text-slate-900 dark:text-slate-100">
                                        {item.invoice_number}
                                    </td>
                                    <td class="py-3 px-2 text-slate-500">{item.date}</td>
                                    <td class="py-3 px-2 font-medium">{item.warehouse_name}</td>
                                    <td class="py-3 px-2 text-right font-bold text-emerald-600 dark:text-emerald-400">
                                        {formatNumber(item.qty)} pcs
                                    </td>
                                    <td class="py-3 px-2 text-right font-semibold">
                                        {formatRupiah(item.total_hpp)}
                                    </td>
                                </tr>
                            {:else}
                                <tr>
                                    <td colspan="5" class="py-10 text-center text-slate-400 text-sm">
                                        Belum ada transaksi barang masuk pada periode ini.
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Barang Keluar Terbaru (Stock Out) -->
        <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 shadow-sm border border-slate-100 dark:border-slate-800 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-5 pb-4 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-500/10 text-rose-600 dark:bg-rose-950/50 dark:text-rose-400">
                            <ArrowUpRight class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Barang Keluar Terbaru (Stock Out)</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Riwayat pengeluaran stok per toko & outlet</p>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-400 font-semibold uppercase tracking-wider">
                                <th class="pb-3 px-2">Toko / Outlet</th>
                                <th class="pb-3 px-2">Tanggal</th>
                                <th class="pb-3 px-2">Gudang</th>
                                <th class="pb-3 px-2 text-right">Qty</th>
                                <th class="pb-3 px-2 text-right">Total Omset</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 text-slate-700 dark:text-slate-300">
                            {#each recentStockOuts as item}
                                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                                    <td class="py-3 px-2">
                                        <div class="font-bold text-slate-900 dark:text-slate-100">{item.store_name}</div>
                                        <span class="inline-block mt-0.5 px-2 py-0.5 text-[10px] font-bold rounded-md uppercase tracking-wider 
                                            {item.marketplace === 'TIKTOK' ? 'bg-pink-100 text-pink-700 dark:bg-pink-950/60 dark:text-pink-300' : 
                                            (item.marketplace === 'SHOPEE' ? 'bg-orange-100 text-orange-700 dark:bg-orange-950/60 dark:text-orange-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300')}">
                                            {item.marketplace}
                                        </span>
                                    </td>
                                    <td class="py-3 px-2 text-slate-500">{item.date}</td>
                                    <td class="py-3 px-2 font-medium">{item.warehouse_name}</td>
                                    <td class="py-3 px-2 text-right font-bold text-rose-600 dark:text-rose-400">
                                        {formatNumber(item.qty)} pcs
                                    </td>
                                    <td class="py-3 px-2 text-right font-semibold text-emerald-600 dark:text-emerald-400">
                                        {formatRupiah(item.total_omset)}
                                    </td>
                                </tr>
                            {:else}
                                <tr>
                                    <td colspan="5" class="py-10 text-center text-slate-400 text-sm">
                                        Belum ada transaksi barang keluar pada periode ini.
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Financial Trend Visualization per Period -->
    <div class="rounded-3xl bg-white dark:bg-slate-900 p-6 md:p-8 shadow-sm border border-slate-100 dark:border-slate-800">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h3 class="text-lg font-extrabold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    <BarChart3 class="h-5 w-5 text-indigo-600" />
                    Tren Keuangan: HPP, Omset & Profit per Periode
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Perbandingan HPP modal, total omset penjualan, dan estimasi profit antar periode penarikan</p>
            </div>
            <div class="flex items-center gap-4 text-xs font-semibold">
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-slate-400"></span> Total HPP</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-emerald-500"></span> Total Omset</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-indigo-500"></span> Est. Profit</span>
            </div>
        </div>

        <!-- Financial Chart Bars -->
        <div class="h-72 flex items-end justify-between gap-4 pt-10 px-4 border-b border-slate-100 dark:border-slate-800 overflow-x-auto">
            {#each financialCharts as f}
                <div class="flex-1 min-w-[70px] flex flex-col items-center gap-2 h-full justify-end group relative">
                    <div class="w-full flex items-end justify-center gap-1 h-full">
                        <!-- HPP Bar -->
                        <div 
                            style="height: {Math.max((f.total_hpp / maxFinancialVal) * 100, 4)}%;"
                            class="w-1/3 bg-slate-300 dark:bg-slate-700 rounded-t-md transition-all group-hover:bg-slate-400"
                            title="{f.period_name} | HPP: {formatRupiah(f.total_hpp)}"
                        ></div>
                        <!-- Omset Bar -->
                        <div 
                            style="height: {Math.max((f.total_omset / maxFinancialVal) * 100, 4)}%;"
                            class="w-1/3 bg-emerald-500 rounded-t-md transition-all group-hover:bg-emerald-600"
                            title="{f.period_name} | Omset: {formatRupiah(f.total_omset)}"
                        ></div>
                        <!-- Profit Bar -->
                        <div 
                            style="height: {Math.max((Math.max(f.est_profit, 0) / maxFinancialVal) * 100, 4)}%;"
                            class="w-1/3 bg-indigo-600 rounded-t-md transition-all group-hover:bg-indigo-700"
                            title="{f.period_name} | Est. Profit: {formatRupiah(f.est_profit)}"
                        ></div>
                    </div>

                    <!-- Label -->
                    <span class="text-[11px] text-slate-500 font-semibold truncate max-w-[80px] text-center" title={f.period_name}>
                        {f.period_name.replace('Periode Penarikan', '').trim()}
                    </span>
                </div>
            {/each}
        </div>
    </div>
</AdminLayout>
