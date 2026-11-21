<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import { router, Link } from '@inertiajs/svelte';
    import {
        ArrowLeft,
        Factory,
        Search,
        Calendar,
        Layers,
        TrendingUp,
        Package,
        CheckCircle2,
        AlertCircle,
        X,
        ChevronDown,
        ChevronUp,
        Copy,
        Check,
        Phone,
        RotateCcw,
        DollarSign
    } from '@lucide/svelte';

    interface ProductItem {
        variant_id: number;
        sku: string;
        product_name: string;
        variant: string;
        unit_price: number;
        in_qty: number;
        in_hpp: number;
        out_qty: number;
        out_hpp: number;
        sisa_qty: number;
        sisa_hpp: number;
    }

    interface KonveksiItem {
        konveksi_id: number;
        name: string;
        contact: string | null;
        color: string;
        stock_in_qty: number;
        stock_in_hpp: number;
        stock_out_qty: number;
        stock_out_hpp: number;
        sisa_hutang: number;
        sisa_qty: number;
        liquidation_rate: number;
        products: ProductItem[];
    }

    interface SellerDetail {
        id: number;
        uuid?: string;
        seller_name: string;
        phone: string | null;
        address: string | null;
        brand?: { id: number; name: string } | null;
    }

    interface SummaryData {
        total_konveksi: number;
        total_stock_in_hpp: number;
        total_stock_out_hpp: number;
        total_sisa_hutang: number;
        total_stock_in_qty: number;
        total_stock_out_qty: number;
        total_sisa_qty: number;
        overall_liquidation: number;
    }

    let {
        seller,
        konveksis = [],
        summary = {
            total_konveksi: 0,
            total_stock_in_hpp: 0,
            total_stock_out_hpp: 0,
            total_sisa_hutang: 0,
            total_stock_in_qty: 0,
            total_stock_out_qty: 0,
            total_sisa_qty: 0,
            overall_liquidation: 0,
        },
        periods = [],
        filters = { period_id: '', search: '' },
    }: {
        seller: SellerDetail;
        konveksis: KonveksiItem[];
        summary: SummaryData;
        periods?: { id: number; name: string }[];
        filters?: { period_id?: string; search?: string };
    } = $props();

    let periodId = $state(filters.period_id ?? '');
    let searchQuery = $state(filters.search ?? '');

    // Track which konveksi cards have their SKU breakdown expanded
    let expandedKonveksis = $state<Record<number, boolean>>({});

    let copiedSummary = $state(false);

    function toggleExpand(id: number) {
        expandedKonveksis[id] = !expandedKonveksis[id];
    }

    function expandAll() {
        const next: Record<number, boolean> = {};
        konveksis.forEach((k) => (next[k.konveksi_id] = true));
        expandedKonveksis = next;
    }

    function collapseAll() {
        expandedKonveksis = {};
    }

    let searchTimer: any;
    function handleSearchInput() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            applyFilter();
        }, 400);
    }

    function applyFilter() {
        router.get(
            `/superadmin/finance/seller-debt/${seller.uuid || seller.id}/konveksi`,
            { period_id: periodId, search: searchQuery },
            { preserveState: true, replace: true }
        );
    }

    function resetFilter() {
        periodId = '';
        searchQuery = '';
        router.get(`/superadmin/finance/seller-debt/${seller.uuid || seller.id}/konveksi`, {}, { replace: true });
    }

    function formatRupiah(num: number): string {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num || 0);
    }

    function copySummaryText() {
        const periodName = periods.find((p) => String(p.id) === String(periodId))?.name ?? 'Semua Periode';
        let text = `RINCIAN SISA HUTANG MODAL PER KONVEKSI\n` +
            `Mitra / Seller: ${seller.seller_name}\n` +
            `Periode: ${periodName}\n` +
            `Total Sisa Hutang: ${formatRupiah(summary.total_sisa_hutang)}\n` +
            `----------------------------------------\n`;

        konveksis.forEach((k, idx) => {
            text += `${idx + 1}. ${k.name}\n` +
                `   - Masuk: ${formatRupiah(k.stock_in_hpp)} (${k.stock_in_qty} pcs)\n` +
                `   - Terjual: ${formatRupiah(k.stock_out_hpp)} (${k.stock_out_qty} pcs)\n` +
                `   - Sisa Hutang: ${formatRupiah(k.sisa_hutang)} (Likuidasi: ${k.liquidation_rate}%)\n\n`;
        });

        navigator.clipboard.writeText(text);
        copiedSummary = true;
        setTimeout(() => (copiedSummary = false), 2000);
    }

    const backToDetailUrl = $derived(
        `/superadmin/finance/seller-debt/${seller.uuid || seller.id}${periodId ? `?period_id=${periodId}` : ''}`
    );
