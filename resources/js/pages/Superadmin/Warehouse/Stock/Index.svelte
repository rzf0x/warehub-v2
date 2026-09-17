<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import Badge from '@/components/Badge.svelte';
    import { router, Link } from '@inertiajs/svelte';
    import { Activity, Search, AlertTriangle, History, Building2, Package, Layers, AlertCircle, CheckCircle2 } from '@lucide/svelte';

    interface StockItem {
        id: number;
        qty: number;
        allocated_qty: number;
        warehouse?: { id: number; name: string };
        product_variant?: {
            sku: string;
            size: string;
            color: string;
            price: number;
            selling_price?: number;
            product?: { product_name: string; seller?: { name?: string; seller_name?: string } };
        };
    }

    interface LogItem {
        id: number;
        qty_before: number;
        qty_change: number;
        qty_after: number;
        note: string;
        created_at: string;
        warehouse?: { name: string };
        product_variant?: {
            sku: string;
            product?: { product_name: string };
        };
    }

    interface SummaryStats {
        total_qty: number;
        total_items: number;
        total_warehouses: number;
        low_stock_count: number;
        out_of_stock_count: number;
    }

    let {
        activeTab = 'stocks',
        stocks,
        logs,
        warehouses = [],
        summary = {
            total_qty: 0,
            total_items: 0,
            total_warehouses: 0,
            low_stock_count: 0,
            out_of_stock_count: 0,
        },
        filters = { search: '', warehouse_id: '' },
    }: {
        activeTab?: string;
        stocks?: {
            data: StockItem[];
            links: any[];
            current_page: number;
            last_page: number;
            total: number;
        };
        logs?: {
            data: LogItem[];
            links: any[];
            current_page: number;
            last_page: number;
            total: number;
        };
        warehouses?: Array<{ id: number; name: string }>;
        summary?: SummaryStats;
        filters?: { search?: string; warehouse_id?: string };
    } = $props();

    let search = $state(filters.search ?? '');
    let warehouseId = $state(filters.warehouse_id ?? '');

    function applyFilter() {
        const url = activeTab === 'logs' ? '/superadmin/warehouse/stocks/logs' : '/superadmin/warehouse/stocks';
        router.get(url, { search, warehouse_id: warehouseId }, { preserveState: true, replace: true });
    }
</script>

