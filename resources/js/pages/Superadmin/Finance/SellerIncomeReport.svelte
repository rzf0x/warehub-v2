<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import { router } from '@inertiajs/svelte';
    import { PieChart, Printer, Search, Calendar, UserCheck, DollarSign, ArrowUpRight, TrendingUp } from '@lucide/svelte';

    interface ReportRow {
        seller_id: number;
        seller_name: string;
        total_qty_sold: number;
        total_hpp_sold: number;
        total_omset: number;
        gross_profit: number;
        margin_percent: number;
        stock_in_hpp: number;
        net_debt: number;
    }

    let {
        sellers = [],
        periods = [],
        reportData = [],
        summary = { total_sellers: 0, total_qty_sold: 0, total_hpp_sold: 0, total_omset: 0, total_gross_profit: 0 },
        filters = { seller_id: '', period_id: '', start_date: '', end_date: '' },
    }: {
        sellers?: { id: number; seller_name: string }[];
        periods?: { id: number; name: string }[];
        reportData?: ReportRow[];
        summary?: { total_sellers: number; total_qty_sold: number; total_hpp_sold: number; total_omset: number; total_gross_profit: number };
        filters?: { seller_id?: string; period_id?: string; start_date?: string; end_date?: string };
    } = $props();

    let sellerId = $state(filters.seller_id ?? '');
    let periodId = $state(filters.period_id ?? '');
    let startDate = $state(filters.start_date ?? '');
    let endDate = $state(filters.end_date ?? '');

    function formatRupiah(num: number): string {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num || 0);
    }

    function applyFilter() {
        router.get(
            '/superadmin/finance/seller-income-report',
            { seller_id: sellerId, period_id: periodId, start_date: startDate, end_date: endDate },
            { preserveState: true, replace: true }
        );
    }
</script>

<AdminLayout title="Laporan Penghasilan & HPP Seller" breadcrumbs={[{ name: 'Keuangan' }, { name: 'Laporan HPP Seller' }]}>
    <!-- Header -->
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                <PieChart class="h-7 w-7 text-emerald-500" />
                Laporan Penghasilan & Margin HPP Seller
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Ringkasan pendapatan bersih seller, HPP barang terjual, dan persentase margin profit</p>
        </div>

        <div>
            <a
                href="/superadmin/finance/seller-income-report/pdf?seller_id={sellerId}&period_id={periodId}&start_date={startDate}&end_date={endDate}"
                target="_blank"
                class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/30 hover:bg-indigo-700 transition-colors"
            >
                <Printer class="h-4 w-4" />
                Cetak PDF Laporan
            </a>
        </div>
    </div>

    <!-- Summary Stats Grid -->
    <div class="mb-8 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Total Qty Terjual</span>
            <span class="text-2xl font-bold text-slate-800 dark:text-slate-100 mt-1 block">{summary.total_qty_sold} Pcs</span>
        </div>

        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Total HPP Barang Terjual</span>
            <span class="text-xl font-bold text-sky-600 dark:text-sky-400 mt-1 block">{formatRupiah(summary.total_hpp_sold)}</span>
        </div>

        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Total Omset Penjualan</span>
            <span class="text-xl font-bold text-indigo-600 dark:text-indigo-400 mt-1 block">{formatRupiah(summary.total_omset)}</span>
        </div>

        <div class="rounded-2xl bg-gradient-to-br from-emerald-900 to-teal-950 p-5 text-white shadow-md">
            <span class="block text-xs font-semibold uppercase tracking-wider text-emerald-200">Total Laba Kotor (Profit)</span>
            <span class="text-xl font-black text-emerald-300 mt-1 block">{formatRupiah(summary.total_gross_profit)}</span>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="mb-6 rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Filter Seller -->
        <div>
            <label for="f_seller" class="block text-[11px] font-semibold text-slate-400 uppercase mb-1">Mitra / Seller</label>
            <select
                id="f_seller"
                bind:value={sellerId}
                onchange={applyFilter}
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-emerald-500"
            >
                <option value="">Semua Seller</option>
                {#each sellers as s}
                    <option value={s.id}>{s.seller_name}</option>
                {/each}
            </select>
        </div>

        <!-- Filter Period -->
        <div>
            <label for="f_period" class="block text-[11px] font-semibold text-slate-400 uppercase mb-1">Periode Pembukuan</label>
            <select
                id="f_period"
                bind:value={periodId}
                onchange={applyFilter}
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-emerald-500"
            >
                <option value="">Semua Periode</option>
                {#each periods as p}
                    <option value={p.id}>{p.name}</option>
                {/each}
            </select>
        </div>

        <!-- Start Date -->
        <div>
            <label for="f_start" class="block text-[11px] font-semibold text-slate-400 uppercase mb-1">Dari Tanggal</label>
            <input
                id="f_start"
                type="date"
                bind:value={startDate}
                onchange={applyFilter}
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-emerald-500"
            />
        </div>

        <!-- End Date -->
        <div>
            <label for="f_end" class="block text-[11px] font-semibold text-slate-400 uppercase mb-1">Sampai Tanggal</label>
            <input
                id="f_end"
                type="date"
                bind:value={endDate}
                onchange={applyFilter}
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-emerald-500"
            />
        </div>
    </div>

    <!-- Table Card -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-xs border border-slate-100 dark:border-slate-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 dark:bg-slate-950/50 text-slate-500 font-semibold border-b border-slate-100 dark:border-slate-800 uppercase text-[11px] tracking-wider">
                    <tr>
                        <th class="px-6 py-4 w-12">#</th>
                        <th class="px-6 py-4">Seller / Mitra</th>
                        <th class="px-6 py-4 text-center">QTY Terjual</th>
                        <th class="px-6 py-4 text-right">Total HPP Terjual</th>
                        <th class="px-6 py-4 text-right">Total Omset Penjualan</th>
                        <th class="px-6 py-4 text-right">Laba Kotor (Profit)</th>
                        <th class="px-6 py-4 text-center">% Margin</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    {#each reportData as item, index}
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="px-6 py-4 text-xs text-slate-400">{index + 1}</td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                                    <UserCheck class="h-4 w-4 text-emerald-500" />
                                    <span>{item.seller_name}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center font-bold text-slate-800 dark:text-slate-100">{item.total_qty_sold} Pcs</td>
                            <td class="px-6 py-4 text-right font-medium text-sky-600 dark:text-sky-400">{formatRupiah(item.total_hpp_sold)}</td>
                            <td class="px-6 py-4 text-right font-medium text-indigo-600 dark:text-indigo-400">{formatRupiah(item.total_omset)}</td>
                            <td class="px-6 py-4 text-right font-black text-emerald-600 dark:text-emerald-400 text-base">{formatRupiah(item.gross_profit)}</td>
                            <td class="px-6 py-4 text-center font-bold">
                                <span class="rounded-full bg-emerald-50 dark:bg-emerald-950/60 px-2.5 py-1 text-xs text-emerald-700 dark:text-emerald-300 border border-emerald-200">
                                    {item.margin_percent}%
                                </span>
                            </td>
                        </tr>
                    {:else}
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-400">Belum ada data laporan penghasilan seller.</td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    </div>
</AdminLayout>
