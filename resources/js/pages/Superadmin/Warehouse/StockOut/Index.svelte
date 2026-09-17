<script lang="ts">
    import AdminLayout from '@/Layouts/AdminLayout.svelte';
    import ConfirmDialog from '@/Components/ConfirmDialog.svelte';
    import Pagination from '@/Components/Pagination.svelte';
    import { router, Link } from '@inertiajs/svelte';
    import { 
        ArrowUpRight, Search, Plus, Trash2, Printer, BarChart3, Calculator, 
        FileText, Store, Package, DollarSign, Wallet, Sparkles, User, Calendar
    } from '@lucide/svelte';

    interface Item {
        id: number;
        product_variant?: {
            sku: string;
            size: string;
            color: string;
            product?: { product_name: string; seller?: { id: number; name: string } };
        };
        qty: number;
        price: number;
        selling_price: number;
    }

    interface StockOutRecord {
        id: number;
        resi_summary: string;
        date: string;
        note: string | null;
        total_amount?: number;
        total_selling_price: number;
        bruto?: number;
        warehouse?: { id: number; name: string };
        store?: { id: number; store_name: string; seller?: { id: number; name: string } };
        period?: { id: number; name: string };
        user?: { id: number; name: string };
        items: Item[];
    }

    let {
        stockOuts,
        warehouses = [],
        stores = [],
        periods = [],
        users = [],
        metrics = { total_qty: 0, total_hpp: 0, total_bruto: 0, penjualan_bersih: 0, estimasi_keuntungan: 0 },
        filters = { search: '', warehouse_id: '', store_id: '', period_id: '', user_id: '' },
    }: {
        stockOuts: {
            data: StockOutRecord[];
            links: any[];
            current_page: number;
            last_page: number;
            total: number;
        };
        warehouses?: Array<{ id: number; name: string }>;
        stores?: Array<{ id: number; store_name: string }>;
        periods?: Array<{ id: number; name: string }>;
        users?: Array<{ id: number; name: string }>;
        metrics?: {
            total_qty: number;
            total_hpp: number;
            total_bruto: number;
            penjualan_bersih: number;
            estimasi_keuntungan: number;
        };
        filters?: { search?: string; warehouse_id?: string; store_id?: string; period_id?: string; user_id?: string };
    } = $props();

    let search = $state(filters.search ?? '');
    let warehouseId = $state(filters.warehouse_id ?? '');
    let storeId = $state(filters.store_id ?? '');
    let periodId = $state(filters.period_id ?? '');
    let userId = $state(filters.user_id ?? '');

    let isConfirmDeleteOpen = $state(false);
    let selectedRecord = $state<StockOutRecord | null>(null);

    function applyFilter() {
        router.get(
            '/superadmin/warehouse/stock-out', 
            { search, warehouse_id: warehouseId, store_id: storeId, period_id: periodId, user_id: userId }, 
            { preserveState: true, replace: true }
        );
    }

    function openDeleteModal(rec: StockOutRecord) {
        selectedRecord = rec;
        isConfirmDeleteOpen = true;
    }

    function submitDelete() {
        if (!selectedRecord) return;
        router.delete(`/superadmin/warehouse/stock-out/${selectedRecord.id}`, {
            onSuccess: () => {
                isConfirmDeleteOpen = false;
            },
        });
    }

    function formatRupiah(num: number) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(num || 0);
    }

    function formatDate(dateStr: string) {
        if (!dateStr) return '-';
        try {
            const clean = String(dateStr).split('T')[0];
            const parts = clean.split('-');
            if (parts.length === 3) {
                const [year, month, day] = parts;
                const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                const monthName = months[parseInt(month, 10) - 1] || month;
                return `${day} ${monthName} ${year}`;
            }
            return clean;
        } catch (e) {
            return dateStr;
        }
    }

    function getItemTotalQty(rec: StockOutRecord) {
        return rec.items?.reduce((acc, i) => acc + (i.qty || 0), 0) || 0;
    }

    function getItemTotalHpp(rec: StockOutRecord) {
        if (rec.total_amount && Number(rec.total_amount) > 0) return Number(rec.total_amount);
        return rec.items?.reduce((acc, i) => acc + ((i.qty || 0) * (i.price || 0)), 0) || 0;
    }

    function getItemTotalBruto(rec: StockOutRecord) {
        if (rec.bruto && Number(rec.bruto) > 0) return Number(rec.bruto);
        return Number(rec.total_selling_price || 0);
    }

    function getItemPenjualanBersih(rec: StockOutRecord) {
        return Number(rec.total_selling_price || 0);
    }

    function getSellerName(rec: StockOutRecord) {
        if (rec.store?.seller?.name) return rec.store.seller.name;
        if (rec.items && rec.items.length > 0 && rec.items[0].product_variant?.product?.seller?.name) {
            return rec.items[0].product_variant.product.seller.name;
        }
        if (rec.store?.store_name) return rec.store.store_name;
        return '-';
    }
