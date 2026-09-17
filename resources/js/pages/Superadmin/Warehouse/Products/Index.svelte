<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import ConfirmDialog from '@/components/ConfirmDialog.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import Badge from '@/components/Badge.svelte';
    import Modal from '@/components/Modal.svelte';
    import { router, Link } from '@inertiajs/svelte';
    import { Package, Search, Plus, Edit2, Trash2, Printer, PackagePlus, Layers, Store, Eye, Tag } from '@lucide/svelte';

    interface Variant {
        id: number;
        sku: string;
        size: string;
        color: string;
        price: number | string | null;
        selling_price: number | string | null;
        barcode: string | null;
    }

    interface ProductItem {
        id: number;
        product_name: string;
        sku: string;
        product_type: string;
        seller?: { id: number; name: string };
        brand?: { id: number; name: string };
        category?: { id: number; name: string };
        konveksi?: { id: number; name: string };
        variants: Variant[];
        created_at: string;
    }

    let {
        products,
        sellers = [],
        brands = [],
        filters = { search: '', seller_id: '', brand_id: '' },
    }: {
        products: {
            data: ProductItem[];
            links: any[];
            current_page: number;
            last_page: number;
            total: number;
        };
        sellers?: Array<{ id: number; name: string }>;
        brands?: Array<{ id: number; name: string }>;
        filters?: { search?: string; seller_id?: string; brand_id?: string };
    } = $props();

    let search = $state(filters.search ?? '');
    let sellerId = $state(filters.seller_id ?? '');
    let brandId = $state(filters.brand_id ?? '');

    let isConfirmDeleteOpen = $state(false);
    let selectedProduct = $state<ProductItem | null>(null);

    let isDetailModalOpen = $state(false);
    let detailProduct = $state<ProductItem | null>(null);

    function applyFilter() {
        router.get(
            '/superadmin/warehouse/products',
            { search, seller_id: sellerId, brand_id: brandId },
            { preserveState: true, replace: true }
        );
    }

    function openDetailModal(prod: ProductItem) {
        detailProduct = prod;
        isDetailModalOpen = true;
    }

    function openDeleteModal(prod: ProductItem) {
        selectedProduct = prod;
        isConfirmDeleteOpen = true;
    }

    function submitDelete() {
        if (!selectedProduct) return;
        router.delete(`/superadmin/warehouse/products/${selectedProduct.id}`, {
            onSuccess: () => {
                isConfirmDeleteOpen = false;
            },
        });
    }

    function formatRupiah(num: number | string | null | undefined): string {
        if (num === null || num === undefined || num === '') return '-';
        const val = typeof num === 'string' ? parseFloat(num) : num;
        if (isNaN(val)) return '-';
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(val);
    }
</script>

