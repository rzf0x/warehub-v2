<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import Modal from '@/components/Modal.svelte';
    import { router, Link } from '@inertiajs/svelte';
    import {
        Activity,
        History,
        ClipboardCheck,
        Search,
        FileSpreadsheet,
        FileText,
        Download,
        Upload,
        RotateCcw,
        Save,
        ChevronDown,
        ChevronUp,
        AlertTriangle,
        CheckCircle2,
        AlertCircle,
        Info,
        Package,
        Wallet,
        Layers,
        Boxes,
        Printer,
        X,
        Check,
    } from '@lucide/svelte';

    interface SellerOption {
        id: number;
        uuid?: string;
        seller_name: string;
        phone?: string | null;
    }

    interface OptionItem {
        id: number;
        name: string;
    }

    interface VariantRow {
        variant_id: number;
        product_id: number;
        prefix: string;
        sku: string;
        product_name: string;
        size: string;
        color: string;
        konveksi_name: string;
        hpp_price: number;
        in_qty: number;
        in_hpp: number;
        single_out_qty: number;
        single_out_hpp: number;
        bundle_out_qty: number;
        bundle_out_hpp: number;
        out_qty: number;
        out_hpp: number;
        sisa_debt_qty: number;
        sisa_debt_hpp: number;
        warehouse_stock_qty: number;
        all_warehouse_stock_qty: number;
        stock_hpp_value: number;
        qty_gap: number;
    }

    interface PrefixGroup {
        prefix: string;
        variant_count: number;
        in_qty: number;
        in_hpp: number;
        single_out_qty: number;
        bundle_out_qty: number;
        out_qty: number;
        out_hpp: number;
        sisa_debt_qty: number;
        sisa_debt_hpp: number;
        warehouse_stock_qty: number;
        all_warehouse_stock_qty: number;
        non_zero_stock_count: number;
        stock_hpp_value: number;
        qty_gap: number;
        variants: VariantRow[];
    }

    interface AvailablePrefix {
        prefix: string;
        variant_count: number;
        sisa_debt_qty: number;
        sisa_debt_hpp: number;
        warehouse_stock_qty: number;
        non_zero_stock_count: number;
    }

    interface ReportSummary {
        total_variants: number;
        total_prefixes: number;
        in_qty: number;
        in_hpp: number;
        single_out_qty: number;
        bundle_out_qty: number;
        out_qty: number;
        out_hpp: number;
        sisa_debt_qty: number;
        sisa_debt_hpp: number;
        total_adjustment: number;
        net_debt_with_adjustment: number;
        warehouse_stock_qty: number;
        all_warehouse_stock_qty: number;
        non_zero_stock_count: number;
        overall_non_zero_stock_count: number;
        overall_warehouse_stock_qty: number;
        stock_hpp_value: number;
        qty_gap: number;
    }

    interface RecentOpname {
        id: number;
        date: string;
        warehouse_name: string;
        user_name: string;
        items_count: number;
        note: string;
    }

    interface ReportData {
        groups: PrefixGroup[];
        available_prefixes: AvailablePrefix[];
        summary: ReportSummary;
        recent_opnames: RecentOpname[];
    }

    interface FlashProps {
        success?: string | null;
        error?: string | null;
        warning?: string | null;
    }

    interface ParsedCsvMatch {
        variant_id: number;
        sku: string;
        prefix: string;
        product_name: string;
        old_qty: number;
        new_qty: number;
    }

    let {
        sellers = [],
        warehouses = [],
        periods = [],
        selectedSeller = null,
        selectedWarehouse = null,
        report = null,
        filters = {
            seller_id: '',
            warehouse_id: '',
            period_id: '',
            prefix: '',
            search: '',
        },
        flash = {
            success: null,
            error: null,
            warning: null,
        },
    }: {
        sellers?: SellerOption[];
        warehouses?: OptionItem[];
        periods?: OptionItem[];
        selectedSeller?: SellerOption | null;
        selectedWarehouse?: OptionItem | null;
        report?: ReportData | null;
        filters?: {
            seller_id?: string;
            warehouse_id?: string;
            period_id?: string;
            prefix?: string;
            search?: string;
        };
        flash?: FlashProps;
    } = $props();

    let sellerId = $state('');
    let warehouseId = $state('');
    let periodId = $state('');
    let prefixFilter = $state('');
    let search = $state('');

    let bannerDismissed = $state(false);
    let expandedGroups = $state<Record<string, boolean>>({});
    let opnameInputs = $state<Record<number, string>>({});

    // Modal state: Reset Zero
    let showResetModal = $state(false);
    let resetScope = $state<'all' | 'prefix'>('all');
    let resetDate = $state(new Date().toISOString().split('T')[0]);
    let resetNote = $state('');
    let resetConfirmText = $state('');
    let resetSubmitting = $state(false);

    // Modal state: Save Opname
    let showSaveOpnameModal = $state(false);
    let saveOpnameDate = $state(new Date().toISOString().split('T')[0]);
    let saveOpnameNote = $state('');
    let saveOpnameSubmitting = $state(false);

    // Modal state: CSV Import
    let showCsvModal = $state(false);
    let csvFileName = $state('');
    let csvMatched = $state<ParsedCsvMatch[]>([]);
    let csvUnmatchedSkus = $state<string[]>([]);
    let csvErrors = $state<string[]>([]);

    $effect(() => {
        sellerId = String(filters.seller_id ?? '');
        warehouseId = String(filters.warehouse_id ?? '');
        periodId = String(filters.period_id ?? '');
        prefixFilter = String(filters.prefix ?? '');
        search = String(filters.search ?? '');
    });

    $effect(() => {
        if (flash.success || flash.error || flash.warning) {
            bannerDismissed = false;
        }
    });

    $effect(() => {
        const nextInputs: Record<number, string> = {};
        const nextExpanded: Record<string, boolean> = {};

        if (report?.groups) {
            report.groups.forEach((group, idx) => {
                nextExpanded[group.prefix] = report.groups.length <= 8 || idx < 5;
                group.variants.forEach((v) => {
                    nextInputs[v.variant_id] = String(v.warehouse_stock_qty);
                });
            });
        }

        opnameInputs = nextInputs;
        expandedGroups = nextExpanded;
    });

    const allVariants = $derived.by<VariantRow[]>(() => {
        if (!report?.groups) return [];
        return report.groups.flatMap((g) => g.variants);
    });

    function parseDraftQty(variantId: number, fallbackQty: number): number | null {
        const raw = opnameInputs[variantId];
        if (raw === undefined || raw === null || String(raw).trim() === '') {
            return null;
        }
        const parsed = Number(raw);
        if (!Number.isInteger(parsed) || parsed < 0) {
            return null;
        }
        return parsed;
    }

    const invalidInputCount = $derived.by(() => {
        let count = 0;
        for (const v of allVariants) {
            const parsed = parseDraftQty(v.variant_id, v.warehouse_stock_qty);
            if (parsed === null) {
                count++;
            }
        }
        return count;
    });

    const changedItems = $derived.by(() => {
        const list: Array<{
            product_variant_id: number;
            sku: string;
            prefix: string;
            product_name: string;
            system_qty: number;
            physical_qty: number;
            diff: number;
        }> = [];

        for (const v of allVariants) {
            const parsed = parseDraftQty(v.variant_id, v.warehouse_stock_qty);
            if (parsed === null) continue;
            if (parsed !== v.warehouse_stock_qty) {
                list.push({
                    product_variant_id: v.variant_id,
                    sku: v.sku,
                    prefix: v.prefix,
                    product_name: v.product_name,
                    system_qty: v.warehouse_stock_qty,
                    physical_qty: parsed,
                    diff: parsed - v.warehouse_stock_qty,
                });
            }
        }
        return list;
    });

    const changedBeforeTotal = $derived(changedItems.reduce((acc, it) => acc + it.system_qty, 0));
    const changedAfterTotal = $derived(changedItems.reduce((acc, it) => acc + it.physical_qty, 0));

    const activePrefixObj = $derived.by(() => {
        if (!prefixFilter || !report?.available_prefixes) return null;
        return report.available_prefixes.find(
            (p) => p.prefix.toUpperCase() === prefixFilter.toUpperCase()
        ) ?? null;
    });

    function formatRupiah(num: number): string {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(num || 0);
    }

    function applyFilter(overrides?: { prefix?: string }) {
        const nextPrefix = overrides && overrides.prefix !== undefined ? overrides.prefix : prefixFilter;
        router.get(
            '/superadmin/warehouse/stocks/reconciliation',
            {
                seller_id: sellerId,
                warehouse_id: warehouseId,
                period_id: periodId,
                prefix: nextPrefix,
                search,
            },
            { preserveState: false, replace: true }
        );
    }

    function selectPrefix(prefix: string) {
        prefixFilter = prefix;
        applyFilter({ prefix });
    }

    function buildQueryString(extra: Record<string, string> = {}): string {
        const params = new URLSearchParams();
        if (sellerId) params.set('seller_id', sellerId);
        if (warehouseId) params.set('warehouse_id', warehouseId);
        if (periodId) params.set('period_id', periodId);
        if (prefixFilter) params.set('prefix', prefixFilter);
        if (search) params.set('search', search);
        for (const [k, v] of Object.entries(extra)) {
            if (v) params.set(k, v);
        }
        return params.toString();
    }

    const exportCsvUrl = $derived(
        `/superadmin/warehouse/stocks/reconciliation/export-csv?${buildQueryString()}`
    );
    const exportPdfUrl = $derived(
        `/superadmin/warehouse/stocks/reconciliation/export-pdf?${buildQueryString()}`
    );
    const templateCsvUrl = $derived(
        `/superadmin/warehouse/stocks/reconciliation/template-csv?${buildQueryString()}`
    );

    function toggleGroup(prefix: string) {
        expandedGroups = {
            ...expandedGroups,
            [prefix]: !expandedGroups[prefix],
        };
    }

    function expandAllGroups() {
        const next: Record<string, boolean> = {};
        report?.groups.forEach((g) => {
            next[g.prefix] = true;
        });
        expandedGroups = next;
    }

    function collapseAllGroups() {
        const next: Record<string, boolean> = {};
        report?.groups.forEach((g) => {
            next[g.prefix] = false;
        });
        expandedGroups = next;
    }

    function setAllDraftZero() {
        const next: Record<number, string> = { ...opnameInputs };
        for (const v of allVariants) {
            next[v.variant_id] = '0';
        }
        opnameInputs = next;
    }

    function resetDraftToSystem() {
        const next: Record<number, string> = {};
        for (const v of allVariants) {
            next[v.variant_id] = String(v.warehouse_stock_qty);
        }
        opnameInputs = next;
    }

    function setVariantInput(variantId: number, value: string) {
        opnameInputs = {
            ...opnameInputs,
            [variantId]: value,
        };
    }

    function openResetZeroModal() {
        resetScope = prefixFilter ? 'prefix' : 'all';
        resetDate = new Date().toISOString().split('T')[0];
        resetNote = selectedSeller
            ? `Reset Nol Pra-Opname Mitra: ${selectedSeller.seller_name}${prefixFilter ? ` [Prefix: ${prefixFilter}]` : ''}`
            : '';
        resetConfirmText = '';
        showResetModal = true;
    }

    function submitResetZero() {
        if (!selectedSeller || !warehouseId || resetConfirmText.trim().toUpperCase() !== 'NOL') {
            return;
        }

        resetSubmitting = true;
        const payload: {
            seller_id: number;
            warehouse_id: number;
            date: string;
            note: string;
            prefixes?: string[];
        } = {
            seller_id: selectedSeller.id,
            warehouse_id: Number(warehouseId),
            date: resetDate,
            note: resetNote,
        };

        if (resetScope === 'prefix' && prefixFilter) {
            payload.prefixes = [prefixFilter];
        }

        router.post('/superadmin/warehouse/stocks/reconciliation/reset-zero', payload, {
            preserveScroll: true,
            onFinish: () => {
                resetSubmitting = false;
                showResetModal = false;
            },
        });
    }

    function openSaveOpnameModal() {
        if (changedItems.length === 0 || invalidInputCount > 0) return;
        saveOpnameDate = new Date().toISOString().split('T')[0];
        saveOpnameNote = selectedSeller
            ? `Input Hasil Stock Opname Mitra: ${selectedSeller.seller_name}${prefixFilter ? ` [Prefix: ${prefixFilter}]` : ''}`
            : '';
        showSaveOpnameModal = true;
    }

    function submitSaveOpname() {
        if (!selectedSeller || !warehouseId || changedItems.length === 0) return;
        saveOpnameSubmitting = true;

        router.post(
            '/superadmin/warehouse/stocks/reconciliation/apply-opname',
            {
                seller_id: selectedSeller.id,
                warehouse_id: Number(warehouseId),
                date: saveOpnameDate,
                note: saveOpnameNote,
                items: changedItems.map((it) => ({
                    product_variant_id: it.product_variant_id,
                    physical_qty: it.physical_qty,
                })),
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    saveOpnameSubmitting = false;
                    showSaveOpnameModal = false;
                },
            }
        );
    }

    function openCsvImportModal() {
        csvFileName = '';
        csvMatched = [];
        csvUnmatchedSkus = [];
        csvErrors = [];
        showCsvModal = true;
    }

    function parseCsvLine(line: string, delimiter: string): string[] {
        const result: string[] = [];
        let current = '';
        let inQuotes = false;

        for (let i = 0; i < line.length; i++) {
            const ch = line[i];
            if (ch === '"') {
                if (inQuotes && line[i + 1] === '"') {
                    current += '"';
                    i++;
                } else {
                    inQuotes = !inQuotes;
                }
            } else if (ch === delimiter && !inQuotes) {
                result.push(current.trim());
                current = '';
            } else {
                current += ch;
            }
        }
        result.push(current.trim());
        return result;
    }

    function handleCsvFileSelected(event: Event) {
        const input = event.target as HTMLInputElement;
        const file = input.files?.[0];
        if (!file) return;

        csvFileName = file.name;
        csvMatched = [];
        csvUnmatchedSkus = [];
        csvErrors = [];

        const reader = new FileReader();
        reader.onload = () => {
            const rawText = String(reader.result ?? '').replace(/^\uFEFF/, '');
            const lines = rawText
                .split(/\r?\n/)
                .map((l) => l.trim())
                .filter((l) => l.length > 0);

            if (lines.length < 2) {
                csvErrors = ['File CSV kosong atau tidak memiliki baris data.'];
                return;
            }

            const headerLine = lines[0];
            const semiCount = (headerLine.match(/;/g) || []).length;
            const commaCount = (headerLine.match(/,/g) || []).length;
            const delimiter = semiCount > commaCount ? ';' : ',';

            const headers = parseCsvLine(headerLine, delimiter).map((h) => h.toLowerCase().replace(/\s+/g, '_'));
            const skuCol = headers.findIndex((h) => h === 'sku' || h === 'kode_sku' || h === 'variant_sku');
            const qtyCol = headers.findIndex((h) =>
                ['stok_fisik_opname', 'physical_qty', 'stok_fisik', 'qty', 'fisik', 'opname'].includes(h)
            );

            if (skuCol === -1 || qtyCol === -1) {
                csvErrors = [
                    'Header CSV harus memiliki kolom "sku" dan salah satu kolom qty ("stok_fisik_opname", "physical_qty", "stok_fisik", "qty", atau "fisik").',
                ];
                return;
            }

            const variantBySku = new Map<string, VariantRow>();
            for (const v of allVariants) {
                variantBySku.set(v.sku.trim().toUpperCase(), v);
            }

            const matchedMap = new Map<number, ParsedCsvMatch>();
            const unmatched: string[] = [];
            const errors: string[] = [];

            for (let i = 1; i < lines.length; i++) {
                const cols = parseCsvLine(lines[i], delimiter);
                const rawSku = (cols[skuCol] ?? '').trim();
                const rawQty = (cols[qtyCol] ?? '').trim();

                if (!rawSku || rawQty === '') {
                    continue;
                }

                if (!/^-?\d+$/.test(rawQty)) {
                    errors.push(`Baris ${i + 1} (${rawSku}): nilai "${rawQty}" bukan bilangan bulat valid.`);
                    continue;
                }

                const parsedQty = Number(rawQty);
                if (parsedQty < 0) {
                    errors.push(`Baris ${i + 1} (${rawSku}): stok fisik tidak boleh negatif (${parsedQty}).`);
                    continue;
                }

                const variant = variantBySku.get(rawSku.toUpperCase());
                if (!variant) {
                    unmatched.push(rawSku);
                    continue;
                }

                matchedMap.set(variant.variant_id, {
                    variant_id: variant.variant_id,
                    sku: variant.sku,
                    prefix: variant.prefix,
                    product_name: variant.product_name,
                    old_qty: variant.warehouse_stock_qty,
                    new_qty: parsedQty,
                });
            }

            csvMatched = Array.from(matchedMap.values());
            csvUnmatchedSkus = unmatched;
            csvErrors = errors;
        };

        reader.readAsText(file, 'UTF-8');
    }

    function applyCsvToDraft() {
        if (csvMatched.length === 0) return;

        const nextInputs: Record<number, string> = { ...opnameInputs };
        const nextExpanded: Record<string, boolean> = { ...expandedGroups };

        for (const item of csvMatched) {
            nextInputs[item.variant_id] = String(item.new_qty);
            nextExpanded[item.prefix] = true;
        }

        opnameInputs = nextInputs;
        expandedGroups = nextExpanded;
        showCsvModal = false;
    }
