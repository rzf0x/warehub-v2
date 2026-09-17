<script lang="ts">
    import SellerMobileLayout from '@/layouts/SellerMobileLayout.svelte';
    import BottomSheet from '@/components/seller/BottomSheet.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import { router } from '@inertiajs/svelte';
    import {
        Package,
        Search,
        Layers,
        ChevronDown,
        ChevronUp,
        Boxes,
        Tag,
        DollarSign,
        Coins,
        SlidersHorizontal,
        Sparkles
    } from '@lucide/svelte';

    interface VariantWarehouseStock {
        warehouse_name: string;
        qty: number;
    }

    interface ProductVariant {
        id: number;
        sku: string;
        size: string;
        color: string;
        price: number;
        selling_price: number;
        stock: number;
        stocks_per_warehouse: VariantWarehouseStock[];
    }

    interface ProductItem {
        id: number;
        product_name: string;
        sku: string;
        product_type: string;
        image: string | null;
        category_name: string;
        brand_name: string;
        total_stock: number;
        variant_count: number;
        hpp_range: { min: number; max: number };
        selling_range: { min: number; max: number };
        variants: ProductVariant[];
    }

    interface PaginatedProducts {
        data: ProductItem[];
        links: any[];
        meta?: any;
    }

    let {
        products = { data: [], links: [] },
        categories = [],
        brands = [],
        filters = { search: '', category_id: '', brand_id: '', product_type: '' },
    }: {
        products?: PaginatedProducts;
        categories?: { id: number; name: string }[];
        brands?: { id: number; name: string }[];
        filters?: { search?: string; category_id?: string; brand_id?: string; product_type?: string };
    } = $props();

    let search = $state(filters.search ?? '');
    let categoryId = $state(filters.category_id ?? '');
    let selectedProductForVariants = $state<ProductItem | null>(null);
    let showVariantsSheet = $state(false);
    let expandedProductIds = $state<number[]>([]);

    function formatRupiah(num: number): string {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num || 0);
    }

    function applyFilter() {
        router.get(
            '/seller/products',
            { search, category_id: categoryId },
            { preserveState: true, replace: true }
        );
    }

    function toggleAccordion(pId: number) {
        if (expandedProductIds.includes(pId)) {
            expandedProductIds = expandedProductIds.filter((id) => id !== pId);
        } else {
            expandedProductIds = [...expandedProductIds, pId];
        }
    }

    function openVariantsSheet(p: ProductItem) {
        selectedProductForVariants = p;
        showVariantsSheet = true;
    }
</script>

