<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import { Link, router } from '@inertiajs/svelte';
    import {
        ArrowLeft,
        Shirt,
        Layers,
        Package,
        ShoppingBag,
        Search,
        RotateCcw,
        Copy,
        Check,
        Filter,
        Calendar,
        Warehouse as WarehouseIcon,
        Store,
        UserCheck,
        HelpCircle,
        FileSpreadsheet
    } from '@lucide/svelte';

    interface CategorizedVariant {
        variant_id: number;
        product_id: number;
        product_name: string;
        sku: string;
        size?: string;
        color?: string;
        barcode?: string;
        category_name: string;
        seller_name: string;
        type: 'baju' | 'celana' | 'other';
        single_qty: number;
        bundle_qty: number;
        qty: number;
        bundle_sources: string[];
        price: number;
        total_amount: number;
    }

    interface FilterOption {
        id: number;
        name?: string;
        store_name?: string;
        seller_name?: string;
    }

    let {
        items = [],
        summary = {
            total_pcs: 0,
            baju_count: 0,
            celana_count: 0,
            other_count: 0,
            bundle_unpacked_pcs: 0,
        },
        periods = [],
        warehouses = [],
        stores = [],
        sellers = [],
        filters = {
            period_id: '',
            warehouse_id: '',
            store_id: '',
            seller_id: '',
            search: '',
            type: 'all',
        },
    }: {
        items: CategorizedVariant[];
        summary: {
            total_pcs: number;
            baju_count: number;
            celana_count: number;
            other_count: number;
            bundle_unpacked_pcs: number;
        };
        periods?: FilterOption[];
        warehouses?: FilterOption[];
        stores?: FilterOption[];
        sellers?: FilterOption[];
        filters?: {
            period_id?: string | number;
            warehouse_id?: string | number;
            store_id?: string | number;
            seller_id?: string | number;
            search?: string;
            type?: string;
        };
    } = $props();

    let periodId = $state(String(filters.period_id ?? ''));
    let warehouseId = $state(String(filters.warehouse_id ?? ''));
    let storeId = $state(String(filters.store_id ?? ''));
    let sellerId = $state(String(filters.seller_id ?? ''));
    let search = $state(filters.search ?? '');
    let activeType = $state(filters.type ?? 'all');

    let copiedSku = $state<string | null>(null);
    let copiedSummary = $state(false);

    function applyFilters(newType?: string) {
        const typeToUse = newType !== undefined ? newType : activeType;
        router.get(
            '/superadmin/warehouse/stock-out/baju-celana',
            {
                period_id: periodId || undefined,
                warehouse_id: warehouseId || undefined,
                store_id: storeId || undefined,
                seller_id: sellerId || undefined,
                search: search || undefined,
                type: typeToUse !== 'all' ? typeToUse : undefined,
            },
            {
                preserveState: true,
                replace: true,
            }
        );
    }

    function switchType(t: string) {
        activeType = t;
        applyFilters(t);
    }

    function resetFilters() {
        periodId = '';
        warehouseId = '';
        storeId = '';
        sellerId = '';
        search = '';
        activeType = 'all';
        router.get('/superadmin/warehouse/stock-out/baju-celana', {}, { replace: true });
    }

    function copyToClipboard(text: string, skuKey?: string) {
        navigator.clipboard.writeText(text);
        if (skuKey) {
            copiedSku = skuKey;
            setTimeout(() => (copiedSku = null), 2000);
        } else {
            copiedSummary = true;
            setTimeout(() => (copiedSummary = false), 2000);
        }
    }

    function copySummaryText() {
        const text = `REKAP PEMISAHAN BAJU & CELANA TERJUAL\n` +
            `Total Terjual: ${summary.total_pcs.toLocaleString('id-ID')} pcs\n` +
            `👕 Total Baju: ${summary.baju_count.toLocaleString('id-ID')} pcs\n` +
            `👖 Total Celana: ${summary.celana_count.toLocaleString('id-ID')} pcs\n` +
            (summary.other_count > 0 ? `📦 Lainnya: ${summary.other_count.toLocaleString('id-ID')} pcs\n` : '') +
            `🎁 Paket Bundle Terurai: ${summary.bundle_unpacked_pcs.toLocaleString('id-ID')} paket`;
        copyToClipboard(text);
    }

    function formatRupiah(num: number) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(num || 0);
    }

    // Build URL back to main stock out page preserving filter values
    const backToStockOutUrl = $derived(
        `/superadmin/warehouse/stock-out?` +
        new URLSearchParams({
            period_id: periodId,
            warehouse_id: warehouseId,
            store_id: storeId,
            search: search,
        }).toString()
    );
