<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import { Link } from '@inertiajs/svelte';
    import {
        ArrowLeft,
        FileText,
        PackageCheck,
        DollarSign,
        Layers,
        UserCheck,
        Search,
        Warehouse,
        Calendar,
        User,
        Factory,
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
    }

    let {
        stockIn,
        items = [],
        metrics = {
            total_qty: 0,
            total_hpp: 0,
            total_variants: 0,
            total_sellers: 0,
            total_konveksis: 0,
        },
    }: {
        stockIn: {
            id: number;
            detail_key: string;
            invoice_number: string;
            date: string;
            note: string | null;
            warehouse: { id: number; name: string } | null;
            user: { id: number; name: string } | null;
            period: { id: number; name: string } | null;
            sellers: string[];
            konveksis: string[];
            created_at?: string | null;
        };
        items?: DetailItem[];
        metrics?: {
            total_qty: number;
            total_hpp: number;
            total_variants: number;
            total_sellers: number;
            total_konveksis: number;
        };
    } = $props();

    let itemSearch = $state('');
    let selectedBaseSkus = $state<string[]>([]);
    let showSkuChips = $state(true);

    function extractBaseSku(skuStr: string, sizeStr: string): string {
        const raw = (skuStr || '').toUpperCase().trim();
        if (!raw) return '-';
        const sz = (sizeStr || '').toUpperCase().trim();
        if (sz && sz !== '-') {
            const escaped = sz.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const stripped = raw.replace(new RegExp(`[\\-\\s_]*${escaped}(?:\\-1)?$`, 'i'), '').trim();
            if (stripped) return stripped;
        }
        return (
            raw
                .replace(/[\-\s_]+(7XL|6XL|5XL|4XL|3XL|2XL|XXXXXXL|XXXXXL|XXXXL|XXXL|XXL|XL|L|M|S|XS|ALL\s*SIZE|JUMBO|STANDAR)(?:\-1)?$/i, '')
                .trim() || raw
        );
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

    const KONVEKSI_BADGE_STYLES = [
        { pill: 'bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-300 border-amber-200/80 dark:border-amber-800/70', icon: 'text-amber-500' },
        { pill: 'bg-sky-50 dark:bg-sky-950/50 text-sky-700 dark:text-sky-300 border-sky-200/80 dark:border-sky-800/70', icon: 'text-sky-500' },
        { pill: 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200/80 dark:border-emerald-800/70', icon: 'text-emerald-500' },
        { pill: 'bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-300 border-rose-200/80 dark:border-rose-800/70', icon: 'text-rose-500' },
        { pill: 'bg-violet-50 dark:bg-violet-950/50 text-violet-700 dark:text-violet-300 border-violet-200/80 dark:border-violet-800/70', icon: 'text-violet-500' },
        { pill: 'bg-teal-50 dark:bg-teal-950/50 text-teal-700 dark:text-teal-300 border-teal-200/80 dark:border-teal-800/70', icon: 'text-teal-500' },
        { pill: 'bg-orange-50 dark:bg-orange-950/50 text-orange-700 dark:text-orange-300 border-orange-200/80 dark:border-orange-800/70', icon: 'text-orange-500' },
        { pill: 'bg-fuchsia-50 dark:bg-fuchsia-950/50 text-fuchsia-700 dark:text-fuchsia-300 border-fuchsia-200/80 dark:border-fuchsia-800/70', icon: 'text-fuchsia-500' },
        { pill: 'bg-cyan-50 dark:bg-cyan-950/50 text-cyan-700 dark:text-cyan-300 border-cyan-200/80 dark:border-cyan-800/70', icon: 'text-cyan-500' },
        { pill: 'bg-lime-50 dark:bg-lime-950/50 text-lime-700 dark:text-lime-300 border-lime-200/80 dark:border-lime-800/70', icon: 'text-lime-500' },
        { pill: 'bg-pink-50 dark:bg-pink-950/50 text-pink-700 dark:text-pink-300 border-pink-200/80 dark:border-pink-800/70', icon: 'text-pink-500' },
        { pill: 'bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border-indigo-200/80 dark:border-indigo-800/70', icon: 'text-indigo-500' },
    ];

    function getKonveksiBadgeStyle(name: string) {
        const key = (name || '').trim().toLowerCase();
        let hash = 0;
        for (let i = 0; i < key.length; i++) {
            hash = (hash * 31 + key.charCodeAt(i)) >>> 0;
        }
        return KONVEKSI_BADGE_STYLES[hash % KONVEKSI_BADGE_STYLES.length];
    }

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
    title={`Detail Stock In ${stockIn.invoice_number}`}
    breadcrumbs={[
        { name: 'Mutasi Stok' },
        { name: 'Penerimaan (Stock In)', url: '/superadmin/warehouse/stock-in' },
        { name: stockIn.invoice_number },
    ]}
>
    <div class="polkadot-surface rounded-3xl p-4 sm:p-6 -m-2 sm:-m-3 border border-[#E6007A]/15 dark:border-[#E6007A]/25">
    <!-- Header -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-start gap-3">
            <Link
                href="/superadmin/warehouse/stock-in"
                class="mt-0.5 inline-flex items-center justify-center rounded-xl border border-[#E6007A]/25 bg-white/90 dark:bg-slate-900/90 p-2.5 text-[#E6007A] dark:text-pink-300 hover:bg-[#E6007A]/10 transition-colors shadow-xs"
                title="Kembali ke Daftar Stock In"
            >
                <ArrowLeft class="h-4 w-4" />
            </Link>
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-bold text-[#E6007A] dark:text-pink-400 tracking-tight font-mono">
                        {stockIn.invoice_number}
                    </h1>
                    <span class="inline-flex items-center rounded-full bg-[#E6007A]/10 border border-[#E6007A]/25 px-2.5 py-0.5 text-[11px] font-bold text-[#E6007A] dark:text-pink-300">
                        Read-Only
                    </span>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Rincian penerimaan barang masuk, HPP modal, seller, dan vendor konveksi
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a
                href={`/superadmin/warehouse/stock-in/${stockIn.id}/pdf`}
                target="_blank"
                class="inline-flex items-center gap-2 rounded-xl bg-[#E6007A] px-4 py-2 text-xs font-semibold text-white shadow-lg shadow-[#E6007A]/30 hover:bg-[#c9006b] transition-colors"
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
                    <Calendar class="h-3.5 w-3.5 text-sky-500" />
                    Tanggal
                </div>
                <div class="font-bold text-slate-800 dark:text-slate-100">{formatDate(stockIn.date)}</div>
            </div>

            <div class="space-y-1">
                <div class="flex items-center gap-1.5 text-slate-400 font-semibold uppercase text-[10px]">
                    <Layers class="h-3.5 w-3.5 text-indigo-500" />
                    Periode
                </div>
                <div class="font-bold text-indigo-600 dark:text-indigo-400">{stockIn.period?.name ?? '-'}</div>
            </div>

            <div class="space-y-1">
                <div class="flex items-center gap-1.5 text-slate-400 font-semibold uppercase text-[10px]">
                    <Warehouse class="h-3.5 w-3.5 text-amber-500" />
                    Gudang Tujuan
                </div>
                <div class="font-bold text-slate-800 dark:text-slate-100">{stockIn.warehouse?.name ?? '-'}</div>
            </div>

            <div class="space-y-1">
                <div class="flex items-center gap-1.5 text-slate-400 font-semibold uppercase text-[10px]">
                    <UserCheck class="h-3.5 w-3.5 text-purple-500" />
                    Mitra / Seller
                </div>
                <div class="font-bold text-slate-800 dark:text-slate-100">
                    {stockIn.sellers.length > 0 ? stockIn.sellers.join(', ') : '-'}
                </div>
            </div>

            <div class="space-y-1">
                <div class="flex items-center gap-1.5 text-slate-400 font-semibold uppercase text-[10px]">
                    <Factory class="h-3.5 w-3.5 text-emerald-500" />
                    Vendor Konveksi
                </div>
                <div class="flex flex-wrap gap-1">
                    {#each stockIn.konveksis as konvName}
                        {@const badge = getKonveksiBadgeStyle(konvName)}
                        <span class={`inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-semibold border ${badge.pill}`}>
                            <Factory class={`h-3 w-3 shrink-0 ${badge.icon}`} />
                            <span>{konvName}</span>
                        </span>
                    {:else}
                        <span class="font-bold text-slate-800 dark:text-slate-100">-</span>
                    {/each}
                </div>
            </div>

            <div class="space-y-1">
                <div class="flex items-center gap-1.5 text-slate-400 font-semibold uppercase text-[10px]">
                    <User class="h-3.5 w-3.5 text-cyan-500" />
                    Petugas
                </div>
                <div class="font-bold text-slate-800 dark:text-slate-100">{stockIn.user?.name ?? '-'}</div>
            </div>
        </div>

        {#if stockIn.note}
            <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-300">
                <span class="font-bold text-slate-400 uppercase text-[10px] mr-2">Catatan:</span>
                {stockIn.note}
            </div>
        {/if}
    </div>

    <!-- KPI Metrics -->
    <div class="mb-6 grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="flex items-center justify-between text-slate-500">
                <span class="text-[10px] font-bold uppercase tracking-wider">Total Barang Masuk</span>
                <PackageCheck class="h-4 w-4 text-emerald-500" />
            </div>
            <div class="text-xl font-extrabold text-emerald-600 dark:text-emerald-400">
                +{metrics.total_qty.toLocaleString('id-ID')} <span class="text-xs font-semibold text-slate-400">pcs</span>
            </div>
            <div class="text-[11px] text-slate-400">Total fisik stok diterima</div>
        </div>

        <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="flex items-center justify-between text-slate-500">
                <span class="text-[10px] font-bold uppercase tracking-wider">Total Nilai HPP</span>
                <DollarSign class="h-4 w-4 text-indigo-500" />
            </div>
            <div class="text-xl font-extrabold text-indigo-600 dark:text-indigo-400 truncate">
                {formatRupiah(metrics.total_hpp)}
            </div>
            <div class="text-[11px] text-slate-400">Total modal barang masuk</div>
        </div>

        <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="flex items-center justify-between text-slate-500">
                <span class="text-[10px] font-bold uppercase tracking-wider">Varian SKU</span>
                <Layers class="h-4 w-4 text-sky-500" />
            </div>
            <div class="text-xl font-extrabold text-slate-800 dark:text-slate-100">
                {metrics.total_variants.toLocaleString('id-ID')} <span class="text-xs font-semibold text-slate-400">SKU</span>
            </div>
            <div class="text-[11px] text-slate-400">{availableBaseSkus.length} grup Base SKU</div>
        </div>

        <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="flex items-center justify-between text-slate-500">
                <span class="text-[10px] font-bold uppercase tracking-wider">Seller & Konveksi</span>
                <UserCheck class="h-4 w-4 text-purple-500" />
            </div>
            <div class="text-xl font-extrabold text-purple-600 dark:text-purple-400">
                {metrics.total_sellers} <span class="text-xs font-semibold text-slate-400">Seller</span> • {metrics.total_konveksis} <span class="text-xs font-semibold text-slate-400">Konv.</span>
            </div>
            <div class="text-[11px] text-slate-400">Tercatat pada surat jalan ini</div>
        </div>
    </div>

    <!-- Detail Items Table (Read-Only + Multi-SKU Filter) -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden">
        <div class="p-4 border-b border-slate-200 dark:border-slate-800 space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">
                            Daftar Barang Masuk ({filteredItems.length} / {items.length} SKU)
                        </h2>
                        {#if hasActiveItemFilter}
                            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-100 dark:bg-indigo-950/60 px-2.5 py-0.5 text-[11px] font-bold text-indigo-700 dark:text-indigo-300">
                                <Filter class="h-3 w-3" />
                                Filter Aktif (+{filteredItemsQty.toLocaleString('id-ID')} pcs)
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
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 pl-8 pr-8 py-2 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
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
                            class="shrink-0 inline-flex items-center gap-1 rounded-xl border border-indigo-200 dark:border-indigo-900/60 bg-indigo-50 dark:bg-indigo-950/40 px-3 py-2 text-xs font-semibold text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 transition-colors"
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
                                            ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs'
                                            : 'bg-slate-50 dark:bg-slate-950 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-800 hover:border-indigo-300 dark:hover:border-indigo-700 hover:text-indigo-600 dark:hover:text-indigo-400'
                                    }`}
                                >
                                    <span>{grp.base}</span>
                                    <span class={`text-[10px] font-sans ${active ? 'text-indigo-100' : 'text-slate-400'}`}>
                                        (+{grp.totalQty})
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
                        <th class="px-4 py-3 text-right">QTY Masuk</th>
                        <th class="px-4 py-3 text-right">HPP / pcs</th>
                        <th class="px-4 py-3 text-right">Subtotal HPP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    {#each filteredItems as item, idx}
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="px-4 py-3 text-slate-400 font-mono">{idx + 1}</td>
                            <td class="px-4 py-3">
                                <div class="font-mono font-bold text-slate-800 dark:text-slate-100">{item.sku}</div>
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
                            <td class="px-4 py-3">
                                {#if item.konveksi_name && item.konveksi_name !== '-'}
                                    {@const badge = getKonveksiBadgeStyle(item.konveksi_name)}
                                    <span class={`inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-semibold border whitespace-nowrap ${badge.pill}`}>
                                        <Factory class={`h-3 w-3 shrink-0 ${badge.icon}`} />
                                        <span>{item.konveksi_name}</span>
                                    </span>
                                {:else}
                                    <span class="text-slate-400">-</span>
                                {/if}
                            </td>
                            <td class="px-4 py-3 text-right font-extrabold text-emerald-600 dark:text-emerald-400 whitespace-nowrap">
                                +{item.qty.toLocaleString('id-ID')} pcs
                            </td>
                            <td class="px-4 py-3 text-right font-mono">{formatRupiah(item.price)}</td>
                            <td class="px-4 py-3 text-right font-mono font-semibold text-indigo-600 dark:text-indigo-400">
                                {formatRupiah(item.subtotal_hpp)}
                            </td>
                        </tr>
                    {:else}
                        <tr>
                            <td colspan="8" class="px-6 py-10 text-center text-slate-400">
                                Tidak ada item barang yang cocok dengan filter.
                            </td>
                        </tr>
                    {/each}
                </tbody>
                {#if filteredItems.length > 0}
                    <tfoot class="bg-slate-50 dark:bg-slate-800/60 border-t border-slate-200 dark:border-slate-700 font-bold text-slate-800 dark:text-slate-100">
                        <tr>
                            <td colspan="5" class="px-4 py-3 text-right uppercase text-[11px] text-slate-500">Total</td>
                            <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400 whitespace-nowrap">
                                +{filteredItemsQty.toLocaleString('id-ID')} pcs
                            </td>
                            <td class="px-4 py-3"></td>
                            <td class="px-4 py-3 text-right font-mono text-indigo-600 dark:text-indigo-400">
                                {formatRupiah(filteredItemsHpp)}
                            </td>
                        </tr>
                    </tfoot>
                {/if}
            </table>
        </div>
    </div>
    </div>
</AdminLayout>

<style>
    .polkadot-surface {
        background-color: rgba(230, 0, 122, 0.025);
        background-image:
            radial-gradient(rgba(230, 0, 122, 0.14) 1.5px, transparent 1.5px),
            radial-gradient(rgba(230, 0, 122, 0.08) 1.5px, transparent 1.5px);
        background-size: 24px 24px;
        background-position: 0 0, 12px 12px;
    }

    :global(.dark) .polkadot-surface {
        background-color: rgba(230, 0, 122, 0.04);
        background-image:
            radial-gradient(rgba(230, 0, 122, 0.22) 1.5px, transparent 1.5px),
            radial-gradient(rgba(230, 0, 122, 0.12) 1.5px, transparent 1.5px);
    }
</style>
