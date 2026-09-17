<script lang="ts">
    import SellerMobileLayout from '@/layouts/SellerMobileLayout.svelte';
    import { Link, router } from '@inertiajs/svelte';
    import {
        Boxes,
        AlertTriangle,
        Search,
        CheckCircle2,
        XCircle,
        ChevronRight,
        Activity,
        Building2,
        ShieldAlert
    } from '@lucide/svelte';

    interface WarehouseStock {
        warehouse_id: number;
        warehouse_name: string;
        qty: number;
    }

    interface VariantStockItem {
        id: number;
        product_id: number;
        product_name: string;
        category_name: string;
        sku: string;
        size: string;
        color: string;
        price: number;
        selling_price: number;
        total_qty: number;
        status: 'safe' | 'low_stock' | 'out_of_stock';
        status_label: string;
        stocks: WarehouseStock[];
    }

    let {
        variants = [],
        lowStockAlerts = [],
        summary = {
            total_variants: 0,
            low_stock_count: 0,
            out_of_stock_count: 0,
            safe_stock_count: 0,
            total_stock_pcs: 0,
        },
        filters = { search: '', status: 'all' },
    }: {
        variants?: VariantStockItem[];
        lowStockAlerts?: VariantStockItem[];
        summary?: {
            total_variants: number;
            low_stock_count: number;
            out_of_stock_count: number;
            safe_stock_count: number;
            total_stock_pcs: number;
        };
        filters?: { search?: string; status?: string };
    } = $props();

    let search = $state(filters.search ?? '');
    let statusFilter = $state(filters.status ?? 'all');

    function applyFilter() {
        router.get(
            '/seller/stocks',
            { search, status: statusFilter },
            { preserveState: true, replace: true }
        );
    }
</script>

