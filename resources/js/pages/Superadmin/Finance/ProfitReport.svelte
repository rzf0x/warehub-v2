<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import Modal from '@/components/Modal.svelte';
    import { router } from '@inertiajs/svelte';
    import { TrendingUp, Printer, Calendar, Search, ArrowUpRight, DollarSign, PackageCheck, Coins, Layers, FileText, CheckSquare, Square } from '@lucide/svelte';

    interface PeriodRow {
        period_id: number;
        period_name: string;
        start_date: string | null;
        end_date: string | null;
        is_public: boolean;
        qty_keluar: number;
        hpp_modal: number;
        bruto: number;
        penghasilan_bersih: number;
        est_untung: number;
    }

    let {
        periods = [],
        reportData = [],
        summary = {
            total_periods: 0,
            total_qty_keluar: 0,
            total_hpp_modal: 0,
            total_bruto: 0,
            total_penghasilan_bersih: 0,
            total_est_untung: 0,
        },
        filters = { period_id: '', search: '' },
    }: {
        periods?: { id: number; name: string }[];
        reportData?: PeriodRow[];
        summary?: {
            total_periods: number;
            total_qty_keluar: number;
            total_hpp_modal: number;
            total_bruto: number;
            total_penghasilan_bersih: number;
            total_est_untung: number;
        };
        filters?: { period_id?: string; search?: string };
    } = $props();

    let periodId = $state(filters.period_id ?? '');
    let search = $state(filters.search ?? '');

    // State Bulk Selection Checkboxes
    let selectedPeriodIds = $state<number[]>([]);

    let isAllSelected = $derived(
        reportData.length > 0 && selectedPeriodIds.length === reportData.length
    );

    function toggleSelectAll() {
        if (isAllSelected) {
            selectedPeriodIds = [];
        } else {
            selectedPeriodIds = reportData.map((item) => item.period_id);
        }
    }

    function toggleSelectRow(pId: number) {
        if (selectedPeriodIds.includes(pId)) {
            selectedPeriodIds = selectedPeriodIds.filter((id) => id !== pId);
        } else {
            selectedPeriodIds = [...selectedPeriodIds, pId];
        }
    }

    // State Modal Print PDF
    let showPrintModal = $state(false);
    let selectedPrintPeriodId = $state('');

    function formatRupiah(num: number): string {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num || 0);
    }

    function formatDate(dateStr: string | null): string {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        return new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }).format(d);
    }

    function applyFilter() {
        selectedPeriodIds = [];
        router.get(
            '/superadmin/finance/profit-report',
            { period_id: periodId, search: search },
            { preserveState: true, replace: true }
        );
    }

    function openPrintModal(pId: string = '') {
        selectedPrintPeriodId = pId;
        showPrintModal = true;
    }

    function handlePrintPdf() {
        const url = `/superadmin/finance/profit-report/pdf?period_id=${selectedPrintPeriodId}`;
        window.open(url, '_blank');
        showPrintModal = false;
    }

    function handlePrintBulkPdf() {
        if (selectedPeriodIds.length === 0) return;
        const url = `/superadmin/finance/profit-report/pdf?period_ids=${selectedPeriodIds.join(',')}`;
        window.open(url, '_blank');
    }
</script>