</script>

<AdminLayout title="Pengeluaran Barang (Stock Out)" breadcrumbs={[{ name: 'Mutasi Stok' }, { name: 'Pengeluaran (Stock Out)' }]}>
    <!-- Header -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                <ArrowUpRight class="h-7 w-7 text-rose-500" />
                Pengeluaran Barang (Stock Out / Penjualan)
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Pencatatan pengeluaran barang persediaan berdasarkan resi & toko marketplace</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Link
                href="/superadmin/warehouse/stock-out/chart"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors shadow-xs"
            >
                <BarChart3 class="h-4 w-4 text-indigo-500" />
                Visualisasi Omset
            </Link>

            <Link
                href="/superadmin/warehouse/stock-out/settlement"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors shadow-xs"
            >
                <Calculator class="h-4 w-4 text-emerald-500" />
                Analisis Toko
            </Link>

            <a
                href="/superadmin/warehouse/stock-out/summary/pdf"
                target="_blank"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3.5 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors shadow-xs"
            >
                <Printer class="h-4 w-4 text-slate-500" />
                PDF Summary
            </a>

            <Link
                href="/superadmin/warehouse/stock-out/create"
                class="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-4 py-2 text-xs font-semibold text-white shadow-lg shadow-rose-600/30 hover:bg-rose-700 transition-colors"
            >
                <Plus class="h-4 w-4" />
                Catat Stock Out
            </Link>
        </div>
    </div>

    <!-- KPI Metrics Summary Cards -->
    <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <!-- 1. Total Qty -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                <span class="text-xs font-bold uppercase tracking-wider">Total Qty</span>
                <div class="p-2 rounded-xl bg-rose-50 dark:bg-rose-950/50 text-rose-500">
                    <Package class="h-4 w-4" />
                </div>
            </div>
            <div class="text-xl font-extrabold text-slate-800 dark:text-slate-100">
                {metrics.total_qty.toLocaleString('id-ID')} <span class="text-xs font-semibold text-slate-400">pcs</span>
            </div>
            <div class="text-[11px] text-slate-400">Total unit barang keluar</div>
        </div>

        <!-- 2. Total HPP -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                <span class="text-xs font-bold uppercase tracking-wider">Total HPP</span>
                <div class="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                    <DollarSign class="h-4 w-4" />
                </div>
            </div>
            <div class="text-xl font-extrabold text-slate-800 dark:text-slate-100 truncate">
                {formatRupiah(metrics.total_hpp)}
            </div>
            <div class="text-[11px] text-slate-400">Modal HPP stok keluar</div>
        </div>

        <!-- 3. Total Bruto -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                <span class="text-xs font-bold uppercase tracking-wider">Total Bruto</span>
                <div class="p-2 rounded-xl bg-cyan-50 dark:bg-cyan-950/50 text-cyan-500">
                    <BarChart3 class="h-4 w-4" />
                </div>
            </div>
            <div class="text-xl font-extrabold text-cyan-600 dark:text-cyan-400 truncate">
                {formatRupiah(metrics.total_bruto)}
            </div>
            <div class="text-[11px] text-slate-400">Total nilai omset kotor</div>
        </div>

        <!-- 4. Penjualan Bersih -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                <span class="text-xs font-bold uppercase tracking-wider">Penjualan Bersih</span>
                <div class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-500">
                    <Wallet class="h-4 w-4" />
                </div>
            </div>
            <div class="text-xl font-extrabold text-emerald-600 dark:text-emerald-400 truncate">
                {formatRupiah(metrics.penjualan_bersih)}
            </div>
            <div class="text-[11px] text-slate-400">Omset penjualan terdata</div>
        </div>

        <!-- 5. Estimasi Keuntungan -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                <span class="text-xs font-bold uppercase tracking-wider">Estimasi Profit</span>
                <div class="p-2 rounded-xl bg-violet-50 dark:bg-violet-950/50 text-violet-500">
                    <Sparkles class="h-4 w-4" />
                </div>
            </div>
            <div class="text-xl font-extrabold text-violet-600 dark:text-violet-400 truncate">
                {formatRupiah(metrics.estimasi_keuntungan)}
            </div>
            <div class="text-[11px] text-slate-400">Profit = Bruto - HPP</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-200 dark:border-slate-800 flex flex-col md:flex-row flex-wrap items-center gap-3">
        <!-- Warehouse filter -->
        <div class="w-full sm:w-auto flex-1 min-w-[150px]">
            <select
                bind:value={warehouseId}
                onchange={applyFilter}
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-rose-500"
            >
                <option value="">Semua Gudang</option>
                {#each warehouses as w}
                    <option value={w.id}>{w.name}</option>
                {/each}
            </select>
        </div>

        <!-- Store filter -->
        <div class="w-full sm:w-auto flex-1 min-w-[150px]">
            <select
                bind:value={storeId}
                onchange={applyFilter}
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-rose-500"
            >
                <option value="">Semua Toko Marketplace</option>
                {#each stores as st}
                    <option value={st.id}>{st.store_name}</option>
                {/each}
            </select>
        </div>

        <!-- Period filter -->
        <div class="w-full sm:w-auto flex-1 min-w-[150px]">
            <select
                bind:value={periodId}
                onchange={applyFilter}
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-rose-500"
            >
                <option value="">Semua Periode</option>
                {#each periods as p}
                    <option value={p.id}>{p.name}</option>
                {/each}
            </select>
        </div>

        <!-- User / Admin filter -->
        <div class="w-full sm:w-auto flex-1 min-w-[150px]">
            <select
                bind:value={userId}
                onchange={applyFilter}
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-rose-500"
            >
                <option value="">Semua Admin / User</option>
                {#each users as u}
                    <option value={u.id}>{u.name}</option>
                {/each}
            </select>
        </div>

        <!-- Search -->
        <div class="relative w-full sm:w-auto flex-1 min-w-[180px]">
            <Search class="absolute left-3 top-2.5 h-3.5 w-3.5 text-slate-400" />
            <input
                type="text"
                bind:value={search}
                onkeyup={(e) => e.key === 'Enter' && applyFilter()}
                placeholder="Cari resi / catatan..."
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 pl-8 pr-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-rose-500"
            />
        </div>
    </div>

    <!-- Data Table (Exact User-Requested Columns) -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-xs border border-slate-200 dark:border-slate-800 overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600 dark:text-slate-400">
                <thead class="bg-slate-50 dark:bg-slate-800/50 uppercase font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="px-4 py-3.5">Tanggal</th>
                        <th class="px-4 py-3.5">Periode</th>
                        <th class="px-4 py-3.5">Admin</th>
                        <th class="px-4 py-3.5">Seller</th>
                        <th class="px-4 py-3.5">QTY Keluar</th>
                        <th class="px-4 py-3.5">HPP (Modal)</th>
                        <th class="px-4 py-3.5">Bruto</th>
                        <th class="px-4 py-3.5">Penghasilan Bersih</th>
                        <th class="px-4 py-3.5">Est. Untung</th>
                        <th class="px-4 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    {#each stockOuts.data as item}
                        {@const rowQty = getItemTotalQty(item)}
                        {@const rowHpp = getItemTotalHpp(item)}
                        {@const rowBruto = getItemTotalBruto(item)}
                        {@const rowBersih = getItemPenjualanBersih(item)}
                        {@const rowUntung = rowBersih - rowHpp}
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                            <!-- 1. Tanggal -->
                            <td class="px-4 py-3.5 font-medium text-slate-800 dark:text-slate-200 text-xs whitespace-nowrap">{formatDate(item.date)}</td>
                            
                            <!-- 2. Periode -->
                            <td class="px-4 py-3.5 text-xs text-indigo-600 dark:text-indigo-400 font-semibold">{item.period?.name ?? '-'}</td>
                            
                            <!-- 3. Admin -->
                            <td class="px-4 py-3.5 text-xs text-slate-600 dark:text-slate-300 font-medium">{item.user?.name ?? '-'}</td>
                            
                            <!-- 4. Seller -->
                            <td class="px-4 py-3.5 text-xs font-semibold text-slate-800 dark:text-slate-200">{getSellerName(item)}</td>
                            
                            <!-- 5. QTY Keluar -->
                            <td class="px-4 py-3.5 text-xs font-extrabold text-rose-600 dark:text-rose-400">-{rowQty.toLocaleString('id-ID')} pcs</td>
                            
                            <!-- 6. HPP (Modal) -->
                            <td class="px-4 py-3.5 text-xs font-semibold text-slate-700 dark:text-slate-300">{formatRupiah(rowHpp)}</td>
                            
                            <!-- 7. Bruto -->
                            <td class="px-4 py-3.5 text-xs font-semibold text-cyan-600 dark:text-cyan-400">{formatRupiah(rowBruto)}</td>
                            
                            <!-- 8. Penghasilan Bersih -->
                            <td class="px-4 py-3.5 text-xs font-bold text-emerald-600 dark:text-emerald-400">{formatRupiah(rowBersih)}</td>
                            
                            <!-- 9. Est. Untung -->
                            <td class="px-4 py-3.5 text-xs font-bold text-violet-600 dark:text-violet-400">{formatRupiah(rowUntung)}</td>
                            
                            <!-- 10. Aksi -->
                            <td class="px-4 py-3.5 text-right space-x-1 whitespace-nowrap">
                                <a
                                    href={`/superadmin/warehouse/stock-out/${item.id}/pdf`}
                                    target="_blank"
                                    class="inline-flex items-center gap-1 rounded-lg p-2 text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 transition-colors"
                                    title="Cetak Surat Jalan PDF"
                                >
                                    <FileText class="h-4 w-4" />
                                </a>
                                <button
                                    onclick={() => openDeleteModal(item)}
                                    class="inline-flex items-center gap-1 rounded-lg p-2 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition-colors"
                                    title="Batalkan Stock Out"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                    {:else}
                        <tr>
                            <td colspan="10" class="px-6 py-12 text-center text-slate-400">Belum ada transaksi pengeluaran barang.</td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    </div>

    <Pagination links={stockOuts.links} />

    <!-- Delete Confirm -->
    <ConfirmDialog
        show={isConfirmDeleteOpen}
        title="Batalkan Pengeluaran Barang"
        message="Apakah Anda yakin ingin membatalkan transaksi pengeluaran #{selectedRecord?.resi_summary}? Kuantitas stok fisik di gudang akan dikembalikan."
        confirmText="Batalkan Transaksi"
        onconfirm={submitDelete}
        oncancel={() => isConfirmDeleteOpen = false}
    />
</AdminLayout>
