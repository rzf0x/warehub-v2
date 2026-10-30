<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import { router, Link } from '@inertiajs/svelte';
    import {
        ArrowDownLeft,
        Plus,
        Trash2,
        ArrowLeft,
        Save,
        UserCheck,
        Package,
        Layers,
        Loader2,
        Calculator,
        Search,
        X,
        CheckCircle2,
        Building2,
        Users,
        Lock,
    } from '@lucide/svelte';

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
        konveksi_id: number | string;
        selected: boolean;
    }

    interface ProductMasterRow {
        id: string;
        product_id: number | string;
        bulk_konveksi_id: number | string;
        selectedProduct: ProductItem | null;
        searchQuery: string;
        showDropdown: boolean;
        variants: VariantRowInput[];
    }

    interface SellerGroup {
        id: string;
        seller_id: number | string;
        sellerProducts: ProductItem[];
        loadingSeller: boolean;
        productRows: ProductMasterRow[];
    }

    interface ExistingStockInItem {
        id: number;
        product_variant_id: number;
        product_id: number | null;
        seller_id: number | null;
        konveksi_id: number | null;
        qty: number;
        price: number;
    }

    interface ExistingStockIn {
        id: number;
        warehouse_id: number;
        period_id: number | null;
        invoice_number: string;
        date: string;
        note: string | null;
        items: ExistingStockInItem[];
    }

    let {
        warehouses = [],
        periods = [],
        sellers = [],
        konveksis = [],
        defaultInvoiceNumber = '',
        stockIn = null,
        initialSellerProducts = {},
    }: {
        warehouses?: OptionItem[];
        periods?: OptionItem[];
        sellers?: OptionItem[];
        konveksis?: OptionItem[];
        defaultInvoiceNumber?: string;
        stockIn?: ExistingStockIn | null;
        initialSellerProducts?: Record<number, ProductItem[]>;
    } = $props();

    const isEdit = $derived(!!stockIn);

    function generateFallbackInvoice(): string {
        const now = new Date();
        const yyyy = now.getFullYear();
        const mm = String(now.getMonth() + 1).padStart(2, '0');
        const dd = String(now.getDate()).padStart(2, '0');
        const rand = Math.floor(1000 + Math.random() * 9000);
        return `WHB/IN/${yyyy}${mm}${dd}/${rand}`;
    }

    let headerForm = $state({
        warehouse_id: stockIn?.warehouse_id ?? warehouses[0]?.id ?? '',
        period_id: stockIn?.period_id ?? periods[0]?.id ?? '',
        invoice_number: stockIn?.invoice_number || defaultInvoiceNumber || generateFallbackInvoice(),
        date: stockIn?.date ?? new Date().toISOString().split('T')[0],
        note: stockIn?.note ?? '',
    });

    function createEmptyProductRow(): ProductMasterRow {
        return {
            id: Math.random().toString(36).substring(2, 9),
            product_id: '',
            bulk_konveksi_id: konveksis[0]?.id ?? '',
            selectedProduct: null,
            searchQuery: '',
            showDropdown: false,
            variants: [],
        };
    }

    function createEmptySellerGroup(): SellerGroup {
        return {
            id: Math.random().toString(36).substring(2, 9),
            seller_id: '',
            sellerProducts: [],
            loadingSeller: false,
            productRows: [createEmptyProductRow()],
        };
    }

    function buildInitialSellerGroups(): SellerGroup[] {
        if (!stockIn || !stockIn.items || stockIn.items.length === 0) {
            return [createEmptySellerGroup()];
        }

        // Group items by seller_id -> product_id
        const sellerMap = new Map<number, Map<number, ExistingStockInItem[]>>();

        for (const item of stockIn.items) {
            const sId = Number(item.seller_id || 0);
            const pId = Number(item.product_id || 0);
            if (!sId || !pId) continue;

            if (!sellerMap.has(sId)) {
                sellerMap.set(sId, new Map());
            }
            const prodMap = sellerMap.get(sId)!;
            if (!prodMap.has(pId)) {
                prodMap.set(pId, []);
            }
            prodMap.get(pId)!.push(item);
        }

        if (sellerMap.size === 0) {
            return [createEmptySellerGroup()];
        }

        const groups: SellerGroup[] = [];

        for (const [sId, prodMap] of sellerMap.entries()) {
            const catalog = initialSellerProducts[sId] || [];
            const productRows: ProductMasterRow[] = [];

            for (const [pId, existingItems] of prodMap.entries()) {
                const prod = catalog.find((p) => Number(p.id) === Number(pId)) || null;
                const firstKonveksi = existingItems[0]?.konveksi_id ?? konveksis[0]?.id ?? '';

                const variants: VariantRowInput[] = (prod?.variants || []).map((v) => {
                    const matched = existingItems.find((ei) => Number(ei.product_variant_id) === Number(v.id));
                    return {
                        product_variant_id: v.id,
                        sku: v.sku,
                        size: v.size,
                        color: v.color,
                        qty: matched ? Number(matched.qty) : 0,
                        price: matched ? Number(matched.price) : Number(v.price) || 0,
                        konveksi_id: matched ? (matched.konveksi_id ?? '') : firstKonveksi,
                        selected: !!matched,
                    };
                });

                productRows.push({
                    id: Math.random().toString(36).substring(2, 9),
                    product_id: pId,
                    bulk_konveksi_id: firstKonveksi,
                    selectedProduct: prod,
                    searchQuery: prod ? `${prod.product_name} (${prod.sku || 'Master SKU'})` : '',
                    showDropdown: false,
                    variants,
                });
            }

            groups.push({
                id: Math.random().toString(36).substring(2, 9),
                seller_id: sId,
                sellerProducts: catalog,
                loadingSeller: false,
                productRows: productRows.length > 0 ? productRows : [createEmptyProductRow()],
            });
        }

        return groups;
    }

    let sellerGroups = $state<SellerGroup[]>(buildInitialSellerGroups());

    async function fetchSellerProducts(group: SellerGroup, sellerId: number) {
        if (!sellerId) {
            group.sellerProducts = [];
            group.productRows = [createEmptyProductRow()];
            return;
        }

        group.loadingSeller = true;
        try {
            const res = await fetch(`/superadmin/warehouse/stock-in/seller-products?seller_id=${sellerId}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (res.ok) {
                const data: ProductItem[] = await res.json();
                group.sellerProducts = data;
            }
        } catch (err) {
            console.error('Failed to load seller products', err);
        } finally {
            group.loadingSeller = false;
        }
    }

    function onSellerChange(group: SellerGroup, sellerIdValue: string) {
        group.seller_id = sellerIdValue ? Number(sellerIdValue) : '';
        group.productRows = [createEmptyProductRow()];

        if (group.seller_id) {
            fetchSellerProducts(group, Number(group.seller_id));
        } else {
            group.sellerProducts = [];
        }
    }

    function addSellerGroup() {
        sellerGroups.push(createEmptySellerGroup());
    }

    function removeSellerGroup(gIdx: number) {
        if (sellerGroups.length > 1) {
            sellerGroups.splice(gIdx, 1);
        }
    }

    function addProductRow(group: SellerGroup) {
        group.productRows.push(createEmptyProductRow());
    }

    function removeProductRow(group: SellerGroup, pIdx: number) {
        if (group.productRows.length > 1) {
            group.productRows.splice(pIdx, 1);
        }
    }

    function selectProduct(row: ProductMasterRow, product: ProductItem) {
        row.product_id = product.id;
        row.selectedProduct = product;
        row.searchQuery = `${product.product_name} (${product.sku || 'Master SKU'})`;
        row.showDropdown = false;

        if (product.variants && product.variants.length > 0) {
            row.variants = product.variants.map((v) => ({
                product_variant_id: v.id,
                sku: v.sku,
                size: v.size,
                color: v.color,
                qty: 0,
                price: Number(v.price) || 0,
                konveksi_id: row.bulk_konveksi_id ?? konveksis[0]?.id ?? '',
                selected: false,
            }));
        } else {
            row.variants = [];
        }
    }

    function applyBulkKonveksi(row: ProductMasterRow, konveksiIdValue: string) {
        row.bulk_konveksi_id = konveksiIdValue ? Number(konveksiIdValue) : '';
        for (const v of row.variants) {
            v.konveksi_id = row.bulk_konveksi_id;
        }
    }

    function clearProduct(row: ProductMasterRow) {
        row.product_id = '';
        row.selectedProduct = null;
        row.variants = [];
        row.searchQuery = '';
        row.showDropdown = true;
    }

    function toggleVariantSku(variant: VariantRowInput) {
        variant.selected = !variant.selected;
        if (variant.selected && (!variant.qty || variant.qty <= 0)) {
            variant.qty = 1;
        } else if (!variant.selected) {
            variant.qty = 0;
        }
    }

    function selectAllVariants(row: ProductMasterRow, selectAll: boolean) {
        for (const v of row.variants) {
            v.selected = selectAll;
            if (selectAll && (!v.qty || v.qty <= 0)) {
                v.qty = 1;
            } else if (!selectAll) {
                v.qty = 0;
            }
        }
    }

    function getSellerName(sellerId: number | string): string {
        if (!sellerId) return '';
        const found = sellers.find((s) => Number(s.id) === Number(sellerId));
        return found?.seller_name || found?.name || '';
    }

    function formatRupiah(num: number): string {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num || 0);
    }

    function getRowQtySum(variants: VariantRowInput[]): number {
        return (variants || []).reduce((a, b) => a + (b.selected && b.qty > 0 ? Number(b.qty) : 0), 0);
    }

    function getRowNominalSum(variants: VariantRowInput[]): number {
        return (variants || []).reduce((a, b) => a + (b.selected && b.qty > 0 ? Number(b.qty) * Number(b.price) : 0), 0);
    }

    function getGroupQtySum(group: SellerGroup): number {
        return group.productRows.reduce((acc, r) => acc + getRowQtySum(r.variants), 0);
    }

    function getGroupNominalSum(group: SellerGroup): number {
        return group.productRows.reduce((acc, r) => acc + getRowNominalSum(r.variants), 0);
    }

    function getFilteredProducts(group: SellerGroup, row: ProductMasterRow): ProductItem[] {
        const q = (row.searchQuery || '').trim().toLowerCase();
        if (!q) return group.sellerProducts;
        return group.sellerProducts.filter((p) => {
            const nameMatch = (p.product_name || '').toLowerCase().includes(q);
            const masterSkuMatch = (p.sku || '').toLowerCase().includes(q);
            const variantSkuMatch = (p.variants || []).some((v) => (v.sku || '').toLowerCase().includes(q));
            return nameMatch || masterSkuMatch || variantSkuMatch;
        });
    }

    let grandTotalQty = $derived(
        sellerGroups.reduce((gAcc, group) => {
            return (
                gAcc +
                group.productRows.reduce((rAcc, row) => {
                    return rAcc + row.variants.reduce((vAcc, v) => vAcc + (v.selected && v.qty > 0 ? Number(v.qty) : 0), 0);
                }, 0)
            );
        }, 0)
    );

    let grandTotalNominal = $derived(
        sellerGroups.reduce((gAcc, group) => {
            return (
                gAcc +
                group.productRows.reduce((rAcc, row) => {
                    return rAcc + row.variants.reduce((vAcc, v) => vAcc + (v.selected && v.qty > 0 ? Number(v.qty) * Number(v.price) : 0), 0);
                }, 0)
            );
        }, 0)
    );

    let grandTotalActiveVariants = $derived(
        sellerGroups.reduce((gAcc, group) => {
            return (
                gAcc +
                group.productRows.reduce((rAcc, row) => {
                    return rAcc + row.variants.filter((v) => v.selected && v.qty > 0).length;
                }, 0)
            );
        }, 0)
    );

    function handleSubmit() {
        const payloadItems: Array<{
            product_variant_id: number;
            konveksi_id: number | string | null;
            qty: number;
            price: number;
        }> = [];

        for (const group of sellerGroups) {
            for (const row of group.productRows) {
                for (const v of row.variants) {
                    if (v.selected && v.qty > 0) {
                        payloadItems.push({
                            product_variant_id: v.product_variant_id,
                            konveksi_id: v.konveksi_id || null,
                            qty: Number(v.qty),
                            price: Number(v.price),
                        });
                    }
                }
            }
        }

        if (payloadItems.length === 0) {
            alert('Mohon pilih minimal 1 SKU varian dan isi Qty barang masuk (> 0).');
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

        if (isEdit && stockIn) {
            router.put(`/superadmin/warehouse/stock-in/${stockIn.id}`, payload);
        } else {
            router.post('/superadmin/warehouse/stock-in', payload);
        }
    }
</script>

<AdminLayout
    title={isEdit ? `Edit Penerimaan Barang #${headerForm.invoice_number}` : 'Catat Penerimaan Barang Masuk'}
    breadcrumbs={[
        { name: 'Mutasi Stok' },
        { name: 'Penerimaan (Stock In)', url: '/superadmin/warehouse/stock-in' },
        { name: isEdit ? 'Edit Stock In' : 'Catat Stock In' },
    ]}
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
                    {isEdit ? 'Edit Surat Jalan Penerimaan Barang (Stock In)' : 'Input Surat Jalan Penerimaan Barang (Stock In)'}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    1 Invoice bisa memuat beberapa Seller, dan setiap Seller bisa memuat beberapa Produk &amp; SKU Varian
                </p>
            </div>
        </div>

        <button
            type="button"
            onclick={handleSubmit}
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/30 hover:bg-indigo-700 transition-colors"
        >
            <Save class="h-4 w-4" />
            {isEdit ? 'Simpan Perubahan' : 'Simpan Stock In'}
        </button>
    </div>

    <form onsubmit={(e) => { e.preventDefault(); handleSubmit(); }} class="space-y-8">
        <!-- Header Metadata Card -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-100 dark:border-slate-800 space-y-6">
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 border-b border-slate-100 dark:border-slate-800 pb-3">Header Surat Jalan</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Invoice Number (Readonly System Generated) -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="invoice_number" class="block text-xs font-semibold uppercase text-slate-500">No Invoice / Surat Jalan</label>
                        <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-slate-400">
                            <Lock class="h-3 w-3" />
                            System Auto
                        </span>
                    </div>
                    <input
                        id="invoice_number"
                        type="text"
                        value={headerForm.invoice_number}
                        readonly
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-100 dark:bg-slate-950/80 px-4 py-2.5 text-sm font-mono font-bold text-sky-600 dark:text-sky-400 cursor-not-allowed select-all focus:outline-none"
                    />
                </div>

                <!-- Warehouse -->
                <div>
                    <label for="warehouse_id" class="block text-xs font-semibold uppercase text-slate-500 mb-1.5">Gudang Penerima</label>
                    <select
                        id="warehouse_id"
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
                        id="period_id"
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
                        id="date"
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
                    id="note"
                    type="text"
                    bind:value={headerForm.note}
                    placeholder="Contoh: Penerimaan PO barang masuk kloter ke-1..."
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                />
            </div>
        </div>

        <!-- Items Card (Multi-Seller Groups -> Multi-Products per Seller) -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-100 dark:border-slate-800 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-4">
                <div>
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        <Package class="h-5 w-5 text-indigo-500" />
                        Daftar Item Barang Masuk Per Seller
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Langkah: 1) Pilih Seller &rarr; 2) Cari &amp; Pilih Produk (bisa tambah beberapa produk di seller yang sama) &rarr; 3) Klik SKU untuk isi Qty, Konveksi &amp; Harga/Pcs &rarr; 4) Tambah Seller Lain jika dalam 1 Invoice ada seller berbeda
                    </p>
                </div>

                <button
                    type="button"
                    onclick={addSellerGroup}
                    class="inline-flex items-center gap-1.5 rounded-xl bg-purple-600 text-white px-4 py-2.5 text-xs font-semibold hover:bg-purple-700 transition-colors shadow-xs shrink-0"
                >
                    <Users class="h-4 w-4" />
                    + Tambah Seller Lain
                </button>
            </div>

            <!-- Daftar Blok Seller -->
            <div class="space-y-8">
                {#each sellerGroups as group, gIdx (group.id)}
                    {@const sellerName = getSellerName(group.seller_id)}
                    {@const groupQty = getGroupQtySum(group)}
                    {@const groupNominal = getGroupNominalSum(group)}

                    <div class="rounded-2xl border-2 border-purple-200/80 dark:border-purple-900/70 bg-purple-50/20 dark:bg-purple-950/10 overflow-hidden shadow-xs">
                        <!-- Header Blok Seller (Pilih Seller sekali untuk seluruh produk di grup ini) -->
                        <div class="bg-purple-50/90 dark:bg-purple-950/40 border-b border-purple-200/70 dark:border-purple-900/60 p-5">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div class="flex-1 max-w-xl">
                                    <label for="seller_select_{group.id}" class="block text-xs font-bold uppercase text-purple-700 dark:text-purple-300 mb-2 flex items-center gap-1.5">
                                        <UserCheck class="h-4 w-4 text-purple-600 dark:text-purple-400" />
                                        Seller #{gIdx + 1} — Pilih Pemilik / Seller
                                    </label>
                                    <select
                                        id="seller_select_{group.id}"
                                        value={group.seller_id}
                                        onchange={(e) => onSellerChange(group, (e.target as HTMLSelectElement).value)}
                                        required
                                        class="w-full rounded-xl border border-purple-200 dark:border-purple-800 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm font-semibold text-purple-800 dark:text-purple-200 focus:ring-2 focus:ring-purple-500 shadow-2xs"
                                    >
                                        <option value="">-- Pilih Seller Terlebih Dahulu --</option>
                                        {#each sellers as s}
                                            <option value={s.id}>{s.seller_name || s.name}</option>
                                        {/each}
                                    </select>
                                </div>

                                <div class="flex flex-wrap items-center gap-3">
                                    {#if group.seller_id}
                                        {#if group.loadingSeller}
                                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-purple-600 dark:text-purple-400">
                                                <Loader2 class="h-4 w-4 animate-spin" />
                                                Memuat katalog produk {sellerName}...
                                            </span>
                                        {:else}
                                            <div class="rounded-xl bg-white dark:bg-slate-900 border border-purple-200 dark:border-purple-800 px-3.5 py-2 text-xs flex items-center gap-2">
                                                <span class="font-bold text-purple-700 dark:text-purple-300">{sellerName}</span>
                                                <span class="text-slate-300 dark:text-slate-700">|</span>
                                                <span class="font-semibold text-indigo-600 dark:text-indigo-400">{group.sellerProducts.length} Produk Katalog</span>
                                                {#if groupQty > 0}
                                                    <span class="text-slate-300 dark:text-slate-700">|</span>
                                                    <span class="font-bold text-emerald-600 dark:text-emerald-400">+{groupQty.toLocaleString('id-ID')} Pcs ({formatRupiah(groupNominal)})</span>
                                                {/if}
                                            </div>
                                        {/if}
                                    {/if}

                                    {#if sellerGroups.length > 1}
                                        <button
                                            type="button"
                                            onclick={() => removeSellerGroup(gIdx)}
                                            class="inline-flex items-center gap-1.5 rounded-xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800 px-3 py-2 text-xs font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-100 dark:hover:bg-rose-900/60 transition-colors"
                                            title="Hapus Blok Seller Ini"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                            Hapus Seller
                                        </button>
                                    {/if}
                                </div>
                            </div>
                        </div>

                        <!-- Isi Blok Seller: Daftar Produk Milik Seller Ini -->
                        <div class="p-5 space-y-5">
                            {#if !group.seller_id}
                                <div class="rounded-xl border border-dashed border-slate-200 dark:border-slate-800 bg-white/60 dark:bg-slate-900/40 p-8 text-center space-y-1.5">
                                    <UserCheck class="h-7 w-7 text-slate-400 mx-auto" />
                                    <p class="text-xs font-semibold text-slate-600 dark:text-slate-300">Seller #{gIdx + 1} Belum Dipilih</p>
                                    <p class="text-[11px] text-slate-400">Pilih <strong>Pemilik / Seller</strong> pada dropdown di atas untuk menampilkan pencarian produk milik seller tersebut.</p>
                                </div>
                            {:else}
                                <div class="space-y-5">
                                    {#each group.productRows as row, pIdx (row.id)}
                                        {@const filteredProds = getFilteredProducts(group, row)}
                                        {@const selectedVariants = row.variants.filter((v) => v.selected)}

                                        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-5 shadow-2xs">
                                            <!-- Header Baris Produk: Pencarian Produk Milik Seller -->
                                            <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-4">
                                                <div class="flex-1 relative">
                                                    <div class="flex items-center justify-between mb-1.5">
                                                        <label for="product_search_{row.id}" class="block text-xs font-bold uppercase text-sky-600 dark:text-sky-400 flex items-center gap-1.5">
                                                            <Package class="h-4 w-4" />
                                                            Produk #{pIdx + 1} (Milik {sellerName}) — Cari &amp; Pilih Produk Master
                                                        </label>
                                                        {#if group.loadingSeller}
                                                            <span class="text-[11px] text-sky-500 flex items-center gap-1 font-medium">
                                                                <Loader2 class="h-3.5 w-3.5 animate-spin" />
                                                                Memuat produk...
                                                            </span>
                                                        {/if}
                                                    </div>

                                                    <div class="relative">
                                                        <Search class="absolute left-3.5 top-3 h-4 w-4 text-slate-400 pointer-events-none" />
                                                        <input
                                                            id="product_search_{row.id}"
                                                            type="text"
                                                            bind:value={row.searchQuery}
                                                            onfocus={() => { row.showDropdown = true; }}
                                                            oninput={() => {
                                                                row.showDropdown = true;
                                                                if (row.product_id && row.selectedProduct && row.searchQuery !== `${row.selectedProduct.product_name} (${row.selectedProduct.sku || 'Master SKU'})`) {
                                                                    row.product_id = '';
                                                                    row.selectedProduct = null;
                                                                    row.variants = [];
                                                                }
                                                            }}
                                                            onblur={() => setTimeout(() => { row.showDropdown = false; }, 200)}
                                                            placeholder="Ketik nama produk atau SKU milik {sellerName}..."
                                                            disabled={group.loadingSeller}
                                                            class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 pl-10 pr-9 py-2.5 text-xs font-semibold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-sky-500 disabled:opacity-50"
                                                        />

                                                        {#if row.searchQuery || row.product_id}
                                                            <button
                                                                type="button"
                                                                onclick={() => clearProduct(row)}
                                                                class="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                                                                title="Ganti / Hapus pilihan produk"
                                                            >
                                                                <X class="h-4 w-4" />
                                                            </button>
                                                        {/if}

                                                        <!-- Dropdown hasil pencarian produk seller -->
                                                        {#if row.showDropdown && !group.loadingSeller}
                                                            <div
                                                                class="absolute left-0 right-0 top-full mt-1.5 z-50 max-h-64 overflow-y-auto rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-2xl divide-y divide-slate-100 dark:divide-slate-800"
                                                            >
                                                                {#if group.sellerProducts.length === 0}
                                                                    <div class="p-4 text-center text-xs text-slate-400">
                                                                        Belum ada produk terdaftar untuk seller {sellerName}.
                                                                    </div>
                                                                {:else if filteredProds.length === 0}
                                                                    <div class="p-4 text-center text-xs text-slate-400">
                                                                        Tidak ada produk milik {sellerName} yang cocok dengan "{row.searchQuery}".
                                                                    </div>
                                                                {:else}
                                                                    {#each filteredProds as p}
                                                                        <button
                                                                            type="button"
                                                                            onclick={() => selectProduct(row, p)}
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
                                                                                            SKU Varian: {p.variants.map((v) => v.sku).slice(0, 4).join(', ')}{p.variants.length > 4 ? '...' : ''}
                                                                                        </span>
                                                                                    {/if}
                                                                                </div>
                                                                            </div>

                                                                            <span class="shrink-0 text-[10px] font-bold bg-indigo-50 dark:bg-indigo-950/80 text-indigo-600 dark:text-indigo-300 px-2.5 py-1 rounded-lg border border-indigo-100 dark:border-indigo-800">
                                                                                {p.variants?.length || 0} SKU
                                                                            </span>
                                                                        </button>
                                                                    {/each}
                                                                {/if}
                                                            </div>
                                                        {/if}
                                                    </div>
                                                </div>

                                                <!-- Tombol Hapus Produk -->
                                                {#if group.productRows.length > 1}
                                                    <button
                                                        type="button"
                                                        onclick={() => removeProductRow(group, pIdx)}
                                                        class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 border border-transparent hover:border-rose-200 dark:hover:border-rose-900 transition-colors self-end md:mt-5"
                                                        title="Hapus Produk Ini"
                                                    >
                                                        <Trash2 class="h-4 w-4" />
                                                        Hapus Produk
                                                    </button>
                                                {/if}
                                            </div>

                                            <!-- STEP 3: List SKU Varian (MUNCUL SETELAH PRODUK DIPILIH) -->
                                            {#if row.product_id && row.selectedProduct}
                                                {#if row.variants.length > 0}
                                                    <div class="space-y-4">
                                                        <!-- Bagian Pilihan List SKU -->
                                                        <div class="space-y-2.5">
                                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                                <h4 class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 flex items-center gap-1.5">
                                                                    <Layers class="h-4 w-4" />
                                                                    Pilih / Tekan SKU Varian yang Masuk ({selectedVariants.length} dari {row.variants.length} SKU dipilih)
                                                                </h4>
                                                                <div class="flex items-center gap-2">
                                                                    <button
                                                                        type="button"
                                                                        onclick={() => selectAllVariants(row, true)}
                                                                        class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:underline"
                                                                    >
                                                                        Pilih Semua SKU
                                                                    </button>
                                                                    <span class="text-slate-300 dark:text-slate-700">|</span>
                                                                    <button
                                                                        type="button"
                                                                        onclick={() => selectAllVariants(row, false)}
                                                                        class="text-[11px] font-semibold text-slate-500 hover:text-rose-500 hover:underline"
                                                                    >
                                                                        Reset Pilihan
                                                                    </button>
                                                                </div>
                                                            </div>

                                                            <!-- Grid Tombol/Chip List SKU -->
                                                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2.5">
                                                                {#each row.variants as v}
                                                                    <button
                                                                        type="button"
                                                                        onclick={() => toggleVariantSku(v)}
                                                                        class="group relative flex flex-col items-start justify-between rounded-xl border p-3 text-left transition-all {v.selected
                                                                            ? 'border-indigo-500 bg-indigo-50/80 dark:border-indigo-500 dark:bg-indigo-950/60 shadow-xs ring-1 ring-indigo-500/40'
                                                                            : 'border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-950/60 hover:border-indigo-300 dark:hover:border-indigo-700'}"
                                                                    >
                                                                        <div class="flex items-center justify-between w-full gap-1 mb-1">
                                                                            <span class="font-mono text-xs font-bold {v.selected ? 'text-indigo-700 dark:text-indigo-300' : 'text-slate-800 dark:text-slate-200'} truncate">
                                                                                {v.sku}
                                                                            </span>
                                                                            {#if v.selected}
                                                                                <CheckCircle2 class="h-4 w-4 text-indigo-600 dark:text-indigo-400 shrink-0" />
                                                                            {/if}
                                                                        </div>

                                                                        <div class="text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-1">
                                                                            <span class="font-semibold text-slate-700 dark:text-slate-300">{v.size || '-'}</span>
                                                                            {#if v.color}
                                                                                <span>&bull;</span>
                                                                                <span class="truncate">{v.color}</span>
                                                                            {/if}
                                                                        </div>

                                                                        {#if v.selected && v.qty > 0}
                                                                            <div class="mt-2 pt-1.5 border-t border-indigo-200/60 dark:border-indigo-800/60 w-full flex items-center justify-between text-[10px] font-bold text-emerald-600 dark:text-emerald-400">
                                                                                <span>+{v.qty.toLocaleString('id-ID')} Pcs</span>
                                                                                <span>{formatRupiah(v.qty * v.price)}</span>
                                                                            </div>
                                                                        {/if}
                                                                    </button>
                                                                {/each}
                                                            </div>
                                                        </div>

                                                        <!-- Form Input untuk SKU yang Ditekan / Dipilih -->
                                                        {#if selectedVariants.length > 0}
                                                            <div class="space-y-3 pt-2">
                                                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-xl bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-200/70 dark:border-emerald-900/60 px-4 py-2.5">
                                                                    <div>
                                                                        <h5 class="text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                                                                            Form Input Barang Masuk per SKU Terpilih
                                                                        </h5>
                                                                        <span class="text-[11px] text-slate-500 dark:text-slate-400">
                                                                            Isi Qty masuk, Konveksi, dan Harga per 1 Pcs untuk masing-masing ukuran/SKU
                                                                        </span>
                                                                    </div>

                                                                    <!-- Bulk Konveksi Control -->
                                                                    <div class="flex items-center gap-2 bg-white dark:bg-slate-900 px-3 py-1.5 rounded-xl border border-emerald-200 dark:border-emerald-800 shadow-2xs">
                                                                        <Building2 class="h-4 w-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
                                                                        <label for="bulk_konveksi_{row.id}" class="text-[11px] font-bold text-slate-700 dark:text-slate-300 whitespace-nowrap">
                                                                            Set Semua Konveksi:
                                                                        </label>
                                                                        <select
                                                                            id="bulk_konveksi_{row.id}"
                                                                            value={row.bulk_konveksi_id}
                                                                            onchange={(e) => applyBulkKonveksi(row, (e.target as HTMLSelectElement).value)}
                                                                            class="rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-2.5 py-1 text-xs font-semibold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-emerald-500"
                                                                        >
                                                                            <option value="">Internal / Tanpa Konveksi</option>
                                                                            {#each konveksis as k}
                                                                                <option value={k.id}>{k.name}</option>
                                                                            {/each}
                                                                        </select>
                                                                    </div>
                                                                </div>

                                                                <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
                                                                    <table class="w-full text-left text-xs">
                                                                        <thead class="bg-slate-100/80 dark:bg-slate-800/80 text-slate-600 dark:text-slate-300 font-semibold border-b border-slate-200 dark:border-slate-800">
                                                                            <tr>
                                                                                <th class="p-3 whitespace-nowrap">SKU &amp; Ukuran</th>
                                                                                <th class="p-3 min-w-[180px] whitespace-nowrap">Konveksi</th>
                                                                                <th class="p-3 w-36 whitespace-nowrap">Qty Barang Masuk (Pcs)</th>
                                                                                <th class="p-3 w-44 whitespace-nowrap">Harga per-1 Pcs (Rp)</th>
                                                                                <th class="p-3 w-44 text-right whitespace-nowrap">Total (Qty &times; Harga)</th>
                                                                                <th class="p-3 w-12 text-center"></th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                                                            {#each row.variants as v}
                                                                                {#if v.selected}
                                                                                    {@const rowSubtotal = (v.qty > 0 ? Number(v.qty) : 0) * (v.price > 0 ? Number(v.price) : 0)}
                                                                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition-colors bg-indigo-50/20 dark:bg-indigo-950/10">
                                                                                        <!-- SKU & Size -->
                                                                                        <td class="p-3 whitespace-nowrap">
                                                                                            <div class="font-mono font-bold text-indigo-600 dark:text-indigo-400">{v.sku}</div>
                                                                                            <div class="text-[11px] text-slate-500">
                                                                                                Ukuran: <span class="font-semibold text-slate-700 dark:text-slate-200">{v.size || '-'}</span>
                                                                                                {#if v.color}
                                                                                                    <span class="mx-1">/</span>
                                                                                                    <span>{v.color}</span>
                                                                                                {/if}
                                                                                            </div>
                                                                                        </td>

                                                                                        <!-- Konveksi -->
                                                                                        <td class="p-3">
                                                                                            <select
                                                                                                bind:value={v.konveksi_id}
                                                                                                class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 px-2.5 py-1.5 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                                                                                            >
                                                                                                <option value="">Internal / Tanpa Konveksi</option>
                                                                                                {#each konveksis as k}
                                                                                                    <option value={k.id}>{k.name}</option>
                                                                                                {/each}
                                                                                            </select>
                                                                                        </td>

                                                                                        <!-- Qty Masuk -->
                                                                                        <td class="p-3">
                                                                                            <input
                                                                                                type="number"
                                                                                                bind:value={v.qty}
                                                                                                min="1"
                                                                                                placeholder="0"
                                                                                                class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 px-2.5 py-1.5 text-xs font-bold text-emerald-600 dark:text-emerald-400 focus:ring-2 focus:ring-indigo-500"
                                                                                            />
                                                                                        </td>

                                                                                        <!-- Harga per 1 Pcs -->
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

                                                                                        <!-- Total Nominal (Qty x Harga) -->
                                                                                        <td class="p-3 text-right font-bold text-slate-800 dark:text-slate-100 whitespace-nowrap">
                                                                                            {formatRupiah(rowSubtotal)}
                                                                                        </td>

                                                                                        <!-- Batal Pilih SKU -->
                                                                                        <td class="p-3 text-center">
                                                                                            <button
                                                                                                type="button"
                                                                                                onclick={() => toggleVariantSku(v)}
                                                                                                class="rounded-lg p-1.5 text-slate-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition-colors"
                                                                                                title="Tutup / Batal pilih SKU ini"
                                                                                            >
                                                                                                <X class="h-4 w-4" />
                                                                                            </button>
                                                                                        </td>
                                                                                    </tr>
                                                                                {/if}
                                                                            {/each}
                                                                        </tbody>
                                                                        <tfoot class="bg-slate-50 dark:bg-slate-950/60 font-bold border-t border-slate-200 dark:border-slate-800">
                                                                            <tr>
                                                                                <td colspan="2" class="p-3 text-right text-slate-500 uppercase text-[10px] tracking-wider font-bold">
                                                                                    Subtotal Produk ({row.selectedProduct.product_name}):
                                                                                </td>
                                                                                <td class="p-3 text-emerald-600 dark:text-emerald-400 font-extrabold text-sm whitespace-nowrap">
                                                                                    {getRowQtySum(row.variants).toLocaleString('id-ID')} Pcs
                                                                                </td>
                                                                                <td colspan="3" class="p-3 text-right text-indigo-600 dark:text-indigo-400 font-extrabold text-sm whitespace-nowrap">
                                                                                    {formatRupiah(getRowNominalSum(row.variants))}
                                                                                </td>
                                                                            </tr>
                                                                        </tfoot>
                                                                    </table>
                                                                </div>
                                                            </div>
                                                        {:else}
                                                            <div class="rounded-xl border border-dashed border-indigo-200 dark:border-indigo-900/60 bg-indigo-50/40 dark:bg-indigo-950/20 p-4 text-center text-xs text-indigo-600 dark:text-indigo-300">
                                                                Klik salah satu atau beberapa <strong>SKU Varian</strong> di atas untuk membuka form input Qty, Konveksi, dan Harga per-1 Pcs.
                                                            </div>
                                                        {/if}
                                                    </div>
                                                {:else}
                                                    <div class="p-4 text-center text-xs text-slate-400 italic bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800">
                                                        Tidak ada SKU varian terdaftar pada produk ini.
                                                    </div>
                                                {/if}
                                            {/if}
                                        </div>
                                    {/each}

                                    <!-- Tombol Tambah Produk Lain di Dalam Seller Ini -->
                                    <div class="flex justify-start">
                                        <button
                                            type="button"
                                            onclick={() => addProductRow(group)}
                                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl border-2 border-dashed border-indigo-300 dark:border-indigo-800 bg-indigo-50/60 dark:bg-indigo-950/40 px-4 py-2.5 text-xs font-bold text-indigo-600 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 transition-colors"
                                        >
                                            <Plus class="h-4 w-4" />
                                            + Tambah Produk Lain untuk Seller {sellerName}
                                        </button>
                                    </div>
                                </div>
                            {/if}
                        </div>
                    </div>
                {/each}

                <!-- Tombol Tambah Seller Lain di Bawah Daftar Seller -->
                <div class="flex justify-center pt-2">
                    <button
                        type="button"
                        onclick={addSellerGroup}
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl border-2 border-dashed border-purple-300 dark:border-purple-800 bg-purple-50/70 dark:bg-purple-950/40 px-6 py-3 text-xs font-bold text-purple-700 dark:text-purple-300 hover:bg-purple-100 dark:hover:bg-purple-900/60 transition-colors shadow-2xs"
                    >
                        <Users class="h-4 w-4" />
                        + Tambah Seller Lain dalam Invoice Ini
                    </button>
                </div>
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
                        <span class="block text-[11px] uppercase tracking-wider text-slate-400 font-semibold">SKU Varian Diinput</span>
                        <span class="text-2xl font-black text-purple-300 mt-1 block">
                            {grandTotalActiveVariants} <span class="text-xs font-normal text-slate-400">SKU</span>
                        </span>
                    </div>

                    <div class="p-3.5 rounded-xl bg-white/5 border border-white/10">
                        <span class="block text-[11px] uppercase tracking-wider text-slate-400 font-semibold">Total QTY Barang Masuk</span>
                        <span class="text-2xl font-black text-emerald-400 mt-1 block">
                            {grandTotalQty.toLocaleString('id-ID')} <span class="text-xs font-normal text-slate-400">Pcs</span>
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

            <!-- Bottom Submit Button -->
            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end">
                <button
                    type="submit"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/30 hover:bg-indigo-700 transition-colors"
                >
                    <Save class="h-4 w-4" />
                    {isEdit ? 'Simpan Perubahan' : 'Simpan Stock In'} ({grandTotalQty.toLocaleString('id-ID')} Pcs)
                </button>
            </div>
        </div>
    </form>
</AdminLayout>