</script>

<AdminLayout
    title="Rekonsiliasi & Opname Mitra"
    breadcrumbs={[
        { name: 'Mutasi Stok' },
        { name: 'Stok Fisik Realtime', url: '/superadmin/warehouse/stocks' },
        { name: 'Rekonsiliasi & Opname Mitra' },
    ]}
>
    <!-- Header -->
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                <ClipboardCheck class="h-7 w-7 text-indigo-500" />
                Rekonsiliasi Hutang & Opname Per-Varian Mitra
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Hitung sisa hutang modal per-varian & prefix SKU, nol-kan stok aman pra-opname, dan input hasil stock opname terbaru
            </p>
        </div>
    </div>

    <!-- Tabs Navigation (consistent with Stock/Index.svelte) -->
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
            class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition-colors bg-indigo-600 text-white shadow-md shadow-indigo-600/20"
        >
            <ClipboardCheck class="h-4 w-4" />
            Rekonsiliasi & Opname Mitra
        </Link>

        <Link
            href="/superadmin/warehouse/stocks/print-sku"
            class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition-colors text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"
        >
            <Printer class="h-4 w-4 text-emerald-500" />
            Print SKU Stock
        </Link>
    </div>

    <!-- Flash Banner -->
    {#if !bannerDismissed && (flash.success || flash.warning || flash.error)}
        <div
            class="mb-6 flex items-start justify-between gap-3 rounded-2xl border p-4 text-sm shadow-xs
            {flash.error
                ? 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-800 dark:bg-rose-950/50 dark:text-rose-200'
                : flash.warning
                  ? 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-200'
                  : 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200'}"
        >
            <div class="flex items-start gap-2.5">
                {#if flash.error}
                    <AlertCircle class="h-5 w-5 shrink-0 text-rose-600 dark:text-rose-400 mt-0.5" />
                {:else if flash.warning}
                    <AlertTriangle class="h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400 mt-0.5" />
                {:else}
                    <CheckCircle2 class="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400 mt-0.5" />
                {/if}
                <p class="font-medium leading-relaxed">{flash.error || flash.warning || flash.success}</p>
            </div>
            <button
                type="button"
                onclick={() => (bannerDismissed = true)}
                class="rounded-lg p-1 opacity-70 hover:opacity-100 transition-opacity"
                aria-label="Tutup notifikasi"
            >
                <X class="h-4 w-4" />
            </button>
        </div>
    {/if}

    <!-- Filter & Selector Panel -->
    <div class="mb-6 rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label for="rec_seller_id" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">
                    Pilih Mitra (Seller)
                </label>
                <select
                    id="rec_seller_id"
                    bind:value={sellerId}
                    onchange={() => applyFilter({ prefix: '' })}
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-sm font-medium text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                >
                    <option value="">-- Pilih Mitra --</option>
                    {#each sellers as s}
                        <option value={String(s.id)}>{s.seller_name}</option>
                    {/each}
                </select>
            </div>

            <div>
                <label for="rec_warehouse_id" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">
                    Gudang Target Opname
                </label>
                <select
                    id="rec_warehouse_id"
                    bind:value={warehouseId}
                    onchange={() => applyFilter()}
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-sm font-medium text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                >
                    {#each warehouses as w}
                        <option value={String(w.id)}>{w.name}</option>
                    {/each}
                </select>
            </div>

            <div>
                <label for="rec_period_id" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">
                    Periode Hutang
                </label>
                <select
                    id="rec_period_id"
                    bind:value={periodId}
                    onchange={() => applyFilter()}
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-sm font-medium text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                >
                    <option value="">Semua Periode (Total Akumulasi)</option>
                    {#each periods as p}
                        <option value={String(p.id)}>{p.name}</option>
                    {/each}
                </select>
            </div>

            <div>
                <label for="rec_search" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">
                    Cari SKU / Produk
                </label>
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-3 h-4 w-4 text-slate-400" />
                        <input
                            id="rec_search"
                            type="text"
                            bind:value={search}
                            onkeydown={(e) => e.key === 'Enter' && applyFilter()}
                            placeholder="AYD, RTG, nama..."
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 pl-9 pr-3 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                    </div>
                    <button
                        type="button"
                        onclick={() => applyFilter()}
                        class="rounded-xl bg-slate-800 dark:bg-slate-200 px-3.5 py-2.5 text-xs font-semibold text-white dark:text-slate-900 hover:bg-slate-700 dark:hover:bg-slate-300 transition-colors"
                    >
                        Cari
                    </button>
                </div>
            </div>
        </div>

        <div class="flex items-start gap-2.5 rounded-xl bg-indigo-50/70 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/60 px-4 py-3 text-xs text-indigo-800 dark:text-indigo-200">
            <Info class="h-4 w-4 shrink-0 text-indigo-600 dark:text-indigo-400 mt-0.5" />
            <p class="leading-relaxed">
                Hutang per-varian dihitung dari seluruh histori <strong>Stock In − Stock Out</strong> (termasuk komponen penjualan <strong>Bundle</strong>).
                Aksi <strong>Nol-kan Stok</strong> &amp; <strong>Simpan Opname</strong> hanya mengubah stok fisik (<code>stocks</code>) lewat jalur resmi
                <strong>Audit Stock Opname</strong> sehingga hutang modal lama dan alur Stock In / Out tetap aman 100%.
            </p>
        </div>
    </div>

    {#if !selectedSeller || !report}
        <!-- Empty State before Seller Selection -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-12 text-center border border-slate-100 dark:border-slate-800 shadow-xs space-y-3">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                <ClipboardCheck class="h-7 w-7" />
            </div>
            <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">Pilih Mitra Terlebih Dahulu</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 max-w-md mx-auto">
                Pilih nama mitra/seller dan gudang target di atas untuk melihat rincian hutang per-varian yang dikelompokkan otomatis per prefix SKU
                (misalnya <span class="font-mono font-semibold text-indigo-600 dark:text-indigo-400">AYD, RTG, STG, PTG, DTG</span>).
            </p>
        </div>
    {:else}
        <!-- 4 KPI Summary Cards -->
        <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="rounded-2xl bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 p-5 text-white shadow-md flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-indigo-200">Sisa Hutang Modal Varian</span>
                        <Wallet class="h-4 w-4 text-amber-300" />
                    </div>
                    <span class="text-2xl font-black text-amber-300 mt-1 block">
                        {formatRupiah(report.summary.sisa_debt_hpp)}
                    </span>
                    <span class="text-xs text-indigo-200/90 mt-1 block">
                        {report.summary.sisa_debt_qty.toLocaleString('id-ID')} pcs tertahan ({report.summary.total_variants} varian)
                    </span>
                </div>
                <div class="mt-3 pt-2 border-t border-indigo-800/60 text-[11px] text-indigo-200/80 flex items-center justify-between">
                    <span>Net + Adj ({formatRupiah(report.summary.total_adjustment)}):</span>
                    <span class="font-bold text-white">{formatRupiah(report.summary.net_debt_with_adjustment)}</span>
                </div>
            </div>

            <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Total Modal Masuk (Stock In)</span>
                        <Package class="h-4 w-4 text-sky-500" />
                    </div>
                    <span class="text-xl font-bold text-sky-600 dark:text-sky-400 mt-1 block">
                        {formatRupiah(report.summary.in_hpp)}
                    </span>
                </div>
                <div class="mt-3 pt-2 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500">
                    <span>Total Barang Masuk:</span>
                    <span class="font-bold text-slate-800 dark:text-slate-100">{report.summary.in_qty.toLocaleString('id-ID')} pcs</span>
                </div>
            </div>

            <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Modal Keluar (Out + Bundle)</span>
                        <Layers class="h-4 w-4 text-emerald-500" />
                    </div>
                    <span class="text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-1 block">
                        {formatRupiah(report.summary.out_hpp)}
                    </span>
                </div>
                <div class="mt-3 pt-2 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500">
                    <span>Terjual: {report.summary.out_qty.toLocaleString('id-ID')} pcs</span>
                    <span class="font-semibold text-indigo-600 dark:text-indigo-400">
                        ({report.summary.bundle_out_qty.toLocaleString('id-ID')} via bundle)
                    </span>
                </div>
            </div>

            <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                            Stok Fisik ({selectedWarehouse?.name ?? 'Gudang'})
                        </span>
                        <Boxes class="h-4 w-4 text-purple-500" />
                    </div>
                    <span class="text-xl font-bold text-slate-800 dark:text-slate-100 mt-1 block">
                        {report.summary.warehouse_stock_qty.toLocaleString('id-ID')} <span class="text-sm font-normal text-slate-500">pcs</span>
                    </span>
                    <span class="text-[11px] text-slate-500 mt-1 block">
                        {report.summary.non_zero_stock_count} varian stok ≠ 0
                    </span>
                </div>
                <div class="mt-3 pt-2 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500">
                    <span>Nilai HPP Stok Fisik:</span>
                    <span class="font-bold text-slate-800 dark:text-slate-100">{formatRupiah(report.summary.stock_hpp_value)}</span>
                </div>
            </div>
        </div>

        <!-- Action Toolbar -->
        <div class="mb-6 rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <!-- Group 1: Audit & Export -->
            <div class="flex flex-wrap items-center gap-2.5">
                <a
                    href={exportCsvUrl}
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3.5 py-2 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
                >
                    <FileSpreadsheet class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                    Export CSV
                </a>

                <a
                    href={exportPdfUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3.5 py-2 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
                >
                    <FileText class="h-4 w-4 text-rose-600 dark:text-rose-400" />
                    Export PDF
                </a>

                <a
                    href={templateCsvUrl}
                    class="inline-flex items-center gap-2 rounded-xl border border-indigo-200 dark:border-indigo-800 bg-indigo-50/70 dark:bg-indigo-950/40 px-3.5 py-2 text-xs font-bold text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 transition-colors"
                >
                    <Download class="h-4 w-4" />
                    Download Template CSV Opname
                </a>
            </div>

            <!-- Group 2: Eksekusi Opname -->
            <div class="flex flex-wrap items-center gap-2.5">
                <button
                    type="button"
                    onclick={openCsvImportModal}
                    disabled={allVariants.length === 0}
                    class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-indigo-600/25 hover:bg-indigo-700 disabled:opacity-40 transition-colors"
                >
                    <Upload class="h-4 w-4" />
                    Import CSV Opname
                </button>

                <button
                    type="button"
                    onclick={openResetZeroModal}
                    disabled={report.summary.overall_non_zero_stock_count === 0 || !warehouseId}
                    class="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-rose-600/25 hover:bg-rose-700 disabled:opacity-40 transition-colors"
                >
                    <RotateCcw class="h-4 w-4" />
                    Nol-kan Semua Stok Mitra
                </button>
            </div>
        </div>

        <!-- Prefix SKU Filter Chips -->
        {#if report.available_prefixes.length > 0}
            <div class="mb-6 rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800">
                <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-2.5">
                    Filter Kelompok Prefix SKU ({report.available_prefixes.length} Seri)
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        onclick={() => selectPrefix('')}
                        class="inline-flex items-center gap-2 rounded-xl px-3.5 py-1.5 text-xs font-bold border transition-all
                        {!prefixFilter
                            ? 'bg-indigo-600 border-indigo-600 text-white shadow-sm'
                            : 'bg-slate-50 dark:bg-slate-950 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'}"
                    >
                        <span>Semua Prefix</span>
                    </button>

                    {#each report.available_prefixes as p}
                        {@const isSelected = prefixFilter.toUpperCase() === p.prefix.toUpperCase()}
                        <button
                            type="button"
                            onclick={() => selectPrefix(p.prefix)}
                            class="inline-flex items-center gap-2 rounded-xl px-3.5 py-1.5 text-xs font-bold border transition-all
                            {isSelected
                                ? 'bg-indigo-600 border-indigo-600 text-white shadow-sm'
                                : 'bg-slate-50 dark:bg-slate-950 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'}"
                        >
                            <span class="font-mono">{p.prefix}</span>
                            <span class="rounded-md px-1.5 py-0.5 text-[10px] {isSelected ? 'bg-indigo-500 text-white' : 'bg-slate-200/80 dark:bg-slate-800 text-slate-600 dark:text-slate-300'}">
                                {p.variant_count} SKU
                            </span>
                            <span class="text-[10px] {isSelected ? 'text-amber-200' : p.sisa_debt_hpp > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'}">
                                {formatRupiah(p.sisa_debt_hpp)}
                            </span>
                        </button>
                    {/each}
                </div>
            </div>
        {/if}

        <!-- Quick-Opname Helper Bar -->
        {#if report.groups.length > 0}
            <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-2.5 text-xs">
                    <button
                        type="button"
                        onclick={expandAllGroups}
                        class="font-semibold text-indigo-600 dark:text-indigo-400 hover:underline"
                    >
                        Buka Semua
                    </button>
                    <span class="text-slate-300 dark:text-slate-700">•</span>
                    <button
                        type="button"
                        onclick={collapseAllGroups}
                        class="font-semibold text-slate-500 dark:text-slate-400 hover:underline"
                    >
                        Tutup Semua
                    </button>
                    {#if invalidInputCount > 0}
                        <span class="ml-2 inline-flex items-center gap-1 rounded-full bg-rose-50 dark:bg-rose-950/60 px-2.5 py-0.5 font-semibold text-rose-600 dark:text-rose-400">
                            <AlertCircle class="h-3.5 w-3.5" />
                            {invalidInputCount} input tidak valid
                        </span>
                    {/if}
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        onclick={setAllDraftZero}
                        class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                    >
                        Set Input = 0 Semua
                    </button>
                    <button
                        type="button"
                        onclick={resetDraftToSystem}
                        class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                    >
                        Salin Stok Sistem
                    </button>
                    {#if changedItems.length > 0}
                        <button
                            type="button"
                            onclick={resetDraftToSystem}
                            class="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/40 px-3 py-1.5 text-xs font-semibold text-amber-700 dark:text-amber-300 hover:bg-amber-100 dark:hover:bg-amber-900/50 transition-colors"
                        >
                            Batal Perubahan Draft ({changedItems.length})
                        </button>
                    {/if}
                </div>
            </div>
        {/if}

        <!-- Grouped Tables per Prefix SKU -->
        <div class="space-y-4 mb-8">
            {#each report.groups as group}
                {@const isExpanded = !!expandedGroups[group.prefix]}
                <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-xs border border-slate-200/80 dark:border-slate-800 overflow-hidden">
                    <!-- Group Accordion Header -->
                    <div class="p-4 sm:p-5 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 bg-slate-50/70 dark:bg-slate-950/40">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 min-w-12 px-2.5 items-center justify-center rounded-xl bg-indigo-600 text-white font-mono font-extrabold text-sm shadow-xs">
                                {group.prefix}
                            </div>
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-base font-extrabold text-slate-900 dark:text-slate-50">
                                        Seri SKU {group.prefix}
                                    </h3>
                                    <span class="rounded-full bg-slate-200/80 dark:bg-slate-800 px-2.5 py-0.5 text-[11px] font-semibold text-slate-700 dark:text-slate-300">
                                        {group.variant_count} Varian
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    Stok sistem non-zero: {group.non_zero_stock_count} varian ({group.warehouse_stock_qty.toLocaleString('id-ID')} pcs)
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 lg:gap-6 text-xs">
                            <div>
                                <span class="text-[10px] uppercase font-semibold text-slate-400 block">Masuk (In)</span>
                                <span class="font-bold text-sky-600 dark:text-sky-400 block">{formatRupiah(group.in_hpp)}</span>
                                <span class="text-[10px] text-slate-400">{group.in_qty.toLocaleString('id-ID')} pcs</span>
                            </div>

                            <div>
                                <span class="text-[10px] uppercase font-semibold text-slate-400 block">Keluar (Out)</span>
                                <span class="font-bold text-emerald-600 dark:text-emerald-400 block">{formatRupiah(group.out_hpp)}</span>
                                <span class="text-[10px] text-slate-400">{group.out_qty.toLocaleString('id-ID')} pcs</span>
                            </div>

                            <div>
                                <span class="text-[10px] uppercase font-semibold text-slate-400 block">Sisa Hutang</span>
                                <span class="font-black {group.sisa_debt_hpp > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'} block">
                                    {formatRupiah(group.sisa_debt_hpp)}
                                </span>
                                <span class="text-[10px] text-slate-400">{group.sisa_debt_qty.toLocaleString('id-ID')} pcs</span>
                            </div>

                            <div>
                                <span class="text-[10px] uppercase font-semibold text-slate-400 block">Stok Fisik (Gap)</span>
                                <span class="font-bold text-slate-800 dark:text-slate-100 block">{group.warehouse_stock_qty.toLocaleString('id-ID')} pcs</span>
                                <span class="text-[10px] font-semibold {group.qty_gap < 0 ? 'text-rose-600 dark:text-rose-400' : group.qty_gap > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'}">
                                    Gap: {group.qty_gap > 0 ? `+${group.qty_gap}` : group.qty_gap} pcs
                                </span>
                            </div>
                        </div>

                        <button
                            type="button"
                            onclick={() => toggleGroup(group.prefix)}
                            class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors shrink-0"
                        >
                            <span>{isExpanded ? 'Tutup' : `Lihat ${group.variant_count} Varian`}</span>
                            {#if isExpanded}
                                <ChevronUp class="h-4 w-4" />
                            {:else}
                                <ChevronDown class="h-4 w-4" />
                            {/if}
                        </button>
                    </div>

                    {#if isExpanded}
                        <div class="overflow-x-auto border-t border-slate-200 dark:border-slate-800">
                            <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                                <thead class="bg-slate-50 dark:bg-slate-950 text-[10px] uppercase font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                                    <tr>
                                        <th class="px-4 py-3">SKU &amp; Produk</th>
                                        <th class="px-4 py-3 text-right">HPP</th>
                                        <th class="px-4 py-3 text-right">Masuk (In)</th>
                                        <th class="px-4 py-3 text-right">Keluar (Out)</th>
                                        <th class="px-4 py-3 text-right">Sisa Hutang</th>
                                        <th class="px-4 py-3 text-center">Stok Sistem</th>
                                        <th class="px-4 py-3 text-center w-44">Opname Baru (Fisik)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    {#each group.variants as v}
                                        {@const parsedDraft = parseDraftQty(v.variant_id, v.warehouse_stock_qty)}
                                        {@const isInvalid = parsedDraft === null}
                                        {@const isChanged = parsedDraft !== null && parsedDraft !== v.warehouse_stock_qty}
                                        {@const draftDiff = parsedDraft !== null ? parsedDraft - v.warehouse_stock_qty : 0}

                                        <tr class="transition-colors {isChanged ? 'bg-amber-50/60 dark:bg-amber-950/20' : 'hover:bg-slate-50/70 dark:hover:bg-slate-800/30'}">
                                            <td class="px-4 py-3">
                                                <div class="font-mono font-bold text-indigo-600 dark:text-indigo-400">{v.sku}</div>
                                                <div class="font-semibold text-slate-800 dark:text-slate-100 mt-0.5">{v.product_name}</div>
                                                <div class="text-[11px] text-slate-400 mt-0.5">
                                                    {v.size} / {v.color} • Konveksi: {v.konveksi_name}
                                                </div>
                                            </td>

                                            <td class="px-4 py-3 text-right font-medium text-slate-700 dark:text-slate-200 whitespace-nowrap">
                                                {formatRupiah(v.hpp_price)}
                                            </td>

                                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                                <div class="font-bold text-sky-600 dark:text-sky-400">{v.in_qty.toLocaleString('id-ID')} pcs</div>
                                                <div class="text-[11px] text-slate-400">{formatRupiah(v.in_hpp)}</div>
                                            </td>

                                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                                <div class="font-bold text-emerald-600 dark:text-emerald-400">
                                                    {v.out_qty.toLocaleString('id-ID')} pcs
                                                </div>
                                                <div class="text-[11px] text-slate-400">{formatRupiah(v.out_hpp)}</div>
                                                {#if v.bundle_out_qty > 0}
                                                    <span class="mt-0.5 inline-block rounded bg-indigo-50 dark:bg-indigo-950/60 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-600 dark:text-indigo-300">
                                                        +{v.bundle_out_qty} via bundle
                                                    </span>
                                                {/if}
                                            </td>

                                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                                <div class="font-black {v.sisa_debt_hpp > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'}">
                                                    {formatRupiah(v.sisa_debt_hpp)}
                                                </div>
                                                <div class="text-[11px] font-semibold text-slate-500">
                                                    {v.sisa_debt_qty.toLocaleString('id-ID')} pcs
                                                </div>
                                            </td>

                                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                                <div class="font-extrabold text-slate-800 dark:text-slate-100">
                                                    {v.warehouse_stock_qty.toLocaleString('id-ID')} pcs
                                                </div>
                                                <div class="mt-0.5">
                                                    <span class="inline-block rounded-full px-2 py-0.5 text-[10px] font-semibold {v.qty_gap < 0 ? 'bg-rose-50 text-rose-600 dark:bg-rose-950/50 dark:text-rose-400' : v.qty_gap > 0 ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300' : 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400'}">
                                                        vs hutang: {v.qty_gap > 0 ? `+${v.qty_gap}` : v.qty_gap}
                                                    </span>
                                                </div>
                                            </td>

                                            <td class="px-4 py-3">
                                                <div class="flex items-center justify-center gap-2">
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        step="1"
                                                        aria-label={`Stok opname baru ${v.sku}`}
                                                        value={opnameInputs[v.variant_id] ?? String(v.warehouse_stock_qty)}
                                                        oninput={(e) => setVariantInput(v.variant_id, (e.currentTarget as HTMLInputElement).value)}
                                                        class="w-24 rounded-xl border px-2.5 py-1.5 text-center text-xs font-bold focus:outline-none focus:ring-2
                                                        {isInvalid
                                                            ? 'border-rose-500 bg-rose-50 text-rose-700 focus:ring-rose-500'
                                                            : isChanged
                                                              ? 'border-amber-400 bg-white dark:bg-slate-900 text-amber-700 dark:text-amber-300 focus:ring-amber-500'
                                                              : 'border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 focus:ring-indigo-500'}"
                                                    />
                                                </div>
                                                {#if isChanged}
                                                    <div class="mt-1 text-center text-[10px] font-bold font-mono {draftDiff > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'}">
                                                        {draftDiff > 0 ? `+${draftDiff}` : draftDiff} pcs
                                                    </div>
                                                {:else if isInvalid}
                                                    <div class="mt-1 text-center text-[10px] font-semibold text-rose-600">
                                                        Harus &ge; 0
                                                    </div>
                                                {/if}
                                            </td>
                                        </tr>
                                    {/each}
                                </tbody>
                                <tfoot class="bg-slate-50 dark:bg-slate-950 font-bold border-t border-slate-200 dark:border-slate-800 text-[11px]">
                                    <tr>
                                        <td colspan="2" class="px-4 py-3 text-right uppercase text-slate-500">
                                            Subtotal {group.prefix}:
                                        </td>
                                        <td class="px-4 py-3 text-right text-sky-600 dark:text-sky-400">
                                            <div>{group.in_qty.toLocaleString('id-ID')} pcs</div>
                                            <div>{formatRupiah(group.in_hpp)}</div>
                                        </td>
                                        <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400">
                                            <div>{group.out_qty.toLocaleString('id-ID')} pcs</div>
                                            <div>{formatRupiah(group.out_hpp)}</div>
                                        </td>
                                        <td class="px-4 py-3 text-right {group.sisa_debt_hpp > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'}">
                                            <div>{formatRupiah(group.sisa_debt_hpp)}</div>
                                            <div>{group.sisa_debt_qty.toLocaleString('id-ID')} pcs</div>
                                        </td>
                                        <td class="px-4 py-3 text-center text-slate-800 dark:text-slate-100">
                                            {group.warehouse_stock_qty.toLocaleString('id-ID')} pcs
                                        </td>
                                        <td class="px-4 py-3 text-center text-slate-500">
                                            {group.variant_count} SKU
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    {/if}
                </div>
            {:else}
                <div class="rounded-2xl bg-white dark:bg-slate-900 p-10 text-center border border-slate-100 dark:border-slate-800 text-sm text-slate-500">
                    Tidak ada varian produk non-bundle ditemukan untuk filter yang dipilih.
                </div>
            {/each}
        </div>

        <!-- Recent Opname History Card -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-xs border border-slate-100 dark:border-slate-800 overflow-hidden mb-24">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Riwayat Audit Stock Opname Mitra Terakhir</h3>
                    <p class="text-xs text-slate-400">Catatan resmi perubahan stok fisik di tabel Stock Opname &amp; Stock Logs</p>
                </div>
                <Link
                    href="/superadmin/warehouse/stock-opname"
                    class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline"
                >
                    Lihat Semua Opname &rarr;
                </Link>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 text-[10px] uppercase font-semibold text-slate-500 border-b border-slate-100 dark:border-slate-800">
                        <tr>
                            <th class="px-6 py-3">ID Opname</th>
                            <th class="px-6 py-3">Tanggal</th>
                            <th class="px-6 py-3">Gudang</th>
                            <th class="px-6 py-3">Auditor</th>
                            <th class="px-6 py-3">Jumlah Varian</th>
                            <th class="px-6 py-3">Catatan</th>
                            <th class="px-6 py-3 text-right">PDF</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        {#each report.recent_opnames as opn}
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                <td class="px-6 py-3 font-mono font-bold text-slate-800 dark:text-slate-100">#OPN-{opn.id}</td>
                                <td class="px-6 py-3">{opn.date}</td>
                                <td class="px-6 py-3 font-medium">{opn.warehouse_name}</td>
                                <td class="px-6 py-3">{opn.user_name}</td>
                                <td class="px-6 py-3 font-semibold">{opn.items_count} varian</td>
                                <td class="px-6 py-3">{opn.note}</td>
                                <td class="px-6 py-3 text-right">
                                    <a
                                        href={`/superadmin/warehouse/stock-opname/${opn.id}/pdf`}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/50 font-semibold"
                                    >
                                        <FileText class="h-3.5 w-3.5" />
                                        PDF
                                    </a>
                                </td>
                            </tr>
                        {:else}
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-slate-400">
                                    Belum ada riwayat stock opname untuk mitra ini.
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </div>
    {/if}

    <!-- Sticky Save Bar -->
    {#if selectedSeller && report && changedItems.length > 0}
        <div class="fixed bottom-4 left-4 right-4 lg:left-72 z-30 rounded-2xl border border-amber-300 dark:border-amber-700 bg-slate-900/95 dark:bg-slate-800/95 text-white px-5 py-3.5 shadow-2xl backdrop-blur-md flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-500/20 text-amber-400 shrink-0">
                    <Save class="h-5 w-5" />
                </div>
                <div class="text-xs sm:text-sm">
                    <p class="font-bold">
                        {changedItems.length} varian diubah untuk Stock Opname ({selectedWarehouse?.name})
                    </p>
                    <p class="text-xs text-slate-300">
                        Total pcs berubah: {changedBeforeTotal.toLocaleString('id-ID')} &rarr; {changedAfterTotal.toLocaleString('id-ID')} pcs
                        (Diff: {changedAfterTotal - changedBeforeTotal > 0 ? `+${changedAfterTotal - changedBeforeTotal}` : changedAfterTotal - changedBeforeTotal})
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2.5">
                <button
                    type="button"
                    onclick={resetDraftToSystem}
                    class="rounded-xl border border-slate-600 px-3.5 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-800 transition-colors"
                >
                    Batal
                </button>
                <button
                    type="button"
                    onclick={openSaveOpnameModal}
                    disabled={invalidInputCount > 0}
                    class="inline-flex items-center gap-2 rounded-xl bg-amber-500 px-4 py-2 text-xs font-bold text-slate-950 hover:bg-amber-400 disabled:opacity-40 transition-colors"
                >
                    <Check class="h-4 w-4" />
                    Simpan Hasil Opname
                </button>
            </div>
        </div>
    {/if}

    <!-- Modal: Reset Nol Semua Stok Mitra -->
    <Modal show={showResetModal} title="Nol-kan Stok Fisik Mitra (Pra-Opname)" maxWidth="lg" onclose={() => (showResetModal = false)}>
        <div class="space-y-4 text-sm">
            <div class="flex items-start gap-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 p-3.5 text-xs text-rose-800 dark:text-rose-200">
                <AlertTriangle class="h-5 w-5 shrink-0 text-rose-600 dark:text-rose-400 mt-0.5" />
                <div class="space-y-1">
                    <p class="font-bold">Tindakan ini akan mengatur stok fisik (stocks.qty) menjadi 0.</p>
                    <p>
                        Perubahan dicatat resmi sebagai dokumen <strong>Stock Opname</strong> &amp; <strong>Stock Log</strong>.
                        Histori hutang modal lama (<code>stock_in_items</code> &amp; <code>stock_out_items</code>) <strong>TIDAK berubah</strong>.
                    </p>
                </div>
            </div>

            {#if prefixFilter && activePrefixObj}
                <div class="space-y-2">
                    <span class="block text-xs font-semibold uppercase text-slate-500">Cakupan Reset Nol</span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs">
                        <button
                            type="button"
                            onclick={() => (resetScope = 'all')}
                            class="rounded-xl border p-3 text-left transition-all {resetScope === 'all' ? 'border-rose-500 bg-rose-50/70 dark:bg-rose-950/40 text-rose-900 dark:text-rose-100' : 'border-slate-200 dark:border-slate-700'}"
                        >
                            <div class="font-bold">Semua SKU Mitra di Gudang Ini</div>
                            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                {report?.summary.overall_non_zero_stock_count ?? 0} varian non-zero ({report?.summary.overall_warehouse_stock_qty ?? 0} pcs)
                            </div>
                        </button>
                        <button
                            type="button"
                            onclick={() => (resetScope = 'prefix')}
                            class="rounded-xl border p-3 text-left transition-all {resetScope === 'prefix' ? 'border-rose-500 bg-rose-50/70 dark:bg-rose-950/40 text-rose-900 dark:text-rose-100' : 'border-slate-200 dark:border-slate-700'}"
                        >
                            <div class="font-bold">Hanya Prefix {prefixFilter}</div>
                            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                {activePrefixObj.non_zero_stock_count} varian non-zero ({activePrefixObj.warehouse_stock_qty} pcs)
                            </div>
                        </button>
                    </div>
                </div>
            {:else}
                <div class="rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 p-3 text-xs">
                    <span class="text-slate-500">Target:</span>
                    <strong class="text-slate-800 dark:text-slate-100 ml-1">
                        {selectedSeller?.seller_name} — {selectedWarehouse?.name}
                    </strong>
                    <div class="mt-1 text-slate-600 dark:text-slate-300">
                        Total akan di-nol-kan: <strong>{report?.summary.overall_non_zero_stock_count ?? 0} varian non-zero</strong>
                        ({(report?.summary.overall_warehouse_stock_qty ?? 0).toLocaleString('id-ID')} pcs)
                    </div>
                </div>
            {/if}

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="reset_date" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Tanggal Audit</label>
                    <input
                        id="reset_date"
                        type="date"
                        bind:value={resetDate}
                        required
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs"
                    />
                </div>
                <div>
                    <label for="reset_confirm" class="block text-xs font-semibold uppercase text-slate-500 mb-1">
                        Ketik <span class="font-mono text-rose-600">NOL</span> untuk konfirmasi
                    </label>
                    <input
                        id="reset_confirm"
                        type="text"
                        bind:value={resetConfirmText}
                        placeholder="NOL"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs font-mono font-bold uppercase"
                    />
                </div>
            </div>

            <div>
                <label for="reset_note" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Catatan Audit</label>
                <input
                    id="reset_note"
                    type="text"
                    bind:value={resetNote}
                    maxlength="255"
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs"
                />
            </div>

            <div class="flex justify-end gap-2.5 pt-2">
                <button
                    type="button"
                    onclick={() => (showResetModal = false)}
                    class="rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800"
                >
                    Batal
                </button>
                <button
                    type="button"
                    onclick={submitResetZero}
                    disabled={resetSubmitting || resetConfirmText.trim().toUpperCase() !== 'NOL'}
                    class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white hover:bg-rose-700 disabled:opacity-40 transition-colors"
                >
                    {resetSubmitting ? 'Memproses...' : 'Ya, Nol-kan Stok Sekarang'}
                </button>
            </div>
        </div>
    </Modal>

    <!-- Modal: Simpan Hasil Stock Opname -->
    <Modal show={showSaveOpnameModal} title="Simpan Hasil Stock Opname Baru" maxWidth="lg" onclose={() => (showSaveOpnameModal = false)}>
        <div class="space-y-4 text-sm">
            <div class="rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900 p-3.5 text-xs text-amber-900 dark:text-amber-200 space-y-1">
                <p class="font-bold">
                    {changedItems.length} varian akan diperbarui di {selectedWarehouse?.name}.
                </p>
                <p>
                    Perubahan stok dicatat resmi di tabel <code>stock_opnames</code> &amp; <code>stock_logs</code> tanpa memengaruhi histori Stock In / Hutang Mitra lama.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="save_opname_date" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Tanggal Opname</label>
                    <input
                        id="save_opname_date"
                        type="date"
                        bind:value={saveOpnameDate}
                        required
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs"
                    />
                </div>
                <div>
                    <label for="save_opname_note" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Catatan Audit</label>
                    <input
                        id="save_opname_note"
                        type="text"
                        bind:value={saveOpnameNote}
                        maxlength="255"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs"
                    />
                </div>
            </div>

            <div class="max-h-56 overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-800">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-950 text-[10px] uppercase text-slate-500 sticky top-0">
                        <tr>
                            <th class="px-3 py-2">SKU</th>
                            <th class="px-3 py-2 text-center">Sistem</th>
                            <th class="px-3 py-2 text-center">Opname Baru</th>
                            <th class="px-3 py-2 text-center">Diff</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        {#each changedItems as it}
                            <tr>
                                <td class="px-3 py-2 font-mono font-semibold">{it.sku}</td>
                                <td class="px-3 py-2 text-center">{it.system_qty}</td>
                                <td class="px-3 py-2 text-center font-bold text-amber-600">{it.physical_qty}</td>
                                <td class="px-3 py-2 text-center font-mono font-bold {it.diff > 0 ? 'text-emerald-600' : 'text-rose-600'}">
                                    {it.diff > 0 ? `+${it.diff}` : it.diff}
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end gap-2.5 pt-2">
                <button
                    type="button"
                    onclick={() => (showSaveOpnameModal = false)}
                    class="rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800"
                >
                    Batal
                </button>
                <button
                    type="button"
                    onclick={submitSaveOpname}
                    disabled={saveOpnameSubmitting}
                    class="rounded-xl bg-amber-600 px-4 py-2 text-xs font-bold text-white hover:bg-amber-700 disabled:opacity-40 transition-colors"
                >
                    {saveOpnameSubmitting ? 'Menyimpan...' : 'Simpan Audit Opname'}
                </button>
            </div>
        </div>
    </Modal>

    <!-- Modal: Import CSV Hasil Opname -->
    <Modal show={showCsvModal} title="Import CSV Hasil Stock Opname" maxWidth="xl" onclose={() => (showCsvModal = false)}>
        <div class="space-y-4 text-sm">
            <div class="rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 p-3.5 text-xs text-slate-600 dark:text-slate-300 space-y-2">
                <p>
                    Upload file CSV berisi kolom <code>sku</code> dan <code>stok_fisik_opname</code> (mendukung delimiter <code>,</code> atau <code>;</code> dari Excel).
                    Baris yang kolom qty-nya dikosongkan akan dilewati; angka <code>0</code> akan diterapkan.
                </p>
                <a
                    href={templateCsvUrl}
                    class="inline-flex items-center gap-1.5 font-bold text-indigo-600 dark:text-indigo-400 hover:underline"
                >
                    <Download class="h-3.5 w-3.5" />
                    Download Template CSV untuk {selectedSeller?.seller_name}
                </a>
            </div>

            <div>
                <label for="csv_opname_file" class="block text-xs font-semibold uppercase text-slate-500 mb-1.5">Pilih File CSV</label>
                <input
                    id="csv_opname_file"
                    type="file"
                    accept=".csv,text/csv"
                    onchange={handleCsvFileSelected}
                    class="block w-full text-xs text-slate-600 dark:text-slate-300 file:mr-3 file:rounded-xl file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-xs file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100"
                />
                {#if csvFileName}
                    <p class="mt-1 text-[11px] text-slate-400">File terpilih: {csvFileName}</p>
                {/if}
            </div>

            {#if csvErrors.length > 0}
                <div class="rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 p-3 text-xs text-rose-700 dark:text-rose-300 space-y-1">
                    <p class="font-bold">Terdapat error parsing CSV:</p>
                    <ul class="list-disc pl-4 space-y-0.5 max-h-28 overflow-y-auto">
                        {#each csvErrors as err}
                            <li>{err}</li>
                        {/each}
                    </ul>
                </div>
            {/if}

            {#if csvUnmatchedSkus.length > 0}
                <div class="rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900 p-3 text-xs text-amber-800 dark:text-amber-200">
                    <p class="font-bold">{csvUnmatchedSkus.length} SKU tidak ditemukan di daftar varian mitra ini (dilewati):</p>
                    <p class="font-mono text-[11px] mt-1 break-all">{csvUnmatchedSkus.slice(0, 20).join(', ')}{csvUnmatchedSkus.length > 20 ? ' ...' : ''}</p>
                </div>
            {/if}

            {#if csvMatched.length > 0}
                <div class="rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900 p-3 text-xs text-emerald-800 dark:text-emerald-200">
                    <strong>{csvMatched.length} SKU cocok</strong> dan siap dimasukkan ke kolom input tabel untuk dicek sebelum disimpan.
                </div>

                <div class="max-h-48 overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-800">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-950 text-[10px] uppercase text-slate-500 sticky top-0">
                            <tr>
                                <th class="px-3 py-2">SKU</th>
                                <th class="px-3 py-2">Produk</th>
                                <th class="px-3 py-2 text-center">Sistem</th>
                                <th class="px-3 py-2 text-center">CSV Baru</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            {#each csvMatched as m}
                                <tr>
                                    <td class="px-3 py-1.5 font-mono font-semibold">{m.sku}</td>
                                    <td class="px-3 py-1.5">{m.product_name}</td>
                                    <td class="px-3 py-1.5 text-center">{m.old_qty}</td>
                                    <td class="px-3 py-1.5 text-center font-bold text-indigo-600">{m.new_qty}</td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            {/if}

            <div class="flex justify-end gap-2.5 pt-2">
                <button
                    type="button"
                    onclick={() => (showCsvModal = false)}
                    class="rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800"
                >
                    Tutup
                </button>
                <button
                    type="button"
                    onclick={applyCsvToDraft}
                    disabled={csvMatched.length === 0}
                    class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 disabled:opacity-40 transition-colors"
                >
                    Terapkan ke Tabel ({csvMatched.length})
                </button>
            </div>
        </div>
    </Modal>
</AdminLayout>
