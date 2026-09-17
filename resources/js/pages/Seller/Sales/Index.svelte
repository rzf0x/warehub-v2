<script lang="ts">
    import SellerMobileLayout from '@/layouts/SellerMobileLayout.svelte';
    import BottomSheet from '@/components/seller/BottomSheet.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import { Link, router } from '@inertiajs/svelte';
    import {
        TrendingUp,
        Calendar,
        ChevronRight,
        Coins,
        DollarSign,
        PackageCheck,
        Store,
        Search,
        Trophy,
        ArrowUpRight,
        Filter,
        ChevronDown
    } from '@lucide/svelte';

    interface TransactionItem {
        id: number;
        date: string;
        store_name: string;
        resi_summary: string | null;
        total_qty: number;
        total_omset: number;
        total_hpp: number;
        profit: number;
        items: Array<{
            id: number;
            product_name: string;
            sku: string;
            variant_info: string;
            qty: number;
            price: number;
            selling_price: number;
            total_omset: number;
        }>;
    }

    interface ChartItem {
        date: string;
        label: string;
        omset: number;
    }

    interface PaginatedSales {
        data: TransactionItem[];
        links: any[];
    }

    let {
        metrics = { total_omset: 0, total_hpp: 0, gross_profit: 0, total_qty: 0 },
        sales = { data: [], links: [] },
        chartData = [],
        stores = [],
        filters = { preset: 'this_month', start_date: '', end_date: '', store_id: '', search: '' },
    }: {
        metrics?: { total_omset: number; total_hpp: number; gross_profit: number; total_qty: number };
        sales?: PaginatedSales;
        chartData?: ChartItem[];
        stores?: { id: number; store_name: string; marketplace: string }[];
        filters?: { preset?: string; start_date?: string; end_date?: string; store_id?: string; search?: string };
    } = $props();

    let preset = $state(filters.preset ?? 'this_month');
    let startDate = $state(filters.start_date ?? '');
    let endDate = $state(filters.end_date ?? '');
    let storeId = $state(filters.store_id ?? '');
    let search = $state(filters.search ?? '');

    let showDatePickerSheet = $state(false);
    let expandedTxIds = $state<number[]>([]);

    function formatRupiah(num: number): string {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num || 0);
    }

    function applyFilter(overridePreset?: string) {
        if (overridePreset) preset = overridePreset;
        router.get(
            '/seller/sales',
            { preset, start_date: startDate, end_date: endDate, store_id: storeId, search },
            { preserveState: true, replace: true }
        );
        showDatePickerSheet = false;
    }

    function toggleTxExpand(id: number) {
        if (expandedTxIds.includes(id)) {
            expandedTxIds = expandedTxIds.filter((i) => i !== id);
        } else {
            expandedTxIds = [...expandedTxIds, id];
        }
    }

    let maxOmsetChart = $derived(
        Math.max(...chartData.map((d) => d.omset), 100000)
    );
</script>

