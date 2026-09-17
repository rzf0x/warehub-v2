<script lang="ts">
    import SellerMobileLayout from '@/layouts/SellerMobileLayout.svelte';
    import { Link } from '@inertiajs/svelte';
    import {
        Boxes,
        TrendingUp,
        Wallet,
        ClipboardList,
        PlusCircle,
        AlertTriangle,
        Upload,
        ChevronRight,
        Store,
        ArrowUpRight,
        PackageCheck,
        Sparkles
    } from '@lucide/svelte';

    interface TransactionItem {
        id: number;
        date: string;
        product_name: string;
        variant_info: string;
        store_name: string;
        qty: number;
        total_omset: number;
        total_hpp: number;
    }

    interface ChartItem {
        day: string;
        omset: number;
    }

    interface StoreItem {
        id: number;
        store_name: string;
        marketplace: string;
    }

    let {
        seller = null,
        metrics = { total_stock_units: 0, monthly_omset: 0, net_debt: 0, active_po_count: 0 },
        recentTransactions = [],
        chartData = [],
        stores = [],
    }: {
        seller?: { id: number; seller_name: string; address?: string; phone?: string } | null;
        metrics?: { total_stock_units: number; monthly_omset: number; net_debt: number; active_po_count: number };
        recentTransactions?: TransactionItem[];
        chartData?: ChartItem[];
        stores?: StoreItem[];
    } = $props();

    function formatRupiah(num: number): string {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num || 0);
    }

    let maxOmsetChart = $derived(
        Math.max(...chartData.map((d) => d.omset), 100000)
    );
</script>

