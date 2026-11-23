<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import Badge from '@/components/Badge.svelte';
    import { router, Link } from '@inertiajs/svelte';
    import {
        ClipboardCheck,
        Search,
        Plus,
        FileText,
        ChevronDown,
        ChevronUp,
        Clock,
        User,
        Layers,
        History,
        Building2,
    } from '@lucide/svelte';

    interface Item {
        id: number;
        created_at?: string;
        product_variant?: {
            sku: string;
            size: string;
            color: string;
            price?: number;
            product?: {
                product_name: string;
                seller?: { seller_name?: string; name?: string };
            };
        };
        system_qty: number;
        physical_qty: number;
        difference: number;
    }

    interface StockOpnameRecord {
        id: number;
        date: string;
        created_at?: string;
        note: string | null;
        warehouse?: { id: number; name: string };
        user?: { name: string; email?: string };
        items: Item[];
    }

    interface OpnameItemHistoryRow {
        id: number;
        stock_opname_id: number;
        system_qty: number;
        physical_qty: number;
        difference: number;
        created_at?: string;
        stock_opname?: {
            id: number;
            date?: string;
            created_at?: string;
            note?: string | null;
            warehouse?: { id: number; name: string };
            user?: { id: number; name: string };
        };
        product_variant?: {
            id: number;
            sku: string;
            size: string;
            color: string;
            price?: number;
            product?: {
                id: number;
                product_name: string;
                seller?: { id: number; seller_name?: string; name?: string };
            };
        };
    }

    let {
        stockOpnames,
        opnameItemsHistory,
        warehouses = [],
        filters = { search: '', warehouse_id: '' },
    }: {
        stockOpnames: {
            data: StockOpnameRecord[];
            links: any[];
            current_page: number;
            last_page: number;
            total: number;
        };
        opnameItemsHistory?: {
            data: OpnameItemHistoryRow[];
            links: any[];
            current_page: number;
            last_page: number;
            total: number;
        };
        warehouses?: Array<{ id: number; name: string }>;
        filters?: { search?: string; warehouse_id?: string };
    } = $props();

    let search = $state(filters.search ?? '');
    let warehouseId = $state(filters.warehouse_id ?? '');
    let activeTab = $state<'items' | 'sessions'>('items');
    let expandedOpnames = $state<Record<number, boolean>>({});

    function toggleExpandOpname(id: number) {
        expandedOpnames = {
            ...expandedOpnames,
            [id]: !expandedOpnames[id],
        };
    }

    function formatFullDateTime(dateStr?: string, fallbackDate?: string): { dayDate: string; time: string } {
        const raw = dateStr || fallbackDate;
        if (!raw) return { dayDate: '-', time: '-' };
        const d = new Date(raw);
        if (isNaN(d.getTime())) return { dayDate: String(raw), time: '-' };

        const dayDate = d.toLocaleDateString('id-ID', {
            weekday: 'long',
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
        const time =
            d.toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            }) + ' WIB';

        return { dayDate, time };
    }

    function extractUserName(userObj?: { name?: string }, noteStr?: string | null): string {
        if (userObj?.name) return userObj.name;
        if (noteStr) {
            const match = noteStr.match(/oleh\s+([^\(\[\]]+)$/i);
            if (match && match[1]) return match[1].trim();
        }
        return 'Super Admin';
    }

    function applyFilter() {
        router.get('/superadmin/warehouse/stock-opname', { search, warehouse_id: warehouseId }, { preserveState: true, replace: true });
    }
</script>

<AdminLayout title="Audit Stock Opname" breadcrumbs={[{ name: 'Mutasi Stok' }, { name: 'Audit Stock Opname' }]}>
    <!-- Header -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                <ClipboardCheck class="h-7 w-7 text-amber-500" />
                Audit &amp; Histori Stock Opname Gudang
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Riwayat lengkap perubahan stok opname: barang/SKU, stok awal, stok setelah opname, selisih, siapa yang mengubah, beserta hari, tanggal &amp; jam detail
            </p>
        </div>

        <Link
            href="/superadmin/warehouse/stock-opname/create"
            class="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-amber-600/30 hover:bg-amber-700 transition-colors"
        >
            <Plus class="h-4 w-4" />
            Mulai Stock Opname
        </Link>
    </div>

    <!-- View Mode Tabs -->
    <div class="mb-6 flex flex-wrap items-center gap-2.5 border-b border-slate-200 dark:border-slate-800 pb-3">
        <button
            type="button"
            onclick={() => (activeTab = 'items')}
            class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs sm:text-sm font-semibold transition-colors cursor-pointer
            {activeTab === 'items'
                ? 'bg-amber-600 text-white shadow-md shadow-amber-600/20'
                : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'}"
        >
            <History class="h-4 w-4" />
            Histori Detail Per-Barang / SKU ({opnameItemsHistory?.total ?? 0})
        </button>

        <button
            type="button"
            onclick={() => (activeTab = 'sessions')}
            class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs sm:text-sm font-semibold transition-colors cursor-pointer
            {activeTab === 'sessions'
                ? 'bg-amber-600 text-white shadow-md shadow-amber-600/20'
                : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'}"
        >
            <Layers class="h-4 w-4" />
            Rekap Per-Nota Audit (#OPN) ({stockOpnames.total})
        </button>
    </div>

    <!-- Filters -->
    <div class="mb-6 rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="w-full sm:w-64">
            <select
                bind:value={warehouseId}
                onchange={applyFilter}
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-amber-500"
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
                placeholder="Cari SKU, nama barang, nama pengubah (user), atau catatan opname..."
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 pl-10 pr-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500"
            />
        </div>
    </div>

    {#if activeTab === 'items' && opnameItemsHistory}
        <!-- TAB 1: Histori Detail Per-Barang / SKU -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-xs border border-slate-100 dark:border-slate-800 overflow-hidden mb-6">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm text-slate-600 dark:text-slate-400">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 text-[11px] uppercase font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800">
                        <tr>
                            <th class="px-4 py-3.5">Hari, Tanggal &amp; Jam</th>
                            <th class="px-4 py-3.5">ID Opname &amp; Gudang</th>
                            <th class="px-4 py-3.5">Barang / SKU / Size</th>
                            <th class="px-4 py-3.5 text-right">Stok Awal</th>
                            <th class="px-4 py-3.5 text-right">Setelah Opname</th>
                            <th class="px-4 py-3.5 text-right">Selisih</th>
                            <th class="px-4 py-3.5">Diubah Oleh (User)</th>
                            <th class="px-4 py-3.5">Catatan / Sumber</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        {#each opnameItemsHistory.data as row (row.id)}
                            {@const dt = formatFullDateTime(row.created_at || row.stock_opname?.created_at, row.stock_opname?.date)}
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition-colors">
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <div class="font-bold text-slate-800 dark:text-slate-100 text-xs">{dt.dayDate}</div>
                                    <div class="inline-flex items-center gap-1 text-[11px] font-mono text-amber-600 dark:text-amber-400 mt-0.5">
                                        <Clock class="h-3 w-3" />
                                        {dt.time}
                                    </div>
                                </td>

                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <span class="font-mono font-bold text-xs text-slate-800 dark:text-slate-100 block">
                                        #OPN-{row.stock_opname_id}
                                    </span>
                                    <span class="inline-flex items-center gap-1 text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                        <Building2 class="h-3 w-3" />
                                        {row.stock_opname?.warehouse?.name ?? '-'}
                                    </span>
                                </td>

                                <td class="px-4 py-3.5">
                                    <div class="font-bold text-slate-800 dark:text-slate-100 text-xs">
                                        {row.product_variant?.product?.product_name ?? 'Produk'}
                                    </div>
                                    <div class="flex flex-wrap items-center gap-1.5 mt-0.5">
                                        <span class="rounded bg-indigo-50 dark:bg-indigo-950/70 px-1.5 py-0.5 font-mono text-[11px] font-bold text-indigo-600 dark:text-indigo-400">
                                            {row.product_variant?.sku ?? '-'}
                                        </span>
                                        <span class="text-[11px] font-semibold text-slate-600 dark:text-slate-300">
                                            Size: {row.product_variant?.size ?? '-'}
                                        </span>
                                        {#if row.product_variant?.color && row.product_variant.color !== '-'}
                                            <span class="text-[11px] text-slate-400">• {row.product_variant.color}</span>
                                        {/if}
                                    </div>
                                    {#if row.product_variant?.product?.seller}
                                        <div class="text-[10px] text-slate-400 mt-0.5">
                                            Mitra: {row.product_variant.product.seller.seller_name || row.product_variant.product.seller.name}
                                        </div>
                                    {/if}
                                </td>

                                <td class="px-4 py-3.5 text-right font-bold text-slate-600 dark:text-slate-300 whitespace-nowrap">
                                    {row.system_qty.toLocaleString('id-ID')} Pcs
                                </td>

                                <td class="px-4 py-3.5 text-right font-extrabold text-slate-900 dark:text-slate-100 whitespace-nowrap">
                                    {row.physical_qty.toLocaleString('id-ID')} Pcs
                                </td>

                                <td class="px-4 py-3.5 text-right font-extrabold whitespace-nowrap">
                                    {#if row.difference > 0}
                                        <span class="inline-flex items-center rounded-lg bg-emerald-50 dark:bg-emerald-950/60 px-2 py-0.5 text-xs text-emerald-600 dark:text-emerald-400">
                                            +{row.difference.toLocaleString('id-ID')} Pcs
                                        </span>
                                    {:else if row.difference < 0}
                                        <span class="inline-flex items-center rounded-lg bg-rose-50 dark:bg-rose-950/60 px-2 py-0.5 text-xs text-rose-600 dark:text-rose-400">
                                            {row.difference.toLocaleString('id-ID')} Pcs
                                        </span>
                                    {:else}
                                        <span class="text-xs text-slate-400">0 Pcs</span>
                                    {/if}
                                </td>

                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:text-slate-200">
                                        <User class="h-3.5 w-3.5 text-amber-500" />
                                        {extractUserName(row.stock_opname?.user, row.stock_opname?.note)}
                                    </div>
                                </td>

                                <td class="px-4 py-3.5 text-xs text-slate-600 dark:text-slate-300 max-w-xs">
                                    {row.stock_opname?.note ?? '-'}
                                </td>
                            </tr>
                        {:else}
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-slate-400">
                                    Belum ada histori item stock opname.
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </div>

        <Pagination links={opnameItemsHistory.links} />
    {:else}
        <!-- TAB 2: Rekap Per-Nota Audit (#OPN) dengan Expand Rincian Barang -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-xs border border-slate-100 dark:border-slate-800 overflow-hidden mb-6">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600 dark:text-slate-400">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 text-xs uppercase font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800">
                        <tr>
                            <th class="px-5 py-4">No</th>
                            <th class="px-5 py-4">ID Opname</th>
                            <th class="px-5 py-4">Hari, Tanggal &amp; Jam</th>
                            <th class="px-5 py-4">Gudang</th>
                            <th class="px-5 py-4">Diubah Oleh (Auditor)</th>
                            <th class="px-5 py-4">Catatan</th>
                            <th class="px-5 py-4">Total Item</th>
                            <th class="px-5 py-4 text-right">Aksi &amp; PDF</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        {#each stockOpnames.data as item, index (item.id)}
                            {@const dt = formatFullDateTime(item.created_at, item.date)}
                            {@const isExpanded = !!expandedOpnames[item.id]}
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                                <td class="px-5 py-4 text-xs text-slate-400">{index + 1 + (stockOpnames.current_page - 1) * 10}</td>
                                <td class="px-5 py-4 font-mono font-bold text-slate-800 dark:text-slate-100">#OPN-{item.id}</td>
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <div class="text-xs font-bold text-slate-800 dark:text-slate-100">{dt.dayDate}</div>
                                    <div class="text-[11px] font-mono text-amber-600 dark:text-amber-400">{dt.time}</div>
                                </td>
                                <td class="px-5 py-4 font-medium text-slate-700 dark:text-slate-200">{item.warehouse?.name ?? '-'}</td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:text-slate-200">
                                        <User class="h-3.5 w-3.5 text-amber-500" />
                                        {extractUserName(item.user, item.note)}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-xs text-slate-600 dark:text-slate-300 max-w-xs">{item.note ?? '-'}</td>
                                <td class="px-5 py-4 font-semibold text-slate-800 dark:text-slate-100">{item.items.length} SKU</td>
                                <td class="px-5 py-4 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-2">
                                        <button
                                            type="button"
                                            onclick={() => toggleExpandOpname(item.id)}
                                            class="inline-flex items-center gap-1 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 cursor-pointer"
                                        >
                                            {#if isExpanded}
                                                Tutup Detail
                                                <ChevronUp class="h-3.5 w-3.5" />
                                            {:else}
                                                Lihat Barang ({item.items.length})
                                                <ChevronDown class="h-3.5 w-3.5" />
                                            {/if}
                                        </button>

                                        <a
                                            href={`/superadmin/warehouse/stock-opname/${item.id}/pdf`}
                                            target="_blank"
                                            class="inline-flex items-center gap-1 rounded-lg p-2 text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/50 transition-colors"
                                            title="Cetak Laporan Opname PDF"
                                        >
                                            <FileText class="h-4 w-4" />
                                        </a>
                                    </div>
                                </td>
                            </tr>

                            {#if isExpanded}
                                <tr class="bg-slate-50/70 dark:bg-slate-950/60">
                                    <td colspan="8" class="px-6 py-4">
                                        <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
                                            <table class="w-full text-left text-xs">
                                                <thead class="bg-slate-100 dark:bg-slate-800 text-[11px] uppercase font-semibold text-slate-500">
                                                    <tr>
                                                        <th class="px-4 py-2.5">No</th>
                                                        <th class="px-4 py-2.5">Nama Produk</th>
                                                        <th class="px-4 py-2.5">Kode SKU</th>
                                                        <th class="px-4 py-2.5 text-center">Size / Warna</th>
                                                        <th class="px-4 py-2.5 text-right">Stok Awal (Sistem)</th>
                                                        <th class="px-4 py-2.5 text-right">Setelah Opname (Fisik)</th>
                                                        <th class="px-4 py-2.5 text-right">Selisih</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                                    {#each item.items as sub, sIdx (sub.id)}
                                                        <tr>
                                                            <td class="px-4 py-2 text-slate-400">{sIdx + 1}</td>
                                                            <td class="px-4 py-2 font-bold text-slate-800 dark:text-slate-100">
                                                                {sub.product_variant?.product?.product_name ?? '-'}
                                                            </td>
                                                            <td class="px-4 py-2 font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                                                {sub.product_variant?.sku ?? '-'}
                                                            </td>
                                                            <td class="px-4 py-2 text-center">
                                                                {sub.product_variant?.size ?? '-'} / {sub.product_variant?.color ?? '-'}
                                                            </td>
                                                            <td class="px-4 py-2 text-right font-semibold">
                                                                {sub.system_qty.toLocaleString('id-ID')} Pcs
                                                            </td>
                                                            <td class="px-4 py-2 text-right font-extrabold text-slate-900 dark:text-slate-100">
                                                                {sub.physical_qty.toLocaleString('id-ID')} Pcs
                                                            </td>
                                                            <td class="px-4 py-2 text-right font-extrabold {sub.difference > 0 ? 'text-emerald-600' : sub.difference < 0 ? 'text-rose-600' : 'text-slate-400'}">
                                                                {sub.difference > 0 ? `+${sub.difference}` : sub.difference} Pcs
                                                            </td>
                                                        </tr>
                                                    {/each}
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            {/if}
                        {:else}
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-slate-400">Belum ada riwayat audit stock opname.</td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </div>

        <Pagination links={stockOpnames.links} />
    {/if}
</AdminLayout>
