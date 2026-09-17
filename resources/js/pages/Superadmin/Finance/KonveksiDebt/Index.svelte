<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import { router, Link } from '@inertiajs/svelte';
    import { Receipt, Search, Eye, Factory, DollarSign, CheckCircle2, AlertCircle } from '@lucide/svelte';

    interface KonveksiDebtItem {
        id: number;
        name: string;
        contact: string | null;
        color: string | null;
        total_tagihan: number;
        total_paid: number;
        sisa_tagihan: number;
        status: string;
    }

    let {
        konveksiDebts = [],
        summary = { total_konveksis: 0, total_tagihan: 0, total_paid: 0, total_sisa_tagihan: 0 },
        filters = { search: '' },
    }: {
        konveksiDebts?: KonveksiDebtItem[];
        summary?: { total_konveksis: number; total_tagihan: number; total_paid: number; total_sisa_tagihan: number };
        filters?: { search?: string };
    } = $props();

    let search = $state(filters.search ?? '');

    function formatRupiah(num: number): string {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num || 0);
    }

    function applyFilter() {
        router.get('/superadmin/finance/konveksi-debt', { search }, { preserveState: true, replace: true });
    }
</script>

<AdminLayout title="Tagihan Konveksi" breadcrumbs={[{ name: 'Keuangan' }, { name: 'Tagihan Konveksi' }]}>
    <!-- Header -->
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                <Receipt class="h-7 w-7 text-amber-500" />
                Monitoring Tagihan Vendor Konveksi
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Pencatatan tagihan barang masuk vendor & riwayat pembayaran DP/Lunas</p>
        </div>
    </div>

    <!-- Summary Stats Grid -->
    <div class="mb-8 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Total Vendor Konveksi</span>
            <span class="text-2xl font-bold text-slate-800 dark:text-slate-100 mt-1 block">{summary.total_konveksis} Vendor</span>
        </div>

        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Total Tagihan Produksi</span>
            <span class="text-xl font-bold text-sky-600 dark:text-sky-400 mt-1 block">{formatRupiah(summary.total_tagihan)}</span>
        </div>

        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Total Pembayaran (Paid)</span>
            <span class="text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-1 block">{formatRupiah(summary.total_paid)}</span>
        </div>

        <div class="rounded-2xl bg-gradient-to-br from-amber-900 to-amber-950 p-5 text-white shadow-md">
            <span class="block text-xs font-semibold uppercase tracking-wider text-amber-200">Sisa Tagihan Belum Dibayar</span>
            <span class="text-xl font-black text-amber-300 mt-1 block">{formatRupiah(summary.total_sisa_tagihan)}</span>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="mb-6 rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 flex justify-between items-center">
        <div class="relative w-full sm:w-80">
            <Search class="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
            <input
                type="text"
                bind:value={search}
                onkeyup={(e) => e.key === 'Enter' && applyFilter()}
                placeholder="Cari nama konveksi / kontak..."
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 pl-10 pr-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500"
            />
        </div>
    </div>

    <!-- Table Card -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-xs border border-slate-100 dark:border-slate-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 dark:bg-slate-950/50 text-slate-500 font-semibold border-b border-slate-100 dark:border-slate-800 uppercase text-[11px] tracking-wider">
                    <tr>
                        <th class="px-6 py-4 w-12">#</th>
                        <th class="px-6 py-4">Vendor Konveksi</th>
                        <th class="px-6 py-4 text-right">Total Tagihan</th>
                        <th class="px-6 py-4 text-right">Total Dibayar</th>
                        <th class="px-6 py-4 text-right">Sisa Tagihan</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    {#each konveksiDebts as item, index}
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="px-6 py-4 text-xs text-slate-400">{index + 1}</td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                                    <Factory class="h-4 w-4 text-amber-500" />
                                    <span>{item.name}</span>
                                </div>
                                <span class="text-xs text-slate-400 block mt-0.5">{item.contact || 'Tanpa Kontak'}</span>
                            </td>
                            <td class="px-6 py-4 text-right font-medium text-sky-600 dark:text-sky-400">{formatRupiah(item.total_tagihan)}</td>
                            <td class="px-6 py-4 text-right font-medium text-emerald-600 dark:text-emerald-400">{formatRupiah(item.total_paid)}</td>
                            <td class="px-6 py-4 text-right font-black text-amber-600 dark:text-amber-400 text-base">{formatRupiah(item.sisa_tagihan)}</td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold
                                    {item.status === 'Lunas' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
                                     item.status === 'Sebagian / DP' ? 'bg-amber-50 text-amber-700 border border-amber-200' :
                                     'bg-rose-50 text-rose-700 border border-rose-200'}">
                                    {#if item.status === 'Lunas'}
                                        <CheckCircle2 class="h-3.5 w-3.5" />
                                    {:else}
                                        <AlertCircle class="h-3.5 w-3.5" />
                                    {/if}
                                    {item.status}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <Link
                                    href="/superadmin/finance/konveksi-debt/{item.id}"
                                    class="inline-flex items-center gap-1 rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700 shadow-xs transition-colors"
                                >
                                    <Eye class="h-3.5 w-3.5" />
                                    Bayar / Detail
                                </Link>
                            </td>
                        </tr>
                    {:else}
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-400">Belum ada tagihan vendor konveksi.</td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    </div>
</AdminLayout>
