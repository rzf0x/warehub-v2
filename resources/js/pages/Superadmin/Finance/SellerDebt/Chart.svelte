<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import { router, Link } from '@inertiajs/svelte';
    import { BarChart3, ArrowLeft, UserCheck, Wallet, DollarSign } from '@lucide/svelte';

    interface ChartItem {
        seller_id: number;
        seller_name: string;
        stock_in_hpp: number;
        stock_out_hpp: number;
        net_debt: number;
    }

    let {
        sellers = [],
        chartData = [],
        selectedSellerId = '',
    }: {
        sellers?: { id: number; seller_name: string }[];
        chartData?: ChartItem[];
        selectedSellerId?: number | string;
    } = $props();

    let sellerId = $state(selectedSellerId ?? '');

    function formatRupiah(num: number): string {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num || 0);
    }

    function applyFilter() {
        router.get('/superadmin/finance/seller-debt/chart', { seller_id: sellerId }, { preserveState: true, replace: true });
    }

    let maxNominal = $derived(
        Math.max(...chartData.map((d) => Math.max(d.stock_in_hpp, d.stock_out_hpp, Math.abs(d.net_debt))), 1)
    );
</script>

<AdminLayout title="Grafik Analisis Hutang Seller" breadcrumbs={[{ name: 'Keuangan' }, { name: 'Hutang Seller', href: '/superadmin/finance/seller-debt' }, { name: 'Grafik Analisis' }]}>
    <!-- Header -->
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <Link
                href="/superadmin/finance/seller-debt"
                class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-2.5 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors shadow-xs"
            >
                <ArrowLeft class="h-5 w-5" />
            </Link>
            <div>
                <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                    <BarChart3 class="h-7 w-7 text-purple-500" />
                    Grafik Analisis Hutang Seller
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Visualisasi perbandingan Stock In HPP vs Stock Out HPP per seller</p>
            </div>
        </div>

        <div class="w-full sm:w-64">
            <select
                bind:value={sellerId}
                onchange={applyFilter}
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3.5 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-purple-500"
            >
                <option value="">Semua Seller (Perbandingan)</option>
                {#each sellers as s}
                    <option value={s.id}>{s.seller_name}</option>
                {/each}
            </select>
        </div>
    </div>

    <!-- Chart Visualization Cards -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-100 dark:border-slate-800 space-y-6">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <Wallet class="h-5 w-5 text-purple-500" />
                Perbandingan Nominal per Seller
            </h3>

            <div class="flex items-center gap-4 text-xs font-semibold">
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-sky-500 inline-block"></span> Stock In HPP</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-emerald-500 inline-block"></span> Stock Out HPP</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-amber-500 inline-block"></span> Sisa Hutang Net</span>
            </div>
        </div>

        <div class="space-y-6">
            {#each chartData as item}
                {@const inPercent = (item.stock_in_hpp / maxNominal) * 100}
                {@const outPercent = (item.stock_out_hpp / maxNominal) * 100}
                {@const debtPercent = (Math.max(0, item.net_debt) / maxNominal) * 100}

                <div class="space-y-2 p-4 rounded-xl bg-slate-50/70 dark:bg-slate-950/60 border border-slate-200/60 dark:border-slate-800/60">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-sm text-slate-800 dark:text-slate-100 flex items-center gap-2">
                            <UserCheck class="h-4 w-4 text-purple-500" />
                            {item.seller_name}
                        </span>
                        <span class="text-xs font-bold text-amber-600 dark:text-amber-400">Net Hutang: {formatRupiah(item.net_debt)}</span>
                    </div>

                    <!-- Bars -->
                    <div class="space-y-2 pt-1">
                        <!-- Stock In Bar -->
                        <div class="space-y-1">
                            <div class="flex justify-between text-[11px] font-semibold text-slate-500">
                                <span>Stock In HPP</span>
                                <span class="text-sky-600 dark:text-sky-400">{formatRupiah(item.stock_in_hpp)}</span>
                            </div>
                            <div class="h-3 w-full rounded-full bg-slate-200 dark:bg-slate-800 overflow-hidden">
                                <div class="h-full bg-sky-500 rounded-full transition-all duration-500" style="width: {Math.max(inPercent, 2)}%"></div>
                            </div>
                        </div>

                        <!-- Stock Out Bar -->
                        <div class="space-y-1">
                            <div class="flex justify-between text-[11px] font-semibold text-slate-500">
                                <span>Stock Out HPP</span>
                                <span class="text-emerald-600 dark:text-emerald-400">{formatRupiah(item.stock_out_hpp)}</span>
                            </div>
                            <div class="h-3 w-full rounded-full bg-slate-200 dark:bg-slate-800 overflow-hidden">
                                <div class="h-full bg-emerald-500 rounded-full transition-all duration-500" style="width: {Math.max(outPercent, 2)}%"></div>
                            </div>
                        </div>

                        <!-- Net Debt Bar -->
                        <div class="space-y-1">
                            <div class="flex justify-between text-[11px] font-semibold text-slate-500">
                                <span>Sisa Net Saldo Hutang</span>
                                <span class="text-amber-600 dark:text-amber-400">{formatRupiah(item.net_debt)}</span>
                            </div>
                            <div class="h-3 w-full rounded-full bg-slate-200 dark:bg-slate-800 overflow-hidden">
                                <div class="h-full bg-amber-500 rounded-full transition-all duration-500" style="width: {Math.max(debtPercent, 2)}%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            {:else}
                <div class="py-12 text-center text-slate-400">Tidak ada data analisis grafik.</div>
            {/each}
        </div>
    </div>
</AdminLayout>
