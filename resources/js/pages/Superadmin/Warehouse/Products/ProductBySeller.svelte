<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import Badge from '@/components/Badge.svelte';
    import Modal from '@/components/Modal.svelte';
    import { router, Link } from '@inertiajs/svelte';
    import { Store, Search, Package, Layers, ArrowLeft, Eye, Tag, ChevronRight, UserCheck } from '@lucide/svelte';

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

    interface SellerItem {
        id: number;
        name: string;
        brand_name?: string | null;
        products_count: number;
    }

    let {
        sellers = [],
        selectedSeller = null,
        products = null,
        filters = { seller_id: '', search: '' },
    }: {
        sellers?: SellerItem[];
        selectedSeller?: SellerItem | null;
        products?: {
            data: ProductItem[];
            links: any[];
            current_page: number;
            last_page: number;
            total: number;
        } | null;
        filters?: { seller_id?: string; search?: string };
    } = $props();

    let sellerId = $state(filters.seller_id ?? '');
    let search = $state(filters.search ?? '');
    let sellerSearch = $state('');

    let isDetailModalOpen = $state(false);
    let detailProduct = $state<ProductItem | null>(null);

    const filteredSellers = $derived(
        sellers.filter(
            (s) =>
                s.name?.toLowerCase().includes(sellerSearch.toLowerCase()) ||
                (s.brand_name && s.brand_name.toLowerCase().includes(sellerSearch.toLowerCase()))
        )
    );

    function selectSeller(id: number | string) {
        sellerId = String(id);
        search = '';
        router.get('/superadmin/warehouse/products/by-seller', { seller_id: sellerId }, { preserveState: true });
    }

    function clearSelectedSeller() {
        sellerId = '';
        search = '';
        router.get('/superadmin/warehouse/products/by-seller', {}, { preserveState: true });
    }

    function handleSearchProducts() {
        router.get('/superadmin/warehouse/products/by-seller', { seller_id: sellerId, search }, { preserveState: true, replace: true });
    }

    function openDetailModal(prod: ProductItem) {
        detailProduct = prod;
        isDetailModalOpen = true;
    }

    function formatRupiah(num: number | string | null | undefined): string {
        if (num === null || num === undefined || num === '') return '-';
        const val = typeof num === 'string' ? parseFloat(num) : num;
        if (isNaN(val)) return '-';
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(val);
    }
</script>