</script>

<AdminLayout
    title="Detail Debt Konveksi - {seller.seller_name}"
    breadcrumbs={[
        { name: 'Keuangan' },
        { name: 'Hutang Seller', url: '/superadmin/finance/seller-debt' },
        { name: seller.seller_name, url: `/superadmin/finance/seller-debt/${seller.uuid || seller.id}` },
        { name: 'Detail Debt Konveksi' },
    ]}
>
    <!-- Top Identity & Info Banner -->
    <div class="mb-6 rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-200/80 dark:border-slate-800">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
            <!-- Left: Back Button + Seller Identity -->
            <div class="flex items-start gap-4">
                <Link
                    href={backToDetailUrl}
                    class="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 p-2.5 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors shadow-xs shrink-0"
                    title="Kembali ke Rekening Koran Seller"
                >
                    <ArrowLeft class="h-5 w-5" />
                </Link>

                <div class="space-y-1.5">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1 class="text-2xl font-extrabold text-slate-900 dark:text-slate-50 tracking-tight flex items-center gap-2">
                            <Factory class="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                            Detail Debt Konveksi - {seller.seller_name}
                        </h1>
                        {#if seller.brand?.name}
                            <span class="inline-flex items-center gap-1 rounded-full bg-purple-50 dark:bg-purple-950/50 border border-purple-200 dark:border-purple-800 px-2.5 py-0.5 text-xs font-semibold text-purple-700 dark:text-purple-300">
                                <Layers class="h-3 w-3" />
                                {seller.brand.name}
                            </span>
                        {/if}
                    </div>

                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Rincian alokasi modal barang masuk, barang terjual, dan sisa tanggungan hutang ke masing-masing vendor konveksi untuk seller ini.
                    </p>
                </div>
            </div>

            <!-- Right: Action Buttons -->
            <div class="flex flex-wrap items-center gap-2.5 pt-2 lg:pt-0">
                <button
                    onclick={copySummaryText}
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3.5 py-2.5 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 active:scale-95 transition-all shadow-xs"
                >
                    {#if copiedSummary}
                        <Check class="h-4 w-4 text-emerald-500" />
                        <span class="text-emerald-600 dark:text-emerald-400">Tersalin!</span>
                    {:else}
                        <Copy class="h-4 w-4 text-slate-500" />
                        <span>Salin Rekap Konveksi</span>
                    {/if}
                </button>

                <Link
                    href={`/superadmin/finance/seller-debt/${seller.uuid || seller.id}`}
                    class="inline-flex items-center gap-2 rounded-xl bg-purple-600 px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-purple-600/30 hover:bg-purple-700 active:scale-95 transition-all"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Rekening Koran Seller
                </Link>
            </div>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="mb-6 flex flex-col sm:flex-row items-center justify-between gap-3 bg-white dark:bg-slate-900 p-3 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
        <!-- Search Konveksi / SKU -->
        <div class="relative w-full sm:w-96">
            <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
            <input
                type="text"
                placeholder="Cari Konveksi, SKU, atau Nama Produk..."
                bind:value={searchQuery}
                oninput={handleSearchInput}
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 pl-10 pr-4 py-2 text-xs text-slate-800 dark:text-slate-100 placeholder:text-slate-400 focus:ring-2 focus:ring-indigo-500"
            />
            {#if searchQuery}
                <button
                    onclick={() => { searchQuery = ''; applyFilter(); }}
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                >
                    <X class="h-3.5 w-3.5" />
                </button>
            {/if}
        </div>

        <!-- Period Selector & Reset -->
        <div class="flex items-center gap-2 w-full sm:w-auto">
            <Calendar class="h-4 w-4 text-slate-400 shrink-0" />
            <select
                bind:value={periodId}
                onchange={applyFilter}
                class="w-full sm:w-60 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs font-medium text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
            >
                <option value="">Semua Periode Akuntansi</option>
                {#each periods as p}
                    <option value={p.id}>{p.name}</option>
                {/each}
            </select>

            <button
                onclick={resetFilter}
                class="inline-flex items-center gap-1 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 p-2 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                title="Reset Filter"
            >
                <RotateCcw class="h-3.5 w-3.5" />
            </button>
        </div>
    </div>

    <!-- KPI Summary Cards (4 Grid) -->
    <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- 1. Total Sisa Hutang Modal -->
        <div class="rounded-2xl bg-gradient-to-br from-indigo-900 via-purple-900 to-slate-900 p-5 text-white shadow-md flex flex-col justify-between">
            <div>
                <span class="block text-[11px] font-semibold uppercase tracking-wider text-indigo-200">Total Sisa Hutang Konveksi</span>
                <span class="text-2xl font-black text-amber-300 mt-1 block">{formatRupiah(summary.total_sisa_hutang)}</span>
            </div>
            <div class="mt-3 flex items-center justify-between text-xs text-indigo-200/80 pt-2 border-t border-indigo-800/60">
                <span>Total Konveksi:</span>
                <span class="font-bold text-amber-200">{summary.total_konveksi} Vendor</span>
            </div>
        </div>

        <!-- 2. Total Masuk (HPP) -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800 flex flex-col justify-between">
            <div>
                <span class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400">Total Modal Masuk (Stock In)</span>
                <span class="text-xl font-bold text-sky-600 dark:text-sky-400 mt-1 block">{formatRupiah(summary.total_stock_in_hpp)}</span>
            </div>
            <div class="mt-3 flex items-center justify-between text-xs text-slate-400 pt-2 border-t border-slate-100 dark:border-slate-800">
                <span>Barang Masuk:</span>
                <span class="font-bold text-slate-700 dark:text-slate-200">{summary.total_stock_in_qty.toLocaleString('id-ID')} Pcs</span>
            </div>
        </div>

        <!-- 3. Total Terjual (HPP) -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800 flex flex-col justify-between">
            <div>
                <span class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400">Total Modal Terjual (Stock Out)</span>
                <span class="text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-1 block">{formatRupiah(summary.total_stock_out_hpp)}</span>
            </div>
            <div class="mt-3 flex items-center justify-between text-xs text-slate-400 pt-2 border-t border-slate-100 dark:border-slate-800">
                <span>Barang Terjual:</span>
                <span class="font-bold text-slate-700 dark:text-slate-200">{summary.total_stock_out_qty.toLocaleString('id-ID')} Pcs</span>
            </div>
        </div>

        <!-- 4. Sisa Stok & Likuidasi -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800 flex flex-col justify-between">
            <div>
                <span class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400">Likuidasi Keseluruhan</span>
                <div class="flex items-baseline gap-2 mt-1">
                    <span class="text-xl font-bold text-indigo-600 dark:text-indigo-400">{summary.overall_liquidation}%</span>
                    <span class="text-xs text-slate-400 font-medium">terjual</span>
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between text-xs text-slate-400 pt-2 border-t border-slate-100 dark:border-slate-800">
                <span>Sisa Stok Fisik:</span>
                <span class="font-bold text-slate-700 dark:text-slate-200">{summary.total_sisa_qty.toLocaleString('id-ID')} Pcs</span>
            </div>
        </div>
    </div>

    <!-- Expansion Controls -->
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider flex items-center gap-2">
            <Factory class="h-4 w-4 text-indigo-500" />
            Daftar Vendor Konveksi ({konveksis.length})
        </h2>

        <div class="flex items-center gap-2">
            <button
                onclick={expandAll}
                class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline"
            >
                Buka Semua Rincian
            </button>
            <span class="text-slate-300 dark:text-slate-700">•</span>
            <button
                onclick={collapseAll}
                class="text-xs font-semibold text-slate-500 dark:text-slate-400 hover:underline"
            >
                Tutup Semua
            </button>
        </div>
    </div>

    <!-- Konveksi Cards List -->
    <div class="space-y-4 mb-8">
        {#each konveksis as k, index}
            {@const isExpanded = !!expandedKonveksis[k.konveksi_id]}
            {@const hasDebt = k.sisa_hutang > 0}
            
            <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-xs border border-slate-200/80 dark:border-slate-800 overflow-hidden transition-all">
                <!-- Konveksi Header Row -->
                <div class="p-5 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <div
                            class="h-10 w-10 rounded-xl flex items-center justify-center font-bold text-white shrink-0 shadow-xs"
                            style="background-color: {k.color || '#6366f1'};"
                        >
                            <Factory class="h-5 w-5" />
                        </div>

                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-extrabold text-slate-900 dark:text-slate-50">
                                    {k.name}
                                </h3>
                                {#if hasDebt}
                                    <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-900 px-2 py-0.5 text-[10px] font-bold text-rose-700 dark:text-rose-300">
                                        <AlertCircle class="h-3 w-3" />
                                        Tanggungan Aktif
                                    </span>
                                {:else}
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:text-emerald-300">
                                        <CheckCircle2 class="h-3 w-3" />
                                        Lunas / Surplus
                                    </span>
                                {/if}
                            </div>

                            <div class="flex items-center gap-3 text-xs text-slate-400 mt-0.5">
                                {#if k.contact}
                                    <span class="flex items-center gap-1 text-slate-500">
                                        <Phone class="h-3 w-3" />
                                        {k.contact}
                                    </span>
                                {/if}
                                <span>{k.products.length} SKU varian terdaftar</span>
                            </div>
                        </div>
                    </div>

                    <!-- Metrics Grid on Card Header -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 lg:gap-6 text-xs border-t lg:border-t-0 border-slate-100 dark:border-slate-800 pt-3 lg:pt-0">
                        <div>
                            <span class="text-[10px] uppercase font-semibold text-slate-400 block">Barang Masuk (In)</span>
                            <span class="font-bold text-sky-600 dark:text-sky-400 block">{formatRupiah(k.stock_in_hpp)}</span>
                            <span class="text-[10px] text-slate-400">{k.stock_in_qty} pcs</span>
                        </div>

                        <div>
                            <span class="text-[10px] uppercase font-semibold text-slate-400 block">Barang Terjual (Out)</span>
                            <span class="font-bold text-emerald-600 dark:text-emerald-400 block">{formatRupiah(k.stock_out_hpp)}</span>
                            <span class="text-[10px] text-slate-400">{k.stock_out_qty} pcs</span>
                        </div>

                        <div>
                            <span class="text-[10px] uppercase font-semibold text-slate-400 block">Sisa Stok Fisik</span>
                            <span class="font-bold text-slate-700 dark:text-slate-200 block">{k.sisa_qty} pcs</span>
                            <span class="text-[10px] text-indigo-500 font-semibold">Likuidasi: {k.liquidation_rate}%</span>
                        </div>

                        <div class="bg-slate-50 dark:bg-slate-950 p-2.5 rounded-xl border border-slate-200/60 dark:border-slate-800">
                            <span class="text-[10px] uppercase font-extrabold text-slate-500 block">Sisa Hutang Modal</span>
                            <span class="text-sm font-black {hasDebt ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'} block">
                                {formatRupiah(k.sisa_hutang)}
                            </span>
                        </div>
                    </div>

                    <!-- Expand Toggle Button -->
                    <button
                        onclick={() => toggleExpand(k.konveksi_id)}
                        class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors shrink-0"
                    >
                        <span>{isExpanded ? 'Tutup Rincian' : `Lihat ${k.products.length} SKU`}</span>
                        {#if isExpanded}
                            <ChevronUp class="h-4 w-4" />
                        {:else}
                            <ChevronDown class="h-4 w-4" />
                        {/if}
                    </button>
                </div>

                <!-- Progress Bar Likuidasi Konveksi -->
                <div class="px-5 pb-3">
                    <div class="h-1.5 w-full bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden flex">
                        <div
                            class="bg-emerald-500 h-full transition-all duration-300"
                            style="width: {Math.min(k.liquidation_rate, 100)}%;"
                            title="Terlikuidasi: {k.liquidation_rate}%"
                        ></div>
                        <div
                            class="bg-rose-400/70 h-full transition-all duration-300 flex-1"
                            title="Sisa Tertahan"
                        ></div>
                    </div>
                </div>

                <!-- Expandable SKU Table -->
                {#if isExpanded}
                    <div class="border-t border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/40 p-4 space-y-2 animate-in fade-in duration-150">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">
                            Rincian SKU Produk dari {k.name} ({k.products.length} Varian)
                        </h4>

                        <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 uppercase font-semibold border-b border-slate-200 dark:border-slate-800 text-[10px]">
                                    <tr>
                                        <th class="p-2.5 w-10 text-center">No</th>
                                        <th class="p-2.5">Produk & Varian</th>
                                        <th class="p-2.5 font-mono">SKU</th>
                                        <th class="p-2.5 text-right">HPP Satuan</th>
                                        <th class="p-2.5 text-center">Masuk (In)</th>
                                        <th class="p-2.5 text-right">Total In (HPP)</th>
                                        <th class="p-2.5 text-center">Keluar (Out)</th>
                                        <th class="p-2.5 text-right">Total Out (HPP)</th>
                                        <th class="p-2.5 text-center">Sisa Qty</th>
                                        <th class="p-2.5 text-right">Sisa Hutang Modal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    {#each k.products as prod, pIndex}
                                        {@const prodHasDebt = prod.sisa_hpp > 0}
                                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                            <td class="p-2.5 text-center font-mono text-slate-400">{pIndex + 1}</td>
                                            <td class="p-2.5">
                                                <span class="font-bold text-slate-800 dark:text-slate-100 block">{prod.product_name}</span>
                                                <span class="text-[10px] text-slate-400">Varian: {prod.variant}</span>
                                            </td>
                                            <td class="p-2.5 font-mono font-semibold text-indigo-600 dark:text-indigo-400">
                                                {prod.sku}
                                            </td>
                                            <td class="p-2.5 text-right text-slate-600 dark:text-slate-300">
                                                {formatRupiah(prod.unit_price)}
                                            </td>
                                            <td class="p-2.5 text-center font-bold text-sky-600 dark:text-sky-400">
                                                {prod.in_qty} pcs
                                            </td>
                                            <td class="p-2.5 text-right text-sky-600 dark:text-sky-400">
                                                {formatRupiah(prod.in_hpp)}
                                            </td>
                                            <td class="p-2.5 text-center font-bold text-emerald-600 dark:text-emerald-400">
                                                {prod.out_qty} pcs
                                            </td>
                                            <td class="p-2.5 text-right text-emerald-600 dark:text-emerald-400">
                                                {formatRupiah(prod.out_hpp)}
                                            </td>
                                            <td class="p-2.5 text-center font-bold text-slate-700 dark:text-slate-200">
                                                {prod.sisa_qty} pcs
                                            </td>
                                            <td class="p-2.5 text-right font-black {prodHasDebt ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'}">
                                                {formatRupiah(prod.sisa_hpp)}
                                            </td>
                                        </tr>
                                    {:else}
                                        <tr>
                                            <td colspan="10" class="p-6 text-center text-slate-400">
                                                Tidak ada SKU terdata untuk konveksi ini.
                                            </td>
                                        </tr>
                                    {/each}
                                </tbody>
                                <tfoot class="bg-slate-50 dark:bg-slate-950 font-bold border-t border-slate-200 dark:border-slate-800 text-[11px]">
                                    <tr>
                                        <td colspan="4" class="p-2.5 text-right uppercase text-slate-500">Subtotal {k.name}:</td>
                                        <td class="p-2.5 text-center text-sky-600">{k.stock_in_qty} pcs</td>
                                        <td class="p-2.5 text-right text-sky-600">{formatRupiah(k.stock_in_hpp)}</td>
                                        <td class="p-2.5 text-center text-emerald-600">{k.stock_out_qty} pcs</td>
                                        <td class="p-2.5 text-right text-emerald-600">{formatRupiah(k.stock_out_hpp)}</td>
                                        <td class="p-2.5 text-center">{k.sisa_qty} pcs</td>
                                        <td class="p-2.5 text-right text-sm {hasDebt ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600'}">
                                            {formatRupiah(k.sisa_hutang)}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                {/if}
            </div>
        {:else}
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-12 text-center border border-slate-200 dark:border-slate-800 space-y-3">
                <Factory class="h-10 w-10 text-slate-300 dark:text-slate-600 mx-auto" />
                <h3 class="text-base font-bold text-slate-700 dark:text-slate-300">Tidak ada data hutang konveksi ditemukan</h3>
                <p class="text-xs text-slate-400 max-w-sm mx-auto">
                    Belum ada riwayat barang masuk atau barang keluar yang terkait dengan vendor konveksi untuk seller ini pada filter yang dipilih.
                </p>
            </div>
        {/each}
    </div>
</AdminLayout>