<AdminLayout title="Laporan Keuntungan" breadcrumbs={[{ name: 'Keuangan' }, { name: 'Keuntungan' }]}>
    <!-- Header -->
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                <TrendingUp class="h-7 w-7 text-emerald-500" />
                Laporan Keuntungan Per-Periode
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Rekapitulasi HPP, Bruto, Penghasilan Bersih, dan Estimasi Keuntungan per periode pembukuan</p>
        </div>

        <div class="flex items-center gap-2">
            {#if selectedPeriodIds.length > 0}
                <button
                    type="button"
                    onclick={handlePrintBulkPdf}
                    class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-emerald-600/30 hover:bg-emerald-700 transition-colors animate-pulse"
                >
                    <Printer class="h-4 w-4" />
                    Cetak PDF Bulk ({selectedPeriodIds.length} Periode)
                </button>
            {/if}

            <button
                type="button"
                onclick={() => openPrintModal(periodId)}
                class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/30 hover:bg-indigo-700 transition-colors"
            >
                <Printer class="h-4 w-4" />
                Cetak / Export PDF Custom
            </button>
        </div>
    </div>

    <!-- Summary Stats Grid -->
    <div class="mb-8 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <!-- Card QTY Keluar -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800 flex flex-col justify-between">
            <div>
                <span class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-slate-400">
                    <PackageCheck class="h-4 w-4 text-sky-500" />
                    QTY Keluar
                </span>
                <span class="text-2xl font-bold text-slate-800 dark:text-slate-100 mt-2 block">{summary.total_qty_keluar.toLocaleString('id-ID')} Pcs</span>
            </div>
            <span class="text-[11px] text-slate-400 mt-2">Total barang keluar</span>
        </div>

        <!-- Card HPP Modal -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800 flex flex-col justify-between">
            <div>
                <span class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-slate-400">
                    <Coins class="h-4 w-4 text-amber-500" />
                    HPP (Modal)
                </span>
                <span class="text-lg font-bold text-amber-600 dark:text-amber-400 mt-2 block truncate">{formatRupiah(summary.total_hpp_modal)}</span>
            </div>
            <span class="text-[11px] text-slate-400 mt-2">Modal harga pokok</span>
        </div>

        <!-- Card Bruto -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800 flex flex-col justify-between">
            <div>
                <span class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-slate-400">
                    <Layers class="h-4 w-4 text-sky-500" />
                    Bruto
                </span>
                <span class="text-lg font-bold text-sky-600 dark:text-sky-400 mt-2 block truncate">{formatRupiah(summary.total_bruto)}</span>
            </div>
            <span class="text-[11px] text-slate-400 mt-2">Total kotor omset</span>
        </div>

        <!-- Card Penghasilan Bersih -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800 flex flex-col justify-between">
            <div>
                <span class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-slate-400">
                    <DollarSign class="h-4 w-4 text-indigo-500" />
                    Penghasilan Bersih
                </span>
                <span class="text-lg font-bold text-indigo-600 dark:text-indigo-400 mt-2 block truncate">{formatRupiah(summary.total_penghasilan_bersih)}</span>
            </div>
            <span class="text-[11px] text-slate-400 mt-2">Penjualan bersih</span>
        </div>

        <!-- Card Est. Untung -->
        <div class="rounded-2xl bg-gradient-to-br from-emerald-900 to-teal-950 p-5 text-white shadow-md flex flex-col justify-between">
            <div>
                <span class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-emerald-200">
                    <ArrowUpRight class="h-4 w-4 text-emerald-300" />
                    Est. Untung
                </span>
                <span class="text-xl font-black text-emerald-300 mt-2 block truncate">{formatRupiah(summary.total_est_untung)}</span>
            </div>
            <span class="text-[11px] text-emerald-200/80 mt-2">Bersih - HPP Modal</span>
        </div>
    </div>

    <!-- Bulk Selection Floating / Notice Bar -->
    {#if selectedPeriodIds.length > 0}
        <div class="mb-4 rounded-2xl bg-indigo-600 text-white p-4 shadow-xl flex items-center justify-between transition-all">
            <div class="flex items-center gap-3">
                <CheckSquare class="h-6 w-6 text-indigo-200" />
                <div>
                    <p class="font-bold text-sm">{selectedPeriodIds.length} Periode Ditandai (Bulk Selected)</p>
                    <p class="text-xs text-indigo-100/80">Siap untuk dicetak sekaligus ke dalam laporan PDF gabungan</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    onclick={handlePrintBulkPdf}
                    class="inline-flex items-center gap-2 rounded-xl bg-white text-indigo-700 px-4 py-2 text-xs font-bold shadow-sm hover:bg-indigo-50 transition-colors"
                >
                    <Printer class="h-4 w-4" />
                    Cetak PDF {selectedPeriodIds.length} Periode
                </button>

                <button
                    type="button"
                    onclick={() => (selectedPeriodIds = [])}
                    class="rounded-xl bg-indigo-700 hover:bg-indigo-800 text-indigo-100 px-3 py-2 text-xs font-semibold transition-colors"
                >
                    Batal Pilih
                </button>
            </div>
        </div>
    {/if}

    <!-- Filters Bar -->
    <div class="mb-6 rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <!-- Filter Period Dropdown -->
        <div>
            <label for="f_period" class="block text-[11px] font-semibold text-slate-400 uppercase mb-1">Filter Periode Tampilan</label>
            <select
                id="f_period"
                bind:value={periodId}
                onchange={applyFilter}
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-emerald-500"
            >
                <option value="">Semua Periode</option>
                {#each periods as p}
                    <option value={p.id}>{p.name}</option>
                {/each}
            </select>
        </div>

        <!-- Search input -->
        <div>
            <label for="f_search" class="block text-[11px] font-semibold text-slate-400 uppercase mb-1">Cari Nama Periode</label>
            <div class="relative">
                <Search class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                <input
                    id="f_search"
                    type="text"
                    placeholder="Nama periode..."
                    bind:value={search}
                    oninput={applyFilter}
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 pl-9 pr-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-emerald-500"
                />
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-end gap-2">
            <button
                type="button"
                onclick={() => { periodId = ''; search = ''; applyFilter(); }}
                class="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-100 dark:bg-slate-800 px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors"
            >
                Reset Filter
            </button>
        </div>
    </div>

    <!-- Table Card -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-xs border border-slate-100 dark:border-slate-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 dark:bg-slate-950/50 text-slate-500 font-semibold border-b border-slate-100 dark:border-slate-800 uppercase text-[11px] tracking-wider">
                    <tr>
                        <th class="px-4 py-4 w-10 text-center">
                            <input
                                type="checkbox"
                                checked={isAllSelected}
                                onchange={toggleSelectAll}
                                class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                                title="Pilih Semua Periode (Bulk Select)"
                            />
                        </th>
                        <th class="px-4 py-4 w-12 text-center">#</th>
                        <th class="px-6 py-4">Periode</th>
                        <th class="px-6 py-4 text-center">QTY Keluar</th>
                        <th class="px-6 py-4 text-right">HPP (Modal)</th>
                        <th class="px-6 py-4 text-right">Bruto</th>
                        <th class="px-6 py-4 text-right">Penghasilan Bersih</th>
                        <th class="px-6 py-4 text-right">Est. Untung</th>
                        <th class="px-6 py-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    {#each reportData as item, index}
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors {selectedPeriodIds.includes(item.period_id) ? 'bg-indigo-50/40 dark:bg-indigo-950/20' : ''}">
                            <td class="px-4 py-4 text-center">
                                <input
                                    type="checkbox"
                                    checked={selectedPeriodIds.includes(item.period_id)}
                                    onchange={() => toggleSelectRow(item.period_id)}
                                    class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                                />
                            </td>
                            <td class="px-4 py-4 text-xs text-slate-400 text-center">{index + 1}</td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                                    <Calendar class="h-4 w-4 text-emerald-500 shrink-0" />
                                    <span>{item.period_name}</span>
                                </div>
                                <div class="text-[11px] text-slate-400 mt-0.5 ml-6">
                                    {formatDate(item.start_date)} - {formatDate(item.end_date)}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center font-bold text-slate-800 dark:text-slate-100">
                                {item.qty_keluar.toLocaleString('id-ID')} Pcs
                            </td>
                            <td class="px-6 py-4 text-right font-medium text-amber-600 dark:text-amber-400">
                                {formatRupiah(item.hpp_modal)}
                            </td>
                            <td class="px-6 py-4 text-right font-medium text-sky-600 dark:text-sky-400">
                                {formatRupiah(item.bruto)}
                            </td>
                            <td class="px-6 py-4 text-right font-medium text-indigo-600 dark:text-indigo-400">
                                {formatRupiah(item.penghasilan_bersih)}
                            </td>
                            <td class="px-6 py-4 text-right font-black text-base">
                                <span class="{item.est_untung >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'}">
                                    {formatRupiah(item.est_untung)}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <button
                                    type="button"
                                    onclick={() => openPrintModal(item.period_id.toString())}
                                    title="Pilih Cetak PDF Periode Ini"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-indigo-600 hover:text-white dark:hover:bg-indigo-600 transition-colors"
                                >
                                    <Printer class="h-3.5 w-3.5" />
                                    Cetak PDF
                                </button>
                            </td>
                        </tr>
                    {:else}
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-slate-400">Belum ada data laporan keuntungan per-periode.</td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Custom Select Cetak PDF -->
    <Modal
        show={showPrintModal}
        title="Cetak PDF Laporan Keuntungan"
        maxWidth="md"
        onclose={() => (showPrintModal = false)}
    >
        <div class="space-y-4">
            <div class="rounded-xl bg-indigo-50 dark:bg-indigo-950/40 p-4 border border-indigo-100 dark:border-indigo-900/60 flex items-start gap-3">
                <FileText class="h-5 w-5 text-indigo-600 dark:text-indigo-400 shrink-0 mt-0.5" />
                <p class="text-xs text-indigo-900 dark:text-indigo-200 leading-relaxed">
                    Pilih periode yang ingin Anda cetak ke dalam format dokumen PDF. Anda dapat memilih <strong>Semua Periode</strong> atau salah satu <strong>Periode Spesifik</strong>.
                </p>
            </div>

            <div>
                <label for="modal_print_period" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">
                    Pilih Periode Yang Dicetak
                </label>
                <select
                    id="modal_print_period"
                    bind:value={selectedPrintPeriodId}
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                >
                    <option value="">-- Semua Periode (Gabungan) --</option>
                    {#each periods as p}
                        <option value={p.id}>{p.name}</option>
                    {/each}
                </select>
            </div>

            <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-100 dark:border-slate-800">
                <button
                    type="button"
                    onclick={() => (showPrintModal = false)}
                    class="rounded-xl px-4 py-2.5 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                >
                    Batal
                </button>

                <button
                    type="button"
                    onclick={handlePrintPdf}
                    class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-xs font-semibold text-white shadow-lg shadow-indigo-600/30 hover:bg-indigo-700 transition-colors"
                >
                    <Printer class="h-4 w-4" />
                    Cetak / Buka PDF
                </button>
            </div>
        </div>
    </Modal>
</AdminLayout>
