<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import { Link } from '@inertiajs/svelte';
    import {
        ArrowLeft,
        FileText,
        Package,
        DollarSign,
        BarChart3,
        Wallet,
        Sparkles,
        Receipt,
        Search,
        Warehouse,
        Store,
        Calendar,
        User,
        Layers,
        Tag,
        ChevronLeft,
        ChevronRight,
        Filter,
        X,
    } from '@lucide/svelte';

    interface DetailItem {
        id: number;
        sku: string;
        product_name: string;
        product_type: string;
        size: string;
        color: string;
        seller_name: string;
        konveksi_name: string;
        qty: number;
        price: number;
        subtotal_hpp: number;
        selling_price: number;
        subtotal_selling: number;
    }

    interface ResiRow {
        order_id: string;
        no_resi: string;
        payment: number;
    }

    let {
        stockOut,
        items = [],
        resis = [],
        resiInfo = { total_resi: 0, label: '-', preview: '-' },
        metrics = {
            total_qty: 0,
            total_hpp: 0,
            total_bruto: 0,
            penjualan_bersih: 0,
            biaya_marketplace: 0,
            estimasi_keuntungan: 0,
        },
    }: {
        stockOut: {
            id: number;
            date: string;
            note: string | null;
            warehouse: { id: number; name: string } | null;
            user: { id: number; name: string } | null;
            period: { id: number; name: string } | null;
            store: { id: number; store_name: string; marketplace?: string; seller_name?: string | null } | null;
            created_at?: string | null;
        };
        items?: DetailItem[];
        resis?: ResiRow[];
        resiInfo?: { total_resi: number; label: string; preview: string };
        metrics?: {
            total_qty: number;
            total_hpp: number;
            total_bruto: number;
            penjualan_bersih: number;
            biaya_marketplace: number;
            estimasi_keuntungan: number;
        };
    } = $props();

    let itemSearch = $state('');
    let selectedBaseSkus = $state<string[]>([]);
    let showSkuChips = $state(true);
    let resiSearch = $state('');
    let resiPage = $state(1);
    const resiPerPage = 20;

    function extractBaseSku(skuStr: string, sizeStr: string): string {
        const raw = (skuStr || '').toUpperCase().trim();
        if (!raw) return '-';
        const sz = (sizeStr || '').toUpperCase().trim();
        if (sz && sz !== '-') {
            const escaped = sz.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const stripped = raw.replace(new RegExp(`[\\-\\s_]*${escaped}(?:\\-1)?$`, 'i'), '').trim();
            if (stripped) return stripped;
        }
        return raw
            .replace(/[\-\s_]+(7XL|6XL|5XL|4XL|3XL|2XL|XXXXXXL|XXXXXL|XXXXL|XXXL|XXL|XL|L|M|S|XS|ALL\s*SIZE|JUMBO|STANDAR)(?:\-1)?$/i, '')
            .trim() || raw;
    }

    let availableBaseSkus = $derived.by(() => {
        const map = new Map<string, { base: string; totalQty: number; variantCount: number }>();
        for (const it of items) {
            const base = extractBaseSku(it.sku, it.size);
            const existing = map.get(base);
            if (existing) {
                existing.totalQty += it.qty || 0;
                existing.variantCount += 1;
            } else {
                map.set(base, { base, totalQty: it.qty || 0, variantCount: 1 });
            }
        }
        return Array.from(map.values());
    });

    let searchTokens = $derived(
        itemSearch
            .split(/[,;\n]+/)
            .map((t) => t.trim().toLowerCase())
            .filter((t) => t.length > 0)
    );

    let hasActiveItemFilter = $derived(selectedBaseSkus.length > 0 || searchTokens.length > 0);

    function toggleBaseSku(base: string) {
        if (selectedBaseSkus.includes(base)) {
            selectedBaseSkus = selectedBaseSkus.filter((s) => s !== base);
        } else {
            selectedBaseSkus = [...selectedBaseSkus, base];
        }
    }

    function clearItemFilters() {
        itemSearch = '';
        selectedBaseSkus = [];
    }

    let filteredItems = $derived(
        items.filter((it) => {
            const base = extractBaseSku(it.sku, it.size);

            if (selectedBaseSkus.length > 0 && !selectedBaseSkus.includes(base)) {
                return false;
            }

            if (searchTokens.length === 0) {
                return true;
            }

            const skuLower = it.sku.toLowerCase();
            const baseLower = base.toLowerCase();
            const prodLower = it.product_name.toLowerCase();
            const sizeLower = it.size.toLowerCase();
            const colorLower = it.color.toLowerCase();
            const sellerLower = it.seller_name.toLowerCase();
            const konvLower = it.konveksi_name.toLowerCase();

            return searchTokens.some(
                (q) =>
                    skuLower.includes(q) ||
                    baseLower.includes(q) ||
                    prodLower.includes(q) ||
                    sizeLower.includes(q) ||
                    colorLower.includes(q) ||
                    sellerLower.includes(q) ||
                    konvLower.includes(q)
            );
        })
    );

    let filteredItemsQty = $derived(filteredItems.reduce((acc, i) => acc + (i.qty || 0), 0));
    let filteredItemsHpp = $derived(filteredItems.reduce((acc, i) => acc + (i.subtotal_hpp || 0), 0));
    let filteredItemsSelling = $derived(filteredItems.reduce((acc, i) => acc + (i.subtotal_selling || 0), 0));

    let filteredResis = $derived(
        resis.filter((r) => {
            const q = resiSearch.trim().toLowerCase();
            if (!q) return true;
            return (
                (r.order_id || '').toLowerCase().includes(q) ||
                (r.no_resi || '').toLowerCase().includes(q)
            );
        })
    );

    let totalResiPages = $derived(Math.max(1, Math.ceil(filteredResis.length / resiPerPage)));
    let paginatedResis = $derived(
        filteredResis.slice((resiPage - 1) * resiPerPage, resiPage * resiPerPage)
    );

    function formatRupiah(num: number) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(num || 0);
    }

    function formatDate(dateStr: string) {
        if (!dateStr) return '-';
        const clean = String(dateStr).split('T')[0];
        const parts = clean.split('-');
        if (parts.length === 3) {
            const [year, month, day] = parts;
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            return `${day} ${months[parseInt(month, 10) - 1] || month} ${year}`;
        }
        return clean;
    }
