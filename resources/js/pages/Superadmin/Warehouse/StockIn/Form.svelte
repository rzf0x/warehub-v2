<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import { router, Link } from '@inertiajs/svelte';
    import { ArrowDownLeft, Plus, Trash2, ArrowLeft, Save, UserCheck, Package, Layers, Loader2, Calculator, Search, X } from '@lucide/svelte';

    interface OptionItem {
        id: number;
        name?: string;
        seller_name?: string;
    }

    interface VariantOption {
        id: number;
        product_id: number;
        sku: string;
        size: string;
        color: string;
        price: number;
    }

    interface ProductItem {
        id: number;
        seller_id: number;
        product_name: string;
        sku: string;
        variants: VariantOption[];
    }

    interface VariantRowInput {
        product_variant_id: number;
        sku: string;
        size: string;
        color: string;
        qty: number;
        price: number;
        enabled: boolean;
    }

    interface ProductMasterRow {
        seller_id: number | string;
        product_id: number | string;
        konveksi_id: number | string;
        variants: VariantRowInput[];
    }

    let {
        warehouses = [],
        periods = [],
        sellers = [],
        konveksis = [],
    }: {
        warehouses?: OptionItem[];
        periods?: OptionItem[];
        sellers?: OptionItem[];
        konveksis?: OptionItem[];
    } = $props();

    let globalSellerId = $state<number | string>(sellers[0]?.id ?? '');

    // Reactive store for products loaded per row index
    let productsByRow = $state<Record<number, ProductItem[]>>({});
    let loadingSellerRow = $state<Record<number, boolean>>({});
    let searchByRow = $state<Record<number, string>>({});
    let showDropdownRow = $state<Record<number, boolean>>({});

    let headerForm = $state({
        warehouse_id: warehouses[0]?.id ?? '',
        period_id: periods[0]?.id ?? '',
        invoice_number: `SJ-IN-${Date.now().toString().slice(-6)}`,
        date: new Date().toISOString().split('T')[0],
        note: '',
    });

    let productRows = $state<ProductMasterRow[]>([
        {
            seller_id: sellers[0]?.id ?? '',
            product_id: '',
            konveksi_id: konveksis[0]?.id ?? '',
            variants: [],
        },
    ]);

    // Auto load products for row 0 if initial seller exists
    if (productRows[0]?.seller_id) {
        fetchSellerProducts(0, Number(productRows[0].seller_id));
    }

    async function fetchSellerProducts(idx: number, sellerId: number) {
        if (!sellerId) {
            productsByRow[idx] = [];
            if (productRows[idx]) productRows[idx].variants = [];
            return;
        }

        loadingSellerRow[idx] = true;
        try {
            const res = await fetch(`/superadmin/warehouse/stock-in/seller-products?seller_id=${sellerId}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (res.ok) {
                const data: ProductItem[] = await res.json();
                productsByRow[idx] = data;

                if (data.length > 0) {
                    selectProduct(idx, data[0]);
                } else {
                    productRows[idx].product_id = '';
                    productRows[idx].variants = [];
                    searchByRow[idx] = '';
                }
            }
        } catch (err) {
            console.error('Failed to load seller products', err);
        } finally {
            loadingSellerRow[idx] = false;
        }
    }

    function selectProduct(idx: number, product: ProductItem) {
        productRows[idx].product_id = product.id;
        searchByRow[idx] = `${product.product_name} (${product.sku || 'Master SKU'})`;
        showDropdownRow[idx] = false;

        if (product.variants && product.variants.length > 0) {
            productRows[idx].variants = product.variants.map((v, vIdx) => ({
                product_variant_id: v.id,
                sku: v.sku,
                size: v.size,
                color: v.color,
                qty: vIdx === 0 ? 10 : 0,
                price: v.price || 0,
                enabled: true,
            }));
        } else {
            productRows[idx].variants = [];
        }
    }

    function clearProduct(idx: number) {
        productRows[idx].product_id = '';
        productRows[idx].variants = [];
        searchByRow[idx] = '';
        showDropdownRow[idx] = true;
    }

    function onSellerChange(idx: number, sellerId: number) {
        productRows[idx].seller_id = sellerId;
        productRows[idx].product_id = '';
        productRows[idx].variants = [];
        searchByRow[idx] = '';
        showDropdownRow[idx] = false;
        fetchSellerProducts(idx, Number(sellerId));
    }

    function onProductChange(idx: number, productId: number) {
        productRows[idx].product_id = productId;
        const products = productsByRow[idx] || [];
        const prod = products.find((p) => p.id === Number(productId));

        if (prod && prod.variants && prod.variants.length > 0) {
            productRows[idx].variants = prod.variants.map((v, vIdx) => ({
                product_variant_id: v.id,
                sku: v.sku,
                size: v.size,
                color: v.color,
                qty: vIdx === 0 ? 10 : 0,
                price: v.price || 0,
                enabled: true,
            }));
        } else {
            productRows[idx].variants = [];
        }
    }

    function applyGlobalSeller() {
        if (!globalSellerId) return;
        productRows.forEach((row, idx) => {
            row.seller_id = globalSellerId;
            fetchSellerProducts(idx, Number(globalSellerId));
        });
    }

    function addItem() {
        const newIdx = productRows.length;
        const initSeller = globalSellerId || sellers[0]?.id || '';
        productRows.push({
            seller_id: initSeller,
            product_id: '',
            konveksi_id: konveksis[0]?.id ?? '',
            variants: [],
        });
        if (initSeller) {
            fetchSellerProducts(newIdx, Number(initSeller));
        }
    }

    function removeItem(idx: number) {
        if (productRows.length > 1) {
            productRows.splice(idx, 1);
            delete productsByRow[idx];
            delete loadingSellerRow[idx];
        }
    }

    function formatRupiah(num: number): string {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num || 0);
    }

    function getRowQtySum(variants: VariantRowInput[]): number {
        return (variants || []).reduce((a, b) => a + (b.qty > 0 ? Number(b.qty) : 0), 0);
    }

    function getRowNominalSum(variants: VariantRowInput[]): number {
        return (variants || []).reduce((a, b) => a + (b.qty > 0 ? Number(b.qty) * Number(b.price) : 0), 0);
    }

    let grandTotalQty = $derived(
        productRows.reduce((acc, row) => {
            return acc + row.variants.reduce((vAcc, v) => vAcc + (v.qty > 0 ? Number(v.qty) : 0), 0);
        }, 0)
    );

    let grandTotalNominal = $derived(
        productRows.reduce((acc, row) => {
            return acc + row.variants.reduce((vAcc, v) => vAcc + (v.qty > 0 ? Number(v.qty) * Number(v.price) : 0), 0);
        }, 0)
    );

    let grandTotalActiveVariants = $derived(
        productRows.reduce((acc, row) => {
            return acc + row.variants.filter((v) => v.qty > 0).length;
        }, 0)
    );

    function handleSubmit() {
        const payloadItems: Array<{
            product_variant_id: number;
            konveksi_id: number | string | null;
            qty: number;
            price: number;
        }> = [];

        for (const row of productRows) {
            for (const v of row.variants) {
                if (v.qty > 0) {
                    payloadItems.push({
                        product_variant_id: v.product_variant_id,
                        konveksi_id: row.konveksi_id || null,
                        qty: Number(v.qty),
                        price: Number(v.price),
                    });
                }
            }
        }

        if (payloadItems.length === 0) {
            alert('Mohon isi Qty barang masuk (> 0) minimal untuk 1 varian produk.');
            return;
        }

        const payload = {
            warehouse_id: headerForm.warehouse_id,
            period_id: headerForm.period_id || null,
            invoice_number: headerForm.invoice_number,
            date: headerForm.date,
            note: headerForm.note || null,
            items: payloadItems,
        };

        router.post('/superadmin/warehouse/stock-in', payload);
    }
</script>

<AdminLayout
    title="Catat Penerimaan Barang Masuk"
    breadcrumbs={[{ name: 'Mutasi Stok' }, { name: 'Penerimaan (Stock In)', href: '/superadmin/warehouse/stock-in' }, { name: 'Catat Stock In' }]}
>
    <!-- Header -->
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <Link
                href="/superadmin/warehouse/stock-in"
                class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-2.5 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors shadow-xs"
            >
                <ArrowLeft class="h-5 w-5" />
            </Link>
            <div>
                <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                    <ArrowDownLeft class="h-7 w-7 text-sky-500" />
                    Input Surat Jalan Penerimaan Barang (Stock In)
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Pilih Produk Master untuk mengisi QTY beberapa varian (S, M, L, 2XL) sekaligus</p>
            </div>
        </div>

        <button
            type="button"
            onclick={handleSubmit}
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/30 hover:bg-indigo-700 transition-colors"
        >
            <Save class="h-4 w-4" />
            Simpan Stock In
        </button>
    </div>

    <form onsubmit={(e) => { e.preventDefault(); handleSubmit(); }} class="space-y-8">
        <!-- Header Metadata Card -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-100 dark:border-slate-800 space-y-6">
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 border-b border-slate-100 dark:border-slate-800 pb-3">Header Surat Jalan</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Invoice Number -->
                <div>
                    <label for="invoice_number" class="block text-xs font-semibold uppercase text-slate-500 mb-1.5">No Invoice / Surat Jalan</label>
                    <input
                        type="text"
                        bind:value={headerForm.invoice_number}
                        required
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm font-mono text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                    />
                </div>

                <!-- Warehouse -->
                <div>
                    <label for="warehouse_id" class="block text-xs font-semibold uppercase text-slate-500 mb-1.5">Gudang Penerima</label>
                    <select
                        bind:value={headerForm.warehouse_id}
                        required
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                    >
                        {#each warehouses as w}
                            <option value={w.id}>{w.name}</option>
                        {/each}
                    </select>
                </div>

                <!-- Period -->
                <div>
                    <label for="period_id" class="block text-xs font-semibold uppercase text-slate-500 mb-1.5">Periode Pembukuan</label>
                    <select
                        bind:value={headerForm.period_id}
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                    >
                        <option value="">Auto Periode Berjalan</option>
                        {#each periods as p}
                            <option value={p.id}>{p.name}</option>
                        {/each}
                    </select>
                </div>

                <!-- Date -->
                <div>
                    <label for="date" class="block text-xs font-semibold uppercase text-slate-500 mb-1.5">Tanggal Penerimaan</label>
                    <input
                        type="date"
                        bind:value={headerForm.date}
                        required
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
            </div>

            <div>
                <label for="note" class="block text-xs font-semibold uppercase text-slate-500 mb-1.5">Catatan (Opsional)</label>
                <input
                    type="text"
                    bind:value={headerForm.note}
                    placeholder="Contoh: Penerimaan PO barang masuk kloter ke-1..."
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                />
            </div>
        </div>

        <!-- Items Card -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-100 dark:border-slate-800 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-4">
                <div>
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        <Package class="h-5 w-5 text-indigo-500" />
                        Daftar Item Barang Masuk Per Seller
                    </h3>
                    <p class="text-xs text-slate-500">Langkah: 1) Pilih Seller &rarr; 2) Pilih Produk Master &rarr; 3) Isi QTY untuk Varian-Varian (S, M, L, 2XL) &rarr; 4) Cek Akumulasi Subtotal</p>
                </div>

                <!-- Global Seller Shortcut -->
                <div class="flex items-center gap-2 bg-slate-50 dark:bg-slate-950 p-2 rounded-xl border border-slate-200 dark:border-slate-800">
                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-400 whitespace-nowrap">Seller Utama:</span>
                    <select
                        bind:value={globalSellerId}
                        onchange={applyGlobalSeller}
                        class="rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 py-1 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                    >
                        {#each sellers as s}
                            <option value={s.id}>{s.seller_name || s.name}</option>
                        {/each}
                    </select>
                    <button
                        type="button"
                        onclick={addItem}
                        class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 text-white px-3 py-1.5 text-xs font-semibold hover:bg-indigo-700 transition-colors ml-2"
                    >
                        <Plus class="h-3.5 w-3.5" />
                        Tambah Produk
                    </button>
                </div>
            </div>

            <!-- Product Master Rows List -->
            <div class="space-y-6">
                {#each productRows as row, idx}
                    {@const products = productsByRow[idx] || []}
                    {@const selectedProd = products.find((p) => p.id === Number(row.product_id))}
                    {@const rowQuery = (searchByRow[idx] || '').trim().toLowerCase()}
                    {@const filteredProds = products.filter((p) => {
                        if (!rowQuery) return true;
                        const nameMatch = (p.product_name || '').toLowerCase().includes(rowQuery);
                        const masterSkuMatch = (p.sku || '').toLowerCase().includes(rowQuery);
                        const variantSkuMatch = (p.variants || []).some((v) => (v.sku || '').toLowerCase().includes(rowQuery));
                        return nameMatch || masterSkuMatch || variantSkuMatch;
                    })}

                    <div class="p-5 rounded-2xl bg-slate-50/80 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 space-y-5 shadow-xs">
                        <!-- Product Master Header -->
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center border-b border-slate-200/70 dark:border-slate-800/70 pb-4">
                            <!-- 1. Seller -->
                            <div class="md:col-span-4">
                                <label for="seller_{idx}" class="block text-[11px] font-bold uppercase text-purple-600 dark:text-purple-400 mb-1 flex items-center gap-1">
                                    <UserCheck class="h-3.5 w-3.5" />
                                    1. Pemilik / Seller
                                </label>
                                <select
                                    id="seller_{idx}"
                                    bind:value={row.seller_id}
                                    onchange={(e) => onSellerChange(idx, Number((e.target as HTMLSelectElement).value))}
                                    required
                                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-xs font-semibold text-purple-700 dark:text-purple-300 focus:ring-2 focus:ring-purple-500"
                                >
                                    <option value="">-- Pilih Seller --</option>
                                    {#each sellers as s}
                                        <option value={s.id}>{s.seller_name || s.name}</option>
                                    {/each}
                                </select>
                            </div>

                            <!-- 2. Product Master Autocomplete Combobox -->
                            <div class="md:col-span-4 relative space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <label for="product_search_{idx}" class="block text-[11px] font-bold uppercase text-sky-600 dark:text-sky-400 flex items-center gap-1">
                                        <Package class="h-3.5 w-3.5" />
                                        2. Cari SKU / Produk Master
                                    </label>
                                    {#if loadingSellerRow[idx]}
                                        <span class="text-[10px] text-sky-500 flex items-center gap-1">
                                            <Loader2 class="h-3 w-3 animate-spin" />
                                            Memuat...
                                        </span>
                                    {/if}
                                </div>

                                <div class="relative">
                                    <Search class="absolute left-3 top-2.5 h-4 w-4 text-slate-400 pointer-events-none" />
                                    <input
                                        id="product_search_{idx}"
                                        type="text"
                                        bind:value={searchByRow[idx]}
                                        onfocus={() => { showDropdownRow[idx] = true; }}
                                        oninput={() => { showDropdownRow[idx] = true; }}
                                        onblur={() => setTimeout(() => { showDropdownRow[idx] = false; }, 200)}
                                        placeholder="Ketik SKU (misal: KPO-2XL) atau Nama Produk..."
                                        disabled={!row.seller_id || loadingSellerRow[idx]}
                                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 pl-9 pr-8 py-2 text-xs font-semibold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-sky-500 disabled:opacity-50"
                                    />

                                    {#if searchByRow[idx] || row.product_id}
                                        <button
                                            type="button"
                                            onclick={() => clearProduct(idx)}
                                            class="absolute right-2.5 top-2.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                                            title="Hapus pilihan"
                                        >
                                            <X class="h-4 w-4" />
                                        </button>
                                    {/if}

                                    <!-- Floating Autocomplete Dropdown List -->
                                    {#if showDropdownRow[idx] && row.seller_id && !loadingSellerRow[idx]}
                                        <div
                                            class="absolute left-0 right-0 top-full mt-1.5 z-50 max-h-64 overflow-y-auto rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-2xl divide-y divide-slate-100 dark:divide-slate-800"
                                        >
                                            {#if products.length === 0}
                                                <div class="p-4 text-center text-xs text-slate-400">Belum ada produk milik seller ini.</div>
                                            {:else if filteredProds.length === 0}
                                                <div class="p-4 text-center text-xs text-slate-400">Tidak ada produk cocok dengan SKU/Nama "{searchByRow[idx]}".</div>
                                            {:else}
                                                {#each filteredProds as p}
                                                    <button
                                                        type="button"
                                                        onclick={() => selectProduct(idx, p)}
                                                        class="w-full text-left px-4 py-3 hover:bg-sky-50 dark:hover:bg-sky-950/60 transition-colors flex items-center justify-between gap-3 group {Number(row.product_id) === p.id ? 'bg-sky-50/80 dark:bg-sky-950/80' : ''}"
                                                    >
                                                        <div class="min-w-0 flex-1">
                                                            <div class="text-xs font-bold text-slate-800 dark:text-slate-100 group-hover:text-sky-600 dark:group-hover:text-sky-400 truncate">
                                                                {p.product_name}
                                                            </div>
                                                            <div class="text-[11px] font-mono text-slate-500 flex flex-wrap items-center gap-2 mt-1">
                                                                <span class="bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-md text-[10px] text-slate-700 dark:text-slate-300 font-semibold">
                                                                    SKU Master: {p.sku || '-'}
                                                                </span>
                                                                {#if p.variants && p.variants.length > 0}
                                                                    <span class="truncate text-[10px] text-indigo-600 dark:text-indigo-400 font-medium">
                                                                        Varian: {p.variants.map((v) => v.sku).slice(0, 4).join(', ')}{p.variants.length > 4 ? '...' : ''}
                                                                    </span>
                                                                {/if}
                                                            </div>
                                                        </div>

                                                        <span class="shrink-0 text-[10px] font-bold bg-indigo-50 dark:bg-indigo-950/80 text-indigo-600 dark:text-indigo-300 px-2.5 py-1 rounded-lg border border-indigo-100 dark:border-indigo-800">
                                                            {p.variants?.length || 0} Varian
                                                        </span>
                                                    </button>
                                                {/each}
                                            {/if}
                                        </div>
                                    {/if}
                                </div>
                            </div>

                            <!-- Vendor Konveksi -->
                            <div class="md:col-span-3">
                                <label for="konveksi_{idx}" class="block text-[11px] font-semibold text-slate-500 mb-1">Vendor Konveksi (Pengirim)</label>
                                <select
                                    id="konveksi_{idx}"
                                    bind:value={row.konveksi_id}
                                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                                >
                                    <option value="">Internal / Tanpa Vendor</option>
                                    {#each konveksis as k}
                                        <option value={k.id}>{k.name}</option>
                                    {/each}
                                </select>
                            </div>

                            <!-- Remove Button -->
                            <div class="md:col-span-1 text-right pt-2 md:pt-4">
                                <button
                                    type="button"
                                    onclick={() => removeItem(idx)}
                                    disabled={productRows.length <= 1}
                                    class="rounded-lg p-2 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/50 disabled:opacity-30 transition-colors"
                                    title="Hapus Produk Master Ini"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <!-- Variants Table for selected Product Master -->
                        {#if row.product_id}
                            {#if row.variants.length > 0}
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 flex items-center gap-1.5">
                                            <Layers class="h-4 w-4" />
                                            3. Daftar Varian Produk ({row.variants.length} Varian Tersedia)
                                        </h4>
                                        <span class="text-[11px] text-slate-400">Isi QTY barang yang masuk untuk setiap varian</span>
                                    </div>

                                    <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
                                        <table class="w-full text-left text-xs">
                                            <thead class="bg-slate-100/80 dark:bg-slate-800/80 text-slate-600 dark:text-slate-300 font-semibold border-b border-slate-200 dark:border-slate-800">
                                                <tr>
                                                    <th class="p-3 w-10 text-center">#</th>
                                                    <th class="p-3">SKU Varian</th>
                                                    <th class="p-3">Ukuran / Warna</th>
                                                    <th class="p-3 w-36">Qty Masuk (Pcs)</th>
                                                    <th class="p-3 w-44">HPP Modal (Rp)</th>
                                                    <th class="p-3 w-40 text-right">Subtotal Nominal</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                                {#each row.variants as v, vIdx}
                                                    {@const rowSubtotal = (v.qty > 0 ? Number(v.qty) : 0) * (v.price > 0 ? Number(v.price) : 0)}
                                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition-colors {v.qty > 0 ? 'bg-indigo-50/30 dark:bg-indigo-950/20' : ''}">
                                                        <td class="p-3 text-center text-slate-400">{vIdx + 1}</td>
                                                        <td class="p-3 font-mono font-bold text-indigo-600 dark:text-indigo-400">{v.sku}</td>
                                                        <td class="p-3 text-slate-700 dark:text-slate-200">
                                                            <span class="font-semibold">{v.size || '-'}</span>
                                                            {#if v.color}
                                                                <span class="text-slate-400 mx-1">/</span>
                                                                <span class="text-slate-500">{v.color}</span>
                                                            {/if}
                                                        </td>
                                                        <td class="p-3">
                                                            <input
                                                                type="number"
                                                                bind:value={v.qty}
                                                                min="0"
                                                                placeholder="0"
                                                                class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 px-2.5 py-1.5 text-xs font-bold text-emerald-600 dark:text-emerald-400 focus:ring-2 focus:ring-indigo-500"
                                                            />
                                                        </td>
                                                        <td class="p-3">
                                                            <input
                                                                type="number"
                                                                bind:value={v.price}
                                                                min="0"
                                                                step="500"
                                                                placeholder="0"
                                                                class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 px-2.5 py-1.5 text-xs font-semibold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                                                            />
                                                        </td>
                                                        <td class="p-3 text-right font-bold text-slate-800 dark:text-slate-100">
                                                            {formatRupiah(rowSubtotal)}
                                                        </td>
                                                    </tr>
                                                {/each}
                                            </tbody>
                                            <tfoot class="bg-slate-50 dark:bg-slate-950/60 font-bold border-t border-slate-200 dark:border-slate-800">
                                                <tr>
                                                    <td colspan="3" class="p-3 text-right text-slate-500 uppercase text-[10px] tracking-wider font-bold">Subtotal Produk Ini:</td>
                                                    <td class="p-3 text-emerald-600 dark:text-emerald-400 font-extrabold text-sm">{getRowQtySum(row.variants)} Pcs</td>
                                                    <td colspan="2" class="p-3 text-right text-indigo-600 dark:text-indigo-400 font-extrabold text-sm">{formatRupiah(getRowNominalSum(row.variants))}</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            {:else}
                                <div class="p-4 text-center text-xs text-slate-400 italic bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800">
                                    Tidak ada varian terdaftar pada produk ini.
                                </div>
                            {/if}
                        {/if}
                    </div>
                {:else}
                    <div class="py-8 text-center text-slate-400 border border-dashed border-slate-200 dark:border-slate-800 rounded-2xl">
                        Belum ada produk master. Klik "+ Tambah Produk" untuk menambah.
                    </div>
                {/each}
            </div>

            <!-- Accumulated Grand Summary Banner Card -->
            <div class="rounded-2xl bg-gradient-to-br from-indigo-900 via-slate-900 to-purple-950 p-6 text-white shadow-lg space-y-4">
                <div class="flex items-center justify-between border-b border-white/10 pb-3">
                    <div class="flex items-center gap-2">
                        <Calculator class="h-5 w-5 text-indigo-400" />
                        <h4 class="text-sm font-bold uppercase tracking-wider text-indigo-200">Akumulasi Total Penerimaan Barang (Stock In)</h4>
                    </div>
                    <span class="text-xs bg-indigo-500/20 border border-indigo-400/30 px-3 py-1 rounded-full text-indigo-200 font-semibold">
                        Real-time Summary
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="p-3.5 rounded-xl bg-white/5 border border-white/10">
                        <span class="block text-[11px] uppercase tracking-wider text-slate-400 font-semibold">Varian Aktif (Qty &gt; 0)</span>
                        <span class="text-2xl font-black text-purple-300 mt-1 block">
                            {grandTotalActiveVariants} <span class="text-xs font-normal text-slate-400">Varian</span>
                        </span>
                    </div>

                    <div class="p-3.5 rounded-xl bg-white/5 border border-white/10">
                        <span class="block text-[11px] uppercase tracking-wider text-slate-400 font-semibold">Total QTY Barang Masuk</span>
                        <span class="text-2xl font-black text-emerald-400 mt-1 block">
                            {grandTotalQty} <span class="text-xs font-normal text-slate-400">Pcs</span>
                        </span>
                    </div>

                    <div class="p-3.5 rounded-xl bg-white/5 border border-white/10">
                        <span class="block text-[11px] uppercase tracking-wider text-slate-400 font-semibold">Total Akumulasi Nominal Stock In</span>
                        <span class="text-2xl font-black text-sky-300 mt-1 block">
                            {formatRupiah(grandTotalNominal)}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Bottom Actions: Add Item & Submit Button -->
            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4">
                <button
                    type="button"
                    onclick={addItem}
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl border border-dashed border-indigo-300 dark:border-indigo-800 bg-indigo-50/50 dark:bg-indigo-950/30 px-5 py-2.5 text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 transition-colors shadow-xs"
                >
                    <Plus class="h-4 w-4" />
                    + Tambah Produk Master Lain
                </button>

                <button
                    type="submit"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/30 hover:bg-indigo-700 transition-colors"
                >
                    <Save class="h-4 w-4" />
                    Simpan Stock In ({grandTotalQty} Pcs)
                </button>
            </div>
        </div>
    </form>
</AdminLayout>
