<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import { router, Link } from '@inertiajs/svelte';
    import { Wallet, Search, Plus, Eye, BarChart3, Calculator, DollarSign, UserCheck, ArrowUpRight, ArrowDownLeft } from '@lucide/svelte';

    interface SellerDebtItem {
        id: number;
        seller_name: string;
        phone: string | null;
        stock_in_hpp: number;
        stock_out_hpp: number;
        adjustments: number;
        net_debt: number;
    }

    interface PeriodOption {
        id: number;
        name: string;
    }

    let {
        sellerDebts = [],
        periods = [],
        summary = { total_sellers: 0, total_stock_in_hpp: 0, total_stock_out_hpp: 0, total_net_debt: 0 },
        filters = { search: '', period_id: '' },
    }: {
        sellerDebts?: SellerDebtItem[];
        periods?: PeriodOption[];
        summary?: { total_sellers: number; total_stock_in_hpp: number; total_stock_out_hpp: number; total_net_debt: number };
        filters?: { search?: string; period_id?: string };
    } = $props();

    let search = $state(filters.search ?? '');
    let periodId = $state(filters.period_id ?? '');

    let isAdjustmentModalOpen = $state(false);
    let selectedSellerId = $state<number | string>('');
    let adjustmentForm = $state({
        seller_id: '',
        period_id: '',
        amount: 0,
        adjustment_date: new Date().toISOString().split('T')[0],
        note: '',
    });

    function formatRupiah(num: number): string {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num || 0);
    }

    function applyFilter() {
        router.get('/superadmin/finance/seller-debt', { search, period_id: periodId }, { preserveState: true, replace: true });
    }

    function openAdjustmentModal(sellerId: number) {
        selectedSellerId = sellerId;
        adjustmentForm.seller_id = String(sellerId);
        adjustmentForm.period_id = periodId || (periods[0]?.id ? String(periods[0].id) : '');
        adjustmentForm.amount = 0;
        adjustmentForm.note = '';
        isAdjustmentModalOpen = true;
    }

    function submitAdjustment() {
        router.post('/superadmin/finance/seller-debt/adjustment', adjustmentForm, {
            onSuccess: () => {
                isAdjustmentModalOpen = false;
            },
        });
    }
</script>

