<script lang="ts">
    import SellerMobileLayout from '@/layouts/SellerMobileLayout.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import { Link } from '@inertiajs/svelte';
    import {
        Activity,
        ArrowLeft,
        Package,
        Boxes,
        ArrowDownLeft,
        ArrowUpRight,
        ClipboardCheck,
        Building2,
        Clock
    } from '@lucide/svelte';

    interface LogItem {
        id: number;
        created_at: string;
        sku: string;
        variant_info: string;
        warehouse_name: string;
        qty_before: number;
        qty_change: number;
        qty_after: number;
        ref_label: string;
        note: string | null;
    }

    interface VariantItem {
        id: number;
        sku: string;
        size: string;
        color: string;
        price: number;
        selling_price: number;
        qty: number;
    }

    interface ProductData {
        id: number;
        product_name: string;
        sku: string;
        category_name: string;
        brand_name: string;
        total_stock_pcs: number;
        variants: VariantItem[];
    }

    interface PaginatedLogs {
        data: LogItem[];
        links: any[];
    }

    let {
        product = null,
        logs = { data: [], links: [] },
    }: {
        product?: ProductData | null;
        logs?: PaginatedLogs;
    } = $props();
</script>

<SellerMobileLayout title="Timeline Pergerakan Stok">
    <div class="space-y-4">
        <!-- Back Navigation Header -->
        <div class="flex items-center gap-3">
            <Link
                href="/seller/stocks"
                class="flex h-9 w-9 items-center justify-center rounded-xl bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800 shadow-2xs hover:bg-slate-100 transition-colors"
            >
                <ArrowLeft class="h-5 w-5" />
            </Link>

            <div>
                <h1 class="text-lg font-black text-slate-800 dark:text-slate-100 leading-tight">Timeline Mutasi Stok</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Histori barang masuk, keluar, & opname</p>
            </div>
        </div>

        <!-- Product Summary Header Card -->
        {#if product}
            <div class="rounded-3xl bg-gradient-to-br from-slate-900 to-indigo-950 p-5 text-white shadow-xl space-y-3">
                <div class="flex items-start justify-between">
                    <div>
                        <span class="rounded-md bg-white/10 px-2 py-0.5 text-[10px] font-bold text-indigo-200 backdrop-blur-md">
                            {product.category_name}
                        </span>
                        <h2 class="text-base font-black text-white mt-1 leading-tight">{product.product_name}</h2>
                        <p class="text-xs text-indigo-200/80 mt-0.5">Parent SKU: <span class="font-mono font-bold text-white">{product.sku}</span></p>
                    </div>

                    <div class="text-right">
                        <span class="text-[10px] uppercase font-bold text-indigo-200 block">Total Stok</span>
                        <span class="text-xl font-black text-emerald-400">{product.total_stock_pcs} Pcs</span>
                    </div>
                </div>

                <!-- Variant Badges Scroll -->
                <div class="pt-2 border-t border-white/10 flex items-center gap-1.5 overflow-x-auto pb-1 no-scrollbar">
                    {#each product.variants as v}
                        <span class="shrink-0 rounded-xl bg-white/10 px-2.5 py-1 text-[10px] font-semibold text-white backdrop-blur-md">
                            {v.sku} ({v.size}): <strong>{v.qty} Pcs</strong>
                        </span>
                    {/each}
                </div>
            </div>
        {/if}

        <!-- Mobile Timeline Feed List -->
        <div class="space-y-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 px-1">Riwayat Pergerakan Stok</h3>

            <div class="relative pl-6 space-y-4 before:absolute before:left-2.5 before:top-3 before:bottom-3 before:w-0.5 before:bg-slate-200 dark:before:bg-slate-800">
                {#each logs.data as log}
                    {@const isPositive = log.qty_change > 0}
                    <div class="relative rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 space-y-2">
                        <!-- Timeline Icon Node -->
                        <div
                            class="absolute -left-6 top-4 flex h-6 w-6 items-center justify-center rounded-full text-white ring-4 ring-slate-50 dark:ring-slate-950
                            {isPositive ? 'bg-emerald-500' : 'bg-rose-500'}"
                        >
                            {#if isPositive}
                                <ArrowDownLeft class="h-3.5 w-3.5" />
                            {:else}
                                <ArrowUpRight class="h-3.5 w-3.5" />
                            {/if}
                        </div>

                        <!-- Top log info -->
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center gap-1 text-[11px] font-bold text-slate-500">
                                <Clock class="h-3 w-3 text-slate-400" />
                                {log.created_at}
                            </span>

                            <span class="rounded-lg px-2 py-0.5 text-xs font-extrabold
                                {isPositive
                                    ? 'bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300'
                                    : 'bg-rose-100 dark:bg-rose-950/80 text-rose-700 dark:text-rose-300'}"
                            >
                                {isPositive ? '+' : ''}{log.qty_change} Pcs
                            </span>
                        </div>

                        <div>
                            <p class="text-xs font-bold text-slate-800 dark:text-slate-100">{log.ref_label}</p>
                            <p class="text-[11px] text-slate-400 mt-0.5">Varian: <span class="font-mono font-bold text-slate-600 dark:text-slate-300">{log.sku}</span> ({log.variant_info})</p>
                        </div>

                        <div class="pt-2 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between text-[11px] text-slate-500">
                            <span class="flex items-center gap-1">
                                <Building2 class="h-3 w-3 text-sky-500" />
                                {log.warehouse_name}
                            </span>

                            <span>
                                Posisi Stok: <strong>{log.qty_before}</strong> → <strong class="text-slate-800 dark:text-slate-100">{log.qty_after} Pcs</strong>
                            </span>
                        </div>

                        {#if log.note}
                            <p class="text-[11px] text-slate-400 italic bg-slate-50 dark:bg-slate-950 p-2 rounded-xl">
                                "{log.note}"
                            </p>
                        {/if}
                    </div>
                {:else}
                    <div class="rounded-2xl bg-white dark:bg-slate-900 p-8 text-center text-slate-400 border border-slate-100 dark:border-slate-800 text-xs">
                        Belum ada riwayat mutasi pergerakan stok untuk produk ini.
                    </div>
                {/each}
            </div>
        </div>

        <!-- Pagination -->
        {#if logs.links && logs.links.length > 3}
            <div class="pt-2 flex justify-center">
                <Pagination links={logs.links} />
            </div>
        {/if}
    </div>
</SellerMobileLayout>
