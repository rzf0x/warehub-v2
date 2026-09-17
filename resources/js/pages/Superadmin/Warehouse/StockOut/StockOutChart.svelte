<script lang="ts">
    import AdminLayout from '@/Layouts/AdminLayout.svelte';
    import { Link } from '@inertiajs/svelte';
    import { BarChart3, ArrowLeft, TrendingUp } from '@lucide/svelte';

    interface MonthlyData {
        month_key: string;
        total_sales: number;
        total_transactions: number;
    }

    let { monthlyOut = [] }: { monthlyOut?: MonthlyData[] } = $props();

    let maxSales = $derived(
        Math.max(...monthlyOut.map((m) => Number(m.total_sales)), 1000000)
    );

    function formatRupiah(num: number): string {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num);
    }
</script>

<AdminLayout title="Visualisasi Omset Penjualan" breadcrumbs={[{ name: 'Mutasi Stok' }, { name: 'Pengeluaran (Stock Out)', url: '/superadmin/warehouse/stock-out' }, { name: 'Visualisasi Omset' }]}>
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
                    <BarChart3 class="h-7 w-7 text-indigo-600" />
                    Visualisasi Omset Penjualan Bulanan (Stock Out)
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Grafik tren transaksi pengeluaran barang & bruto omset penjualan</p>
            </div>
        </div>
    </div>

    <!-- Chart Visual Card -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 p-8 shadow-xs border border-slate-100 dark:border-slate-800 space-y-6 mb-8">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
            <div>
                <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">Tren Pergerakan Omset</h3>
                <p class="text-xs text-slate-500">Perbandingan total penjualan (Rp) per bulan</p>
            </div>
            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600">
                <TrendingUp class="h-4 w-4" /> 12 Bulan Terakhir
            </span>
        </div>

        <!-- Bar Display -->
        <div class="h-80 flex items-end justify-between gap-6 pt-12 px-6 border-b border-slate-100 dark:border-slate-800">
            {#each monthlyOut as item}
                <div class="flex-1 flex flex-col items-center gap-3 h-full justify-end group">
                    <div class="w-full flex items-end justify-center h-full">
                        <div
                            style="height: {(Number(item.total_sales) / maxSales) * 100}%;"
                            class="w-full max-w-[48px] min-h-[8px] bg-gradient-to-t from-indigo-600 to-indigo-400 rounded-t-xl transition-all group-hover:from-indigo-700 group-hover:to-indigo-500 relative"
                            title="{item.month_key}: {formatRupiah(Number(item.total_sales))}"
                        ></div>
                    </div>
                    <span class="text-xs font-mono font-semibold text-slate-600 dark:text-slate-300">{item.month_key}</span>
                </div>
            {:else}
                <div class="w-full text-center py-12 text-slate-400">Belum ada data grafik penjualan.</div>
            {/each}
        </div>
    </div>

    <!-- Data Table -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-xs border border-slate-100 dark:border-slate-800 overflow-hidden">
        <div class="p-6 border-b border-slate-100 dark:border-slate-800">
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Rincian Per Bulan</h3>
        </div>
        <table class="w-full text-left text-sm text-slate-600 dark:text-slate-400">
            <thead class="bg-slate-50 dark:bg-slate-800/50 text-xs uppercase font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800">
                <tr>
                    <th class="px-6 py-4">Bulan</th>
                    <th class="px-6 py-4">Jumlah Transaksi</th>
                    <th class="px-6 py-4">Total Omset Penjualan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                {#each monthlyOut as item}
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                        <td class="px-6 py-4 font-mono font-bold text-slate-800 dark:text-slate-100">{item.month_key}</td>
                        <td class="px-6 py-4 font-semibold text-slate-700 dark:text-slate-200">{item.total_transactions} transaksi</td>
                        <td class="px-6 py-4 font-bold text-indigo-600">{formatRupiah(Number(item.total_sales))}</td>
                    </tr>
                {/each}
            </tbody>
        </table>
    </div>
</AdminLayout>