<SellerMobileLayout title="Monitoring Stok Realtime">
    <div class="space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-black text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                    <Boxes class="h-6 w-6 text-sky-500" />
                    Monitoring Stok Realtime
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Posisi stok fisik produk & alert stok menipis</p>
            </div>
        </div>

        <!-- Low Stock Alert Banner (If Any Low/Out Stock Exists) -->
        {#if summary.low_stock_count > 0 || summary.out_of_stock_count > 0}
            <div class="rounded-3xl bg-gradient-to-r from-amber-500 to-rose-600 p-4 text-white shadow-lg space-y-2">
                <div class="flex items-start gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-white/20 backdrop-blur-md text-white">
                        <ShieldAlert class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="text-sm font-extrabold">Perhatian: Stok Perlu Di-Restock!</h3>
                        <p class="text-xs text-amber-100/90 mt-0.5">
                            Ada <strong>{summary.low_stock_count} varian menipis</strong> & <strong>{summary.out_of_stock_count} varian habis</strong>. Segera buat PO Restock.
                        </p>
                    </div>
                </div>

                <div class="pt-1 flex items-center justify-end">
                    <Link
                        href="/seller/purchase-orders"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-white text-rose-700 px-3 py-1.5 text-xs font-extrabold shadow-sm hover:bg-amber-50 transition-colors"
                    >
                        Buat PO Restock
                        <ChevronRight class="h-3.5 w-3.5" />
                    </Link>
                </div>
            </div>
        {/if}

        <!-- Sticky Search Bar -->
        <div class="relative">
            <Search class="absolute left-3 top-3 h-4 w-4 text-slate-400" />
            <input
                type="text"
                placeholder="Cari SKU / nama varian..."
                bind:value={search}
                oninput={applyFilter}
                class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 pl-9 pr-4 py-2.5 text-xs text-slate-800 dark:text-slate-100 shadow-xs focus:ring-2 focus:ring-sky-500"
            />
        </div>

        <!-- Filter Chips Bar -->
        <div class="flex items-center gap-2 overflow-x-auto pb-1 no-scrollbar">
            <button
                type="button"
                onclick={() => { statusFilter = 'all'; applyFilter(); }}
                class="shrink-0 rounded-xl px-3.5 py-1.5 text-xs font-bold transition-all
                {statusFilter === 'all'
                    ? 'bg-slate-800 dark:bg-slate-100 text-white dark:text-slate-900 shadow-md'
                    : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800'}"
            >
                Semua ({summary.total_variants})
            </button>

            <button
                type="button"
                onclick={() => { statusFilter = 'low_stock'; applyFilter(); }}
                class="shrink-0 rounded-xl px-3.5 py-1.5 text-xs font-bold transition-all flex items-center gap-1
                {statusFilter === 'low_stock'
                    ? 'bg-amber-500 text-white shadow-md shadow-amber-500/30'
                    : 'bg-white dark:bg-slate-900 text-amber-600 dark:text-amber-400 border border-slate-200 dark:border-slate-800'}"
            >
                <AlertTriangle class="h-3.5 w-3.5" />
                Menipis ({summary.low_stock_count})
            </button>

            <button
                type="button"
                onclick={() => { statusFilter = 'out_of_stock'; applyFilter(); }}
                class="shrink-0 rounded-xl px-3.5 py-1.5 text-xs font-bold transition-all flex items-center gap-1
                {statusFilter === 'out_of_stock'
                    ? 'bg-rose-600 text-white shadow-md shadow-rose-600/30'
                    : 'bg-white dark:bg-slate-900 text-rose-600 dark:text-rose-400 border border-slate-200 dark:border-slate-800'}"
            >
                <XCircle class="h-3.5 w-3.5" />
                Habis ({summary.out_of_stock_count})
            </button>

            <button
                type="button"
                onclick={() => { statusFilter = 'safe'; applyFilter(); }}
                class="shrink-0 rounded-xl px-3.5 py-1.5 text-xs font-bold transition-all flex items-center gap-1
                {statusFilter === 'safe'
                    ? 'bg-emerald-600 text-white shadow-md shadow-emerald-600/30'
                    : 'bg-white dark:bg-slate-900 text-emerald-600 dark:text-emerald-400 border border-slate-200 dark:border-slate-800'}"
            >
                <CheckCircle2 class="h-3.5 w-3.5" />
                Aman ({summary.safe_stock_count})
            </button>
        </div>

        <!-- Mobile Variant Stock Cards List -->
        <div class="space-y-3">
            {#each variants as item}
                <div class="rounded-3xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <span class="rounded-md bg-slate-100 dark:bg-slate-800 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:text-slate-300">
                                {item.category_name}
                            </span>
                            <h3 class="text-sm font-extrabold text-slate-800 dark:text-slate-100 leading-tight mt-1">
                                {item.product_name}
                            </h3>
                            <div class="flex items-center gap-2 mt-0.5 text-xs text-slate-500">
                                <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400">{item.sku}</span>
                                {#if item.size}<span>• Ukuran: {item.size}</span>{/if}
                                {#if item.color}<span>• {item.color}</span>{/if}
                            </div>
                        </div>

                        <!-- Status Badge -->
                        <div class="shrink-0 text-right">
                            {#if item.status === 'out_of_stock'}
                                <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 dark:bg-rose-950/60 px-2.5 py-1 text-[11px] font-extrabold text-rose-700 dark:text-rose-300 border border-rose-200">
                                    <XCircle class="h-3 w-3" />
                                    Stok Habis
                                </span>
                            {:else if item.status === 'low_stock'}
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 dark:bg-amber-950/60 px-2.5 py-1 text-[11px] font-extrabold text-amber-700 dark:text-amber-300 border border-amber-200">
                                    <AlertTriangle class="h-3 w-3" />
                                    Stok Menipis
                                </span>
                            {:else}
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-950/60 px-2.5 py-1 text-[11px] font-extrabold text-emerald-700 dark:text-emerald-300 border border-emerald-200">
                                    <CheckCircle2 class="h-3 w-3" />
                                    Stok Aman
                                </span>
                            {/if}

                            <p class="text-base font-black text-slate-800 dark:text-slate-100 mt-1">{item.total_qty} Pcs</p>
                        </div>
                    </div>

                    <!-- Warehouse Stock Breakdown -->
                    {#if item.stocks && item.stocks.length > 0}
                        <div class="pt-2 border-t border-slate-100 dark:border-slate-800/80 flex flex-wrap gap-1.5">
                            {#each item.stocks as st}
                                <span class="inline-flex items-center gap-1 rounded-xl bg-slate-50 dark:bg-slate-950 px-2.5 py-1 text-[10px] font-medium text-slate-600 dark:text-slate-400 border border-slate-100 dark:border-slate-800">
                                    <Building2 class="h-3 w-3 text-sky-500" />
                                    {st.warehouse_name}: <strong class="text-slate-800 dark:text-slate-200">{st.qty} Pcs</strong>
                                </span>
                            {/each}
                        </div>
                    {/if}

                    <!-- Timeline Button -->
                    <div class="flex items-center justify-end pt-1">
                        <Link
                            href="/seller/stocks/product/{item.product_id}"
                            class="inline-flex items-center gap-1 text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 transition-colors"
                        >
                            <Activity class="h-3.5 w-3.5" />
                            Timeline Pergerakan Stok
                            <ChevronRight class="h-3.5 w-3.5" />
                        </Link>
                    </div>
                </div>
            {:else}
                <div class="rounded-3xl bg-white dark:bg-slate-900 p-8 text-center text-slate-400 border border-slate-100 dark:border-slate-800 text-xs space-y-2">
                    <Boxes class="h-10 w-10 mx-auto text-slate-300 dark:text-slate-700" />
                    <p class="font-bold">Stok Tidak Ditemukan</p>
                    <p class="text-[11px] text-slate-400">Tidak ada varian stok yang cocok dengan kriteria pencarian Anda.</p>
                </div>
            {/each}
        </div>
    </div>
</SellerMobileLayout>