<AdminLayout title="Katalog Produk & Varian" breadcrumbs={[{ name: 'Gudang & Katalog' }, { name: 'Katalog Produk' }]}>
    <!-- Page Header -->
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                <Package class="h-7 w-7 text-indigo-600" />
                Katalog Produk & Varian
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Manajemen katalog barang master, varian SKU, HPP, dan harga jual</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a
                href="/superadmin/warehouse/products/pdf"
                target="_blank"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3.5 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors shadow-xs"
            >
                <Printer class="h-4 w-4 text-slate-500" />
                Cetak PDF Stream
            </a>

            <Link
                href="/superadmin/warehouse/products/by-seller"
                class="inline-flex items-center gap-2 rounded-xl border border-sky-200 dark:border-sky-900 bg-sky-50 dark:bg-sky-950/50 px-3.5 py-2.5 text-sm font-semibold text-sky-700 dark:text-sky-300 hover:bg-sky-100 transition-colors shadow-xs"
            >
                <Store class="h-4 w-4 text-sky-600 dark:text-sky-400" />
                List Product by Seller
            </Link>

            <Link
                href="/superadmin/warehouse/products/bundle/create"
                class="inline-flex items-center gap-2 rounded-xl bg-purple-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-purple-600/30 hover:bg-purple-700 transition-colors"
            >
                <PackagePlus class="h-4 w-4" />
                Rakit Bundle
            </Link>

            <Link
                href="/superadmin/warehouse/products/create"
                class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/30 hover:bg-indigo-700 transition-colors"
            >
                <Plus class="h-4 w-4" />
                Tambah Produk
            </Link>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="mb-6 rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 flex flex-col md:flex-row items-center gap-4">
        <!-- Search Input -->
        <div class="relative flex-1 w-full">
            <Search class="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
            <input
                type="text"
                bind:value={search}
                onkeyup={(e) => e.key === 'Enter' && applyFilter()}
                placeholder="Cari berdasarkan nama produk, SKU, barcode..."
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 pl-10 pr-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
        </div>

        <!-- Filter Seller -->
        <div class="w-full md:w-48">
            <select
                bind:value={sellerId}
                onchange={applyFilter}
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
                <option value="">Semua Seller</option>
                {#each sellers as s}
                    <option value={s.id}>{s.name}</option>
                {/each}
            </select>
        </div>

        <!-- Filter Brand -->
        <div class="w-full md:w-48">
            <select
                bind:value={brandId}
                onchange={applyFilter}
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
                <option value="">Semua Brand</option>
                {#each brands as b}
                    <option value={b.id}>{b.name}</option>
                {/each}
            </select>
        </div>
    </div>

    <!-- Data Table -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-xs border border-slate-100 dark:border-slate-800 overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600 dark:text-slate-400">
                <thead class="bg-slate-50 dark:bg-slate-800/50 text-xs uppercase font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800">
                    <tr>
                        <th class="px-6 py-4">No</th>
                        <th class="px-6 py-4">Nama Produk & SKU Master</th>
                        <th class="px-6 py-4">Seller & Brand</th>
                        <th class="px-6 py-4">Kategori</th>
                        <th class="px-6 py-4">Tipe</th>
                        <th class="px-6 py-4">Total Varian</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    {#each products.data as item, index}
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="px-6 py-4 text-xs text-slate-400">{index + 1 + (products.current_page - 1) * 10}</td>
                            <td class="px-6 py-4">
                                <p class="font-bold text-slate-800 dark:text-slate-100">{item.product_name}</p>
                                <span class="inline-flex items-center text-xs text-indigo-600 font-mono">SKU: {item.sku}</span>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-xs font-semibold text-slate-700 dark:text-slate-200 flex items-center gap-1">
                                    <Store class="h-3.5 w-3.5 text-slate-400" />
                                    {item.seller?.name ?? '-'}
                                </p>
                                <p class="text-[11px] text-slate-500 flex items-center gap-1">
                                    <Layers class="h-3 w-3 text-slate-400" />
                                    {item.brand?.name ?? '-'}
                                </p>
                            </td>
                            <td class="px-6 py-4 text-slate-700 dark:text-slate-300">{item.category?.name ?? '-'}</td>
                            <td class="px-6 py-4">
                                <Badge variant={item.product_type === 'bundle' ? 'warning' : 'primary'}>
                                    {item.product_type.toUpperCase()}
                                </Badge>
                            </td>
                            <td class="px-6 py-4">
                                <button
                                    onclick={() => openDetailModal(item)}
                                    class="font-semibold text-indigo-600 dark:text-indigo-400 hover:underline"
                                >
                                    {item.variants.length} varian
                                </button>
                            </td>
                            <td class="px-6 py-4 text-right space-x-1">
                                <button
                                    onclick={() => openDetailModal(item)}
                                    class="inline-flex items-center gap-1 rounded-lg p-2 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                                    title="Lihat Detail Varian"
                                >
                                    <Eye class="h-4 w-4" />
                                </button>
                                <Link
                                    href={`/superadmin/warehouse/products/${item.id}/edit`}
                                    class="inline-flex items-center gap-1 rounded-lg p-2 text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 transition-colors"
                                    title="Edit Produk & Varian"
                                >
                                    <Edit2 class="h-4 w-4" />
                                </Link>
                                <button
                                    onclick={() => openDeleteModal(item)}
                                    class="inline-flex items-center gap-1 rounded-lg p-2 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition-colors"
                                    title="Hapus Produk"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                    {:else}
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-400">Tidak ada produk ditemukan.</td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    </div>

    <Pagination links={products.links} />

    <!-- Detail Product & Variants Modal -->
    <Modal show={isDetailModalOpen} title="Detail Produk & Daftar Varian" maxWidth="4xl" onclose={() => isDetailModalOpen = false}>
        {#if detailProduct}
            <div class="space-y-6">
                <!-- Summary Info Box -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                    <div>
                        <span class="text-xs text-slate-400 font-medium uppercase">Nama Produk</span>
                        <p class="text-sm font-bold text-slate-800 dark:text-slate-100 mt-0.5">{detailProduct.product_name}</p>
                        <span class="text-xs font-mono text-indigo-600">SKU: {detailProduct.sku}</span>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400 font-medium uppercase">Seller / Brand</span>
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200 mt-0.5">{detailProduct.seller?.name ?? '-'}</p>
                        <p class="text-xs text-slate-500">{detailProduct.brand?.name ?? '-'}</p>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400 font-medium uppercase">Kategori / Konveksi</span>
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200 mt-0.5">{detailProduct.category?.name ?? '-'}</p>
                        <p class="text-xs text-slate-500">{detailProduct.konveksi?.name ?? '-'}</p>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400 font-medium uppercase">Tipe & Total Varian</span>
                        <div class="mt-1 flex items-center gap-2">
                            <Badge variant={detailProduct.product_type === 'bundle' ? 'warning' : 'primary'}>
                                {detailProduct.product_type.toUpperCase()}
                            </Badge>
                            <span class="text-xs font-bold text-slate-800 dark:text-slate-100">{detailProduct.variants.length} Varian</span>
                        </div>
                    </div>
                </div>

                <!-- Variants Table -->
                <div>
                    <h4 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-3 flex items-center gap-2">
                        <Tag class="h-4 w-4 text-indigo-600" />
                        Daftar Seluruh Varian Produk ({detailProduct.variants.length})
                    </h4>
                    <div class="overflow-x-auto rounded-xl border border-slate-100 dark:border-slate-800">
                        <table class="w-full text-left text-xs text-slate-600 dark:text-slate-400">
                            <thead class="bg-slate-50 dark:bg-slate-800/80 font-semibold uppercase text-slate-500 border-b border-slate-100 dark:border-slate-800">
                                <tr>
                                    <th class="px-4 py-3">No</th>
                                    <th class="px-4 py-3">SKU Varian</th>
                                    <th class="px-4 py-3">Ukuran</th>
                                    <th class="px-4 py-3">Warna</th>
                                    <th class="px-4 py-3">Barcode</th>
                                    <th class="px-4 py-3">HPP (Modal)</th>
                                    <th class="px-4 py-3">Harga Jual</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                {#each detailProduct.variants as v, i}
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                        <td class="px-4 py-3 text-slate-400">{i + 1}</td>
                                        <td class="px-4 py-3 font-mono font-bold text-indigo-600 dark:text-indigo-400">{v.sku}</td>
                                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{v.size}</td>
                                        <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{v.color}</td>
                                        <td class="px-4 py-3 font-mono text-slate-500">{v.barcode ?? '-'}</td>
                                        <td class="px-4 py-3 font-semibold text-slate-700 dark:text-slate-200">{formatRupiah(v.price)}</td>
                                        <td class="px-4 py-3 font-bold text-emerald-600 dark:text-emerald-400">{formatRupiah(v.selling_price)}</td>
                                    </tr>
                                {:else}
                                    <tr>
                                        <td colspan="7" class="px-4 py-6 text-center text-slate-400">Tidak ada varian terdaftar.</td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Footer Close Button -->
                <div class="flex justify-end pt-4 border-t border-slate-100 dark:border-slate-800">
                    <button
                        type="button"
                        onclick={() => isDetailModalOpen = false}
                        class="rounded-xl border border-slate-200 dark:border-slate-700 px-5 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        {/if}
    </Modal>

    <!-- Delete Confirm -->
    <ConfirmDialog
        show={isConfirmDeleteOpen}
        title="Hapus Produk"
        message="Apakah Anda yakin ingin menghapus produk '{selectedProduct?.product_name}' beserta seluruh variannya?"
        confirmText="Hapus Produk"
        onconfirm={submitDelete}
        oncancel={() => isConfirmDeleteOpen = false}
    />
</AdminLayout>
