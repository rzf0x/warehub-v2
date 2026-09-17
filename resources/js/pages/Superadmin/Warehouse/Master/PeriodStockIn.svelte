<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import { Link, router } from '@inertiajs/svelte';
    import { ArrowLeft, ArrowDownLeft, Search, Calendar, Warehouse as WarehouseIcon, User as UserIcon, Package, UserCheck } from '@lucide/svelte';

    interface SellerRecord {
        id?: number;
        seller_name?: string;
        name?: string;
    }

    interface ProductRecord {
        product_name: string;
        seller?: SellerRecord;
    }

    interface ProductVariant {
        sku: string;
        size: string;
        color: string;
        product?: ProductRecord;
    }

    interface StockInItem {
        id: number;
        qty: number;
        price: number | string;
        product_variant?: ProductVariant;
    }

    interface StockInRecord {
        id: number;
        invoice_number: string;
        date: string;
        note?: string;
        warehouse?: { name: string };
        user?: { name: string };
        items: StockInItem[];
    }

    interface Period {
        id: number;
        name: string;
        start_date: string;
        end_date: string;
    }

    let {
        period,
        stockIns,
        filters = { search: '' },
    }: {
        period: Period;
        stockIns: {
            data: StockInRecord[];
            links: any[];
            current_page: number;
            last_page: number;
            total: number;
        };
        filters?: { search?: string };
    } = $props();

    let search = $state(filters.search ?? '');

    function formatDate(dateStr: string): string {
        if (!dateStr) return '-';
        const cleanStr = dateStr.split('T')[0];
        const [year, month, day] = cleanStr.split('-');
        if (!year || !month || !day) return dateStr;
        const d = new Date(Number(year), Number(month) - 1, Number(day));
        return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
    }

    function handleSearch() {
        router.get(`/superadmin/warehouse/periods/${period.id}/stock-in`, { search }, { preserveState: true, replace: true });
    }

    function getUniqueSellers(items: StockInItem[]): string[] {
        const set = new Set<string>();
        for (const it of items || []) {
            const sellerName = it.product_variant?.product?.seller?.seller_name || it.product_variant?.product?.seller?.name;
            if (sellerName) {
                set.add(sellerName);
            }
        }
        return Array.from(set);
    }
</script>

<AdminLayout title={`Detail Stock In - ${period.name}`} breadcrumbs={[{ name: 'Gudang & Katalog' }, { name: 'Periode Pembukuan', href: '/superadmin/warehouse/periods' }, { name: 'Stock In' }]}>
    <!-- Navigation Back -->
    <div class="mb-6">
        <Link
            href="/superadmin/warehouse/periods"
            class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-400 transition-colors"
        >
            <ArrowLeft class="h-4 w-4" />
            <span>Kembali ke Periode Pembukuan</span>
        </Link>
    </div>

    <!-- Header Section -->
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center justify-center p-2 rounded-xl bg-sky-100 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400">
                    <ArrowDownLeft class="h-6 w-6" />
                </span>
                <div>
                    <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight">
                        Detail Stock In (Barang Masuk)
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 flex items-center gap-1.5 mt-0.5">
                        <Calendar class="h-3.5 w-3.5" />
                        <span>Periode: <strong class="text-slate-700 dark:text-slate-200">{period.name}</strong> ({formatDate(period.start_date)} - {formatDate(period.end_date)})</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <div class="rounded-xl border border-sky-200 dark:border-sky-800/60 bg-sky-50/50 dark:bg-sky-950/30 px-4 py-2 text-right">
                <span class="block text-[11px] font-semibold text-sky-600 dark:text-sky-400 uppercase tracking-wider">Total Transaksi</span>
                <span class="text-lg font-bold text-slate-800 dark:text-slate-100">{stockIns.total} Transaksi</span>
            </div>
        </div>
    </div>

    <!-- Filter & Search -->
    <div class="mb-6 rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="relative flex-1">
            <Search class="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
            <input
                type="text"
                bind:value={search}
                onkeyup={(e) => e.key === 'Enter' && handleSearch()}
                placeholder="Cari no invoice, gudang, nama seller/pemilik, atau catatan..."
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 pl-10 pr-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500"
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
                        <th class="px-6 py-4">No. Invoice</th>
                        <th class="px-6 py-4">Tanggal</th>
                        <th class="px-6 py-4">Pemilik / Seller</th>
                        <th class="px-6 py-4">Gudang Tujuan</th>
                        <th class="px-6 py-4">Operator</th>
                        <th class="px-6 py-4 text-center">Total Qty</th>
                        <th class="px-6 py-4">Rincian Barang</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    {#each stockIns.data as item, index}
                        {@const sellers = getUniqueSellers(item.items)}
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="px-6 py-4 text-xs text-slate-400">{index + 1 + (stockIns.current_page - 1) * 15}</td>
                            <td class="px-6 py-4 font-mono font-bold text-sky-600 dark:text-sky-400">
                                {item.invoice_number || '-'}
                            </td>
                            <td class="px-6 py-4 text-slate-700 dark:text-slate-200 font-medium">{formatDate(item.date)}</td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    {#each sellers as sellerName}
                                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-purple-50 dark:bg-purple-950/60 px-2.5 py-1 text-xs font-bold text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                                            <UserCheck class="h-3.5 w-3.5 text-purple-500" />
                                            <span>{sellerName}</span>
                                        </span>
                                    {:else}
                                        <span class="text-xs text-slate-400 italic">Umum / Tidak terdefinisi</span>
                                    {/each}
                                </div>
                            </td>
                            <td class="px-6 py-4 font-semibold text-slate-800 dark:text-slate-100 flex items-center gap-1.5">
                                <WarehouseIcon class="h-4 w-4 text-slate-400" />
                                <span>{item.warehouse?.name ?? '-'}</span>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-500 dark:text-slate-400">
                                {item.user?.name ?? '-'}
                            </td>
                            <td class="px-6 py-4 text-center font-bold text-sky-700 dark:text-sky-300">
                                <span class="rounded-lg bg-sky-50 dark:bg-sky-950/60 px-2.5 py-1 text-xs border border-sky-200 dark:border-sky-800">
                                    {item.items?.reduce((sum, i) => sum + (i.qty || 0), 0)} Pcs
                                </span>
                            </td>
                            <td class="px-6 py-4 max-w-sm">
                                <div class="flex flex-wrap gap-1.5 max-h-28 overflow-y-auto">
                                    {#each item.items ?? [] as it}
                                        {@const itemSeller = it.product_variant?.product?.seller?.seller_name || it.product_variant?.product?.seller?.name}
                                        <span class="rounded-md bg-slate-100 dark:bg-slate-800/80 px-2 py-1 text-[11px] font-medium text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 flex items-center gap-1">
                                            <Package class="h-3 w-3 text-slate-400" />
                                            {#if itemSeller}
                                                <span class="font-bold text-purple-600 dark:text-purple-400">[{itemSeller}]</span>
                                            {/if}
                                            <span>{it.product_variant?.product?.product_name ?? 'Produk'}</span>
                                            <span class="font-mono text-slate-500">({it.product_variant?.sku ?? '-'})</span>
                                            <span class="font-bold text-sky-600">x{it.qty}</span>
                                        </span>
                                    {/each}
                                </div>
                            </td>
                        </tr>
                    {:else}
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-slate-400">
                                Belum ada transaksi Stock In pada periode ini.
                            </td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <Pagination links={stockIns.links} />
</AdminLayout>