</script>

<AdminLayout
    title="Pemisahan Baju & Celana (Stock Out)"
    breadcrumbs={[
        { name: 'Mutasi Stok' },
        { name: 'Pengeluaran (Stock Out)', url: '/superadmin/warehouse/stock-out' },
        { name: 'Pemisahan Baju & Celana' }
    ]}
>
    <!-- Header Page -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <Link
                href={backToStockOutUrl}
                class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 shadow-xs hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                title="Kembali ke Stock Out"
            >
                <ArrowLeft class="h-5 w-5" />
            </Link>
            <div>
                <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                    <Shirt class="h-7 w-7 text-blue-500" />
                    Pemisahan Baju & Celana
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Pemisahan otomatis produk baju vs celana yang terjual pada periode aktif untuk dasar pengurangan produk global
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button
                onclick={copySummaryText}
                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3.5 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors shadow-xs"
            >
                {#if copiedSummary}
                    <Check class="h-4 w-4 text-emerald-500" />
                    <span class="text-emerald-600 dark:text-emerald-400">Tersalin!</span>
                {:else}
                    <Copy class="h-4 w-4 text-slate-500" />
                    <span>Salin Rekap QTY</span>
                {/if}
            </button>

            <Link
                href="/superadmin/warehouse/mapping/manual-global"
                class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-3.5 py-2 text-xs font-semibold text-white shadow-lg shadow-indigo-600/30 hover:bg-indigo-700 transition-colors"
            >
                <FileSpreadsheet class="h-4 w-4" />
                <span>Buka Report Global Product</span>
            </Link>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- 1. Baju (Atasan) -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-blue-200 dark:border-blue-900/50 shadow-xs space-y-1 relative overflow-hidden">
            <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                <span class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">Total Baju (Atasan)</span>
                <div class="p-2 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400">
                    <Shirt class="h-5 w-5" />
                </div>
            </div>
            <div class="text-2xl font-black text-blue-600 dark:text-blue-400">
                {summary.baju_count.toLocaleString('id-ID')} <span class="text-xs font-semibold text-slate-400">pcs</span>
            </div>
            <div class="text-[11px] text-slate-400">
                Kaos, kemeja, atasan, hoodie, gamis, dll
            </div>
        </div>

        <!-- 2. Celana (Bawahan) -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-purple-200 dark:border-purple-900/50 shadow-xs space-y-1 relative overflow-hidden">
            <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                <span class="text-xs font-bold uppercase tracking-wider text-purple-600 dark:text-purple-400">Total Celana (Bawahan)</span>
                <div class="p-2 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400">
                    <Layers class="h-5 w-5" />
                </div>
            </div>
            <div class="text-2xl font-black text-purple-600 dark:text-purple-400">
                {summary.celana_count.toLocaleString('id-ID')} <span class="text-xs font-semibold text-slate-400">pcs</span>
            </div>
            <div class="text-[11px] text-slate-400">
                Celana, kulot, jeans, jogger, rok, short, dll
            </div>
        </div>

        <!-- 3. Paket Bundle Terurai -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-amber-200 dark:border-amber-900/50 shadow-xs space-y-1 relative overflow-hidden">
            <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                <span class="text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">Bundle Terurai</span>
                <div class="p-2 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400">
                    <Package class="h-5 w-5" />
                </div>
            </div>
            <div class="text-2xl font-black text-amber-600 dark:text-amber-400">
                {summary.bundle_unpacked_pcs.toLocaleString('id-ID')} <span class="text-xs font-semibold text-slate-400">paket</span>
            </div>
            <div class="text-[11px] text-slate-400">
                Komponen dipecah jadi baju & celana otomatis
            </div>
        </div>

        <!-- 4. Total Keseluruhan -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1 relative overflow-hidden">
            <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                <span class="text-xs font-bold uppercase tracking-wider">Total Semua Pcs</span>
                <div class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
                    <ShoppingBag class="h-5 w-5" />
                </div>
            </div>
            <div class="text-2xl font-black text-slate-800 dark:text-slate-100">
                {summary.total_pcs.toLocaleString('id-ID')} <span class="text-xs font-semibold text-slate-400">pcs</span>
            </div>
            <div class="text-[11px] text-slate-400">
                {summary.other_count > 0 ? `Termasuk ${summary.other_count} pcs non-baju/celana` : 'Semua item teralokasi sempurna'}
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="mb-6 rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-200 dark:border-slate-800 flex flex-col md:flex-row flex-wrap items-center gap-3">
        <!-- Periode Filter -->
        <div class="w-full sm:w-auto flex-1 min-w-[150px]">
            <label for="filter_period" class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Periode</label>
            <select
                id="filter_period"
                bind:value={periodId}
                onchange={() => applyFilters()}
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500"
            >
                <option value="">Semua Periode</option>
                {#each periods as p}
                    <option value={p.id}>{p.name}</option>
                {/each}
            </select>
        </div>

        <!-- Gudang Filter -->
        <div class="w-full sm:w-auto flex-1 min-w-[150px]">
            <label for="filter_warehouse" class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Gudang</label>
            <select
                id="filter_warehouse"
                bind:value={warehouseId}
                onchange={() => applyFilters()}
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500"
            >
                <option value="">Semua Gudang</option>
                {#each warehouses as w}
                    <option value={w.id}>{w.name}</option>
                {/each}
            </select>
        </div>

        <!-- Toko Marketplace -->
        <div class="w-full sm:w-auto flex-1 min-w-[150px]">
            <label for="filter_store" class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Toko</label>
            <select
                id="filter_store"
                bind:value={storeId}
                onchange={() => applyFilters()}
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500"
            >
                <option value="">Semua Toko</option>
                {#each stores as st}
                    <option value={st.id}>{st.store_name}</option>
                {/each}
            </select>
        </div>

        <!-- Seller Filter -->
        <div class="w-full sm:w-auto flex-1 min-w-[150px]">
            <label for="filter_seller" class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Seller / Mitra</label>
            <select
                id="filter_seller"
                bind:value={sellerId}
                onchange={() => applyFilters()}
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500"
            >
                <option value="">Semua Seller</option>
                {#each sellers as sel}
                    <option value={sel.id}>{sel.seller_name}</option>
                {/each}
            </select>
        </div>

        <!-- Search Bar -->
        <div class="relative w-full sm:w-auto flex-1 min-w-[180px] self-end">
            <label for="filter_search" class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Cari Produk / SKU</label>
            <div class="relative">
                <Search class="absolute left-3 top-2.5 h-3.5 w-3.5 text-slate-400" />
                <input
                    id="filter_search"
                    type="text"
                    bind:value={search}
                    onkeyup={(e) => e.key === 'Enter' && applyFilters()}
                    placeholder="Ketik SKU / nama lalu Enter..."
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 pl-8 pr-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
            </div>
        </div>

        <!-- Reset Button -->
        <div class="self-end">
            <button
                onclick={resetFilters}
                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                title="Reset Semua Filter"
            >
                <RotateCcw class="h-3.5 w-3.5" />
                Reset
            </button>
        </div>
    </div>

    <!-- Category Tabs Selector -->
    <div class="mb-4 flex flex-wrap items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-3">
        <button
            onclick={() => switchType('all')}
            class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition-all
            {activeType === 'all'
                ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900 shadow-sm'
                : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800'}"
        >
            <ShoppingBag class="h-3.5 w-3.5" />
            <span>Semua Produk</span>
            <span class="rounded-md px-1.5 py-0.5 text-[10px] {activeType === 'all' ? 'bg-slate-700 text-white dark:bg-slate-200 dark:text-slate-900' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'}">
                {items.length} varian
            </span>
        </button>

        <button
            onclick={() => switchType('baju')}
            class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition-all
            {activeType === 'baju'
                ? 'bg-blue-600 text-white shadow-md shadow-blue-600/30'
                : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800'}"
        >
            <Shirt class="h-3.5 w-3.5 text-blue-500 {activeType === 'baju' ? 'text-white' : ''}" />
            <span>Baju (Atasan)</span>
            <span class="rounded-md px-1.5 py-0.5 text-[10px] {activeType === 'baju' ? 'bg-blue-700 text-white' : 'bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 font-bold'}">
                {summary.baju_count.toLocaleString('id-ID')} pcs
            </span>
        </button>

        <button
            onclick={() => switchType('celana')}
            class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition-all
            {activeType === 'celana'
                ? 'bg-purple-600 text-white shadow-md shadow-purple-600/30'
                : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800'}"
        >
            <Layers class="h-3.5 w-3.5 text-purple-500 {activeType === 'celana' ? 'text-white' : ''}" />
            <span>Celana (Bawahan)</span>
            <span class="rounded-md px-1.5 py-0.5 text-[10px] {activeType === 'celana' ? 'bg-purple-700 text-white' : 'bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 font-bold'}">
                {summary.celana_count.toLocaleString('id-ID')} pcs
            </span>
        </button>

        {#if summary.other_count > 0}
            <button
                onclick={() => switchType('other')}
                class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition-all
                {activeType === 'other'
                    ? 'bg-slate-700 text-white shadow-md shadow-slate-700/30'
                    : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800'}"
            >
                <HelpCircle class="h-3.5 w-3.5 text-slate-400 {activeType === 'other' ? 'text-white' : ''}" />
                <span>Lainnya / Non-Kategori</span>
                <span class="rounded-md px-1.5 py-0.5 text-[10px] {activeType === 'other' ? 'bg-slate-800 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'}">
                    {summary.other_count.toLocaleString('id-ID')} pcs
                </span>
            </button>
        {/if}
    </div>

    <!-- Data Table -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-xs border border-slate-200 dark:border-slate-800 overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600 dark:text-slate-400">
                <thead class="bg-slate-50 dark:bg-slate-800/50 uppercase font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="px-4 py-3.5 w-12 text-center">No</th>
                        <th class="px-4 py-3.5">Kategori</th>
                        <th class="px-4 py-3.5">Produk & SKU</th>
                        <th class="px-4 py-3.5">Varian (Size & Warna)</th>
                        <th class="px-4 py-3.5">Seller</th>
                        <th class="px-4 py-3.5">Sumber Mutasi</th>
                        <th class="px-4 py-3.5 text-center">Total Terjual (Pcs)</th>
                        <th class="px-4 py-3.5 text-right">HPP Satuan</th>
                        <th class="px-4 py-3.5 text-right">Total Nominal HPP</th>
                        <th class="px-4 py-3.5 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    {#each items as item, index}
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                            <!-- No -->
                            <td class="px-4 py-3.5 text-center font-mono text-slate-400">{index + 1}</td>

                            <!-- Kategori (Baju / Celana) -->
                            <td class="px-4 py-3.5">
                                {#if item.type === 'baju'}
                                    <span class="inline-flex items-center gap-1 rounded-lg bg-blue-50 dark:bg-blue-950/60 px-2 py-0.5 text-[11px] font-bold text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-900">
                                        <Shirt class="h-3 w-3 text-blue-500" />
                                        BAJU
                                    </span>
                                {:else if item.type === 'celana'}
                                    <span class="inline-flex items-center gap-1 rounded-lg bg-purple-50 dark:bg-purple-950/60 px-2 py-0.5 text-[11px] font-bold text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-900">
                                        <Layers class="h-3 w-3 text-purple-500" />
                                        CELANA
                                    </span>
                                {:else}
                                    <span class="inline-flex items-center gap-1 rounded-lg bg-slate-100 dark:bg-slate-800 px-2 py-0.5 text-[11px] font-medium text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                        LAINNYA
                                    </span>
                                {/if}
                                <div class="text-[10px] text-slate-400 mt-0.5">{item.category_name}</div>
                            </td>

                            <!-- Produk & SKU -->
                            <td class="px-4 py-3.5">
                                <div class="font-bold text-slate-800 dark:text-slate-100">{item.product_name}</div>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <span class="font-mono text-xs font-semibold text-indigo-600 dark:text-indigo-400">{item.sku}</span>
                                    <button
                                        onclick={() => copyToClipboard(item.sku, item.sku)}
                                        class="text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                        title="Salin SKU"
                                    >
                                        {#if copiedSku === item.sku}
                                            <Check class="h-3.5 w-3.5 text-emerald-500" />
                                        {:else}
                                            <Copy class="h-3.5 w-3.5" />
                                        {/if}
                                    </button>
                                </div>
                            </td>

                            <!-- Varian -->
                            <td class="px-4 py-3.5">
                                <div class="flex flex-wrap items-center gap-1">
                                    {#if item.size}
                                        <span class="rounded bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 text-[10px] font-semibold text-slate-700 dark:text-slate-300">
                                            Size: {item.size}
                                        </span>
                                    {/if}
                                    {#if item.color}
                                        <span class="rounded bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 text-[10px] font-semibold text-slate-700 dark:text-slate-300">
                                            Warna: {item.color}
                                        </span>
                                    {/if}
                                    {#if !item.size && !item.color}
                                        <span class="text-slate-400 text-[11px]">-</span>
                                    {/if}
                                </div>
                            </td>

                            <!-- Seller -->
                            <td class="px-4 py-3.5 font-medium text-slate-700 dark:text-slate-300">
                                {item.seller_name}
                            </td>

                            <!-- Sumber (Single / Bundle Breakdown) -->
                            <td class="px-4 py-3.5">
                                {#if item.bundle_qty > 0 && item.single_qty > 0}
                                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/60 px-2 py-0.5 rounded border border-amber-200 dark:border-amber-900">
                                        <Package class="h-3 w-3" />
                                        Single ({item.single_qty}) + Bundle ({item.bundle_qty})
                                    </span>
                                    {#if item.bundle_sources.length > 0}
                                        <div class="text-[10px] text-slate-400 mt-0.5 truncate max-w-[180px]" title={item.bundle_sources.join(', ')}>
                                            Dari: {item.bundle_sources.join(', ')}
                                        </div>
                                    {/if}
                                {:else if item.bundle_qty > 0}
                                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/60 px-2 py-0.5 rounded border border-amber-200 dark:border-amber-900">
                                        <Package class="h-3 w-3" />
                                        Pecahan Paket Bundle
                                    </span>
                                    {#if item.bundle_sources.length > 0}
                                        <div class="text-[10px] text-slate-400 mt-0.5 truncate max-w-[180px]" title={item.bundle_sources.join(', ')}>
                                            Dari: {item.bundle_sources.join(', ')}
                                        </div>
                                    {/if}
                                {:else}
                                    <span class="text-[11px] text-slate-500">Penjualan Langsung (Single)</span>
                                {/if}
                            </td>

                            <!-- Total Terjual (Pcs) -->
                            <td class="px-4 py-3.5 text-center">
                                <span class="inline-flex items-center justify-center font-black text-sm text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/50 px-3 py-1 rounded-xl border border-rose-200 dark:border-rose-900">
                                    {item.qty.toLocaleString('id-ID')} pcs
                                </span>
                            </td>

                            <!-- HPP Satuan -->
                            <td class="px-4 py-3.5 text-right font-medium text-slate-600 dark:text-slate-300">
                                {formatRupiah(item.price)}
                            </td>

                            <!-- Total Nominal HPP -->
                            <td class="px-4 py-3.5 text-right font-bold text-slate-800 dark:text-slate-100">
                                {formatRupiah(item.total_amount)}
                            </td>

                            <!-- Aksi / Salin Info -->
                            <td class="px-4 py-3.5 text-center">
                                <button
                                    onclick={() => copyToClipboard(`${item.sku} (${item.qty} pcs)`, item.sku)}
                                    class="inline-flex items-center gap-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-2 py-1 text-[11px] font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
                                    title="Salin SKU & Qty"
                                >
                                    {#if copiedSku === item.sku}
                                        <Check class="h-3 w-3 text-emerald-500" />
                                        <span class="text-emerald-600 text-[10px]">Tersalin</span>
                                    {:else}
                                        <Copy class="h-3 w-3 text-slate-400" />
                                        <span>Salin</span>
                                    {/if}
                                </button>
                            </td>
                        </tr>
                    {:else}
                        <tr>
                            <td colspan="10" class="px-6 py-12 text-center text-slate-400">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <ShoppingBag class="h-8 w-8 text-slate-300 dark:text-slate-600" />
                                    <p class="font-medium text-slate-600 dark:text-slate-400">Tidak ada data produk terjual untuk filter ini.</p>
                                    <p class="text-xs text-slate-400">Coba ubah periode atau bersihkan filter pencarian.</p>
                                </div>
                            </td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    </div>
</AdminLayout>