<SellerMobileLayout title="Dashboard Seller" activeStoreName={stores[0]?.store_name ?? ''}>
    <div class="space-y-5">
        <!-- Seller Welcome Card -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-600 via-indigo-700 to-slate-900 p-5 text-white shadow-xl">
            <div class="absolute -right-4 -bottom-4 opacity-10 pointer-events-none">
                <Boxes class="h-44 w-44" />
            </div>

            <div class="relative z-10 flex flex-col space-y-3">
                <div class="flex items-center justify-between">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1 text-[11px] font-bold text-white backdrop-blur-md">
                        <Sparkles class="h-3 w-3 text-amber-300" />
                        Mitra Seller Portal
                    </span>

                    {#if seller}
                        <Link href="/seller/stores" class="text-xs font-semibold text-indigo-200 hover:text-white flex items-center gap-1">
                            <Store class="h-3.5 w-3.5" />
                            {stores.length} Toko
                        </Link>
                    {/if}
                </div>

                <div>
                    <h2 class="text-xl font-black tracking-tight">Halo, {seller?.seller_name ?? 'Mitra Seller'}! 👋</h2>
                    <p class="text-xs text-indigo-100/80 mt-0.5">Monitoring stok gudang, omset, dan pengajuan PO restock Anda.</p>
                </div>

                <!-- Wallet / Debt Quick Bar -->
                <div class="pt-2 border-t border-white/10 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-semibold uppercase tracking-wider text-indigo-200">Estimasi Tagihan Modal</span>
                        <p class="text-base font-extrabold text-amber-300">{formatRupiah(metrics.net_debt)}</p>
                    </div>

                    <Link
                        href="/seller/debt"
                        class="inline-flex items-center gap-1 rounded-xl bg-white/10 hover:bg-white/20 px-3 py-1.5 text-xs font-bold text-white backdrop-blur-md transition-colors"
                    >
                        Rincian
                        <ChevronRight class="h-3.5 w-3.5" />
                    </Link>
                </div>
            </div>
        </div>

        <!-- Quick Stats Grid (2x2) -->
        <div class="grid grid-cols-2 gap-3">
            <!-- Stat 1: Total Stok -->
            <Link
                href="/seller/stocks"
                class="rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 hover:border-indigo-200 transition-all flex flex-col justify-between"
            >
                <div class="flex items-center justify-between">
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-sky-100 dark:bg-sky-950 text-sky-600 dark:text-sky-400">
                        <Boxes class="h-4 w-4" />
                    </span>
                    <span class="text-[10px] font-semibold text-slate-400">Gudang</span>
                </div>
                <div class="mt-3">
                    <span class="text-lg font-black text-slate-800 dark:text-slate-100 block">{metrics.total_stock_units.toLocaleString('id-ID')}</span>
                    <span class="text-[11px] font-medium text-slate-500">Total Stok Unit</span>
                </div>
            </Link>

            <!-- Stat 2: Omset Bulan Ini -->
            <Link
                href="/seller/sales"
                class="rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 hover:border-indigo-200 transition-all flex flex-col justify-between"
            >
                <div class="flex items-center justify-between">
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400">
                        <TrendingUp class="h-4 w-4" />
                    </span>
                    <span class="text-[10px] font-semibold text-emerald-500">Bulan Ini</span>
                </div>
                <div class="mt-3">
                    <span class="text-sm font-black text-emerald-600 dark:text-emerald-400 block truncate">{formatRupiah(metrics.monthly_omset)}</span>
                    <span class="text-[11px] font-medium text-slate-500">Omset Sales</span>
                </div>
            </Link>

            <!-- Stat 3: Hutang HPP -->
            <Link
                href="/seller/debt"
                class="rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 hover:border-indigo-200 transition-all flex flex-col justify-between"
            >
                <div class="flex items-center justify-between">
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-100 dark:bg-amber-950 text-amber-600 dark:text-amber-400">
                        <Wallet class="h-4 w-4" />
                    </span>
                    <span class="text-[10px] font-semibold text-amber-500">Saldo</span>
                </div>
                <div class="mt-3">
                    <span class="text-sm font-black text-amber-600 dark:text-amber-400 block truncate">{formatRupiah(metrics.net_debt)}</span>
                    <span class="text-[11px] font-medium text-slate-500">Hutang Modal</span>
                </div>
            </Link>

            <!-- Stat 4: PO Aktif -->
            <Link
                href="/seller/purchase-orders"
                class="rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 hover:border-indigo-200 transition-all flex flex-col justify-between"
            >
                <div class="flex items-center justify-between">
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-100 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400">
                        <ClipboardList class="h-4 w-4" />
                    </span>
                    <span class="text-[10px] font-semibold text-indigo-500">Proses</span>
                </div>
                <div class="mt-3">
                    <span class="text-lg font-black text-slate-800 dark:text-slate-100 block">{metrics.active_po_count} PO</span>
                    <span class="text-[11px] font-medium text-slate-500">PO Restock</span>
                </div>
            </Link>
        </div>

        <!-- Floating Quick Action Buttons -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-3.5 shadow-xs border border-slate-100 dark:border-slate-800 grid grid-cols-3 gap-2">
            <Link
                href="/seller/purchase-orders"
                class="flex flex-col items-center justify-center p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 text-slate-700 dark:text-slate-200 transition-colors text-center"
            >
                <PlusCircle class="h-5 w-5 text-indigo-600 dark:text-indigo-400 mb-1" />
                <span class="text-[11px] font-bold">Buat PO</span>
            </Link>

            <Link
                href="/seller/stocks"
                class="flex flex-col items-center justify-center p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 hover:bg-amber-50 dark:hover:bg-amber-950/40 text-slate-700 dark:text-slate-200 transition-colors text-center"
            >
                <AlertTriangle class="h-5 w-5 text-amber-500 mb-1" />
                <span class="text-[11px] font-bold">Stok Menipis</span>
            </Link>

            <Link
                href="/seller/debt"
                class="flex flex-col items-center justify-center p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 text-slate-700 dark:text-slate-200 transition-colors text-center"
            >
                <Upload class="h-5 w-5 text-emerald-500 mb-1" />
                <span class="text-[11px] font-bold">Transfer</span>
            </Link>
        </div>

        <!-- Chart Omset Sales Mobile (7 Hari) -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Grafik Omset Sales</h3>
                    <p class="text-sm font-extrabold text-slate-800 dark:text-slate-100">7 Hari Terakhir</p>
                </div>
                <Link href="/seller/sales" class="text-xs font-bold text-indigo-600 dark:text-indigo-400 flex items-center gap-0.5">
                    Laporan <ChevronRight class="h-3.5 w-3.5" />
                </Link>
            </div>

            <!-- Mini Bar Chart -->
            <div class="pt-2 flex items-end justify-between gap-1.5 h-32">
                {#each chartData as c}
                    {@const heightPercent = maxOmsetChart > 0 ? Math.max((c.omset / maxOmsetChart) * 100, 10) : 10}
                    <div class="flex-1 flex flex-col items-center gap-1 group relative">
                        <div
                            class="w-full rounded-t-lg bg-indigo-500 group-hover:bg-indigo-600 transition-all relative"
                            style="height: {heightPercent}%;"
                        >
                            <!-- Tooltip on hover/touch -->
                            <div class="absolute -top-7 left-1/2 -translate-x-1/2 hidden group-hover:block bg-slate-900 text-white text-[9px] font-bold px-1.5 py-0.5 rounded whitespace-nowrap shadow-md z-10">
                                {formatRupiah(c.omset)}
                            </div>
                        </div>
                        <span class="text-[9px] font-semibold text-slate-400">{c.day}</span>
                    </div>
                {/each}
            </div>
        </div>

        <!-- Transaksi Barang Keluar Terbaru -->
        <div class="space-y-3">
            <div class="flex items-center justify-between px-1">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Penjualan Terbaru</h3>
                <Link href="/seller/sales" class="text-xs font-bold text-indigo-600 dark:text-indigo-400">Lihat Semua</Link>
            </div>

            <div class="space-y-2">
                {#each recentTransactions as tx}
                    <div class="rounded-2xl bg-white dark:bg-slate-900 p-3.5 shadow-xs border border-slate-100 dark:border-slate-800 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
                                <PackageCheck class="h-5 w-5" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-slate-800 dark:text-slate-100 truncate">{tx.product_name}</p>
                                <p class="text-[11px] text-slate-400 truncate">{tx.variant_info} • {tx.store_name}</p>
                            </div>
                        </div>

                        <div class="text-right shrink-0">
                            <span class="text-xs font-extrabold text-emerald-600 dark:text-emerald-400 block">+{tx.qty} Pcs</span>
                            <span class="text-[10px] text-slate-400 font-medium">{formatRupiah(tx.total_omset)}</span>
                        </div>
                    </div>
                {:else}
                    <div class="rounded-2xl bg-white dark:bg-slate-900 p-8 text-center text-slate-400 border border-slate-100 dark:border-slate-800 text-xs">
                        Belum ada transaksi barang keluar terbaru.
                    </div>
                {/each}
            </div>
        </div>
    </div>
</SellerMobileLayout>
