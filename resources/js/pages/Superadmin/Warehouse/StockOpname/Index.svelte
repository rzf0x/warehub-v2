<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import Badge from '@/components/Badge.svelte';
    import { router, Link } from '@inertiajs/svelte';
    import { ClipboardCheck, Search, Plus, FileText, CheckCircle2, AlertTriangle } from '@lucide/svelte';

    interface Item {
        id: number;
        product_variant?: {
            sku: string;
            size: string;
            color: string;
            product?: { product_name: string };
        };
        system_qty: number;
        physical_qty: number;
        difference: number;
    }

    interface StockOpnameRecord {
        id: number;
        date: string;
        note: string | null;
        warehouse?: { id: number; name: string };
        user?: { name: string };
        items: Item[];
    }

    let {
        stockOpnames,
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
        warehouses?: Array<{ id: number; name: string }>;
        filters?: { search?: string; warehouse_id?: string };
    } = $props();

    let search = $state(filters.search ?? '');
    let warehouseId = $state(filters.warehouse_id ?? '');

    function applyFilter() {
        router.get('/superadmin/warehouse/stock-opname', { search, warehouse_id: warehouseId }, { preserveState: true, replace: true });
    }
</script>

<AdminLayout title="Audit Stock Opname" breadcrumbs={[{ name: 'Mutasi Stok' }, { name: 'Audit Stock Opname' }]}>
    <!-- Header -->
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                <ClipboardCheck class="h-7 w-7 text-amber-500" />
                Audit Stock Opname Gudang
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Pencocokan stok fisik lapangan dengan stok sistem & penyesuaian otomatis</p>
        </div>

        <Link
            href="/superadmin/warehouse/stock-opname/create"
            class="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-amber-600/30 hover:bg-amber-700 transition-colors"
        >
            <Plus class="h-4 w-4" />
            Mulai Stock Opname
        </Link>
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
                placeholder="Cari berdasarkan catatan audit..."
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 pl-10 pr-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500"
            />
        </div>
    </div>

    <!-- Table -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-xs border border-slate-100 dark:border-slate-800 overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600 dark:text-slate-400">
                <thead class="bg-slate-50 dark:bg-slate-800/50 text-xs uppercase font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800">
                    <tr>
                        <th class="px-6 py-4">No</th>
                        <th class="px-6 py-4">ID Opname</th>
                        <th class="px-6 py-4">Gudang</th>
                        <th class="px-6 py-4">Auditor / User</th>
                        <th class="px-6 py-4">Total Item Diaudit</th>
                        <th class="px-6 py-4">Status Selisih</th>
                        <th class="px-6 py-4">Tanggal Audit</th>
                        <th class="px-6 py-4 text-right">Laporan PDF</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    {#each stockOpnames.data as item, index}
                        {@const hasDiff = item.items.some((i) => i.difference !== 0)}
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="px-6 py-4 text-xs text-slate-400">{index + 1 + (stockOpnames.current_page - 1) * 10}</td>
                            <td class="px-6 py-4 font-mono font-bold text-slate-800 dark:text-slate-100">#OPN-{item.id}</td>
                            <td class="px-6 py-4 font-medium text-slate-700 dark:text-slate-200">{item.warehouse?.name ?? '-'}</td>
                            <td class="px-6 py-4 text-xs text-slate-500">{item.user?.name ?? '-'}</td>
                            <td class="px-6 py-4 font-semibold text-slate-800 dark:text-slate-100">{item.items.length} varian</td>
                            <td class="px-6 py-4">
                                {#if hasDiff}
                                    <Badge variant="danger">
                                        Ada Selisih Stok
                                    </Badge>
                                {:else}
                                    <Badge variant="success">
                                        100% Sesuai
                                    </Badge>
                                {/if}
                            </td>
                            <td class="px-6 py-4 text-xs">{item.date}</td>
                            <td class="px-6 py-4 text-right">
                                <a
                                    href={`/superadmin/warehouse/stock-opname/${item.id}/pdf`}
                                    target="_blank"
                                    class="inline-flex items-center gap-1 rounded-lg p-2 text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/50 transition-colors"
                                    title="Cetak Laporan Opname PDF"
                                >
                                    <FileText class="h-4 w-4" />
                                </a>
                            </td>
                        </tr>
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
</AdminLayout>
