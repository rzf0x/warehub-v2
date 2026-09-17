<script lang="ts">
    import SellerMobileLayout from '@/layouts/SellerMobileLayout.svelte';
    import { Link, router } from '@inertiajs/svelte';
    import {
        Trophy,
        ArrowLeft,
        Package,
        TrendingUp,
        Coins,
        ChevronRight,
        Sparkles,
        Award,
        Flame
    } from '@lucide/svelte';

    interface TopProductItem {
        rank: number;
        crown: 'gold' | 'silver' | 'bronze' | null;
        product_id: number;
        product_name: string;
        sku: string;
        category_name: string;
        brand_name: string;
        image: string | null;
        total_qty: number;
        total_omset: number;
        total_hpp: number;
        gross_profit: number;
        contribution_percent: number;
    }

    let {
        topProducts = [],
        grandTotalOmset = 0,
        filters = { preset: 'this_month' },
    }: {
        topProducts?: TopProductItem[];
        grandTotalOmset?: number;
        filters?: { preset?: string };
    } = $props();

    let preset = $state(filters.preset ?? 'this_month');

    function formatRupiah(num: number): string {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num || 0);
    }

    function applyFilter(p: string) {
        preset = p;
        router.get(
            '/seller/sales/top-products',
            { preset },
            { preserveState: true, replace: true }
        );
    }
</script>

<SellerMobileLayout title="Ranking 20 Produk Terlaris">
    <div class="space-y-4">
        <!-- Back Navigation Header -->
        <div class="flex items-center gap-3">
            <Link
                href="/seller/sales"
                class="flex h-9 w-9 items-center justify-center rounded-xl bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800 shadow-2xs hover:bg-slate-100 transition-colors"
            >
                <ArrowLeft class="h-5 w-5" />
            </Link>

            <div>
                <h1 class="text-lg font-black text-slate-800 dark:text-slate-100 leading-tight flex items-center gap-1.5">
                    <Trophy class="h-5 w-5 text-amber-500" />
                    Top 20 Best Seller 👑
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Peringkat produk berdasarkan omset sales</p>
            </div>
        </div>

        <!-- Filter Preset Chips -->
        <div class="flex items-center gap-2">
            <button
                type="button"
                onclick={() => applyFilter('this_month')}
                class="flex-1 rounded-xl py-2 text-xs font-bold transition-all text-center
                {preset === 'this_month'
                    ? 'bg-amber-500 text-white shadow-md shadow-amber-500/30'
                    : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800'}"
            >
                Bulan Ini
            </button>

            <button
                type="button"
                onclick={() => applyFilter('7_days')}
                class="flex-1 rounded-xl py-2 text-xs font-bold transition-all text-center
                {preset === '7_days'
                    ? 'bg-amber-500 text-white shadow-md shadow-amber-500/30'
                    : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800'}"
            >
                7 Hari Terakhir
            </button>

            <button
                type="button"
                onclick={() => applyFilter('all_time')}
                class="flex-1 rounded-xl py-2 text-xs font-bold transition-all text-center
                {preset === 'all_time'
                    ? 'bg-amber-500 text-white shadow-md shadow-amber-500/30'
                    : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800'}"
            >
                Semua
            </button>
        </div>

        <!-- Grand Total Omset Summary Card -->
        <div class="rounded-3xl bg-gradient-to-br from-amber-600 via-orange-600 to-slate-900 p-4 text-white shadow-xl flex items-center justify-between">
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-amber-200 block">Total Omset Top Produk</span>
                <span class="text-xl font-black text-white">{formatRupiah(grandTotalOmset)}</span>
            </div>

            <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white/20 backdrop-blur-md">
                <Flame class="h-6 w-6 text-amber-200" />
            </div>
        </div>

        <!-- Ranked Mobile List Items -->
        <div class="space-y-3">
            {#each topProducts as item}
                <div class="rounded-3xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 space-y-3 relative overflow-hidden">
                    <div class="flex items-start gap-3">
                        <!-- Crown / Rank Badge -->
                        <div class="flex flex-col items-center justify-center shrink-0">
                            {#if item.crown === 'gold'}
                                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-tr from-amber-400 to-yellow-300 text-slate-950 font-black shadow-lg shadow-amber-500/40 text-lg">
                                    👑
                                </div>
                            {:else if item.crown === 'silver'}
                                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-tr from-slate-300 to-slate-100 text-slate-900 font-extrabold shadow-md text-lg">
                                    🥈
                                </div>
                            {:else if item.crown === 'bronze'}
                                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-tr from-amber-700 to-amber-900 text-amber-100 font-extrabold shadow-md text-lg">
                                    🥉
                                </div>
                            {:else}
                                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-extrabold text-sm border border-slate-200 dark:border-slate-700">
                                    #{item.rank}
                                </div>
                            {/if}
                        </div>

                        <!-- Product Info -->
                        <div class="min-w-0 flex-1">
                            <span class="rounded-md bg-slate-100 dark:bg-slate-800 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:text-slate-300">
                                {item.category_name}
                            </span>

                            <h3 class="text-sm font-extrabold text-slate-800 dark:text-slate-100 leading-tight mt-0.5 truncate">
                                {item.product_name}
                            </h3>

                            <p class="text-[11px] font-mono text-slate-400">Parent SKU: {item.sku}</p>
                        </div>
                    </div>

                    <!-- Omset & Qty Metrics -->
                    <div class="rounded-2xl bg-slate-50 dark:bg-slate-950 p-3 flex items-center justify-between border border-slate-100 dark:border-slate-800">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Total Qty Terjual</span>
                            <span class="text-xs font-black text-slate-800 dark:text-slate-100">{item.total_qty} Pcs</span>
                        </div>

                        <div class="text-right">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Total Omset</span>
                            <span class="text-xs font-black text-amber-600 dark:text-amber-400">{formatRupiah(item.total_omset)}</span>
                        </div>
                    </div>

                    <!-- Visual Progress Bar for Contribution Percentage -->
                    <div class="space-y-1">
                        <div class="flex items-center justify-between text-[10px]">
                            <span class="font-bold text-slate-400">Kontribusi Omset</span>
                            <span class="font-extrabold text-amber-600 dark:text-amber-400">{item.contribution_percent}%</span>
                        </div>

                        <div class="h-2 w-full rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                            <div
                                class="h-full rounded-full bg-gradient-to-r from-amber-500 to-orange-500 transition-all duration-500"
                                style="width: {Math.max(item.contribution_percent, 5)}%;"
                            ></div>
                        </div>
                    </div>

                    <!-- Navigation to Stock Timeline -->
                    <div class="flex items-center justify-end pt-1">
                        <Link
                            href="/seller/stocks/product/{item.product_id}"
                            class="inline-flex items-center gap-1 text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 transition-colors"
                        >
                            <span>Timeline Stok Produk</span>
                            <ChevronRight class="h-3.5 w-3.5" />
                        </Link>
                    </div>
                </div>
            {:else}
                <div class="rounded-3xl bg-white dark:bg-slate-900 p-8 text-center text-slate-400 border border-slate-100 dark:border-slate-800 text-xs space-y-2">
                    <Trophy class="h-10 w-10 mx-auto text-slate-300 dark:text-slate-700" />
                    <p class="font-bold">Belum Ada Data Best Seller</p>
                    <p class="text-[11px] text-slate-400">Belum ada transaksi penjualan produk yang tercatat pada rentang ini.</p>
                </div>
            {/each}
        </div>
    </div>
</SellerMobileLayout>
