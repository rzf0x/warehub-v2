<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import ConfirmDialog from '@/components/ConfirmDialog.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import { router, Link } from '@inertiajs/svelte';
    import { ArrowDownLeft, Search, Plus, Trash2, Printer, Building2, FileText, Calendar, UserCheck, PackageCheck, DollarSign, Layers } from '@lucide/svelte';

    interface SellerRecord {
        id?: number;
        seller_name?: string;
        name?: string;
    }

    interface ProductRecord {
        product_name: string;
        seller?: SellerRecord;
    }

    interface Item {
        id: number;
        product_variant?: {
            sku: string;
            size: string;
            color: string;
            product?: ProductRecord;
        };
        konveksi?: { name: string };
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
        name: string;
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
        summary = {
            total_transactions: 0,
            total_qty: 0,
            total_nominal: 0,
            total_sellers: 0,
        },
        filters = { search: '', warehouse_id: '', period_id: '', seller_id: '' },
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
        summary?: SummaryStats;
        filters?: { search?: string; warehouse_id?: string; period_id?: string; seller_id?: string };
    } = $props();

    let search = $state(filters.search ?? '');
    let warehouseId = $state(filters.warehouse_id ?? '');
    let periodId = $state(filters.period_id ?? '');
    let sellerId = $state(filters.seller_id ?? '');

    let isConfirmDeleteOpen = $state(false);
    let selectedRecord = $state<StockInRecord | null>(null);

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

    function applyFilter() {
        router.get(
            '/superadmin/warehouse/stock-in',
            { search, warehouse_id: warehouseId, period_id: periodId, seller_id: sellerId },
            { preserveState: true, replace: true }
        );
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
            <a
                href="/superadmin/warehouse/stock-in/report/pdf"
                target="_blank"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3.5 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors shadow-xs"
            >
                <Printer class="h-4 w-4 text-slate-500" />
                Laporan Filtered PDF
            </a>

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
    <div class="mb-6 rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 flex flex-col lg:flex-row items-center justify-between gap-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 w-full lg:w-auto flex-1">
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
        </div>

        <div class="relative flex-1 w-full lg:w-72">
            <Search class="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
            <input
                type="text"
                bind:value={search}
                onkeyup={(e) => e.key === 'Enter' && applyFilter()}
                placeholder="Cari invoice, gudang, seller, atau produk..."
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 pl-10 pr-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
        </div>
    </div>

    <!-- Data Table -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-xs border border-slate-100 dark:border-slate-800 overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600 dark:text-slate-400">
                <thead class="bg-slate-50 dark:bg-slate-800/50 text-xs uppercase font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800">
                    <tr>
                        <th class="px-6 py-4">No</th>
                        <th class="px-6 py-4">No Invoice / SJ</th>
                        <th class="px-6 py-4">Tanggal</th>
                        <th class="px-6 py-4">Pemilik / Seller</th>
                        <th class="px-6 py-4">Gudang Tujuan</th>
                        <th class="px-6 py-4">Periode</th>
                        <th class="px-6 py-4 text-center">Total Qty</th>
                        <th class="px-6 py-4">Petugas</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    {#each stockIns.data as item, index}
                        {@const itemSellers = getUniqueSellers(item.items)}
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="px-6 py-4 text-xs text-slate-400">{index + 1 + (stockIns.current_page - 1) * 15}</td>
                            <td class="px-6 py-4 font-mono font-bold text-sky-600 dark:text-sky-400">{item.invoice_number}</td>
                            <td class="px-6 py-4 text-slate-700 dark:text-slate-200 font-medium whitespace-nowrap">
                                {formatDate(item.date)}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    {#each itemSellers as sellerName}
                                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-purple-50 dark:bg-purple-950/60 px-2.5 py-1 text-xs font-bold text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                                            <UserCheck class="h-3.5 w-3.5 text-purple-500" />
                                            <span>{sellerName}</span>
                                        </span>
                                    {:else}
                                        <span class="text-xs text-slate-400 italic">Umum</span>
                                    {/each}
                                </div>
                            </td>
                            <td class="px-6 py-4 font-semibold text-slate-800 dark:text-slate-100">{item.warehouse?.name ?? '-'}</td>
                            <td class="px-6 py-4 text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                                {item.period?.name ?? '-'}
                            </td>
                            <td class="px-6 py-4 text-center font-bold text-emerald-600 dark:text-emerald-400">
                                <span class="rounded-lg bg-emerald-50 dark:bg-emerald-950/60 px-2.5 py-1 text-xs border border-emerald-200 dark:border-emerald-800">
                                    +{item.items.reduce((acc, i) => acc + i.qty, 0)} Pcs
                                </span>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-500">{item.user?.name ?? '-'}</td>
                            <td class="px-6 py-4 text-right">
                                <button
                                    onclick={() => openDeleteModal(item)}
                                    class="inline-flex items-center gap-1 rounded-lg p-2 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition-colors"
                                    title="Batalkan Penerimaan"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
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
