<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import ConfirmDialog from '@/components/ConfirmDialog.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import { router, Link } from '@inertiajs/svelte';
    import {
        ArrowDownLeft,
        Search,
        Plus,
        Trash2,
        Edit2,
        Building2,
        UserCheck,
        PackageCheck,
        DollarSign,
        RotateCcw,
    } from '@lucide/svelte';

    interface SellerRecord {
        id?: number;
        seller_name?: string;
        name?: string;
    }

    interface ProductRecord {
        product_name?: string;
        seller?: SellerRecord;
    }

    interface Item {
        id: number;
        product_variant?: {
            product?: ProductRecord;
        };
        konveksi?: { id?: number; name: string };
        qty: number;
        price: number;
    }

    interface StockInRecord {
        id: number;
        invoice_number: string;
        date: string;
        note: string | null;
        warehouse?: { id: number; name: string };
        period?: { id: number; name: string };
        user?: { name: string };
        items: Item[];
    }

    interface Option {
        id: number;
        name?: string;
        seller_name?: string;
    }

    interface SummaryStats {
        total_transactions: number;
        total_qty: number;
        total_nominal: number;
        total_sellers: number;
    }

    let {
        stockIns,
        warehouses = [],
        periods = [],
        sellers = [],
        konveksis = [],
        summary = {
            total_transactions: 0,
            total_qty: 0,
            total_nominal: 0,
            total_sellers: 0,
        },
        filters = {
            search: '',
            warehouse_id: '',
            period_id: '',
            seller_id: '',
            konveksi_id: '',
            start_date: '',
            end_date: '',
        },
    }: {
        stockIns: {
            data: StockInRecord[];
            links: any[];
            current_page: number;
            last_page: number;
            total: number;
        };
        warehouses?: Option[];
        periods?: Option[];
        sellers?: Option[];
        konveksis?: Option[];
        summary?: SummaryStats;
        filters?: {
            search?: string;
            warehouse_id?: string;
            period_id?: string;
            seller_id?: string;
            konveksi_id?: string;
            start_date?: string;
            end_date?: string;
        };
    } = $props();

    let search = $state(filters.search ?? '');
    let warehouseId = $state(filters.warehouse_id ?? '');
    let periodId = $state(filters.period_id ?? '');
    let sellerId = $state(filters.seller_id ?? '');
    let konveksiId = $state(filters.konveksi_id ?? '');
    let startDate = $state(filters.start_date ?? '');
    let endDate = $state(filters.end_date ?? '');

    let isConfirmDeleteOpen = $state(false);
    let selectedRecord = $state<StockInRecord | null>(null);

    let hasActiveFilter = $derived(
        Boolean(search || warehouseId || periodId || sellerId || konveksiId || startDate || endDate)
    );

    function formatDate(dateStr: string): string {
        if (!dateStr) return '-';
        const cleanStr = dateStr.split('T')[0];
        const [year, month, day] = cleanStr.split('-');
        if (!year || !month || !day) return dateStr;
        const d = new Date(Number(year), Number(month) - 1, Number(day));
        return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
    }

    function formatRupiah(num: number | string | null | undefined): string {
        if (num === null || num === undefined || num === '') return '-';
        const val = typeof num === 'string' ? parseFloat(num) : num;
        if (isNaN(val)) return '-';
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(val);
    }

    function getUniqueSellers(items: Item[]): string[] {
        const set = new Set<string>();
        for (const it of items || []) {
            const name = it.product_variant?.product?.seller?.seller_name || it.product_variant?.product?.seller?.name;
            if (name) set.add(name);
        }
        return Array.from(set);
    }

    function getUniqueKonveksis(items: Item[]): string[] {
        const set = new Set<string>();
        for (const it of items || []) {
            const name = it.konveksi?.name;
            if (name) set.add(name);
        }
        return Array.from(set);
    }

    function getTotalQty(items: Item[]): number {
        return (items || []).reduce((acc, i) => acc + Number(i.qty || 0), 0);
    }

    function getTotalNominal(items: Item[]): number {
        return (items || []).reduce((acc, i) => acc + Number(i.qty || 0) * Number(i.price || 0), 0);
    }

    function applyFilter() {
        router.get(
            '/superadmin/warehouse/stock-in',
            {
                search,
                warehouse_id: warehouseId,
                period_id: periodId,
                seller_id: sellerId,
                konveksi_id: konveksiId,
                start_date: startDate,
                end_date: endDate,
            },
            { preserveState: true, replace: true }
        );
    }

    function resetFilter() {
        search = '';
        warehouseId = '';
        periodId = '';
        sellerId = '';
        konveksiId = '';
        startDate = '';
        endDate = '';
        router.get('/superadmin/warehouse/stock-in', {}, { preserveState: true, replace: true });
    }

    function openDeleteModal(rec: StockInRecord) {
        selectedRecord = rec;
        isConfirmDeleteOpen = true;
    }

    function submitDelete() {
        if (!selectedRecord) return;
        router.delete(`/superadmin/warehouse/stock-in/${selectedRecord.id}`, {
            onSuccess: () => {
                isConfirmDeleteOpen = false;
            },
        });
    }
