<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import { Link } from '@inertiajs/svelte';
    import { Calculator, ArrowLeft, Store } from '@lucide/svelte';

    interface SettlementItem {
        store_id: number | null;
        gross_sales: number;
        total_orders: number;
        store?: { store_name: string; marketplace?: string };
    }

    let { settlements = [] }: { settlements?: SettlementItem[] } = $props();

    function formatRupiah(num: number): string {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num);
    }
</script>

<AdminLayout title="Analisis Toko & Settlement Marketplace" breadcrumbs={[{ name: 'Mutasi Stok' }, { name: 'Pengeluaran (Stock Out)', url: '/superadmin/warehouse/stock-out' }, { name: 'Analisis Toko' }]}>
    <!-- Header -->
    <div class="mb-8 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <Link
                href="/superadmin/warehouse/stock-out"
                class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-2.5 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors shadow-xs"
            >
                <ArrowLeft class="h-5 w-5" />
            </Link>
            <div>
                <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                    <Calculator class="h-7 w-7 text-emerald-600" />
                    Analisis Omset Per Toko Marketplace
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Ringkasan total transaksi dan estimasi bruto penjualan per toko</p>
            </div>
        </div>
    </div>

    <!-- Settlement Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {#each settlements as item}
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-100 dark:border-slate-800 space-y-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950">
                        <Store class="h-6 w-6" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{item.store?.store_name ?? 'Toko Umum / Direct'}</h3>
                        <p class="text-xs text-slate-400">{item.store?.marketplace ?? 'Marketplace'}</p>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 dark:border-slate-800 space-y-2">
                    <div class="flex justify-between text-xs">
                        <span class="text-slate-500">Total Order Out:</span>
                        <span class="font-bold text-slate-800 dark:text-slate-100">{item.total_orders} transaksi</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Omset Penjualan:</span>
                        <span class="font-bold text-emerald-600">{formatRupiah(Number(item.gross_sales))}</span>
                    </div>
                </div>
            </div>
        {:else}
            <div class="col-span-full rounded-2xl bg-white dark:bg-slate-900 p-12 text-center text-slate-400 border border-slate-100 dark:border-slate-800">
                Belum ada data toko settlement.
            </div>
        {/each}
    </div>
</AdminLayout>