<AdminLayout title="Stok Fisik Realtime & Log Mutasi" breadcrumbs={[{ name: 'Mutasi Stok' }, { name: 'Stok Fisik Realtime' }]}>
    <!-- Header -->
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                <Activity class="h-7 w-7 text-emerald-500" />
                Stok Fisik Realtime & Audit Log
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Pantau kuantitas persediaan barang per gudang dan histori mutasi log</p>
        </div>
    </div>

    <!-- Summary Cards Grid -->
    <div class="mb-8 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Total Stok Fisik -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800 flex items-center justify-between transition-all hover:shadow-md">
            <div>
                <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Total Persediaan Fisik</span>
                <span class="text-2xl font-bold text-slate-800 dark:text-slate-100 mt-1 block">
                    {summary.total_qty.toLocaleString('id-ID')} <span class="text-sm font-normal text-slate-500">Pcs</span>
                </span>
                <span class="text-xs text-emerald-600 dark:text-emerald-400 font-medium mt-1 inline-block">
                    Total unit barang di gudang
                </span>
            </div>
            <div class="h-12 w-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-800 flex items-center justify-center shrink-0">
                <Package class="h-6 w-6" />
            </div>
        </div>

        <!-- Card 2: Total SKU Varian -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800 flex items-center justify-between transition-all hover:shadow-md">
            <div>
                <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Varian Terdaftar</span>
                <span class="text-2xl font-bold text-slate-800 dark:text-slate-100 mt-1 block">
                    {summary.total_items.toLocaleString('id-ID')} <span class="text-sm font-normal text-slate-500">SKU</span>
                </span>
                <span class="text-xs text-sky-600 dark:text-sky-400 font-medium mt-1 inline-block">
                    Dari {summary.total_warehouses} gudang aktif
                </span>
            </div>
            <div class="h-12 w-12 rounded-2xl bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 border border-sky-100 dark:border-sky-800 flex items-center justify-center shrink-0">
                <Layers class="h-6 w-6" />
            </div>
        </div>

        <!-- Card 3: Stok Menipis -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800 flex items-center justify-between transition-all hover:shadow-md">
            <div>
                <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Stok Menipis (&le; 5 Pcs)</span>
                <span class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1 block">
                    {summary.low_stock_count} <span class="text-sm font-normal text-slate-500">SKU</span>
                </span>
                <span class="text-xs text-amber-600 dark:text-amber-400 font-medium mt-1 inline-block">
                    Perlu re-stock ulang
                </span>
            </div>
            <div class="h-12 w-12 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 border border-amber-100 dark:border-amber-800 flex items-center justify-center shrink-0">
                <AlertTriangle class="h-6 w-6" />
            </div>
        </div>

        <!-- Card 4: Stok Habis -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800 flex items-center justify-between transition-all hover:shadow-md">
            <div>
                <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Stok Habis (0 Pcs)</span>
                <span class="text-2xl font-bold text-rose-600 dark:text-rose-400 mt-1 block">
                    {summary.out_of_stock_count} <span class="text-sm font-normal text-slate-500">SKU</span>
                </span>
                <span class="text-xs text-rose-600 dark:text-rose-400 font-medium mt-1 inline-block">
                    Stok persediaan kosong
                </span>
            </div>
            <div class="h-12 w-12 rounded-2xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 border border-rose-100 dark:border-rose-800 flex items-center justify-center shrink-0">
                <AlertCircle class="h-6 w-6" />
            </div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="mb-6 flex items-center gap-3 border-b border-slate-200 dark:border-slate-800 pb-3">
        <Link
            href="/superadmin/warehouse/stocks"
            class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition-colors
            {activeTab !== 'logs'
                ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'}"
        >
            <Activity class="h-4 w-4" />
            Grid Stok Fisik
        </Link>

        <Link
            href="/superadmin/warehouse/stocks/logs"
            class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition-colors
            {activeTab === 'logs'
                ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'}"
        >
            <History class="h-4 w-4" />
            Histori Log Mutasi (Stock Logs)
        </Link>
    </div>

    <!-- Filters -->
    <div class="mb-6 rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="w-full sm:w-64">
            <select
                bind:value={warehouseId}
                onchange={applyFilter}
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
            >
                <option value="">Semua Gudang</option>
                {#each warehouses as w}
                    <option value={w.id}>{w.name}</option>
                {/each}
            </select>
        </div>

        <div class="relative flex-1 w-full">
            <Search class="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
            <input
                type="text"
                bind:value={search}
                onkeyup={(e) => e.key === 'Enter' && applyFilter()}
                placeholder="Cari berdasarkan nama produk, SKU, atau catatan log..."
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 pl-10 pr-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
        </div>
    </div>

    <!-- Content according to activeTab -->
    {#if activeTab !== 'logs' && stocks}
        <!-- Grid Stok Fisik -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-xs border border-slate-100 dark:border-slate-800 overflow-hidden mb-6">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600 dark:text-slate-400">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 text-xs uppercase font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800">
                        <tr>
                            <th class="px-6 py-4">No</th>
                            <th class="px-6 py-4">Gudang</th>
                            <th class="px-6 py-4">Produk & Seller</th>
                            <th class="px-6 py-4">SKU Varian</th>
                            <th class="px-6 py-4">Size / Color</th>
                            <th class="px-6 py-4">Stok Fisik Available</th>
                            <th class="px-6 py-4">Status Alert</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        {#each stocks.data as item, index}
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                                <td class="px-6 py-4 text-xs text-slate-400">{index + 1 + (stocks.current_page - 1) * 15}</td>
                                <td class="px-6 py-4 font-semibold text-slate-800 dark:text-slate-100">{item.warehouse?.name ?? '-'}</td>
                                <td class="px-6 py-4">
                                    <p class="font-bold text-slate-800 dark:text-slate-100">{item.product_variant?.product?.product_name ?? '-'}</p>
                                    <span class="text-xs text-slate-400">{item.product_variant?.product?.seller?.seller_name || item.product_variant?.product?.seller?.name || 'Seller'}</span>
                                </td>
                                <td class="px-6 py-4 font-mono font-bold text-indigo-600 dark:text-indigo-400">{item.product_variant?.sku ?? '-'}</td>
                                <td class="px-6 py-4">{item.product_variant?.size ?? '-'} / {item.product_variant?.color ?? '-'}</td>
                                <td class="px-6 py-4">
                                    <span class="text-base font-extrabold {item.qty <= 5 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-800 dark:text-slate-100'}">
                                        {item.qty} pcs
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    {#if item.qty <= 0}
                                        <Badge variant="danger">Habis</Badge>
                                    {:else if item.qty <= 5}
                                        <Badge variant="warning">Menipis</Badge>
                                    {:else}
                                        <Badge variant="success">Aman</Badge>
                                    {/if}
                                </td>
                            </tr>
                        {:else}
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-slate-400">Belum ada data stok persediaan.</td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </div>

        <Pagination links={stocks.links} />
    {:else if logs}
        <!-- Tab Histori Log Mutasi -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-xs border border-slate-100 dark:border-slate-800 overflow-hidden mb-6">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600 dark:text-slate-400">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 text-xs uppercase font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800">
                        <tr>
                            <th class="px-6 py-4">No</th>
                            <th class="px-6 py-4">Waktu Log</th>
                            <th class="px-6 py-4">Gudang</th>
                            <th class="px-6 py-4">SKU & Produk</th>
                            <th class="px-6 py-4">Sebelum</th>
                            <th class="px-6 py-4">Perubahan (Diff)</th>
                            <th class="px-6 py-4">Sesudah</th>
                            <th class="px-6 py-4">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        {#each logs.data as logItem, index}
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                                <td class="px-6 py-4 text-xs text-slate-400">{index + 1 + (logs.current_page - 1) * 20}</td>
                                <td class="px-6 py-4 text-xs font-mono">{logItem.created_at}</td>
                                <td class="px-6 py-4 text-xs font-semibold text-slate-700 dark:text-slate-200">{logItem.warehouse?.name ?? '-'}</td>
                                <td class="px-6 py-4">
                                    <p class="font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400">{logItem.product_variant?.sku ?? '-'}</p>
                                    <p class="text-[11px] text-slate-500">{logItem.product_variant?.product?.product_name ?? '-'}</p>
                                </td>
                                <td class="px-6 py-4 text-xs">{logItem.qty_before} pcs</td>
                                <td class="px-6 py-4 font-bold">
                                    {#if logItem.qty_change > 0}
                                        <span class="text-emerald-600 dark:text-emerald-400">+{logItem.qty_change} pcs</span>
                                    {:else if logItem.qty_change < 0}
                                        <span class="text-rose-600 dark:text-rose-400">{logItem.qty_change} pcs</span>
                                    {:else}
                                        <span class="text-slate-400">0 pcs</span>
                                    {/if}
                                </td>
                                <td class="px-6 py-4 text-xs font-bold text-slate-800 dark:text-slate-100">{logItem.qty_after} pcs</td>
                                <td class="px-6 py-4 text-xs text-slate-600 dark:text-slate-300">{logItem.note}</td>
                            </tr>
                        {:else}
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-slate-400">Belum ada catatan log mutasi.</td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </div>

        <Pagination links={logs.links} />
    {/if}
</AdminLayout>
