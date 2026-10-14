<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import { Link, router } from '@inertiajs/svelte';
    import {
        Calculator,
        ArrowLeft,
        Store,
        Calendar,
        Users,
        Factory,
        Filter,
        RotateCcw,
        Printer,
        FileText,
        ChevronDown,
        ChevronRight,
        Package,
        Wallet,
        ShoppingBag,
        ExternalLink,
        CheckCircle2,
        Tag,
    } from '@lucide/svelte';

    interface KonveksiBreakdown {
        konveksi_id: number;
        name: string;
        pcs: number;
        nominal: number;
    }

    interface ResiInfo {
        total_resi: number;
        label: string;
        preview: string;
        sample_resis: string[];
    }

    interface FakturItem {
        id: number;
        no_faktur: string;
        date: string;
        resi_info?: ResiInfo;
        pcs: number;
        nominal: number;
        note?: string | null;
        konveksis?: KonveksiBreakdown[];
        pdf_url: string;
    }

    interface StoreItem {
        store_id: number | null;
        store_name: string;
        marketplace: string;
        total_pcs: number;
        nominal_pelunasan: number;
        total_fakturs: number;
        konveksis?: KonveksiBreakdown[];
        fakturs: FakturItem[];
    }

    interface SellerItem {
        seller_id: number | null;
        seller_name: string;
        total_pcs: number;
        total_nominal: number;
        konveksis?: KonveksiBreakdown[];
        stores: StoreItem[];
    }

    interface SettlementPeriod {
        period_id: number | null;
        period_name: string;
        start_date: string | null;
        end_date: string | null;
        total_pcs: number;
        total_nominal: number;
        total_fakturs: number;
        konveksis?: KonveksiBreakdown[];
        sellers: SellerItem[];
    }

    interface FilterMaster {
        id: number;
        name?: string;
        seller_name?: string;
        start_date?: string;
        end_date?: string;
    }

    let {
        periods = [],
        sellers = [],
        konveksis = [],
        settlementPeriods = [],
        summary = {
            total_periods: 0,
            grand_total_pcs: 0,
            grand_total_nominal: 0,
            grand_total_fakturs: 0,
        },
        filters = {},
    }: {
        periods?: FilterMaster[];
        sellers?: FilterMaster[];
        konveksis?: FilterMaster[];
        settlementPeriods?: SettlementPeriod[];
        summary?: {
            total_periods: number;
            grand_total_pcs: number;
            grand_total_nominal: number;
            grand_total_fakturs: number;
        };
        filters?: {
            period_id?: number | null;
            seller_id?: number | null;
            konveksi_id?: number | null;
        };
    } = $props();

    // Filters state (reactive with props via $effect)
    let selectedPeriodId = $state<string>('');
    let selectedSellerId = $state<string>('');
    let selectedKonveksiId = $state<string>('');

    $effect(() => {
        selectedPeriodId = filters?.period_id ? String(filters.period_id) : '';
        selectedSellerId = filters?.seller_id ? String(filters.seller_id) : '';
        selectedKonveksiId = filters?.konveksi_id ? String(filters.konveksi_id) : '';
    });

    // Track accordion toggle state for stores' faktur list
    let expandedStores = $state<Record<string, boolean>>({});

    function toggleStoreFakturs(key: string) {
        expandedStores[key] = !expandedStores[key];
    }

    function formatRupiah(num: number): string {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(num);
    }

    function formatNumber(num: number): string {
        return new Intl.NumberFormat('id-ID').format(num);
    }

    function applyFilters() {
        const params = new URLSearchParams();
        if (selectedPeriodId) params.set('period_id', selectedPeriodId);
        if (selectedSellerId) params.set('seller_id', selectedSellerId);
        if (selectedKonveksiId) params.set('konveksi_id', selectedKonveksiId);

        const url = `/superadmin/warehouse/stock-out/settlement${params.toString() ? '?' + params.toString() : ''}`;
        router.get(url, {}, { preserveState: true, replace: true });
    }

    function resetFilters() {
        selectedPeriodId = '';
        selectedSellerId = '';
        selectedKonveksiId = '';
        router.get('/superadmin/warehouse/stock-out/settlement', {}, { preserveState: true, replace: true });
    }

    function printReport() {
        window.print();
    }

    function getMarketplaceBadgeClass(marketplace: string): string {
        const m = (marketplace || '').toUpperCase();
        if (m.includes('SHOPEE')) {
            return 'bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-950/40 dark:text-orange-400 dark:border-orange-800';
        }
        if (m.includes('TIKTOK')) {
            return 'bg-slate-900 text-slate-100 border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:border-slate-600';
        }
        if (m.includes('TOKOPEDIA')) {
            return 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800';
        }
        if (m.includes('LAZADA')) {
            return 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-400 dark:border-blue-800';
        }
        return 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700';
    }