<SellerMobileLayout title="Katalog Produk Mobile">
    <div class="space-y-4">
        <!-- Header Bar -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-black text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                    <Package class="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                    Katalog Produk
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Daftar produk, SKU Parent, & rincian varian</p>
            </div>
        </div>

        <!-- Sticky Search Input -->
        <div class="relative">
            <Search class="absolute left-3 top-3 h-4 w-4 text-slate-400" />
            <input
                type="text"
                placeholder="Cari produk / SKU Parent..."
                bind:value={search}
                oninput={applyFilter}
                class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 pl-9 pr-4 py-2.5 text-xs text-slate-800 dark:text-slate-100 shadow-xs focus:ring-2 focus:ring-indigo-500"
            />
        </div>

        <!-- Horizontal Filter Chip Carousel -->
        <div class="flex items-center gap-2 overflow-x-auto pb-1 no-scrollbar">
            <button
                type="button"
                onclick={() => { categoryId = ''; applyFilter(); }}
                class="shrink-0 rounded-xl px-3.5 py-1.5 text-xs font-bold transition-all
                {categoryId === ''
                    ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30'
                    : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800'}"
            >
                Semua Kategori
            </button>

            {#each categories as cat}
                <button
                    type="button"
                    onclick={() => { categoryId = cat.id.toString(); applyFilter(); }}
                    class="shrink-0 rounded-xl px-3.5 py-1.5 text-xs font-bold transition-all
                    {categoryId === cat.id.toString()
                        ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30'
                        : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800'}"
                >
                    {cat.name}
                </button>
            {/each}
        </div>

        <!-- Mobile Product Cards List -->
        <div class="space-y-3">
            {#each products.data as item}
                <div class="rounded-3xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 space-y-3">
                    <!-- Top Card info -->
                    <div class="flex items-start gap-3">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold overflow-hidden border border-indigo-100 dark:border-indigo-900/40">
                            {#if item.image}
                                <img src={item.image} alt={item.product_name} class="h-full w-full object-cover" />
                            {:else}
                                <Package class="h-6 w-6" />
                            {/if}
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="rounded-md bg-slate-100 dark:bg-slate-800 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:text-slate-300">
                                    {item.category_name}
                                </span>
                                <span class="rounded-md bg-indigo-50 dark:bg-indigo-950/60 px-2 py-0.5 text-[10px] font-bold text-indigo-600 dark:text-indigo-400">
                                    {item.product_type}
                                </span>
                            </div>

                            <h3 class="text-sm font-extrabold text-slate-800 dark:text-slate-100 leading-tight mt-1 truncate">
                                {item.product_name}
                            </h3>
                            <p class="text-[11px] font-semibold text-slate-400 mt-0.5">
                                Parent SKU: <span class="font-mono text-slate-600 dark:text-slate-300">{item.sku}</span>
                            </p>
                        </div>
                    </div>

                    <!-- Price & Stock Metrics -->
                    <div class="rounded-2xl bg-slate-50 dark:bg-slate-950/60 p-3 grid grid-cols-2 gap-2 border border-slate-100 dark:border-slate-800/80">
                        <div>
                            <span class="flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                <Coins class="h-3 w-3 text-amber-500" />
                                HPP Modal
                            </span>
                            <span class="text-xs font-black text-amber-600 dark:text-amber-400 mt-0.5 block truncate">
                                {formatRupiah(item.hpp_range.min)}
                            </span>
                        </div>

                        <div>
                            <span class="flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                <DollarSign class="h-3 w-3 text-emerald-500" />
                                Harga Jual
                            </span>
                            <span class="text-xs font-black text-emerald-600 dark:text-emerald-400 mt-0.5 block truncate">
                                {formatRupiah(item.selling_range.min)}
                            </span>
                        </div>
                    </div>

                    <!-- Bottom Bar: Total Stock + Accordion Trigger -->
                    <div class="flex items-center justify-between pt-1">
                        <div class="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">
                            <Boxes class="h-4 w-4 text-sky-500" />
                            <span>Total Stok: <strong class="text-indigo-600 dark:text-indigo-400">{item.total_stock} Pcs</strong></span>
                            <span class="text-[10px] text-slate-400 font-normal">({item.variant_count} Varian)</span>
                        </div>

                        <button
                            type="button"
                            onclick={() => openVariantsSheet(item)}
                            class="inline-flex items-center gap-1 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 px-3 py-1.5 text-xs font-bold text-indigo-600 dark:text-indigo-400 transition-colors"
                        >
                            <span>Lihat Varian</span>
                            <ChevronDown class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>
            {:else}
                <div class="rounded-3xl bg-white dark:bg-slate-900 p-8 text-center text-slate-400 border border-slate-100 dark:border-slate-800 text-xs space-y-2">
                    <Package class="h-10 w-10 mx-auto text-slate-300 dark:text-slate-700" />
                    <p class="font-bold">Tidak Ada Produk</p>
                    <p class="text-[11px] text-slate-400">Belum ada produk yang terdaftar untuk akun seller ini.</p>
                </div>
            {/each}
        </div>

        <!-- Pagination -->
        {#if products.links && products.links.length > 3}
            <div class="pt-2 flex justify-center">
                <Pagination links={products.links} />
            </div>
        {/if}
    </div>

    <!-- Bottom Sheet Drawer for Variant Breakdown -->
    <BottomSheet
        show={showVariantsSheet}
        title={selectedProductForVariants?.product_name ?? 'Detail Varian Produk'}
        onclose={() => (showVariantsSheet = false)}
    >
        {#if selectedProductForVariants}
            <div class="space-y-4">
                <div class="rounded-2xl bg-indigo-50 dark:bg-indigo-950/50 p-3 border border-indigo-100 dark:border-indigo-900/60 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-400">SKU Parent</span>
                        <p class="text-xs font-mono font-bold text-indigo-900 dark:text-indigo-200">{selectedProductForVariants.sku}</p>
                    </div>

                    <div class="text-right">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-400">Total Stok</span>
                        <p class="text-sm font-extrabold text-indigo-700 dark:text-indigo-300">{selectedProductForVariants.total_stock} Pcs</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">Daftar Varian ({selectedProductForVariants.variants.length})</h4>

                    {#each selectedProductForVariants.variants as v}
                        <div class="rounded-2xl bg-slate-50 dark:bg-slate-950 p-3.5 border border-slate-200/80 dark:border-slate-800 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-mono text-xs font-bold text-slate-800 dark:text-slate-100">{v.sku}</span>
                                <span class="rounded-md bg-emerald-100 dark:bg-emerald-950/80 px-2 py-0.5 text-[10px] font-extrabold text-emerald-700 dark:text-emerald-300">
                                    Stok: {v.stock} Pcs
                                </span>
                            </div>

                            <div class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300">
                                {#if v.color}
                                    <span class="rounded bg-slate-200 dark:bg-slate-800 px-2 py-0.5 text-[10px] font-semibold">{v.color}</span>
                                {/if}
                                {#if v.size}
                                    <span class="rounded bg-slate-200 dark:bg-slate-800 px-2 py-0.5 text-[10px] font-semibold">Ukuran: {v.size}</span>
                                {/if}
                            </div>

                            <div class="pt-1 flex items-center justify-between text-[11px] border-t border-slate-200/60 dark:border-slate-800/60">
                                <span class="text-amber-600 dark:text-amber-400 font-semibold">Modal: {formatRupiah(v.price)}</span>
                                <span class="text-emerald-600 dark:text-emerald-400 font-bold">Jual: {formatRupiah(v.selling_price)}</span>
                            </div>

                            {#if v.stocks_per_warehouse && v.stocks_per_warehouse.length > 0}
                                <div class="pt-1 flex flex-wrap gap-1">
                                    {#each v.stocks_per_warehouse as st}
                                        <span class="text-[10px] bg-slate-200/70 dark:bg-slate-800/70 text-slate-600 dark:text-slate-400 px-2 py-0.5 rounded-md">
                                            {st.warehouse_name}: <strong>{st.qty}</strong>
                                        </span>
                                    {/each}
                                </div>
                            {/if}
                        </div>
                    {/each}
                </div>
            </div>
        {/if}
    </BottomSheet>
</SellerMobileLayout>
