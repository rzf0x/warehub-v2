<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import { router, Link } from '@inertiajs/svelte';
    import { ArrowLeft, UserCheck, ArrowDownLeft, ArrowUpRight, Plus, Trash2, Calendar, FileText, Wallet } from '@lucide/svelte';

    interface SellerDetail {
        id: number;
        seller_name: string;
        phone: string | null;
        address: string | null;
    }

    interface ItemRow {
        id: number;
        qty: number;
        price: number;
        product_variant?: {
            sku: string;
            size: string;
            color: string;
            product?: { product_name: string };
        };
        stock_in?: { invoice_number: string; date: string; warehouse?: { name: string } };
        stock_out?: { date: string; store?: { store_name: string } };
    }

    interface AdjustmentItem {
        id: number;
        amount: number;
        adjustment_date: string;
        note: string | null;
        period?: { name: string };
    }

    let {
        seller,
        stockInItems,
        stockOutItems,
        adjustments = [],
        periods = [],
        stats = { stock_in_hpp: 0, stock_out_hpp: 0, total_adjustment: 0, net_debt: 0 },
        filters = { period_id: '' },
    }: {
        seller: SellerDetail;
        stockInItems: { data: ItemRow[]; links: any[] };
        stockOutItems: { data: ItemRow[]; links: any[] };
        adjustments?: AdjustmentItem[];
        periods?: { id: number; name: string }[];
        stats?: { stock_in_hpp: number; stock_out_hpp: number; total_adjustment: number; net_debt: number };
        filters?: { period_id?: string };
    } = $props();

    let periodId = $state(filters.period_id ?? '');

    function formatRupiah(num: number): string {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num || 0);
    }

    function formatDate(dateStr: string): string {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    function applyFilter() {
        router.get(`/superadmin/finance/seller-debt/${seller.id}`, { period_id: periodId }, { preserveState: true, replace: true });
    }

    function deleteAdjustment(id: number) {
        if (confirm('Yakin ingin menghapus penyesuaian ini?')) {
            router.delete(`/superadmin/finance/seller-debt/adjustment/${id}`);
        }
    }
</script>

<AdminLayout title="Detail Hutang {seller.seller_name}" breadcrumbs={[{ name: 'Keuangan' }, { name: 'Hutang Seller', href: '/superadmin/finance/seller-debt' }, { name: seller.seller_name }]}>
    <!-- Header -->
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <Link
                href="/superadmin/finance/seller-debt"
                class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-2.5 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors shadow-xs"
            >
                <ArrowLeft class="h-5 w-5" />
            </Link>
            <div>
                <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                    <UserCheck class="h-7 w-7 text-purple-500" />
                    Rincian Hutang: {seller.seller_name}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Riwayat Stock In HPP, Stock Out HPP & Penyesuaian Saldo</p>
            </div>
        </div>

        <div class="w-full sm:w-64">
            <select
                bind:value={periodId}
                onchange={applyFilter}
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3.5 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-purple-500"
            >
                <option value="">Semua Periode</option>
                {#each periods as p}
                    <option value={p.id}>{p.name}</option>
                {/each}
            </select>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="mb-8 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Total Stock In HPP</span>
            <span class="text-xl font-bold text-sky-600 dark:text-sky-400 mt-1 block">{formatRupiah(stats.stock_in_hpp)}</span>
        </div>

        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Total Stock Out HPP</span>
            <span class="text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-1 block">{formatRupiah(stats.stock_out_hpp)}</span>
        </div>

        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Penyesuaian Saldo</span>
            <span class="text-xl font-bold text-indigo-600 dark:text-indigo-400 mt-1 block">{formatRupiah(stats.total_adjustment)}</span>
        </div>

        <div class="rounded-2xl bg-gradient-to-br from-purple-900 to-indigo-900 p-5 text-white shadow-md">
            <span class="block text-xs font-semibold uppercase tracking-wider text-purple-200">Sisa Net Saldo Hutang</span>
            <span class="text-xl font-black text-amber-300 mt-1 block">{formatRupiah(stats.net_debt)}</span>
        </div>
    </div>

    <!-- Adjustment History -->
    {#if adjustments.length > 0}
        <div class="mb-8 rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-100 dark:border-slate-800 space-y-4">
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                <Wallet class="h-5 w-5 text-purple-500" />
                Riwayat Penyesuaian Saldo (Adjustments)
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 font-semibold uppercase">
                        <tr>
                            <th class="p-3">Tanggal</th>
                            <th class="p-3">Periode</th>
                            <th class="p-3">Catatan</th>
                            <th class="p-3 text-right">Nominal (Rp)</th>
                            <th class="p-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        {#each adjustments as adj}
                            <tr>
                                <td class="p-3 font-medium">{formatDate(adj.adjustment_date)}</td>
                                <td class="p-3">{adj.period?.name ?? '-'}</td>
                                <td class="p-3 text-slate-600 dark:text-slate-300">{adj.note || '-'}</td>
                                <td class="p-3 text-right font-bold {adj.amount >= 0 ? 'text-indigo-600' : 'text-rose-500'}">
                                    {formatRupiah(adj.amount)}
                                </td>
                                <td class="p-3 text-center">
                                    <button
                                        onclick={() => deleteAdjustment(adj.id)}
                                        class="text-rose-500 hover:text-rose-700 p-1"
                                        title="Hapus Penyesuaian"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </div>
    {/if}

    <!-- Tables Grid: Stock In vs Stock Out -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Stock In Table -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-100 dark:border-slate-800 space-y-4">
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                <ArrowDownLeft class="h-5 w-5 text-sky-500" />
                Barang Masuk (Stock In HPP)
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 font-semibold uppercase">
                        <tr>
                            <th class="p-2.5">Tgl & Invoice</th>
                            <th class="p-2.5">Produk & Varian</th>
                            <th class="p-2.5 text-center">Qty</th>
                            <th class="p-2.5 text-right">Subtotal HPP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        {#each stockInItems.data as item}
                            <tr>
                                <td class="p-2.5">
                                    <span class="font-mono font-bold text-sky-600 block">{item.stock_in?.invoice_number}</span>
                                    <span class="text-[10px] text-slate-400">{formatDate(item.stock_in?.date || '')}</span>
                                </td>
                                <td class="p-2.5">
                                    <span class="font-medium block">{item.product_variant?.product?.product_name}</span>
                                    <span class="text-[10px] text-slate-400 font-mono">SKU: {item.product_variant?.sku}</span>
                                </td>
                                <td class="p-2.5 text-center font-bold">{item.qty} Pcs</td>
                                <td class="p-2.5 text-right font-bold text-sky-600">{formatRupiah(item.qty * item.price)}</td>
                            </tr>
                        {:else}
                            <tr>
                                <td colspan="4" class="p-6 text-center text-slate-400">Tidak ada item Stock In.</td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
            <Pagination links={stockInItems.links} />
        </div>

        <!-- Stock Out Table -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-100 dark:border-slate-800 space-y-4">
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                <ArrowUpRight class="h-5 w-5 text-emerald-500" />
                Barang Keluar (Stock Out HPP)
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 font-semibold uppercase">
                        <tr>
                            <th class="p-2.5">Tanggal & Toko</th>
                            <th class="p-2.5">Produk & Varian</th>
                            <th class="p-2.5 text-center">Qty</th>
                            <th class="p-2.5 text-right">Subtotal HPP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        {#each stockOutItems.data as item}
                            <tr>
                                <td class="p-2.5">
                                    <span class="font-medium block">{item.stock_out?.store?.store_name ?? 'Penjualan Umum'}</span>
                                    <span class="text-[10px] text-slate-400">{formatDate(item.stock_out?.date || '')}</span>
                                </td>
                                <td class="p-2.5">
                                    <span class="font-medium block">{item.product_variant?.product?.product_name}</span>
                                    <span class="text-[10px] text-slate-400 font-mono">SKU: {item.product_variant?.sku}</span>
                                </td>
                                <td class="p-2.5 text-center font-bold">{item.qty} Pcs</td>
                                <td class="p-2.5 text-right font-bold text-emerald-600">{formatRupiah(item.qty * item.price)}</td>
                            </tr>
                        {:else}
                            <tr>
                                <td colspan="4" class="p-6 text-center text-slate-400">Tidak ada item Stock Out.</td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
            <Pagination links={stockOutItems.links} />
        </div>
    </div>
</AdminLayout>
