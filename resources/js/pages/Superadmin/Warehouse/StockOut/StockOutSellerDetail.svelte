<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import { Link } from '@inertiajs/svelte';
    import { ArrowLeft, User, Package } from '@lucide/svelte';

    interface Item {
        id: number;
        product_variant?: {
            sku: string;
            size: string;
            color: string;
            product?: { product_name: string };
        };
        qty: number;
        price: number;
        selling_price: number;
    }

    interface StockOutRecord {
        id: number;
        resi_summary: string;
        date: string;
        total_selling_price: number;
        items: Item[];
    }

    let {
        seller,
        sellerStockOuts,
    }: {
        seller: { id: number; name: string };
        sellerStockOuts: {
            data: StockOutRecord[];
            links: any[];
            current_page: number;
            last_page: number;
            total: number;
        };
    } = $props();

    function formatRupiah(num: number): string {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num);
    }
</script>

<AdminLayout title="Detail Penjualan Seller" breadcrumbs={[{ name: 'Mutasi Stok' }, { name: 'Pengeluaran (Stock Out)', url: '/superadmin/warehouse/stock-out' }, { name: seller.name }]}>
    <!-- Header -->
    <div class="mb-8 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <Link
                href="/superadmin/warehouse/stock-out"
                class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-2.5 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors shadow-xs"
            >
                <ArrowLeft class="h-5 w-5" />
            </Link>
            <div>
                <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                    <User class="h-7 w-7 text-indigo-600" />
                    Penjualan Per Seller: {seller.name}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Rincian mutasi keluar produk milik seller ini</p>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-xs border border-slate-100 dark:border-slate-800 overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600 dark:text-slate-400">
                <thead class="bg-slate-50 dark:bg-slate-800/50 text-xs uppercase font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800">
                    <tr>
                        <th class="px-6 py-4">No</th>
                        <th class="px-6 py-4">Ref / Resi</th>
                        <th class="px-6 py-4">Tanggal</th>
                        <th class="px-6 py-4">Produk Terjual</th>
                        <th class="px-6 py-4">Total Qty</th>
                        <th class="px-6 py-4">Total Nilai Jual</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    {#each sellerStockOuts.data as item, index}
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="px-6 py-4 text-xs text-slate-400">{index + 1 + (sellerStockOuts.current_page - 1) * 15}</td>
                            <td class="px-6 py-4 font-mono font-bold text-slate-800 dark:text-slate-100">{item.resi_summary}</td>
                            <td class="px-6 py-4 text-xs">{item.date}</td>
                            <td class="px-6 py-4">
                                {#each item.items as sub}
                                    <div class="text-xs">&bull; {sub.product_variant?.product?.product_name} ({sub.product_variant?.sku}) x {sub.qty} pcs</div>
                                {/each}
                            </td>
                            <td class="px-6 py-4 font-bold text-rose-600">
                                -{item.items.reduce((acc, i) => acc + i.qty, 0)} pcs
                            </td>
                            <td class="px-6 py-4 font-semibold text-slate-800 dark:text-slate-100">{formatRupiah(item.total_selling_price)}</td>
                        </tr>
                    {:else}
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-400">Belum ada riwayat penjualan untuk seller ini.</td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    </div>

    <Pagination links={sellerStockOuts.links} />
</AdminLayout>
