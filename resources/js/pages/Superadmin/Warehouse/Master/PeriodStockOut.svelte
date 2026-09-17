<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import { Link, router } from '@inertiajs/svelte';
    import { ArrowLeft, ArrowUpRight, Search, Calendar, Warehouse as WarehouseIcon, Store as StoreIcon, Package, UserCheck } from '@lucide/svelte';

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

    interface StockOutItem {
        id: number;
        qty: number;
        price: number | string;
        selling_price: number | string;
        product_variant?: ProductVariant;
    }

    interface StockOutRecord {
        id: number;
        date: string;
        note?: string;
        total_selling_price?: number | string;
        warehouse?: { name: string };
        store?: { name: string };
        user?: { name: string };
        items: StockOutItem[];
    }

    interface Period {
        id: number;
        name: string;
        start_date: string;
        end_date: string;
    }

    let {
        period,
        stockOuts,
        filters = { search: '' },
    }: {
        period: Period;
        stockOuts: {
            data: StockOutRecord[];
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

    function formatRupiah(num: number | string | null | undefined): string {
        if (num === null || num === undefined || num === '') return '-';
        const val = typeof num === 'string' ? parseFloat(num) : num;
        if (isNaN(val)) return '-';
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(val);
    }

    function handleSearch() {
        router.get(`/superadmin/warehouse/periods/${period.id}/stock-out`, { search }, { preserveState: true, replace: true });
    }

    function getUniqueSellers(items: StockOutItem[]): string[] {
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

<AdminLayout title={`Detail Stock Out - ${period.name}`} breadcrumbs={[{ name: 'Gudang & Katalog' }, { name: 'Periode Pembukuan', href: '/superadmin/warehouse/periods' }, { name: 'Stock Out' }]}>
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
                <span class="inline-flex items-center justify-center p-2 rounded-xl bg-rose-100 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400">
                    <ArrowUpRight class="h-6 w-6" />
                </span>
                <div>
                    <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight">
                        Detail Stock Out (Barang Keluar)
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 flex items-center gap-1.5 mt-0.5">
                        <Calendar class="h-3.5 w-3.5" />
                        <span>Periode: <strong class="text-slate-700 dark:text-slate-200">{period.name}</strong> ({formatDate(period.start_date)} - {formatDate(period.end_date)})</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <div class="rounded-xl border border-rose-200 dark:border-rose-800/60 bg-rose-50/50 dark:bg-rose-950/30 px-4 py-2 text-right">
                <span class="block text-[11px] font-semibold text-rose-600 dark:text-rose-400 uppercase tracking-wider">Total Transaksi</span>
                <span class="text-lg font-bold text-slate-800 dark:text-slate-100">{stockOuts.total} Transaksi</span>
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
                placeholder="Cari gudang, toko, nama seller/pemilik, atau catatan..."
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 pl-10 pr-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-rose-500"
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
                        <th class="px-6 py-4">Tanggal</th>
                        <th class="px-6 py-4">Pemilik / Seller</th>
                        <th class="px-6 py-4">Gudang Asal</th>
                        <th class="px-6 py-4">Toko / Store Tujuan</th>
                        <th class="px-6 py-4">Operator</th>
                        <th class="px-6 py-4 text-center">Total Qty</th>
                        <th class="px-6 py-4">Total Omset</th>
                        <th class="px-6 py-4">Rincian Barang</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    {#each stockOuts.data as item, index}
                        {@const sellers = getUniqueSellers(item.items)}
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="px-6 py-4 text-xs text-slate-400">{index + 1 + (stockOuts.current_page - 1) * 15}</td>
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
                            <td class="px-6 py-4 font-semibold text-indigo-600 dark:text-indigo-400 flex items-center gap-1.5">
                                <StoreIcon class="h-4 w-4 text-indigo-400" />
                                <span>{item.store?.name ?? '-'}</span>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-500 dark:text-slate-400">
                                {item.user?.name ?? '-'}
                            </td>
                            <td class="px-6 py-4 text-center font-bold text-rose-700 dark:text-rose-300">
                                <span class="rounded-lg bg-rose-50 dark:bg-rose-950/60 px-2.5 py-1 text-xs border border-rose-200 dark:border-rose-800">
                                    {item.items?.reduce((sum, i) => sum + (i.qty || 0), 0)} Pcs
                                </span>
                            </td>
                            <td class="px-6 py-4 font-bold text-emerald-600 dark:text-emerald-400">
                                {formatRupiah(item.total_selling_price)}
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
                                            <span class="font-bold text-rose-600">x{it.qty}</span>
                                        </span>
                                    {/each}
                                </div>
                            </td>
                        </tr>
                    {:else}
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-slate-400">
                                Belum ada transaksi Stock Out pada periode ini.
                            </td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <Pagination links={stockOuts.links} />
</AdminLayout>