</script>

<AdminLayout title="Penerimaan Barang (Stock In)" breadcrumbs={[{ name: 'Mutasi Stok' }, { name: 'Penerimaan (Stock In)' }]}>
    <!-- Header -->
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                <ArrowDownLeft class="h-7 w-7 text-sky-500" />
                Penerimaan Barang (Stock In)
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Pencatatan masuknya barang dari vendor/seller ke gudang persediaan</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Link
                href="/superadmin/warehouse/stock-in/create"
                class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/30 hover:bg-indigo-700 transition-colors"
            >
                <Plus class="h-4 w-4" />
                Catat Penerimaan Barang
            </Link>
        </div>
    </div>

    <!-- Summary Cards Grid -->
    <div class="mb-8 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Total Transaksi Stock In -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800 flex items-center justify-between transition-all hover:shadow-md">
            <div>
                <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Total Transaksi</span>
                <span class="text-2xl font-bold text-slate-800 dark:text-slate-100 mt-1 block">
                    {summary.total_transactions} <span class="text-sm font-normal text-slate-500">Penerimaan</span>
                </span>
                <span class="text-xs text-sky-600 dark:text-sky-400 font-medium mt-1 inline-block">
                    Stock In tercatat
                </span>
            </div>
            <div class="h-12 w-12 rounded-2xl bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 border border-sky-100 dark:border-sky-800 flex items-center justify-center shrink-0">
                <ArrowDownLeft class="h-6 w-6" />
            </div>
        </div>

        <!-- Card 2: Total Qty Masuk -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800 flex items-center justify-between transition-all hover:shadow-md">
            <div>
                <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Total Barang Masuk</span>
                <span class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1 block">
                    +{summary.total_qty.toLocaleString('id-ID')} <span class="text-sm font-normal text-slate-500">Pcs</span>
                </span>
                <span class="text-xs text-emerald-600 dark:text-emerald-400 font-medium mt-1 inline-block">
                    Total fisik stok diterima
                </span>
            </div>
            <div class="h-12 w-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-800 flex items-center justify-center shrink-0">
                <PackageCheck class="h-6 w-6" />
            </div>
        </div>

        <!-- Card 3: Total Nilai Modal -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800 flex items-center justify-between transition-all hover:shadow-md">
            <div>
                <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Total Nilai Pembelian</span>
                <span class="text-xl font-bold text-indigo-600 dark:text-indigo-400 mt-1 block">
                    {formatRupiah(summary.total_nominal)}
                </span>
                <span class="text-xs text-indigo-600 dark:text-indigo-400 font-medium mt-1 inline-block">
                    Total HPP Modal Masuk
                </span>
            </div>
            <div class="h-12 w-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-800 flex items-center justify-center shrink-0">
                <DollarSign class="h-6 w-6" />
            </div>
        </div>

        <!-- Card 4: Total Seller/Mitra -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800 flex items-center justify-between transition-all hover:shadow-md">
            <div>
                <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Mitra / Seller</span>
                <span class="text-2xl font-bold text-purple-600 dark:text-purple-400 mt-1 block">
                    {summary.total_sellers} <span class="text-sm font-normal text-slate-500">Mitra</span>
                </span>
                <span class="text-xs text-purple-600 dark:text-purple-400 font-medium mt-1 inline-block">
                    Terdaftar di sistem
                </span>
            </div>
            <div class="h-12 w-12 rounded-2xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 border border-purple-100 dark:border-purple-800 flex items-center justify-center shrink-0">
                <UserCheck class="h-6 w-6" />
            </div>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="mb-6 rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <!-- Filter Gudang -->
            <select
                bind:value={warehouseId}
                onchange={applyFilter}
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
            >
                <option value="">Semua Gudang</option>
                {#each warehouses as w}
                    <option value={w.id}>{w.name}</option>
                {/each}
            </select>

            <!-- Filter Periode -->
            <select
                bind:value={periodId}
                onchange={applyFilter}
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
            >
                <option value="">Semua Periode</option>
                {#each periods as p}
                    <option value={p.id}>{p.name}</option>
                {/each}
            </select>

            <!-- Filter Seller -->
            <select
                bind:value={sellerId}
                onchange={applyFilter}
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
            >
                <option value="">Semua Mitra / Seller</option>
                {#each sellers as s}
                    <option value={s.id}>{s.seller_name || s.name}</option>
                {/each}
            </select>

            <!-- Filter Konveksi -->
            <select
                bind:value={konveksiId}
                onchange={applyFilter}
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
            >
                <option value="">Semua Vendor Konveksi</option>
                {#each konveksis as k}
                    <option value={k.id}>{k.name}</option>
                {/each}
            </select>
        </div>

        <div class="flex flex-col lg:flex-row items-center justify-between gap-3 pt-1">
            <!-- Filter Rentang Tanggal -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 w-full lg:w-auto">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-slate-500 whitespace-nowrap w-14 sm:w-auto">Dari:</span>
                    <input
                        type="date"
                        bind:value={startDate}
                        onchange={applyFilter}
                        class="w-full sm:w-44 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-slate-500 whitespace-nowrap w-14 sm:w-auto">Sampai:</span>
                    <input
                        type="date"
                        bind:value={endDate}
                        onchange={applyFilter}
                        class="w-full sm:w-44 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
            </div>

            <!-- Search & Reset Filter -->
            <div class="flex items-center gap-2 w-full lg:w-auto flex-1 lg:max-w-md justify-end">
                <div class="relative flex-1">
                    <Search class="absolute left-3.5 top-2.5 h-4 w-4 text-slate-400" />
                    <input
                        type="text"
                        bind:value={search}
                        onkeyup={(e) => e.key === 'Enter' && applyFilter()}
                        placeholder="Cari invoice, gudang, seller, konveksi, SKU..."
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 pl-10 pr-4 py-2 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>

                {#if hasActiveFilter}
                    <button
                        type="button"
                        onclick={resetFilter}
                        class="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 dark:border-rose-800 bg-rose-50 dark:bg-rose-950/50 px-3.5 py-2 text-xs font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-100 dark:hover:bg-rose-900/60 transition-colors shrink-0"
                        title="Reset Semua Filter"
                    >
                        <RotateCcw class="h-3.5 w-3.5" />
                        Reset
                    </button>
                {/if}
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-xs border border-slate-100 dark:border-slate-800 overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600 dark:text-slate-400">
                <thead class="bg-slate-50 dark:bg-slate-800/50 text-xs uppercase font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800">
                    <tr>
                        <th class="px-4 py-4 w-12">No</th>
                        <th class="px-4 py-4 whitespace-nowrap">No Invoice / SJ</th>
                        <th class="px-4 py-4 whitespace-nowrap">Tanggal</th>
                        <th class="px-4 py-4">Pemilik / Seller</th>
                        <th class="px-4 py-4 whitespace-nowrap">Gudang Tujuan</th>
                        <th class="px-4 py-4 whitespace-nowrap">Periode</th>
                        <th class="px-5 py-4 text-center whitespace-nowrap min-w-[130px]">Total Qty</th>
                        <th class="px-4 py-4 whitespace-nowrap">Petugas</th>
                        <th class="px-4 py-4 text-right whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    {#each stockIns.data as item, index}
                        {@const itemSellers = getUniqueSellers(item.items)}
                        {@const totalQty = getTotalQty(item.items)}
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="px-4 py-4 text-xs text-slate-400 whitespace-nowrap">{index + 1 + (stockIns.current_page - 1) * 15}</td>
                            <td class="px-4 py-4 font-mono font-bold text-sky-600 dark:text-sky-400 whitespace-nowrap">
                                {item.invoice_number}
                            </td>
                            <td class="px-4 py-4 text-slate-700 dark:text-slate-200 font-medium whitespace-nowrap">
                                {formatDate(item.date)}
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap gap-1">
                                    {#each itemSellers as sellerName}
                                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-purple-50 dark:bg-purple-950/60 px-2.5 py-1 text-xs font-bold text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800 whitespace-nowrap">
                                            <UserCheck class="h-3.5 w-3.5 text-purple-500 shrink-0" />
                                            <span>{sellerName}</span>
                                        </span>
                                    {:else}
                                        <span class="text-xs text-slate-400 italic">Umum</span>
                                    {/each}
                                </div>
                            </td>
                            <td class="px-4 py-4 font-semibold text-slate-800 dark:text-slate-100 whitespace-nowrap">{item.warehouse?.name ?? '-'}</td>
                            <td class="px-4 py-4 text-xs font-semibold text-indigo-600 dark:text-indigo-400 whitespace-nowrap">
                                {item.period?.name ?? '-'}
                            </td>
                            <td class="px-5 py-4 text-center font-bold text-emerald-600 dark:text-emerald-400 whitespace-nowrap min-w-[130px]">
                                <span class="inline-flex items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-950/60 px-3 py-1 text-xs border border-emerald-200 dark:border-emerald-800 whitespace-nowrap">
                                    +{totalQty.toLocaleString('id-ID')} Pcs
                                </span>
                            </td>
                            <td class="px-4 py-4 text-xs text-slate-500 whitespace-nowrap">{item.user?.name ?? '-'}</td>
                            <td class="px-4 py-4 text-right whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    <Link
                                        href={`/superadmin/warehouse/stock-in/${item.id}/edit`}
                                        class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/50 border border-amber-200 dark:border-amber-800 hover:bg-amber-100 dark:hover:bg-amber-900/60 transition-colors"
                                        title="Edit Penerimaan Barang"
                                    >
                                        <Edit2 class="h-3.5 w-3.5" />
                                        <span>Edit</span>
                                    </Link>
                                    <button
                                        type="button"
                                        onclick={() => openDeleteModal(item)}
                                        class="inline-flex items-center gap-1 rounded-lg p-2 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition-colors"
                                        title="Batalkan Penerimaan"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    {:else}
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-slate-400">Belum ada transaksi penerimaan barang.</td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    </div>

    <Pagination links={stockIns.links} />

    <!-- Delete Confirm -->
    <ConfirmDialog
        show={isConfirmDeleteOpen}
        title="Batalkan Penerimaan Barang"
        message="Apakah Anda yakin ingin membatalkan transaksi Surat Jalan #{selectedRecord?.invoice_number}? Stok fisik di gudang akan dikurangi kembali."
        confirmText="Batalkan Transaksi"
        onconfirm={submitDelete}
        oncancel={() => isConfirmDeleteOpen = false}
    />
</AdminLayout>
