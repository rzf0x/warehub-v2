<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import Modal from '@/components/Modal.svelte';
    import Badge from '@/components/Badge.svelte';
    import { router, Link } from '@inertiajs/svelte';
    import {
        Activity,
        History,
        ClipboardCheck,
        Printer,
        Search,
        Store,
        CheckSquare,
        Square,
        Download,
        FileText,
        ArrowLeft,
        ChevronDown,
        ChevronUp,
        ChevronLeft,
        ChevronRight,
        X,
    } from '@lucide/svelte';

    interface SellerOption {
        id: number;
        seller_name: string;
        phone?: string | null;
        brand_name?: string | null;
        products_count: number;
    }

    interface WarehouseOption {
        id: number;
        name: string;
    }

    interface VariantItem {
        id: number;
        sku: string;
        size: string;
        color: string;
        qty: number;
    }

    interface ProductItem {
        id: number;
        product_name: string;
        sku: string;
        product_type: string;
        brand_name: string;
        category_name: string;
        total_qty: number;
        variants_count: number;
        variants: VariantItem[];
    }

    let {
        sellers = [],
        warehouses = [],
        selectedSeller = null,
        selectedWarehouse = null,
        products = [],
        filters = {
            seller_id: '',
            warehouse_id: '',
            search: '',
        },
    }: {
        sellers?: SellerOption[];
        warehouses?: WarehouseOption[];
        selectedSeller?: SellerOption | null;
        selectedWarehouse?: WarehouseOption | null;
        products?: ProductItem[];
        filters?: {
            seller_id?: string;
            warehouse_id?: string;
            search?: string;
        };
    } = $props();

    let sellerId = $state('');
    let warehouseId = $state('');
    let search = $state('');
    let sellerSearch = $state('');

    // Selected variant IDs map for granular or product-level selection
    let selectedVariantIds = $state<Record<number, boolean>>({});
    let expandedProducts = $state<Record<number, boolean>>({});
    let showPreviewModal = $state(false);
    let onlyWithStock = $state(false);

    // Client-side pagination to keep DOM ultra-light even when seller has hundreds of products
    let currentPage = $state(1);
    const pageSize = 20;

    $effect(() => {
        sellerId = String(filters.seller_id ?? '');
        warehouseId = String(filters.warehouse_id ?? '');
        search = String(filters.search ?? '');
    });

    // Reset selection when seller/warehouse changes (products payload from backend changes)
    $effect(() => {
        // Touch products.length so it resets when backend reloads a new seller/warehouse
        const _len = products.length;
        expandedProducts = {};
        selectedVariantIds = {};
        currentPage = 1;
    });

    const filteredSellers = $derived(
        sellers.filter(
            (s) =>
                s.seller_name.toLowerCase().includes(sellerSearch.toLowerCase()) ||
                (s.brand_name && s.brand_name.toLowerCase().includes(sellerSearch.toLowerCase()))
        )
    );

    // Instant client-side filtering by search keyword + stock toggle (0 server round-trip when typing search)
    const displayedProducts = $derived.by(() => {
        const keyword = search.trim().toLowerCase();

        return products.filter((p) => {
            if (onlyWithStock && p.total_qty <= 0) {
                return false;
            }
            if (!keyword) {
                return true;
            }
            if (
                p.product_name.toLowerCase().includes(keyword) ||
                p.sku.toLowerCase().includes(keyword) ||
                p.category_name.toLowerCase().includes(keyword)
            ) {
                return true;
            }
            return p.variants.some((v) => v.sku.toLowerCase().includes(keyword));
        });
    });

    const totalPages = $derived(Math.max(1, Math.ceil(displayedProducts.length / pageSize)));

    const paginatedProducts = $derived.by(() => {
        const safePage = Math.min(Math.max(1, currentPage), totalPages);
        const start = (safePage - 1) * pageSize;
        return displayedProducts.slice(start, start + pageSize);
    });

    const selectedProductsSummary = $derived.by(() => {
        const chosenProducts: Array<{
            id: number;
            product_name: string;
            sku: string;
            category_name: string;
            variants: VariantItem[];
            total_qty: number;
        }> = [];

        let totalVariants = 0;
        let grandTotalQty = 0;

        for (const p of products) {
            const chosenVariants = p.variants.filter((v) => selectedVariantIds[v.id]);
            if (chosenVariants.length > 0) {
                const subQty = chosenVariants.reduce((acc, v) => acc + v.qty, 0);
                chosenProducts.push({
                    id: p.id,
                    product_name: p.product_name,
                    sku: p.sku,
                    category_name: p.category_name,
                    variants: chosenVariants,
                    total_qty: subQty,
                });
                totalVariants += chosenVariants.length;
                grandTotalQty += subQty;
            }
        }

        return {
            products: chosenProducts,
            total_products: chosenProducts.length,
            total_variants: totalVariants,
            grand_total_qty: grandTotalQty,
        };
    });

    const allDisplayedSelected = $derived.by(() => {
        if (displayedProducts.length === 0) return false;
        let totalVars = 0;
        for (const p of displayedProducts) {
            for (const v of p.variants) {
                totalVars++;
                if (!selectedVariantIds[v.id]) return false;
            }
        }
        return totalVars > 0;
    });

    // Only reload from server when Seller or Warehouse changes
    function reloadSellerOrWarehouse(nextSellerId?: string) {
        const targetSeller = nextSellerId !== undefined ? nextSellerId : sellerId;
        router.get(
            '/superadmin/warehouse/stocks/print-sku',
            {
                seller_id: targetSeller,
                warehouse_id: warehouseId,
            },
            {
                only: ['selectedSeller', 'selectedWarehouse', 'products', 'filters'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    }

    function chooseSellerCard(id: number) {
        sellerId = String(id);
        search = '';
        reloadSellerOrWarehouse(String(id));
    }

    function isProductFullySelected(product: ProductItem): boolean {
        if (product.variants.length === 0) return false;
        return product.variants.every((v) => !!selectedVariantIds[v.id]);
    }

    function isProductPartiallySelected(product: ProductItem): boolean {
        const count = product.variants.filter((v) => !!selectedVariantIds[v.id]).length;
        return count > 0 && count < product.variants.length;
    }

    function toggleProductSelection(product: ProductItem) {
        const fullySelected = isProductFullySelected(product);
        const next = { ...selectedVariantIds };
        for (const v of product.variants) {
            if (fullySelected) {
                delete next[v.id];
            } else {
                next[v.id] = true;
            }
        }
        selectedVariantIds = next;
    }

    function toggleVariantSelection(variantId: number) {
        const next = { ...selectedVariantIds };
        if (next[variantId]) {
            delete next[variantId];
        } else {
            next[variantId] = true;
        }
        selectedVariantIds = next;
    }

    function toggleSelectAllDisplayed() {
        const shouldSelect = !allDisplayedSelected;
        const next = { ...selectedVariantIds };
        for (const p of displayedProducts) {
            for (const v of p.variants) {
                if (shouldSelect) {
                    next[v.id] = true;
                } else {
                    delete next[v.id];
                }
            }
        }
        selectedVariantIds = next;
    }

    function toggleExpandProduct(productId: number) {
        expandedProducts = {
            ...expandedProducts,
            [productId]: !expandedProducts[productId],
        };
    }

    function buildPdfUrl(download: boolean): string {
        const params = new URLSearchParams();
        if (sellerId) params.set('seller_id', sellerId);
        if (warehouseId) params.set('warehouse_id', warehouseId);
        if (download) params.set('download', '1');

        const chosenVarIds = Object.keys(selectedVariantIds)
            .filter((k) => selectedVariantIds[Number(k)])
            .map(Number);

        // Compact comma-separated IDs instead of repeating variant_ids[]=... hundreds of times
        if (chosenVarIds.length > 0) {
            params.set('variant_ids', chosenVarIds.join(','));
        }

        return `/superadmin/warehouse/stocks/print-sku/pdf?${params.toString()}`;
    }

    function openPrintPreview() {
        if (selectedProductsSummary.total_variants === 0) return;
        showPreviewModal = true;
    }
</script>

<AdminLayout
    title="Print SKU Stock Mitra"
    breadcrumbs={[
        { name: 'Mutasi Stok' },
        { name: 'Stok Fisik Realtime', url: '/superadmin/warehouse/stocks' },
        { name: 'Print SKU Stock' },
    ]}
>
    <!-- Header -->
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3.5">
            <Link
                href="/superadmin/warehouse/stocks"
                class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-2.5 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors shadow-xs"
                title="Kembali ke Stok Fisik"
            >
                <ArrowLeft class="h-5 w-5" />
            </Link>
            <div>
                <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                    <Printer class="h-7 w-7 text-emerald-500" />
                    Print SKU Stock Per-Seller
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Pilih seller mitra, centang produk/SKU yang ingin dicetak, lihat rekap SKU &amp; Size beserta total stoknya, lalu download sebagai PDF
                </p>
            </div>
        </div>

        {#if selectedSeller && selectedProductsSummary.total_variants > 0}
            <div class="flex items-center gap-2.5">
                <button
                    type="button"
                    onclick={openPrintPreview}
                    class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-emerald-600/25 hover:bg-emerald-700 transition-colors cursor-pointer"
                >
                    <Printer class="h-4 w-4" />
                    Print SKU Terpilih ({selectedProductsSummary.total_products} Produk / {selectedProductsSummary.total_variants} SKU)
                </button>
            </div>
        {/if}
    </div>

    <!-- Tabs Navigation -->
    <div class="mb-6 flex flex-wrap items-center gap-3 border-b border-slate-200 dark:border-slate-800 pb-3">
        <Link
            href="/superadmin/warehouse/stocks"
            class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition-colors text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"
        >
            <Activity class="h-4 w-4" />
            Grid Stok Fisik
        </Link>

        <Link
            href="/superadmin/warehouse/stocks/logs"
            class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition-colors text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"
        >
            <History class="h-4 w-4" />
            Histori Log Mutasi (Stock Logs)
        </Link>

        <Link
            href="/superadmin/warehouse/stocks/reconciliation"
            class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition-colors text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"
        >
            <ClipboardCheck class="h-4 w-4 text-indigo-500" />
            Rekonsiliasi & Opname Mitra
        </Link>

        <Link
            href="/superadmin/warehouse/stocks/print-sku"
            class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition-colors bg-emerald-600 text-white shadow-md shadow-emerald-600/20"
        >
            <Printer class="h-4 w-4" />
            Print SKU Stock
        </Link>
    </div>

    <!-- Filter Bar (Seller, Warehouse, Instant Search) -->
    <div class="mb-6 rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <label for="print_seller_id" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">
                    1. Pilih Seller / Mitra
                </label>
                <select
                    id="print_seller_id"
                    bind:value={sellerId}
                    onchange={() => reloadSellerOrWarehouse()}
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-sm font-medium text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-emerald-500"
                >
                    <option value="">-- Pilih Seller --</option>
                    {#each sellers as s}
                        <option value={String(s.id)}>{s.seller_name} ({s.products_count} Produk)</option>
                    {/each}
                </select>
            </div>

            <div>
                <label for="print_warehouse_id" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">
                    2. Filter Lokasi Gudang
                </label>
                <select
                    id="print_warehouse_id"
                    bind:value={warehouseId}
                    onchange={() => reloadSellerOrWarehouse()}
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-sm font-medium text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-emerald-500"
                >
                    <option value="">Semua Gudang (Akumulasi Stok)</option>
                    {#each warehouses as w}
                        <option value={String(w.id)}>{w.name}</option>
                    {/each}
                </select>
            </div>

            <div>
                <label for="print_search" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">
                    3. Filter Instan Nama Produk / Kode SKU
                </label>
                <div class="relative">
                    <Search class="absolute left-3 top-3 h-4 w-4 text-slate-400" />
                    <input
                        id="print_search"
                        type="text"
                        bind:value={search}
                        oninput={() => (currentPage = 1)}
                        placeholder="Ketik SKU (mis. ATG) atau nama produk..."
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 pl-9 pr-8 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    />
                    {#if search}
                        <button
                            type="button"
                            onclick={() => {
                                search = '';
                                currentPage = 1;
                            }}
                            class="absolute right-2.5 top-2.5 rounded-lg p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                            aria-label="Reset pencarian"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    {/if}
                </div>
            </div>
        </div>
    </div>

    {#if !selectedSeller}
        <!-- STEP 1: Quick Seller Picker Grid when no seller is selected yet -->
        <div class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <h2 class="text-base font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    <Store class="h-5 w-5 text-emerald-500" />
                    Pilih Mitra / Seller untuk Menampilkan Daftar Produk
                </h2>
                <div class="relative w-full sm:w-72">
                    <Search class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                    <input
                        type="text"
                        bind:value={sellerSearch}
                        placeholder="Filter nama seller..."
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 pl-9 pr-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                {#each filteredSellers as s (s.id)}
                    <button
                        type="button"
                        onclick={() => chooseSellerCard(s.id)}
                        class="text-left rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800 hover:border-emerald-500 dark:hover:border-emerald-500 hover:shadow-md transition-all group flex items-center justify-between cursor-pointer"
                    >
                        <div class="flex items-center gap-3.5">
                            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400 group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                                <Store class="h-5 w-5" />
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">
                                    {s.seller_name}
                                </h3>
                                <p class="text-xs text-slate-400 mt-0.5">
                                    {s.brand_name ? `${s.brand_name} • ` : ''}{s.products_count} Produk
                                </p>
                            </div>
                        </div>
                        <span class="rounded-xl bg-slate-100 dark:bg-slate-800 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300 group-hover:bg-emerald-50 group-hover:text-emerald-700 dark:group-hover:bg-emerald-950 dark:group-hover:text-emerald-300 transition-colors">
                            Pilih
                        </span>
                    </button>
                {:else}
                    <div class="col-span-full rounded-2xl bg-white dark:bg-slate-900 p-10 text-center text-slate-400 border border-slate-100 dark:border-slate-800">
                        Seller tidak ditemukan.
                    </div>
                {/each}
            </div>
        </div>
    {:else}
        <!-- STEP 2: Product & SKU Selection List -->
        <div class="space-y-4">
            <!-- Action Toolbar -->
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex flex-wrap items-center gap-3">
                    <button
                        type="button"
                        onclick={toggleSelectAllDisplayed}
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3.5 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors cursor-pointer"
                    >
                        {#if allDisplayedSelected}
                            <CheckSquare class="h-4 w-4 text-emerald-600" />
                            Batal Pilih Semua Hasil Filter ({displayedProducts.length})
                        {:else}
                            <Square class="h-4 w-4 text-slate-400" />
                            Pilih Semua Hasil Filter ({displayedProducts.length} Produk)
                        {/if}
                    </button>

                    <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-300 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            bind:checked={onlyWithStock}
                            onchange={() => (currentPage = 1)}
                            class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                        />
                        Hanya tampilkan produk dengan stok &gt; 0
                    </label>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <div class="text-xs text-slate-500 dark:text-slate-400">
                        Terpilih:
                        <strong class="text-slate-800 dark:text-slate-100">{selectedProductsSummary.total_products} Produk</strong>
                        ({selectedProductsSummary.total_variants} SKU Varian •
                        <strong class="text-emerald-600 dark:text-emerald-400">{selectedProductsSummary.grand_total_qty.toLocaleString('id-ID')} Pcs</strong>)
                    </div>

                    <button
                        type="button"
                        disabled={selectedProductsSummary.total_variants === 0}
                        onclick={openPrintPreview}
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 disabled:bg-slate-300 dark:disabled:bg-slate-800 px-4 py-2 text-xs font-semibold text-white disabled:text-slate-500 shadow-sm hover:bg-emerald-700 transition-colors cursor-pointer disabled:cursor-not-allowed"
                    >
                        <Printer class="h-4 w-4" />
                        Print ({selectedProductsSummary.total_variants} SKU)
                    </button>
                </div>
            </div>

            <!-- Products Cards List (Paginated client-side for fast rendering) -->
            {#if paginatedProducts.length > 0}
                <div class="space-y-2.5">
                    {#each paginatedProducts as product (product.id)}
                        {@const fullySelected = isProductFullySelected(product)}
                        {@const partiallySelected = isProductPartiallySelected(product)}
                        {@const isExpanded = !!expandedProducts[product.id]}
                        <div
                            class="rounded-2xl bg-white dark:bg-slate-900 shadow-xs border transition-colors overflow-hidden
                            {fullySelected || partiallySelected
                                ? 'border-emerald-500/80 dark:border-emerald-500/70 ring-1 ring-emerald-500/20'
                                : 'border-slate-100 dark:border-slate-800'}"
                        >
                            <!-- Product Card Header -->
                            <div class="p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-slate-50/60 dark:bg-slate-800/40">
                                <div class="flex items-start sm:items-center gap-3">
                                    <button
                                        type="button"
                                        onclick={() => toggleProductSelection(product)}
                                        class="mt-0.5 sm:mt-0 flex h-6 w-6 shrink-0 items-center justify-center rounded-lg border transition-colors cursor-pointer
                                        {fullySelected
                                            ? 'bg-emerald-600 border-emerald-600 text-white'
                                            : partiallySelected
                                              ? 'bg-emerald-100 border-emerald-500 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                                              : 'bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-transparent hover:border-emerald-500'}"
                                        aria-label="Pilih produk {product.product_name}"
                                    >
                                        <CheckSquare class="h-4 w-4" />
                                    </button>

                                    <div
                                        role="button"
                                        tabindex="0"
                                        onclick={() => toggleProductSelection(product)}
                                        onkeydown={(e) => e.key === 'Enter' && toggleProductSelection(product)}
                                        class="cursor-pointer select-none"
                                    >
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">
                                                {product.product_name}
                                            </h3>
                                            <span class="rounded-md bg-indigo-50 dark:bg-indigo-950/80 px-2 py-0.5 font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400">
                                                {product.sku}
                                            </span>
                                            <Badge variant={product.product_type === 'bundle' ? 'warning' : 'primary'}>
                                                {product.product_type.toUpperCase()}
                                            </Badge>
                                        </div>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                            Kategori: {product.category_name} • Brand: {product.brand_name} • {product.variants_count} Varian Size/Warna
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between sm:justify-end gap-3 pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-100 dark:border-slate-800">
                                    <div class="text-right">
                                        <span class="block text-[10px] uppercase font-semibold tracking-wider text-slate-400">Total Stok Produk</span>
                                        <span class="text-sm font-extrabold {product.total_qty > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-500'}">
                                            {product.total_qty.toLocaleString('id-ID')} Pcs
                                        </span>
                                    </div>

                                    <button
                                        type="button"
                                        onclick={() => toggleExpandProduct(product.id)}
                                        class="inline-flex items-center gap-1 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                                    >
                                        {#if isExpanded}
                                            Tutup SKU
                                            <ChevronUp class="h-3.5 w-3.5" />
                                        {:else}
                                            Pilih per-SKU ({product.variants_count})
                                            <ChevronDown class="h-3.5 w-3.5" />
                                        {/if}
                                    </button>
                                </div>
                            </div>

                            <!-- Variants Table inside Product Card (Lazy rendered only when expanded) -->
                            {#if isExpanded}
                                <div class="overflow-x-auto border-t border-slate-100 dark:border-slate-800">
                                    <table class="w-full text-left text-xs text-slate-600 dark:text-slate-400">
                                        <thead class="bg-slate-50/50 dark:bg-slate-900/50 text-[11px] uppercase font-semibold text-slate-400 border-b border-slate-100 dark:border-slate-800">
                                            <tr>
                                                <th class="px-4 py-2.5 w-10 text-center">Pilih</th>
                                                <th class="px-4 py-2.5">Kode SKU Varian</th>
                                                <th class="px-4 py-2.5 text-center">Size / Ukuran</th>
                                                <th class="px-4 py-2.5 text-center">Warna</th>
                                                <th class="px-4 py-2.5 text-right">Total Qty Stok</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                            {#each product.variants as v (v.id)}
                                                {@const isChecked = !!selectedVariantIds[v.id]}
                                                <tr
                                                    onclick={() => toggleVariantSelection(v.id)}
                                                    class="cursor-pointer transition-colors {isChecked
                                                        ? 'bg-emerald-50/50 dark:bg-emerald-950/25 hover:bg-emerald-50 dark:hover:bg-emerald-950/40'
                                                        : 'hover:bg-slate-50/60 dark:hover:bg-slate-800/30'}"
                                                >
                                                    <td class="px-4 py-2.5 text-center">
                                                        <input
                                                            type="checkbox"
                                                            checked={isChecked}
                                                            onclick={(e) => e.stopPropagation()}
                                                            onchange={() => toggleVariantSelection(v.id)}
                                                            class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                                                        />
                                                    </td>
                                                    <td class="px-4 py-2.5 font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                                        {v.sku}
                                                    </td>
                                                    <td class="px-4 py-2.5 text-center font-semibold text-slate-800 dark:text-slate-100">
                                                        {v.size}
                                                    </td>
                                                    <td class="px-4 py-2.5 text-center">
                                                        {v.color}
                                                    </td>
                                                    <td class="px-4 py-2.5 text-right font-extrabold {v.qty <= 0 ? 'text-rose-500' : v.qty <= 5 ? 'text-amber-600' : 'text-emerald-600 dark:text-emerald-400'}">
                                                        {v.qty.toLocaleString('id-ID')} pcs
                                                    </td>
                                                </tr>
                                            {/each}
                                        </tbody>
                                    </table>
                                </div>
                            {/if}
                        </div>
                    {/each}
                </div>

                <!-- Client-Side Pagination Controls -->
                {#if totalPages > 1}
                    <div class="rounded-2xl bg-white dark:bg-slate-900 px-5 py-3.5 shadow-xs border border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3">
                        <span class="text-xs text-slate-500 dark:text-slate-400">
                            Menampilkan <strong>{(currentPage - 1) * pageSize + 1}</strong> –
                            <strong>{Math.min(currentPage * pageSize, displayedProducts.length)}</strong>
                            dari <strong>{displayedProducts.length}</strong> produk
                        </span>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                disabled={currentPage <= 1}
                                onclick={() => (currentPage = Math.max(1, currentPage - 1))}
                                class="inline-flex items-center gap-1 rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 disabled:opacity-40 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors cursor-pointer disabled:cursor-not-allowed"
                            >
                                <ChevronLeft class="h-3.5 w-3.5" />
                                Prev
                            </button>

                            <span class="text-xs font-bold text-slate-700 dark:text-slate-200 px-2">
                                Hal {currentPage} / {totalPages}
                            </span>

                            <button
                                type="button"
                                disabled={currentPage >= totalPages}
                                onclick={() => (currentPage = Math.min(totalPages, currentPage + 1))}
                                class="inline-flex items-center gap-1 rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 disabled:opacity-40 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors cursor-pointer disabled:cursor-not-allowed"
                            >
                                Next
                                <ChevronRight class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>
                {/if}
            {:else}
                <div class="rounded-2xl bg-white dark:bg-slate-900 p-12 text-center text-slate-400 border border-slate-100 dark:border-slate-800">
                    Tidak ada produk yang cocok dengan filter "{search}".
                </div>
            {/if}
        </div>
    {/if}

    <!-- MODAL PREVIEW & DOWNLOAD PDF -->
    <Modal
        show={showPreviewModal}
        title="Rekap Print SKU Stock (List SKU, Size & Total Qty)"
        maxWidth="4xl"
        onclose={() => (showPreviewModal = false)}
    >
        <div class="space-y-5">
            <!-- Header Info in Modal -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 rounded-2xl bg-slate-50 dark:bg-slate-800/60 p-4 border border-slate-100 dark:border-slate-800">
                <div>
                    <span class="text-[11px] font-semibold uppercase text-slate-400">Seller / Mitra</span>
                    <p class="text-sm font-bold text-slate-800 dark:text-slate-100 mt-0.5">
                        {selectedSeller?.seller_name ?? '-'}
                    </p>
                </div>
                <div>
                    <span class="text-[11px] font-semibold uppercase text-slate-400">Lokasi Gudang</span>
                    <p class="text-sm font-bold text-slate-800 dark:text-slate-100 mt-0.5">
                        {selectedWarehouse?.name ?? 'Semua Gudang (Akumulasi)'}
                    </p>
                </div>
                <div>
                    <span class="text-[11px] font-semibold uppercase text-slate-400">Total Keseluruhan</span>
                    <p class="text-sm font-extrabold text-emerald-600 dark:text-emerald-400 mt-0.5">
                        {selectedProductsSummary.total_products} Produk • {selectedProductsSummary.total_variants} SKU • {selectedProductsSummary.grand_total_qty.toLocaleString('id-ID')} Pcs
                    </p>
                </div>
            </div>

            <!-- Scrollable SKU & Size List Table -->
            <div class="max-h-[55vh] overflow-y-auto space-y-4 pr-1">
                {#each selectedProductsSummary.products as prod, pIdx (prod.id)}
                    <div class="rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                        <div class="bg-slate-100 dark:bg-slate-800 px-4 py-2.5 flex items-center justify-between">
                            <div>
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-100">
                                    {pIdx + 1}. {prod.product_name}
                                </span>
                                <span class="ml-2 font-mono text-xs text-indigo-600 dark:text-indigo-400">
                                    ({prod.sku})
                                </span>
                            </div>
                            <span class="text-xs font-extrabold text-emerald-600 dark:text-emerald-400">
                                Subtotal: {prod.total_qty.toLocaleString('id-ID')} Pcs
                            </span>
                        </div>

                        <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                            <thead class="bg-slate-50 dark:bg-slate-900/60 text-[10px] uppercase font-semibold text-slate-400 border-b border-slate-100 dark:border-slate-800">
                                <tr>
                                    <th class="px-4 py-2 w-10">No</th>
                                    <th class="px-4 py-2">Kode SKU</th>
                                    <th class="px-4 py-2 text-center">Size</th>
                                    <th class="px-4 py-2 text-center">Warna</th>
                                    <th class="px-4 py-2 text-right">Total Qty</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                {#each prod.variants as v, vIdx (v.id)}
                                    <tr>
                                        <td class="px-4 py-2 text-slate-400">{vIdx + 1}</td>
                                        <td class="px-4 py-2 font-mono font-bold text-indigo-600 dark:text-indigo-400">{v.sku}</td>
                                        <td class="px-4 py-2 text-center font-bold text-slate-800 dark:text-slate-100">{v.size}</td>
                                        <td class="px-4 py-2 text-center">{v.color}</td>
                                        <td class="px-4 py-2 text-right font-bold {v.qty <= 0 ? 'text-rose-500' : 'text-emerald-600 dark:text-emerald-400'}">
                                            {v.qty.toLocaleString('id-ID')} Pcs
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                {/each}
            </div>

            <!-- Grand Total Footer Banner -->
            <div class="rounded-xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 px-4 py-3 flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-800 dark:text-emerald-200">
                    Grand Total ({selectedProductsSummary.total_variants} SKU Terpilih)
                </span>
                <span class="text-base font-extrabold text-emerald-700 dark:text-emerald-300">
                    {selectedProductsSummary.grand_total_qty.toLocaleString('id-ID')} Pcs
                </span>
            </div>

            <!-- Modal Actions: Preview PDF in New Tab or Direct Download PDF -->
            <div class="flex flex-wrap items-center justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                <button
                    type="button"
                    onclick={() => (showPreviewModal = false)}
                    class="rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-2.5 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                >
                    Tutup
                </button>

                <a
                    href={buildPdfUrl(false)}
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 rounded-xl border border-indigo-200 dark:border-indigo-800 bg-indigo-50 dark:bg-indigo-950/60 px-4 py-2.5 text-xs font-semibold text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 transition-colors"
                >
                    <FileText class="h-4 w-4" />
                    Buka Preview PDF
                </a>

                <a
                    href={buildPdfUrl(true)}
                    class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-semibold text-white shadow-md shadow-emerald-600/25 hover:bg-emerald-700 transition-colors"
                >
                    <Download class="h-4 w-4" />
                    Download as PDF
                </a>
            </div>
        </div>
    </Modal>
</AdminLayout>