</script>

<AdminLayout
    title={`Detail Stock Out #${stockOut.id}`}
    breadcrumbs={[
        { name: 'Mutasi Stok' },
        { name: 'Pengeluaran (Stock Out)', url: '/superadmin/warehouse/stock-out' },
        { name: `Detail #${stockOut.id}` },
    ]}
>
    <!-- Header -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-start gap-3">
            <Link
                href="/superadmin/warehouse/stock-out"
                class="mt-0.5 inline-flex items-center justify-center rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-2.5 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors shadow-xs"
                title="Kembali ke Daftar Stock Out"
            >
                <ArrowLeft class="h-4 w-4" />
            </Link>
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight">
                        Detail Pengeluaran Barang #{stockOut.id}
                    </h1>
                    <span class="inline-flex items-center rounded-full bg-slate-100 dark:bg-slate-800 px-2.5 py-0.5 text-[11px] font-bold text-slate-600 dark:text-slate-300">
                        Read-Only
                    </span>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Rincian barang keluar, HPP, pencairan, dan daftar resi (mode lihat detail saja)
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a
                href={`/superadmin/warehouse/stock-out/${stockOut.id}/pdf`}
                target="_blank"
                class="inline-flex items-center gap-2 rounded-xl border border-indigo-200 dark:border-indigo-900/60 bg-indigo-50/80 dark:bg-indigo-950/40 px-4 py-2 text-xs font-semibold text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 transition-colors shadow-xs"
            >
                <FileText class="h-4 w-4" />
                Cetak Surat Jalan PDF
            </a>
        </div>
    </div>

    <!-- Metadata Info Grid -->
    <div class="mb-6 rounded-2xl bg-white dark:bg-slate-900 p-5 border border-slate-200 dark:border-slate-800 shadow-xs">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 text-xs">
            <div class="space-y-1">
                <div class="flex items-center gap-1.5 text-slate-400 font-semibold uppercase text-[10px]">
                    <Calendar class="h-3.5 w-3.5 text-rose-500" />
                    Tanggal
                </div>
                <div class="font-bold text-slate-800 dark:text-slate-100">{formatDate(stockOut.date)}</div>
            </div>

            <div class="space-y-1">
                <div class="flex items-center gap-1.5 text-slate-400 font-semibold uppercase text-[10px]">
                    <Layers class="h-3.5 w-3.5 text-indigo-500" />
                    Periode
                </div>
                <div class="font-bold text-indigo-600 dark:text-indigo-400">{stockOut.period?.name ?? '-'}</div>
            </div>

            <div class="space-y-1">
                <div class="flex items-center gap-1.5 text-slate-400 font-semibold uppercase text-[10px]">
                    <Warehouse class="h-3.5 w-3.5 text-amber-500" />
                    Gudang Asal
                </div>
                <div class="font-bold text-slate-800 dark:text-slate-100">{stockOut.warehouse?.name ?? '-'}</div>
            </div>

            <div class="space-y-1">
                <div class="flex items-center gap-1.5 text-slate-400 font-semibold uppercase text-[10px]">
                    <Store class="h-3.5 w-3.5 text-emerald-500" />
                    Seller / Toko
                </div>
                <div class="font-bold text-slate-800 dark:text-slate-100">
                    {stockOut.store?.seller_name ?? stockOut.store?.store_name ?? '-'}
                </div>
                {#if stockOut.store?.store_name && stockOut.store?.seller_name}
                    <div class="text-[11px] text-slate-400">{stockOut.store.store_name}</div>
                {/if}
            </div>

            <div class="space-y-1">
                <div class="flex items-center gap-1.5 text-slate-400 font-semibold uppercase text-[10px]">
                    <User class="h-3.5 w-3.5 text-cyan-500" />
                    Admin Pencatat
                </div>
                <div class="font-bold text-slate-800 dark:text-slate-100">{stockOut.user?.name ?? '-'}</div>
            </div>

            <div class="space-y-1">
                <div class="flex items-center gap-1.5 text-slate-400 font-semibold uppercase text-[10px]">
                    <Receipt class="h-3.5 w-3.5 text-violet-500" />
                    Ringkasan Resi
                </div>
                <div class="font-bold text-slate-800 dark:text-slate-100">
                    {resiInfo.total_resi.toLocaleString('id-ID')} resi
                </div>
                <div class="text-[11px] text-slate-400 truncate" title={resiInfo.preview}>{resiInfo.label}</div>
            </div>
        </div>

        {#if stockOut.note}
            <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-300">
                <span class="font-bold text-slate-400 uppercase text-[10px] mr-2">Catatan:</span>
                {stockOut.note}
            </div>
        {/if}
    </div>

    <!-- KPI Metrics -->
    <div class="mb-6 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="flex items-center justify-between text-slate-500">
                <span class="text-[10px] font-bold uppercase tracking-wider">Total Qty</span>
                <Package class="h-4 w-4 text-rose-500" />
            </div>
            <div class="text-lg font-extrabold text-rose-600 dark:text-rose-400">
                {metrics.total_qty.toLocaleString('id-ID')} <span class="text-xs font-semibold text-slate-400">pcs</span>
            </div>
            <div class="text-[11px] text-slate-400">{items.length} varian SKU</div>
        </div>

        <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="flex items-center justify-between text-slate-500">
                <span class="text-[10px] font-bold uppercase tracking-wider">Total HPP</span>
                <DollarSign class="h-4 w-4 text-slate-500" />
            </div>
            <div class="text-lg font-extrabold text-slate-800 dark:text-slate-100 truncate">
                {formatRupiah(metrics.total_hpp)}
            </div>
            <div class="text-[11px] text-slate-400">Modal barang keluar</div>
        </div>

        <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="flex items-center justify-between text-slate-500">
                <span class="text-[10px] font-bold uppercase tracking-wider">Total Bruto</span>
                <BarChart3 class="h-4 w-4 text-cyan-500" />
            </div>
            <div class="text-lg font-extrabold text-cyan-600 dark:text-cyan-400 truncate">
                {formatRupiah(metrics.total_bruto)}
            </div>
            <div class="text-[11px] text-slate-400">Omset kotor</div>
        </div>

        <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="flex items-center justify-between text-slate-500">
                <span class="text-[10px] font-bold uppercase tracking-wider">Penjualan Bersih</span>
                <Wallet class="h-4 w-4 text-emerald-500" />
            </div>
            <div class="text-lg font-extrabold text-emerald-600 dark:text-emerald-400 truncate">
                {formatRupiah(metrics.penjualan_bersih)}
            </div>
            <div class="text-[11px] text-slate-400">Pencairan terdata</div>
        </div>

        <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="flex items-center justify-between text-slate-500">
                <span class="text-[10px] font-bold uppercase tracking-wider">Biaya Admin/MP</span>
                <Tag class="h-4 w-4 text-amber-500" />
            </div>
            <div class="text-lg font-extrabold text-amber-600 dark:text-amber-400 truncate">
                {formatRupiah(metrics.biaya_marketplace)}
            </div>
            <div class="text-[11px] text-slate-400">Bruto - Bersih</div>
        </div>

        <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="flex items-center justify-between text-slate-500">
                <span class="text-[10px] font-bold uppercase tracking-wider">Estimasi Profit</span>
                <Sparkles class="h-4 w-4 text-violet-500" />
            </div>
            <div class="text-lg font-extrabold text-violet-600 dark:text-violet-400 truncate">
                {formatRupiah(metrics.estimasi_keuntungan)}
            </div>
            <div class="text-[11px] text-slate-400">Bersih - HPP</div>
        </div>
    </div>

    <!-- Detail Items Table (Read-Only) -->
    <div class="mb-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden">
        <div class="p-4 border-b border-slate-200 dark:border-slate-800 space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">
                            Daftar Barang Keluar ({filteredItems.length} / {items.length} SKU)
                        </h2>
                        {#if hasActiveItemFilter}
                            <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 dark:bg-rose-950/60 px-2.5 py-0.5 text-[11px] font-bold text-rose-700 dark:text-rose-300">
                                <Filter class="h-3 w-3" />
                                Filter Aktif ({filteredItemsQty.toLocaleString('id-ID')} pcs)
                            </span>
                        {/if}
                    </div>
                    <p class="text-[11px] text-slate-400">
                        Ketik multi-SKU dipisah koma (contoh: <span class="font-mono text-slate-600 dark:text-slate-300">ATG06,ATG07,ATG08,ATG09</span>) atau klik pill SKU di bawah
                    </p>
                </div>
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <div class="relative flex-1 sm:w-80">
                        <Search class="absolute left-3 top-2.5 h-3.5 w-3.5 text-slate-400" />
                        <input
                            type="text"
                            bind:value={itemSearch}
                            placeholder="Filter SKU (pisah koma): ATG06,ATG07..."
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 pl-8 pr-8 py-2 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-rose-500"
                        />
                        {#if itemSearch}
                            <button
                                type="button"
                                onclick={() => (itemSearch = '')}
                                class="absolute right-2.5 top-2.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                                title="Hapus pencarian"
                            >
                                <X class="h-3.5 w-3.5" />
                            </button>
                        {/if}
                    </div>
                    {#if hasActiveItemFilter}
                        <button
                            type="button"
                            onclick={clearItemFilters}
                            class="shrink-0 inline-flex items-center gap-1 rounded-xl border border-rose-200 dark:border-rose-900/60 bg-rose-50 dark:bg-rose-950/40 px-3 py-2 text-xs font-semibold text-rose-700 dark:text-rose-300 hover:bg-rose-100 dark:hover:bg-rose-900/60 transition-colors"
                        >
                            <X class="h-3.5 w-3.5" />
                            Reset
                        </button>
                    {/if}
                </div>
            </div>

            {#if availableBaseSkus.length > 1}
                <div class="pt-2 border-t border-slate-100 dark:border-slate-800/80">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                            Pilih Cepat Base SKU ({availableBaseSkus.length} grup{selectedBaseSkus.length > 0 ? ` • ${selectedBaseSkus.length} dipilih` : ''}):
                        </span>
                        <button
                            type="button"
                            onclick={() => (showSkuChips = !showSkuChips)}
                            class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:underline"
                        >
                            {showSkuChips ? 'Sembunyikan' : 'Tampilkan'}
                        </button>
                    </div>
                    {#if showSkuChips}
                        <div class="flex flex-wrap gap-1.5 max-h-28 overflow-y-auto pr-1">
                            {#each availableBaseSkus as grp}
                                {@const active = selectedBaseSkus.includes(grp.base)}
                                <button
                                    type="button"
                                    onclick={() => toggleBaseSku(grp.base)}
                                    class={`inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-[11px] font-mono font-bold border transition-colors ${
                                        active
                                            ? 'bg-rose-600 text-white border-rose-600 shadow-xs'
                                            : 'bg-slate-50 dark:bg-slate-950 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-800 hover:border-rose-300 dark:hover:border-rose-700 hover:text-rose-600 dark:hover:text-rose-400'
                                    }`}
                                >
                                    <span>{grp.base}</span>
                                    <span class={`text-[10px] font-sans ${active ? 'text-rose-100' : 'text-slate-400'}`}>
                                        ({grp.totalQty})
                                    </span>
                                </button>
                            {/each}
                        </div>
                    {/if}
                </div>
            {/if}
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600 dark:text-slate-400">
                <thead class="bg-slate-50 dark:bg-slate-800/50 uppercase font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="px-4 py-3 w-10">#</th>
                        <th class="px-4 py-3">SKU & Nama Produk</th>
                        <th class="px-4 py-3">Size / Warna</th>
                        <th class="px-4 py-3">Seller</th>
                        <th class="px-4 py-3">Konveksi</th>
                        <th class="px-4 py-3 text-right">QTY</th>
                        <th class="px-4 py-3 text-right">HPP / pcs</th>
                        <th class="px-4 py-3 text-right">Subtotal HPP</th>
                        <th class="px-4 py-3 text-right">Jual / pcs</th>
                        <th class="px-4 py-3 text-right">Subtotal Jual</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    {#each filteredItems as item, idx}
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="px-4 py-3 text-slate-400 font-mono">{idx + 1}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="font-mono font-bold text-slate-800 dark:text-slate-100">{item.sku}</span>
                                    {#if item.product_type === 'bundle'}
                                        <span class="rounded bg-indigo-100 dark:bg-indigo-950 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700 dark:text-indigo-300">
                                            BUNDLE
                                        </span>
                                    {/if}
                                </div>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400">{item.product_name}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center rounded-md bg-slate-100 dark:bg-slate-800 px-2 py-0.5 text-[11px] font-bold text-slate-700 dark:text-slate-200">
                                    {item.size}
                                </span>
                                {#if item.color && item.color !== '-'}
                                    <span class="ml-1 text-[11px] text-slate-400">{item.color}</span>
                                {/if}
                            </td>
                            <td class="px-4 py-3 font-medium text-slate-700 dark:text-slate-300">{item.seller_name}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{item.konveksi_name}</td>
                            <td class="px-4 py-3 text-right font-extrabold text-rose-600 dark:text-rose-400 whitespace-nowrap">
                                {item.qty.toLocaleString('id-ID')} pcs
                            </td>
                            <td class="px-4 py-3 text-right font-mono">{formatRupiah(item.price)}</td>
                            <td class="px-4 py-3 text-right font-mono font-semibold text-slate-800 dark:text-slate-200">
                                {formatRupiah(item.subtotal_hpp)}
                            </td>
                            <td class="px-4 py-3 text-right font-mono">{formatRupiah(item.selling_price)}</td>
                            <td class="px-4 py-3 text-right font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                                {formatRupiah(item.subtotal_selling)}
                            </td>
                        </tr>
                    {:else}
                        <tr>
                            <td colspan="10" class="px-6 py-10 text-center text-slate-400">
                                Tidak ada item barang yang cocok dengan pencarian.
                            </td>
                        </tr>
                    {/each}
                </tbody>
                {#if filteredItems.length > 0}
                    <tfoot class="bg-slate-50 dark:bg-slate-800/60 border-t border-slate-200 dark:border-slate-700 font-bold text-slate-800 dark:text-slate-100">
                        <tr>
                            <td colspan="5" class="px-4 py-3 text-right uppercase text-[11px] text-slate-500">Total</td>
                            <td class="px-4 py-3 text-right text-rose-600 dark:text-rose-400 whitespace-nowrap">
                                {filteredItemsQty.toLocaleString('id-ID')} pcs
                            </td>
                            <td class="px-4 py-3"></td>
                            <td class="px-4 py-3 text-right font-mono">{formatRupiah(filteredItemsHpp)}</td>
                            <td class="px-4 py-3"></td>
                            <td class="px-4 py-3 text-right font-mono text-emerald-600 dark:text-emerald-400">
                                {formatRupiah(filteredItemsSelling)}
                            </td>
                        </tr>
                    </tfoot>
                {/if}
            </table>
        </div>
    </div>

    <!-- Resi List (Read-Only, Client-Side Paginated) -->
    {#if resis.length > 0}
        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden">
            <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">
                        Daftar Resi & Pesanan ({filteredResis.length.toLocaleString('id-ID')} resi)
                    </h2>
                    <p class="text-[11px] text-slate-400">Daftar nomor pesanan dan resi yang terlampir pada transaksi ini</p>
                </div>
                <div class="relative w-full sm:w-64">
                    <Search class="absolute left-3 top-2.5 h-3.5 w-3.5 text-slate-400" />
                    <input
                        type="text"
                        bind:value={resiSearch}
                        oninput={() => (resiPage = 1)}
                        placeholder="Cari No. Pesanan / Resi..."
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 pl-8 pr-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-rose-500"
                    />
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600 dark:text-slate-400">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 uppercase font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-4 py-3 w-12">#</th>
                            <th class="px-4 py-3">No. Pesanan</th>
                            <th class="px-4 py-3">No. Resi</th>
                            <th class="px-4 py-3 text-right">Pembayaran / Pencairan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        {#each paginatedResis as r, idx}
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                <td class="px-4 py-2.5 text-slate-400 font-mono">{(resiPage - 1) * resiPerPage + idx + 1}</td>
                                <td class="px-4 py-2.5 font-mono text-slate-700 dark:text-slate-200">{r.order_id || '-'}</td>
                                <td class="px-4 py-2.5 font-mono font-semibold text-slate-800 dark:text-slate-100">{r.no_resi || '-'}</td>
                                <td class="px-4 py-2.5 text-right font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                                    {formatRupiah(r.payment)}
                                </td>
                            </tr>
                        {:else}
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-slate-400">Tidak ada resi yang cocok.</td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>

            {#if totalResiPages > 1}
                <div class="px-4 py-3 bg-slate-50/70 dark:bg-slate-800/40 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500">
                    <span>Halaman {resiPage} dari {totalResiPages}</span>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            disabled={resiPage <= 1}
                            onclick={() => (resiPage = Math.max(1, resiPage - 1))}
                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 py-1.5 text-xs font-semibold disabled:opacity-40 hover:bg-slate-50 dark:hover:bg-slate-800"
                        >
                            <ChevronLeft class="h-3.5 w-3.5" />
                            Prev
                        </button>
                        <button
                            type="button"
                            disabled={resiPage >= totalResiPages}
                            onclick={() => (resiPage = Math.min(totalResiPages, resiPage + 1))}
                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 py-1.5 text-xs font-semibold disabled:opacity-40 hover:bg-slate-50 dark:hover:bg-slate-800"
                        >
                            Next
                            <ChevronRight class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>
            {/if}
        </div>
    {/if}
</AdminLayout>