<AdminLayout title="Perhitungan Hutang Seller" breadcrumbs={[{ name: 'Keuangan' }, { name: 'Hutang Seller' }]}>
    <!-- Header -->
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                <Wallet class="h-7 w-7 text-purple-500" />
                Manajemen Hutang Seller / Mitra
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Perhitungan otomatis saldo hutang modal per seller (Stock In HPP - Stock Out HPP)</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Link
                href="/superadmin/finance/seller-debt/chart"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3.5 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors shadow-xs"
            >
                <BarChart3 class="h-4 w-4 text-purple-500" />
                Grafik Analisis Hutang
            </Link>

            <Link
                href="/superadmin/finance/seller-debt/konveksi-simulation"
                class="inline-flex items-center gap-2 rounded-xl bg-purple-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-purple-600/30 hover:bg-purple-700 transition-colors"
            >
                <Calculator class="h-4 w-4" />
                Simulasi Profit Konveksi
            </Link>
        </div>
    </div>

    <!-- Summary Stats Grid -->
    <div class="mb-8 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Total Mitra/Seller</span>
            <span class="text-2xl font-bold text-slate-800 dark:text-slate-100 mt-1 block">{summary.total_sellers} Seller</span>
        </div>

        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Total Stock In HPP</span>
            <span class="text-xl font-bold text-sky-600 dark:text-sky-400 mt-1 block">{formatRupiah(summary.total_stock_in_hpp)}</span>
        </div>

        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Total Stock Out HPP</span>
            <span class="text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-1 block">{formatRupiah(summary.total_stock_out_hpp)}</span>
        </div>

        <div class="rounded-2xl bg-gradient-to-br from-purple-900 to-indigo-900 p-5 text-white shadow-md">
            <span class="block text-xs font-semibold uppercase tracking-wider text-purple-200">Total Net Sisa Hutang</span>
            <span class="text-xl font-black text-amber-300 mt-1 block">{formatRupiah(summary.total_net_debt)}</span>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="mb-6 rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="w-full sm:w-64">
            <select
                bind:value={periodId}
                onchange={applyFilter}
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-purple-500"
            >
                <option value="">Semua Periode</option>
                {#each periods as p}
                    <option value={p.id}>{p.name}</option>
                {/each}
            </select>
        </div>

        <div class="relative w-full sm:w-80">
            <Search class="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
            <input
                type="text"
                bind:value={search}
                onkeyup={(e) => e.key === 'Enter' && applyFilter()}
                placeholder="Cari nama seller / telepon..."
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 pl-10 pr-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-purple-500"
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
                        <th class="px-6 py-4">Seller / Mitra</th>
                        <th class="px-6 py-4 text-right">Stock In HPP (+)</th>
                        <th class="px-6 py-4 text-right">Stock Out HPP (-)</th>
                        <th class="px-6 py-4 text-right">Penyesuaian</th>
                        <th class="px-6 py-4 text-right">Sisa Net Hutang</th>
                        <th class="px-6 py-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    {#each sellerDebts as item, index}
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="px-6 py-4 text-xs text-slate-400">{index + 1}</td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                                    <UserCheck class="h-4 w-4 text-purple-500" />
                                    <span>{item.seller_name}</span>
                                </div>
                                <span class="text-xs text-slate-400 block mt-0.5">{item.phone || 'Tanpa No Telp'}</span>
                            </td>
                            <td class="px-6 py-4 text-right font-medium text-sky-600 dark:text-sky-400">{formatRupiah(item.stock_in_hpp)}</td>
                            <td class="px-6 py-4 text-right font-medium text-emerald-600 dark:text-emerald-400">{formatRupiah(item.stock_out_hpp)}</td>
                            <td class="px-6 py-4 text-right font-medium {item.adjustments >= 0 ? 'text-indigo-600' : 'text-rose-500'}">
                                {formatRupiah(item.adjustments)}
                            </td>
                            <td class="px-6 py-4 text-right font-black text-amber-600 dark:text-amber-400 text-base">
                                {formatRupiah(item.net_debt)}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button
                                        onclick={() => openAdjustmentModal(item.id)}
                                        class="rounded-lg p-2 text-purple-600 hover:bg-purple-50 dark:hover:bg-purple-950/50 transition-colors"
                                        title="Tambah Penyesuaian Saldo"
                                    >
                                        <Plus class="h-4 w-4" />
                                    </button>

                                    <Link
                                        href="/superadmin/finance/seller-debt/{item.id}"
                                        class="rounded-lg p-2 text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 transition-colors"
                                        title="Lihat Detail Mutasi"
                                    >
                                        <Eye class="h-4 w-4" />
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    {:else}
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-400">Belum ada data hutang seller.</td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Penyesuaian Saldo -->
    {#if isAdjustmentModalOpen}
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4">
            <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-2xl border border-slate-100 dark:border-slate-800 space-y-5">
                <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 border-b border-slate-100 dark:border-slate-800 pb-3">
                    Tambah Penyesuaian Saldo Hutang
                </h3>

                <form onsubmit={(e) => { e.preventDefault(); submitAdjustment(); }} class="space-y-4">
                    <div>
                        <label for="adjustment_amount" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Nominal Adjustment (Rp)</label>
                        <input
                            id="adjustment_amount"
                            type="number"
                            step="1000"
                            bind:value={adjustmentForm.amount}
                            required
                            placeholder="Gunakan tanda (-) untuk pengurangan"
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-purple-500"
                        />
                        <span class="text-[11px] text-slate-400 block mt-1">Masukkan nominal positif (+) untuk menambah hutang, atau negatif (-) untuk pelunasan/potongan.</span>
                    </div>

                    <div>
                        <label for="adjustment_date" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Tanggal</label>
                        <input
                            id="adjustment_date"
                            type="date"
                            bind:value={adjustmentForm.adjustment_date}
                            required
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-purple-500"
                        />
                    </div>

                    <div>
                        <label for="adjustment_note" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Catatan / Alasan</label>
                        <input
                            id="adjustment_note"
                            type="text"
                            bind:value={adjustmentForm.note}
                            placeholder="Contoh: Pembayaran DP modal seller via BCA..."
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-purple-500"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button
                            type="button"
                            onclick={() => (isAdjustmentModalOpen = false)}
                            class="rounded-xl border border-slate-200 dark:border-slate-800 px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="rounded-xl bg-purple-600 px-5 py-2 text-xs font-semibold text-white hover:bg-purple-700 shadow-md shadow-purple-600/30"
                        >
                            Simpan Adjustment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    {/if}
</AdminLayout>