</script>

<svelte:head>
    <title>Settlement & Pelunasan Toko Per-Periode - Warehub v2</title>
</svelte:head>

<AdminLayout
    title="Settlement & Pelunasan Toko Per-Periode"
    breadcrumbs={[
        { name: 'Mutasi Stok' },
        { name: 'Pengeluaran (Stock Out)', url: '/superadmin/warehouse/stock-out' },
        { name: 'Settlement Per-Periode' },
    ]}
>
    <!-- Header Page -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between print:hidden">
        <div class="flex items-center gap-3 sm:gap-4">
            <Link
                href="/superadmin/warehouse/stock-out"
                class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-2.5 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors shadow-xs"
                title="Kembali ke Stock Out"
            >
                <ArrowLeft class="h-5 w-5" />
            </Link>
            <div>
                <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2.5">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-md shadow-emerald-600/30">
                        <Calculator class="h-5 w-5" />
                    </div>
                    Settlement & Pelunasan Toko
                </h1>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                    Rekapitulasi penjualan per periode, seller, toko marketplace, pcs terjual per konveksi, nominal pelunasan, dan nomor faktur.
                </p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-2">
            <button
                type="button"
                onclick={printReport}
                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3.5 py-2.5 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors shadow-xs"
            >
                <Printer class="h-4 w-4 text-slate-500" />
                Cetak / Print
            </button>
        </div>
    </div>

    <!-- Print-only Title -->
    <div class="hidden print:block mb-6 border-b border-slate-300 pb-3">
        <h1 class="text-xl font-bold text-slate-900">Laporan Settlement & Pelunasan Toko Per-Periode</h1>
        <p class="text-xs text-slate-500">Warehub v2 Inventory & Settlement System</p>
    </div>

    <!-- Top Summary Metrics Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
        <!-- 1. Total Periode -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800 shadow-xs">
            <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-1">
                <span class="text-[11px] font-bold uppercase tracking-wider">Periode Aktif</span>
                <div class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400">
                    <Calendar class="h-4 w-4" />
                </div>
            </div>
            <div class="text-xl sm:text-2xl font-extrabold text-slate-800 dark:text-slate-100">
                {summary.total_periods} <span class="text-xs font-medium text-slate-400">Periode</span>
            </div>
            <div class="text-[11px] text-slate-400 mt-0.5">Siklus pembukuan terdata</div>
        </div>

        <!-- 2. Total Pcs Terjual -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800 shadow-xs">
            <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-1">
                <span class="text-[11px] font-bold uppercase tracking-wider">Total Pcs Terjual</span>
                <div class="p-1.5 rounded-lg bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400">
                    <Package class="h-4 w-4" />
                </div>
            </div>
            <div class="text-xl sm:text-2xl font-extrabold text-rose-600 dark:text-rose-400">
                {formatNumber(summary.grand_total_pcs)} <span class="text-xs font-semibold text-rose-500">pcs</span>
            </div>
            <div class="text-[11px] text-slate-400 mt-0.5">Kuantiti produk terkirim</div>
        </div>

        <!-- 3. Total Nominal Pelunasan -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800 shadow-xs">
            <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-1">
                <span class="text-[11px] font-bold uppercase tracking-wider">Nominal Pelunasan</span>
                <div class="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400">
                    <Wallet class="h-4 w-4" />
                </div>
            </div>
            <div class="text-xl sm:text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 truncate">
                {formatRupiah(summary.grand_total_nominal)}
            </div>
            <div class="text-[11px] text-slate-400 mt-0.5">Total pencairan netto</div>
        </div>

        <!-- 4. Total Faktur Dokumen -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800 shadow-xs">
            <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-1">
                <span class="text-[11px] font-bold uppercase tracking-wider">Total Dokumen / Faktur</span>
                <div class="p-1.5 rounded-lg bg-cyan-50 dark:bg-cyan-950/50 text-cyan-600 dark:text-cyan-400">
                    <FileText class="h-4 w-4" />
                </div>
            </div>
            <div class="text-xl sm:text-2xl font-extrabold text-cyan-600 dark:text-cyan-400">
                {formatNumber(summary.grand_total_fakturs)} <span class="text-xs font-medium text-slate-400">Faktur</span>
            </div>
            <div class="text-[11px] text-slate-400 mt-0.5">Batch pengeluaran barang</div>
        </div>
    </div>

    <!-- Filter Bar Component (Periode, Seller, Konveksi) -->
    <div class="mb-6 rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-200 dark:border-slate-800 print:hidden">
        <div class="flex items-center gap-2 mb-3 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            <Filter class="h-4 w-4 text-indigo-500" />
            <span>Filter Laporan Settlement</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            <!-- 1. Filter Periode -->
            <div>
                <label for="filter-period" class="block text-[11px] font-semibold text-slate-600 dark:text-slate-300 mb-1">
                    Periode Pembukuan
                </label>
                <select
                    id="filter-period"
                    bind:value={selectedPeriodId}
                    onchange={applyFilters}
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                >
                    <option value="">Semua Periode</option>
                    {#each periods as p}
                        <option value={p.id}>
                            {p.name} {p.start_date ? `(${p.start_date})` : ''}
                        </option>
                    {/each}
                </select>
            </div>

            <!-- 2. Filter Seller -->
            <div>
                <label for="filter-seller" class="block text-[11px] font-semibold text-slate-600 dark:text-slate-300 mb-1">
                    Mitra / Seller
                </label>
                <select
                    id="filter-seller"
                    bind:value={selectedSellerId}
                    onchange={applyFilters}
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                >
                    <option value="">Semua Seller</option>
                    {#each sellers as s}
                        <option value={s.id}>{s.seller_name}</option>
                    {/each}
                </select>
            </div>

            <!-- 3. Filter Konveksi -->
            <div>
                <label for="filter-konveksi" class="block text-[11px] font-semibold text-slate-600 dark:text-slate-300 mb-1">
                    Konveksi (Vendor Produksi)
                </label>
                <select
                    id="filter-konveksi"
                    bind:value={selectedKonveksiId}
                    onchange={applyFilters}
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                >
                    <option value="">Semua Konveksi</option>
                    {#each konveksis as k}
                        <option value={k.id}>{k.name}</option>
                    {/each}
                </select>
            </div>

            <!-- Reset Button -->
            <div class="flex items-end">
                <button
                    type="button"
                    onclick={resetFilters}
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-100 dark:bg-slate-800 px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors"
                >
                    <RotateCcw class="h-3.5 w-3.5" />
                    Reset Filter
                </button>
            </div>
        </div>
    </div>

    <!-- MAIN HIERARCHICAL CONTENT: GROUPED PER-PERIODE -->
    <div class="space-y-8">
        {#each settlementPeriods as period (period.period_id ?? 'no-period')}
            <div class="rounded-3xl bg-white dark:bg-slate-900 shadow-sm border border-slate-200/90 dark:border-slate-800 overflow-hidden">
                <!-- Section Header: Periode -->
                <div class="bg-gradient-to-r from-slate-900 to-indigo-950 p-5 sm:p-6 text-white flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex items-start sm:items-center gap-3.5">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-indigo-500/20 text-indigo-300 border border-indigo-400/30">
                            <Calendar class="h-6 w-6" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="text-lg sm:text-xl font-bold tracking-tight text-white">
                                    {period.period_name}
                                </h2>
                                {#if period.start_date || period.end_date}
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-500/30 text-indigo-200 border border-indigo-400/20">
                                        {period.start_date ?? '-'} s/d {period.end_date ?? '-'}
                                    </span>
                                {/if}
                            </div>
                            <p class="text-xs text-slate-300 mt-1">
                                Rincian penjualan dan pelunasan seller pada periode pembukuan ini
                            </p>

                            <!-- Konveksi Summary Chips for Period -->
                            {#if period.konveksis && period.konveksis.length > 0}
                                <div class="mt-2.5 flex items-center gap-1.5 flex-wrap">
                                    <span class="text-[10px] text-indigo-300 font-semibold uppercase tracking-wider">Konveksi:</span>
                                    {#each period.konveksis as pk}
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-medium bg-white/10 text-white border border-white/15">
                                            <span>{pk.name}:</span>
                                            <strong class="text-indigo-200">{formatNumber(pk.pcs)} pcs</strong>
                                        </span>
                                    {/each}
                                </div>
                            {/if}
                        </div>
                    </div>

                    <!-- Period Summary Chips -->
                    <div class="flex items-center gap-3 shrink-0 flex-wrap">
                        <div class="rounded-xl bg-white/10 px-3.5 py-2 backdrop-blur-xs border border-white/10 text-right">
                            <div class="text-[10px] uppercase font-bold tracking-wider text-indigo-200">Total Pcs Periode</div>
                            <div class="text-base font-extrabold text-white">
                                {formatNumber(period.total_pcs)} <span class="text-xs font-normal text-indigo-200">pcs</span>
                            </div>
                        </div>
                        <div class="rounded-xl bg-emerald-500/20 px-3.5 py-2 backdrop-blur-xs border border-emerald-400/30 text-right">
                            <div class="text-[10px] uppercase font-bold tracking-wider text-emerald-200">Total Pelunasan</div>
                            <div class="text-base font-extrabold text-emerald-300">
                                {formatRupiah(period.total_nominal)}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sellers List Inside Period -->
                <div class="p-5 sm:p-6 space-y-6">
                    {#each period.sellers as seller (seller.seller_id ?? 'no-seller')}
                        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-950/40 p-4 sm:p-5">
                            <!-- Seller Header Banner -->
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-200/80 dark:border-slate-800">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300 font-bold">
                                        <Users class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Mitra Seller</span>
                                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">
                                            {seller.seller_name}
                                        </h3>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 self-start sm:self-auto text-xs font-semibold">
                                    <span class="px-2.5 py-1 rounded-lg bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                        {formatNumber(seller.total_pcs)} pcs
                                    </span>
                                    <span class="px-2.5 py-1 rounded-lg bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 font-bold">
                                        {formatRupiah(seller.total_nominal)}
                                    </span>
                                </div>
                            </div>

                            <!-- Stores Under Seller -->
                            <div class="mt-4 grid grid-cols-1 gap-4">
                                {#each seller.stores as store, sIdx (store.store_id ?? sIdx)}
                                    {@const storeKey = `${period.period_id}-${seller.seller_id}-${store.store_id ?? sIdx}`}
                                    {@const isExpanded = !!expandedStores[storeKey]}

                                    <div class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-2xs">
                                        <!-- Store Summary Row -->
                                        <div class="p-4 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white dark:bg-slate-900">
                                            <!-- Store Name & Marketplace -->
                                            <div class="flex items-start sm:items-center gap-3">
                                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                                    <Store class="h-5 w-5" />
                                                </div>
                                                <div>
                                                    <div class="flex items-center gap-2 flex-wrap">
                                                        <h4 class="text-sm font-bold text-slate-800 dark:text-slate-100">
                                                            {store.store_name}
                                                        </h4>
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-extrabold border {getMarketplaceBadgeClass(store.marketplace)}">
                                                            {store.marketplace}
                                                        </span>
                                                    </div>
                                                    <p class="text-xs text-slate-400 mt-0.5">
                                                        {store.total_fakturs} transaksi / batch dokumen tercatat
                                                    </p>
                                                </div>
                                            </div>

                                            <!-- Store Metrics & Accordion Toggle -->
                                            <div class="flex items-center gap-4 sm:gap-6 justify-between md:justify-end">
                                                <!-- Pcs Terjual & Konveksi Breakdown -->
                                                <div class="text-left md:text-right">
                                                    <div class="text-[11px] font-medium text-slate-400">Pcs Terjual</div>
                                                    <div class="text-sm sm:text-base font-extrabold text-rose-600 dark:text-rose-400">
                                                        {formatNumber(store.total_pcs)} <span class="text-xs font-medium text-slate-500">pcs</span>
                                                    </div>

                                                    <!-- Detail Breakdown per Konveksi -->
                                                    {#if store.konveksis && store.konveksis.length > 0}
                                                        <div class="mt-1 flex flex-wrap gap-1 md:justify-end">
                                                            {#each store.konveksis as k}
                                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200/80 dark:border-indigo-800">
                                                                    <span class="text-indigo-400">🧵</span>
                                                                    <span>{k.name}:</span>
                                                                    <span class="font-bold">{formatNumber(k.pcs)} pcs</span>
                                                                </span>
                                                            {/each}
                                                        </div>
                                                    {/if}
                                                </div>

                                                <!-- Nominal Pelunasan -->
                                                <div class="text-right">
                                                    <div class="text-[11px] font-medium text-slate-400">Nominal Pelunasan</div>
                                                    <div class="text-sm sm:text-base font-extrabold text-emerald-600 dark:text-emerald-400">
                                                        {formatRupiah(store.nominal_pelunasan)}
                                                    </div>
                                                </div>

                                                <!-- Toggle Button -->
                                                <button
                                                    type="button"
                                                    onclick={() => toggleStoreFakturs(storeKey)}
                                                    class="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 p-2 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                                                    title={isExpanded ? 'Tutup Rincian Faktur' : 'Buka Rincian Faktur'}
                                                >
                                                    <ChevronDown class="h-4 w-4 transition-transform duration-200 {isExpanded ? 'rotate-180' : ''}" />
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Expandable Faktur List Details -->
                                        {#if isExpanded}
                                            <div class="border-t border-slate-100 dark:border-slate-800/80 bg-slate-50/70 dark:bg-slate-950/60 p-4">
                                                <div class="mb-2.5 flex items-center justify-between text-xs font-semibold text-slate-500 dark:text-slate-400">
                                                    <span class="flex items-center gap-1.5">
                                                        <FileText class="h-3.5 w-3.5 text-indigo-500" />
                                                        Rincian Faktur / Dokumen Pengeluaran ({store.fakturs.length})
                                                    </span>
                                                </div>

                                                <div class="overflow-x-auto rounded-xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900">
                                                    <table class="w-full text-left text-xs">
                                                        <thead class="bg-slate-100/70 dark:bg-slate-800/60 uppercase font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-200/80 dark:border-slate-800">
                                                            <tr>
                                                                <th class="px-3.5 py-2.5">No. Faktur</th>
                                                                <th class="px-3.5 py-2.5">Tanggal</th>
                                                                <th class="px-3.5 py-2.5">Ringkasan Resi / Catatan</th>
                                                                <th class="px-3.5 py-2.5 text-right">Pcs Terjual (Detail Konveksi)</th>
                                                                <th class="px-3.5 py-2.5 text-right">Nominal Pelunasan</th>
                                                                <th class="px-3.5 py-2.5 text-center">Surat Jalan</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                                            {#each store.fakturs as faktur}
                                                                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                                                                    <!-- No Faktur -->
                                                                    <td class="px-3.5 py-2.5 font-bold text-indigo-600 dark:text-indigo-400 whitespace-nowrap align-top">
                                                                        {faktur.no_faktur}
                                                                    </td>

                                                                    <!-- Tanggal -->
                                                                    <td class="px-3.5 py-2.5 text-slate-600 dark:text-slate-300 whitespace-nowrap align-top">
                                                                        {faktur.date}
                                                                    </td>

                                                                    <!-- Ringkasan Resi & Catatan (Parsed Cleanly) -->
                                                                    <td class="px-3.5 py-2.5 max-w-xs text-slate-600 dark:text-slate-400 align-top">
                                                                        {#if faktur.resi_info}
                                                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-cyan-50 dark:bg-cyan-950/60 text-cyan-700 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800">
                                                                                    <Package class="h-3 w-3" />
                                                                                    {faktur.resi_info.label}
                                                                                </span>
                                                                            </div>
                                                                            <div class="font-mono text-[11px] text-slate-700 dark:text-slate-300 mt-1 truncate" title={faktur.resi_info.preview}>
                                                                                {faktur.resi_info.preview}
                                                                            </div>
                                                                        {:else}
                                                                            <div class="text-slate-400 text-xs">-</div>
                                                                        {/if}
                                                                        {#if faktur.note}
                                                                            <div class="text-[10px] text-slate-400 mt-0.5 italic truncate" title={faktur.note}>
                                                                                {faktur.note}
                                                                            </div>
                                                                        {/if}
                                                                    </td>

                                                                    <!-- Pcs Terjual & Detail Konveksi -->
                                                                    <td class="px-3.5 py-2.5 text-right whitespace-nowrap align-top">
                                                                        <div class="font-extrabold text-rose-600 dark:text-rose-400 text-xs">
                                                                            {formatNumber(faktur.pcs)} pcs
                                                                        </div>

                                                                        <!-- Breakdown per Konveksi pada Faktur ini -->
                                                                        {#if faktur.konveksis && faktur.konveksis.length > 0}
                                                                            <div class="mt-1 flex flex-col gap-0.5 items-end">
                                                                                {#each faktur.konveksis as fk}
                                                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                                                                        <span>🧵 {fk.name}:</span>
                                                                                        <strong class="text-indigo-600 dark:text-indigo-400 font-bold">{formatNumber(fk.pcs)} pcs</strong>
                                                                                    </span>
                                                                                {/each}
                                                                            </div>
                                                                        {/if}
                                                                    </td>

                                                                    <!-- Nominal Pelunasan -->
                                                                    <td class="px-3.5 py-2.5 text-right font-extrabold text-emerald-600 dark:text-emerald-400 whitespace-nowrap align-top">
                                                                        {formatRupiah(faktur.nominal)}
                                                                    </td>

                                                                    <!-- Surat Jalan Link -->
                                                                    <td class="px-3.5 py-2.5 text-center whitespace-nowrap align-top">
                                                                        <a
                                                                            href={faktur.pdf_url}
                                                                            target="_blank"
                                                                            class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-100 hover:bg-indigo-50 dark:bg-slate-800 dark:hover:bg-indigo-950/60 text-slate-700 hover:text-indigo-600 dark:text-slate-300 dark:hover:text-indigo-400 text-[11px] font-semibold transition-colors"
                                                                            title="Cetak Surat Jalan PDF"
                                                                        >
                                                                            <ExternalLink class="h-3 w-3" />
                                                                            <span>PDF</span>
                                                                        </a>
                                                                    </td>
                                                                </tr>
                                                            {/each}
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        {/if}
                                    </div>
                                {/each}
                            </div>
                        </div>
                    {/each}
                </div>
            </div>
        {:else}
            <!-- Empty State -->
            <div class="rounded-3xl bg-white dark:bg-slate-900 p-12 text-center border border-slate-200 dark:border-slate-800 space-y-4">
                <div class="flex h-16 w-16 mx-auto items-center justify-center rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400">
                    <Store class="h-8 w-8" />
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">
                        Tidak Ada Data Settlement Ditemukan
                    </h3>
                    <p class="text-xs text-slate-400 mt-1 max-w-md mx-auto">
                        Belum ada data transaksi pengeluaran barang (Stock Out) yang cocok dengan filter Periode, Seller, atau Konveksi yang dipilih.
                    </p>
                </div>
                <div class="pt-2">
                    <button
                        type="button"
                        onclick={resetFilters}
                        class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-md shadow-indigo-600/30 hover:bg-indigo-700 transition-colors"
                    >
                        <RotateCcw class="h-4 w-4" />
                        Reset Filter Pencarian
                    </button>
                </div>
            </div>
        {/each}
    </div>
</AdminLayout>