<SellerMobileLayout title="Analisis Penjualan Mobile">
    <div class="space-y-4">
        <!-- Header Bar -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-black text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                    <TrendingUp class="h-6 w-6 text-emerald-500" />
                    Analisis Penjualan
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Ringkasan omset sales, profit, & histori resi</p>
            </div>

            <!-- Date Filter Button Trigger -->
            <button
                type="button"
                onclick={() => (showDatePickerSheet = true)}
                class="inline-flex items-center gap-1.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 px-3 py-2 text-xs font-bold text-slate-700 dark:text-slate-200 shadow-xs hover:bg-slate-100 transition-colors"
            >
                <Calendar class="h-4 w-4 text-emerald-500" />
                <span>Filter</span>
            </button>
        </div>

        <!-- Top Products Ranking Quick Banner -->
        <Link
            href="/seller/sales/top-products"
            class="rounded-3xl bg-gradient-to-r from-amber-500 via-orange-500 to-amber-600 p-4 text-white shadow-xl flex items-center justify-between group"
        >
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-white/20 backdrop-blur-md text-amber-100">
                    <Trophy class="h-6 w-6" />
                </div>
                <div>
                    <h3 class="text-sm font-black">Ranking 20 Produk Terlaris 👑</h3>
                    <p class="text-xs text-amber-100/90">Lihat produk best-seller & kontribusi omset</p>
                </div>
            </div>
            <ChevronRight class="h-5 w-5 text-white/80 group-hover:translate-x-1 transition-transform" />
        </Link>

        <!-- Metric Cards Carousel / Grid (4 Cards) -->
        <div class="grid grid-cols-2 gap-3">
            <!-- Card 1: Total Omset Bruto -->
            <div class="rounded-3xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 space-y-1">
                <span class="flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    <DollarSign class="h-3.5 w-3.5 text-indigo-500" />
                    Omset Bruto
                </span>
                <span class="text-sm font-black text-indigo-600 dark:text-indigo-400 block truncate">{formatRupiah(metrics.total_omset)}</span>
                <span class="text-[10px] text-slate-400 font-medium">Penjualan kotor</span>
            </div>

            <!-- Card 2: HPP Modal -->
            <div class="rounded-3xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 space-y-1">
                <span class="flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    <Coins class="h-3.5 w-3.5 text-amber-500" />
                    HPP (Modal)
                </span>
                <span class="text-sm font-black text-amber-600 dark:text-amber-400 block truncate">{formatRupiah(metrics.total_hpp)}</span>
                <span class="text-[10px] text-slate-400 font-medium">Modal barang terjual</span>
            </div>

            <!-- Card 3: Profit Bersih -->
            <div class="rounded-3xl bg-gradient-to-br from-emerald-900 to-teal-950 p-4 text-white shadow-md space-y-1">
                <span class="flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-emerald-200">
                    <ArrowUpRight class="h-3.5 w-3.5 text-emerald-300" />
                    Profit Bersih
                </span>
                <span class="text-sm font-black text-emerald-300 block truncate">{formatRupiah(metrics.gross_profit)}</span>
                <span class="text-[10px] text-emerald-200/80 font-medium">Omset - HPP Modal</span>
            </div>

            <!-- Card 4: Total Volume Qty -->
            <div class="rounded-3xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 space-y-1">
                <span class="flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    <PackageCheck class="h-3.5 w-3.5 text-sky-500" />
                    Volume Qty
                </span>
                <span class="text-base font-black text-slate-800 dark:text-slate-100 block">{metrics.total_qty.toLocaleString('id-ID')} Pcs</span>
                <span class="text-[10px] text-slate-400 font-medium">Barang terkirim</span>
            </div>
        </div>

        <!-- Touch-Responsive Daily Omset Chart -->
        <div class="rounded-3xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Grafik Pergerakan Omset</h3>
                <span class="text-xs font-bold text-slate-500">{startDate} s/d {endDate}</span>
            </div>

            <div class="pt-2 flex items-end justify-between gap-1.5 h-32 overflow-x-auto">
                {#each chartData as c}
                    {@const heightPercent = maxOmsetChart > 0 ? Math.max((c.omset / maxOmsetChart) * 100, 8) : 8}
                    <div class="flex-1 flex flex-col items-center gap-1 group relative min-w-[24px]">
                        <div
                            class="w-full rounded-t-lg bg-emerald-500 group-hover:bg-emerald-600 transition-all relative"
                            style="height: {heightPercent}%;"
                        >
                            <div class="absolute -top-7 left-1/2 -translate-x-1/2 hidden group-hover:block bg-slate-900 text-white text-[9px] font-bold px-1.5 py-0.5 rounded whitespace-nowrap shadow-md z-10">
                                {formatRupiah(c.omset)}
                            </div>
                        </div>
                        <span class="text-[9px] font-semibold text-slate-400 truncate">{c.label}</span>
                    </div>
                {/each}
            </div>
        </div>

        <!-- Search & Filter Controls -->
        <div class="relative">
            <Search class="absolute left-3 top-3 h-4 w-4 text-slate-400" />
            <input
                type="text"
                placeholder="Cari No. Resi / Toko..."
                bind:value={search}
                oninput={() => applyFilter()}
                class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 pl-9 pr-4 py-2.5 text-xs text-slate-800 dark:text-slate-100 shadow-xs focus:ring-2 focus:ring-emerald-500"
            />
        </div>

        <!-- Mobile Sales Transaction Cards List -->
        <div class="space-y-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 px-1">Riwayat Transaksi Keluar</h3>

            <div class="space-y-3">
                {#each sales.data as tx}
                    {@const isExpanded = expandedTxIds.includes(tx.id)}
                    <div class="rounded-3xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 space-y-3">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <Store class="h-3.5 w-3.5 text-indigo-500" />
                                    <span class="text-xs font-extrabold text-slate-800 dark:text-slate-100">{tx.store_name}</span>
                                </div>
                                <p class="text-[11px] font-mono text-slate-400 mt-0.5">Resi: {tx.resi_summary || '-'}</p>
                            </div>

                            <div class="text-right">
                                <span class="text-xs font-black text-emerald-600 dark:text-emerald-400 block">{formatRupiah(tx.total_omset)}</span>
                                <span class="text-[10px] text-slate-400 font-semibold">{tx.date}</span>
                            </div>
                        </div>

                        <!-- Item Count Bar -->
                        <div class="rounded-2xl bg-slate-50 dark:bg-slate-950 p-2.5 flex items-center justify-between text-xs">
                            <span class="text-slate-500">Total Item: <strong>{tx.total_qty} Pcs</strong></span>
                            <span class="text-emerald-600 dark:text-emerald-400 font-bold">Profit: {formatRupiah(tx.profit)}</span>
                        </div>

                        <!-- Expandable Item Breakdown -->
                        <div class="pt-1 flex items-center justify-between">
                            <button
                                type="button"
                                onclick={() => toggleTxExpand(tx.id)}
                                class="inline-flex items-center gap-1 text-xs font-bold text-indigo-600 dark:text-indigo-400"
                            >
                                <span>{isExpanded ? 'Sembunyikan' : 'Rincian Produk'} ({tx.items.length})</span>
                                {#if isExpanded}
                                    <ChevronUp class="h-3.5 w-3.5" />
                                {:else}
                                    <ChevronDown class="h-3.5 w-3.5" />
                                {/if}
                            </button>
                        </div>

                        {#if isExpanded}
                            <div class="pt-2 border-t border-slate-100 dark:border-slate-800 space-y-2 animate-in fade-in duration-150">
                                {#each tx.items as item}
                                    <div class="rounded-xl bg-slate-50 dark:bg-slate-950 p-2.5 text-xs space-y-0.5">
                                        <div class="flex items-center justify-between font-bold text-slate-800 dark:text-slate-100">
                                            <span class="truncate">{item.product_name}</span>
                                            <span class="text-emerald-600 dark:text-emerald-400">{formatRupiah(item.total_omset)}</span>
                                        </div>
                                        <div class="flex items-center justify-between text-[10px] text-slate-400">
                                            <span>SKU: {item.sku} ({item.variant_info})</span>
                                            <span>{item.qty} Pcs × {formatRupiah(item.selling_price)}</span>
                                        </div>
                                    </div>
                                {/each}
                            </div>
                        {/if}
                    </div>
                {:else}
                    <div class="rounded-3xl bg-white dark:bg-slate-900 p-8 text-center text-slate-400 border border-slate-100 dark:border-slate-800 text-xs">
                        Belum ada transaksi penjualan pada rentang tanggal ini.
                    </div>
                {/each}
            </div>
        </div>

        <!-- Pagination -->
        {#if sales.links && sales.links.length > 3}
            <div class="pt-2 flex justify-center">
                <Pagination links={sales.links} />
            </div>
        {/if}
    </div>

    <!-- Date Range Picker Bottom Sheet -->
    <BottomSheet
        show={showDatePickerSheet}
        title="Filter Rentang Tanggal Penjualan"
        onclose={() => (showDatePickerSheet = false)}
    >
        <div class="space-y-4">
            <div class="space-y-2">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400 block">Preset Cepat</span>

                <div class="grid grid-cols-3 gap-2">
                    <button
                        type="button"
                        onclick={() => applyFilter('today')}
                        class="rounded-xl p-3 border text-xs font-bold text-center transition-all
                        {preset === 'today' ? 'border-emerald-600 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300'}"
                    >
                        Hari Ini
                    </button>

                    <button
                        type="button"
                        onclick={() => applyFilter('7_days')}
                        class="rounded-xl p-3 border text-xs font-bold text-center transition-all
                        {preset === '7_days' ? 'border-emerald-600 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300'}"
                    >
                        7 Hari
                    </button>

                    <button
                        type="button"
                        onclick={() => applyFilter('this_month')}
                        class="rounded-xl p-3 border text-xs font-bold text-center transition-all
                        {preset === 'this_month' ? 'border-emerald-600 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300'}"
                    >
                        Bulan Ini
                    </button>
                </div>
            </div>

            <!-- Custom Dates -->
            <div class="space-y-3 pt-2 border-t border-slate-100 dark:border-slate-800">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400 block">Custom Tanggal</span>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label for="f_start_date" class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Dari Tanggal</label>
                        <input
                            id="f_start_date"
                            type="date"
                            bind:value={startDate}
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-emerald-500"
                        />
                    </div>

                    <div>
                        <label for="f_end_date" class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Sampai Tanggal</label>
                        <input
                            id="f_end_date"
                            type="date"
                            bind:value={endDate}
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-emerald-500"
                        />
                    </div>
                </div>

                <div>
                    <label for="f_store_select" class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Filter Toko</label>
                    <select
                        id="f_store_select"
                        bind:value={storeId}
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-emerald-500"
                    >
                        <option value="">Semua Toko Marketplace</option>
                        {#each stores as st}
                            <option value={st.id}>{st.store_name} ({st.marketplace})</option>
                        {/each}
                    </select>
                </div>
            </div>

            <div class="pt-3 flex justify-end">
                <button
                    type="button"
                    onclick={() => applyFilter('custom')}
                    class="w-full rounded-2xl bg-emerald-600 px-6 py-3 text-xs font-bold text-white shadow-lg shadow-emerald-600/30 hover:bg-emerald-700 transition-colors"
                >
                    Terapkan Filter Tanggal
                </button>
            </div>
        </div>
    </BottomSheet>
</SellerMobileLayout>