<AdminLayout title="List Produk Per Seller" breadcrumbs={[{ name: 'Gudang & Katalog' }, { name: 'Katalog Produk', url: '/superadmin/warehouse/products' }, { name: 'Produk Per Seller' }]}>
    <!-- Page Header -->
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <Link
                href="/superadmin/warehouse/products"
                class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-2.5 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors shadow-xs"
            >
                <ArrowLeft class="h-5 w-5" />
            </Link>
            <div>
                <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                    <Store class="h-7 w-7 text-indigo-600" />
                    {selectedSeller ? `Produk Milik: ${selectedSeller.name}` : 'List Produk Per Seller'}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {selectedSeller ? `Menampilkan seluruh barang master dan varian milik ${selectedSeller.name}` : 'Pilih seller mitra untuk melihat detail produk yang dimiliki'}
                </p>
            </div>
        </div>

        {#if selectedSeller}
            <button
                onclick={clearSelectedSeller}
                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors shadow-xs"
            >
                <ArrowLeft class="h-4 w-4" />
                Pilih Seller Lain
            </button>
        {/if}
    </div>

    {#if !selectedSeller}
        <!-- SELLER SELECTION GRID -->
        <div class="space-y-6">
            <!-- Filter / Search Seller -->
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 flex items-center gap-4">
                <div class="relative flex-1 w-full">
                    <Search class="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
                    <input
                        type="text"
                        bind:value={sellerSearch}
                        placeholder="Cari seller berdasarkan nama atau brand..."
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 pl-10 pr-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
            </div>

            <!-- Sellers Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {#each filteredSellers as seller}
                    <button
                        type="button"
                        onclick={() => selectSeller(seller.id)}
                        class="text-left rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-100 dark:border-slate-800 hover:border-indigo-500 dark:hover:border-indigo-500 hover:shadow-md transition-all group flex flex-col justify-between space-y-4"
                    >
                        <div class="flex items-start justify-between w-full">
                            <div class="flex items-center gap-3">
                                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-400 group-hover:bg-indigo-600 group-hover:text-white transition-colors shadow-xs">
                                    <Store class="h-6 w-6" />
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">{seller.name}</h3>
                                    {#if seller.brand_name}
                                        <p class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1 mt-0.5">
                                            <Layers class="h-3 w-3 text-slate-400" />
                                            {seller.brand_name}
                                        </p>
                                    {/if}
                                </div>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between w-full">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 dark:bg-slate-800 px-3 py-1 text-xs font-semibold text-slate-700 dark:text-slate-300">
                                <Package class="h-3.5 w-3.5 text-indigo-500" />
                                {seller.products_count} Produk
                            </span>

                            <span class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                                Lihat Katalog
                                <ChevronRight class="h-4 w-4" />
                            </span>
                        </div>
                    </button>
                {:else}
                    <div class="col-span-full rounded-2xl bg-white dark:bg-slate-900 p-12 text-center text-slate-400 border border-slate-100 dark:border-slate-800">
                        Tidak ada seller ditemukan.
                    </div>
                {/each}
            </div>
        </div>
    {:else}
        <!-- PRODUCTS LIST FOR SELECTED SELLER -->
        <div class="space-y-6">
            <!-- Filter Bar -->
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 flex flex-col md:flex-row items-center gap-4">
                <!-- Seller Quick Select Dropdown -->
                <div class="w-full md:w-64">
                    <select
                        bind:value={sellerId}
                        onchange={() => selectSeller(sellerId)}
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        {#each sellers as s}
                            <option value={String(s.id)}>{s.name} ({s.products_count} produk)</option>
                        {/each}
                    </select>
                </div>

                <!-- Product Search Input -->
                <div class="relative flex-1 w-full">
                    <Search class="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
                    <input
                        type="text"
                        bind:value={search}
                        onkeyup={(e) => e.key === 'Enter' && handleSearchProducts()}
                        placeholder="Cari produk atau SKU milik seller ini..."
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 pl-10 pr-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
            </div>

            <!-- Products Grid Cards -->
            {#if products && products.data.length > 0}
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {#each products.data as item}
                        <div class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-100 dark:border-slate-800 flex flex-col justify-between space-y-4">
                            <div>
                                <div class="flex items-start justify-between">
                                    <div>
                                        <Badge variant={item.product_type === 'bundle' ? 'warning' : 'primary'}>
                                            {item.product_type.toUpperCase()}
                                        </Badge>
                                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 mt-2">{item.product_name}</h3>
                                        <p class="text-xs font-mono text-indigo-600 mt-0.5">SKU: {item.sku}</p>
                                    </div>
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-400">
                                        <Package class="h-5 w-5" />
                                    </div>
                                </div>

                                <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                                    <div class="rounded-xl bg-slate-50 dark:bg-slate-800/50 p-2.5">
                                        <span class="text-[10px] text-slate-400 uppercase font-medium">Brand</span>
                                        <p class="font-semibold text-slate-700 dark:text-slate-200">{item.brand?.name ?? '-'}</p>
                                    </div>
                                    <div class="rounded-xl bg-slate-50 dark:bg-slate-800/50 p-2.5">
                                        <span class="text-[10px] text-slate-400 uppercase font-medium">Kategori</span>
                                        <p class="font-semibold text-slate-700 dark:text-slate-200">{item.category?.name ?? '-'}</p>
                                    </div>
                                </div>

                                <!-- Variants List Snippet -->
                                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-xs font-semibold text-slate-500">Daftar Varian ({item.variants.length})</span>
                                        <button
                                            onclick={() => openDetailModal(item)}
                                            class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1"
                                        >
                                            <Eye class="h-3.5 w-3.5" />
                                            Detail Full
                                        </button>
                                    </div>
                                    <div class="flex flex-wrap gap-1.5 max-h-24 overflow-y-auto">
                                        {#each item.variants as v}
                                            <span class="rounded-lg bg-slate-100 dark:bg-slate-800 px-2 py-1 text-[11px] font-mono text-slate-700 dark:text-slate-300">
                                                {v.sku} ({v.size}/{v.color})
                                            </span>
                                        {/each}
                                    </div>
                                </div>
                            </div>

                            <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                                <span class="text-xs text-slate-400">Vendor: {item.konveksi?.name ?? '-'}</span>
                                <Link
                                    href={`/superadmin/warehouse/products/${item.id}/edit`}
                                    class="text-xs font-semibold text-indigo-600 hover:underline"
                                >
                                    Edit Produk
                                </Link>
                            </div>
                        </div>
                    {/each}
                </div>

                <Pagination links={products.links} />
            {:else}
                <div class="rounded-2xl bg-white dark:bg-slate-900 p-12 text-center text-slate-400 border border-slate-100 dark:border-slate-800">
                    Seller ini belum memiliki produk terdaftar.
                </div>
            {/if}
        </div>
    {/if}

    <!-- Detail Modal -->
    <Modal show={isDetailModalOpen} title="Detail Produk & Varian" maxWidth="4xl" onclose={() => isDetailModalOpen = false}>
        {#if detailProduct}
            <div class="space-y-6">
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

                <div>
                    <h4 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-3 flex items-center gap-2">
                        <Tag class="h-4 w-4 text-indigo-600" />
                        Daftar Seluruh Varian ({detailProduct.variants.length})
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
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </div>

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
</AdminLayout>
