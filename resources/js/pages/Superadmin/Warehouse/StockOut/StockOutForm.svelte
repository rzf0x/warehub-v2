<script lang="ts">
    import AdminLayout from "@/layouts/AdminLayout.svelte";
    import { router, Link } from "@inertiajs/svelte";
    import {
        ArrowLeft,
        Plus,
        Trash2,
        Save,
        Upload,
        Sparkles,
        AlertTriangle,
        CheckCircle2,
        Package,
        Layers,
        DollarSign,
        Wallet,
        FileText,
        Store,
        RefreshCw,
        Filter,
        Search,
        X,
        Check,
        ChevronDown,
        ChevronUp,
        Tag,
        Grid,
        Edit2,
    } from "@lucide/svelte";

    interface VariantData {
        id: number;
        product_id: number;
        product_name: string;
        seller_id: number;
        seller_name: string;
        sku: string;
        size: string;
        color: string;
        price: number;
        selling_price: number;
        stocks: Record<number, number>; // warehouse_id -> qty
    }

    interface FormItem {
        id?: number;
        product_variant_id: number | null;
        konveksi_id: number | null;
        seller_id: number | null;
        seller_name?: string;
        sku?: string;
        product_name?: string;
        size?: string;
        color?: string;
        qty: number;
        price: number;
        selling_price: number;
        available_stock?: number;
    }

    interface ResiItem {
        order_id: string;
        no_resi: string;
        payment?: number;
    }

    interface MissingSkuItem {
        sku: string;
        product_name: string;
        variation: string;
    }

    let {
        warehouses = [],
        stores = [],
        periods = [],
        sellers = [],
        konveksis = [],
        categories = [],
        brands = [],
        templates = [],
        variants = [],
        activePeriod = null,
        stockOut = null,
        isEdit = false,
    }: {
        warehouses?: Array<{ id: number; name: string }>;
        stores?: Array<{
            id: number;
            seller_id: number;
            store_name: string;
            marketplace: string;
            seller?: { seller_name: string };
        }>;
        periods?: Array<{ id: number; name: string; is_active: boolean }>;
        sellers?: Array<{ id: number; seller_name: string }>;
        konveksis?: Array<{ id: number; name: string }>;
        categories?: Array<{ id: number; name: string }>;
        brands?: Array<{ id: number; name: string }>;
        templates?: Array<{ id: number; template_name: string; items: any[] }>;
        variants?: VariantData[];
        activePeriod?: { id: number; name: string } | null;
        stockOut?: any;
        isEdit?: boolean;
    } = $props();

    // Form Header State
    let warehouseId = $state<number | string>(stockOut?.warehouse_id ?? "");
    let sellerId = $state<number | string>(stockOut?.store?.seller_id ?? "");
    let periodId = $state<number | string>(
        stockOut?.period_id ?? activePeriod?.id ?? periods[0]?.id ?? "",
    );
    let storeId = $state<number | string>(stockOut?.store_id ?? "");
    let date = $state<string>(
        stockOut?.date
            ? String(stockOut.date).split("T")[0]
            : new Date().toISOString().split("T")[0],
    );
    let note = $state<string>(stockOut?.note ?? "");
    let bruto = $state<number>(stockOut?.bruto ? Number(stockOut.bruto) : 0);

    // Form Mode: 'manual' vs 'import'
    let inputMode = $state<"manual" | "import">("manual");

    // Dynamic Items Form
    let formItems = $state<FormItem[]>([]);
    let resiList = $state<ResiItem[]>([]);
    let editingVariantIndex = $state<number | null>(null);
    let variantSearchQuery = $state<string>("");

    function consolidateFormItems(items: FormItem[]): FormItem[] {
        const map = new Map<string, FormItem>();

        for (const item of items) {
            if (!item.product_variant_id) {
                const randKey = "unselected_" + Math.random();
                map.set(randKey, { ...item });
                continue;
            }

            const sId = item.seller_id ?? 0;
            const kId = item.konveksi_id ?? 0;
            const key = `${item.product_variant_id}_${sId}_${kId}`;

            if (!map.has(key)) {
                map.set(key, {
                    ...item,
                    qty: Number(item.qty || 0),
                    price: Number(item.price || 0),
                    selling_price: Math.round(Number(item.selling_price || 0)),
                });
            } else {
                const existing = map.get(key)!;
                const existingQty = Number(existing.qty || 0);
                const itemQty = Number(item.qty || 0);
                const combinedQty = existingQty + itemQty;

                const existingSelling = Number(existing.selling_price || 0);
                const itemSelling = Number(item.selling_price || 0);

                const totalSelling =
                    existingSelling * existingQty + itemSelling * itemQty;
                const newSellingPrice =
                    combinedQty > 0
                        ? Math.round(totalSelling / combinedQty)
                        : existingSelling;

                existing.qty = combinedQty;
                existing.selling_price = newSellingPrice;
            }
        }

        return Array.from(map.values());
    }

    // If Edit Mode, populate existing items
    $effect(() => {
        if (isEdit && stockOut && stockOut.items && formItems.length === 0) {
            const rawItems = stockOut.items.map((i: any) => {
                const v = variants.find((v) => v.id === i.product_variant_id);
                const wId = Number(warehouseId);
                const avail = v ? (v.stocks[wId] ?? 0) : 0;
                return {
                    id: i.id,
                    product_variant_id: i.product_variant_id,
                    konveksi_id: i.konveksi_id ?? null,
                    seller_id: v?.seller_id ?? null,
                    seller_name: v?.seller_name ?? "Mitra",
                    sku: v?.sku ?? i.product_variant?.sku ?? "",
                    product_name:
                        v?.product_name ??
                        i.product_variant?.product?.product_name ??
                        "",
                    size: v?.size ?? i.product_variant?.size ?? "",
                    color: v?.color ?? i.product_variant?.color ?? "",
                    qty: i.qty ?? 1,
                    price: Number(i.price ?? 0),
                    selling_price: Math.round(Number(i.selling_price ?? 0)),
                    available_stock: avail,
                };
            });

            formItems = consolidateFormItems(rawItems);

            if (stockOut.resi_summary) {
                try {
                    const parsed = JSON.parse(stockOut.resi_summary);
                    if (Array.isArray(parsed)) resiList = parsed;
                } catch (e) {
                    resiList = [
                        {
                            order_id: stockOut.resi_summary,
                            no_resi: stockOut.resi_summary,
                        },
                    ];
                }
            }
        }
    });

    // Auto update seller_id when store is picked
    function handleStoreChange() {
        if (!storeId) return;
        const st = stores.find((s) => s.id === Number(storeId));
        if (st && st.seller_id) {
            sellerId = st.seller_id;
        }
    }

    // Manual Items Management
    function addManualItem(paramSellerId?: number) {
        const sId = paramSellerId ?? (sellerId ? Number(sellerId) : (sellers[0]?.id ?? null));
        const sName = sellers.find((s) => s.id === sId)?.seller_name ?? "Mitra";
        formItems = [
            ...formItems,
            {
                product_variant_id: null,
                konveksi_id: null,
                seller_id: sId,
                seller_name: sName,
                qty: 1,
                price: 0,
                selling_price: 0,
                available_stock: 0,
            },
        ];
    }

    function removeManualItem(index: number) {
        formItems = formItems.filter((_, idx) => idx !== index);
    }

    function handleVariantSelect(index: number, variantId: number) {
        const v = variants.find((varItem) => varItem.id === Number(variantId));
        if (!v) return;

        const wId = Number(warehouseId);
        const avail = v.stocks[wId] ?? 0;
        const currentSelling = Number(formItems[index]?.selling_price || 0);

        formItems[index] = {
            ...formItems[index],
            product_variant_id: v.id,
            seller_id: v.seller_id,
            seller_name: v.seller_name,
            sku: v.sku,
            product_name: v.product_name,
            size: v.size,
            color: v.color,
            price: v.price,
            selling_price: currentSelling > 0 ? currentSelling : v.selling_price,
            available_stock: avail,
        };
    }

    function getVariantsBySeller(targetSellerId?: number | null) {
        if (!targetSellerId) return variants;
        const sId = Number(targetSellerId);
        return variants.filter((v) => Number(v.seller_id) === sId);
    }

    function getSearchableVariants(targetSellerId?: number | null, query?: string) {
        let list = getVariantsBySeller(targetSellerId);
        if (!query || !query.trim()) return list;

        const q = query.trim().toLowerCase();
        return list.filter((v) => {
            const skuMatch = v.sku?.toLowerCase().includes(q);
            const nameMatch = v.product_name?.toLowerCase().includes(q);
            const sizeMatch = v.size?.toLowerCase().includes(q);
            const colorMatch = v.color?.toLowerCase().includes(q);
            return skuMatch || nameMatch || sizeMatch || colorMatch;
        });
    }

    function setBulkKonveksiForSeller(sellerId: number, konveksiId: number) {
        formItems = formItems.map((item) => {
            if (item.seller_id === sellerId) {
                return { ...item, konveksi_id: konveksiId };
            }
            return item;
        });
    }

    // Pagination for Manual Items Table
    let displayLimit = $state<number>(50);
    function loadMoreItems() {
        displayLimit += 50;
    }

    // Resi List Management
    let resiSearch = $state<string>("");
    let resiPage = $state<number>(1);
    const resiPerPage = 20;

    function addResiRow() {
        resiList = [...resiList, { order_id: "", no_resi: "" }];
    }

    function removeResiRow(idx: number) {
        resiList = resiList.filter((_, i) => i !== idx);
    }

    let filteredResis = $derived(
        resiList.filter(
            (r) =>
                !resiSearch.trim() ||
                (r.order_id || "")
                    .toLowerCase()
                    .includes(resiSearch.toLowerCase()) ||
                (r.no_resi || "")
                    .toLowerCase()
                    .includes(resiSearch.toLowerCase()),
        ),
    );

    let paginatedResis = $derived(
        filteredResis.slice(
            (resiPage - 1) * resiPerPage,
            resiPage * resiPerPage,
        ),
    );

    let totalResiPages = $derived(
        Math.ceil(filteredResis.length / resiPerPage) || 1,
    );

    // Import Cleaned JSON Engine (Chunked Streaming)
    let isImporting = $state<boolean>(false);
    let importProgress = $state<number>(0);
    let importedFileName = $state<string>("");
    let missingSkusList = $state<MissingSkuItem[]>([]);
    let isQuickCreateOpen = $state<boolean>(false);

    // Quick Create Modal State
    let quickSellerId = $state<number | string>(sellers[0]?.id ?? "");
    let quickCategoryId = $state<number | string>(categories[0]?.id ?? "");
    let quickBrandId = $state<number | string>(brands[0]?.id ?? "");
    let quickKonveksiId = $state<number | string>(konveksis[0]?.id ?? "");
    let quickTemplateId = $state<number | string>(templates[0]?.id ?? "");
    let isQuickCreating = $state<boolean>(false);

    function handleJsonFileUpload(event: Event) {
        const input = event.target as HTMLInputElement;
        if (!input.files || input.files.length === 0) return;

        const file = input.files[0];
        importedFileName = file.name;

        const reader = new FileReader();
        reader.onload = async (e) => {
            try {
                const parsed = JSON.parse(e.target?.result as string);
                let rawItems: any[] = [];
                if (parsed && typeof parsed === "object") {
                    if (Array.isArray(parsed.data)) rawItems = parsed.data;
                    else if (Array.isArray(parsed.orders))
                        rawItems = parsed.orders;
                    else if (Array.isArray(parsed.items))
                        rawItems = parsed.items;
                    else if (Array.isArray(parsed)) rawItems = parsed;
                    else rawItems = [parsed];
                }

                if (!Array.isArray(rawItems) || rawItems.length === 0) {
                    alert("File JSON tidak berisi data pesanan yang valid!");
                    return;
                }

                // If JSON has top-level metrics, auto fill bruto
                if (
                    parsed.total_payment ||
                    parsed.total_master_amount ||
                    parsed.matched_master_amount
                ) {
                    bruto = Number(
                        parsed.total_payment ||
                            parsed.matched_master_amount ||
                            parsed.total_master_amount ||
                            0,
                    );
                }

                await processImportChunks(rawItems);
            } catch (err) {
                alert("Format File JSON tidak valid!");
                importedFileName = "";
            }
        };
        reader.readAsText(file);
    }

    async function processImportChunks(allItems: any[]) {
        isImporting = true;
        importProgress = 10;
        missingSkusList = [];

        const chunkSize = 500;
        const totalChunks = Math.ceil(allItems.length / chunkSize);
        const resolvedMatchedItems: FormItem[] = [];
        const accumulatedResis: ResiItem[] = [];
        const missingMap: Record<string, MissingSkuItem> = {};

        for (let chunkIdx = 0; chunkIdx < totalChunks; chunkIdx++) {
            const chunk = allItems.slice(
                chunkIdx * chunkSize,
                (chunkIdx + 1) * chunkSize,
            );

            try {
                const response = await fetch(
                    "/superadmin/warehouse/stock-out/import-chunked",
                    {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN":
                                (
                                    document.querySelector(
                                        'meta[name="csrf-token"]',
                                    ) as HTMLMetaElement
                                )?.content || "",
                        },
                        body: JSON.stringify({
                            warehouse_id: warehouseId,
                            items: chunk,
                        }),
                    },
                );

                const resData = await response.json();
                if (resData.success) {
                    if (Array.isArray(resData.matched_items)) {
                        resData.matched_items.forEach((m: any) => {
                            resolvedMatchedItems.push({
                                product_variant_id: m.product_variant_id,
                                konveksi_id: null,
                                seller_id: m.seller_id,
                                seller_name: m.seller_name,
                                sku: m.sku,
                                product_name: m.product_name,
                                size: m.size,
                                color: m.color,
                                qty: m.qty,
                                price: Number(m.price || 0),
                                selling_price: Math.round(Number(m.selling_price || 0)),
                                available_stock: m.available_stock,
                            });
                        });
                    }

                    if (Array.isArray(resData.missing_skus)) {
                        resData.missing_skus.forEach((ms: any) => {
                            missingMap[ms.sku] = ms;
                        });
                    }

                    if (Array.isArray(resData.resis)) {
                        accumulatedResis.push(...resData.resis);
                    }
                }
            } catch (err) {
                console.error("Error processing chunk:", err);
            }

            importProgress = Math.round(((chunkIdx + 1) / totalChunks) * 90);
        }

        formItems = consolidateFormItems(resolvedMatchedItems);
        resiList = accumulatedResis;
        missingSkusList = Object.values(missingMap);

        importProgress = 100;
        setTimeout(() => {
            isImporting = false;
            if (missingSkusList.length > 0) {
                isQuickCreateOpen = true;
            }
        }, 400);
    }

    async function handleQuickCreateProducts() {
        if (!quickSellerId) {
            alert("Silakan pilih Seller / Mitra terlebih dahulu!");
            return;
        }

        isQuickCreating = true;
        try {
            const response = await fetch(
                "/superadmin/warehouse/stock-out/quick-create-products",
                {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "application/json",
                        "X-CSRF-TOKEN":
                            (
                                document.querySelector(
                                    'meta[name="csrf-token"]',
                                ) as HTMLMetaElement
                            )?.content || "",
                    },
                    body: JSON.stringify({
                        seller_id: quickSellerId,
                        category_id: quickCategoryId || null,
                        brand_id: quickBrandId || null,
                        konveksi_id: quickKonveksiId || null,
                        template_id: quickTemplateId || null,
                        missing_items: missingSkusList,
                    }),
                },
            );

            const resData = await response.json();
            if (resData.success && Array.isArray(resData.created_variants)) {
                // Add newly created variants to runtime variants list
                variants = [...variants, ...resData.created_variants];

                // Re-run matching for imported items
                alert(resData.message);
                isQuickCreateOpen = false;
            } else {
                alert(resData.message || "Gagal membuat produk baru.");
            }
        } catch (err) {
            alert("Terjadi kesalahan saat membuat produk baru.");
        } finally {
            isQuickCreating = false;
        }
    }

    // Calculations & Validation Metrics
    let liveTotalQty = $derived(
        formItems.reduce((acc, i) => acc + (Number(i.qty) || 0), 0),
    );
    let liveTotalHpp = $derived(
        formItems.reduce(
            (acc, i) => acc + (Number(i.qty) || 0) * (Number(i.price) || 0),
            0,
        ),
    );
    let liveTotalSelling = $derived(
        formItems.reduce(
            (acc, i) =>
                acc + (Number(i.qty) || 0) * (Number(i.selling_price) || 0),
            0,
        ),
    );
    let liveBruto = $derived(bruto > 0 ? bruto : liveTotalSelling);
    let liveEstProfit = $derived(liveBruto - liveTotalHpp);

    // Group items by Seller for grouped Manual UI
    let groupedBySeller = $derived(() => {
        const groups: Record<
            number,
            {
                seller_id: number;
                seller_name: string;
                items: { item: FormItem; index: number }[];
            }
        > = {};
        formItems.forEach((item, index) => {
            const sId = item.seller_id ?? 0;
            const sName =
                item.seller_name ||
                sellers.find((s) => s.id === sId)?.seller_name ||
                "Mitra Umum";
            if (!groups[sId]) {
                groups[sId] = { seller_id: sId, seller_name: sName, items: [] };
            }
            groups[sId].items.push({ item, index });
        });
        return Object.values(groups);
    });

    // Form Submission
    let isSubmitting = $state(false);

    function submitForm() {
        if (!warehouseId) {
            alert("Pilih Gudang Asal terlebih dahulu!");
            return;
        }
        if (!sellerId) {
            alert("Pilih Mitra Seller terlebih dahulu!");
            return;
        }
        if (formItems.length === 0) {
            alert("Tambahkan minimal 1 item barang keluar!");
            return;
        }

        // Validate required item fields
        for (let i = 0; i < formItems.length; i++) {
            if (!formItems[i].product_variant_id) {
                alert(`Item ke-${i + 1} belum memilih Varian SKU!`);
                return;
            }
        }

        isSubmitting = true;

        const resiSummaryStr =
            resiList.length > 0
                ? JSON.stringify(resiList)
                : note ||
                  `Manual Out ${new Date().toISOString().split("T")[0]}`;

        const payload = {
            warehouse_id: warehouseId,
            period_id: periodId || null,
            store_id: storeId || null,
            date: date,
            note: note,
            bruto: liveBruto,
            resi_summary: resiSummaryStr,
            items: formItems.map((i) => ({
                product_variant_id: i.product_variant_id,
                konveksi_id: i.konveksi_id || null,
                qty: i.qty,
                price: i.price,
                selling_price: i.selling_price,
            })),
        };

        if (isEdit && stockOut) {
            router.put(
                `/superadmin/warehouse/stock-out/${stockOut.id}`,
                payload,
                {
                    onFinish: () => {
                        isSubmitting = false;
                    },
                },
            );
        } else {
            router.post("/superadmin/warehouse/stock-out", payload, {
                onFinish: () => {
                    isSubmitting = false;
                },
            });
        }
    }

    function formatRupiah(num: number) {
        return new Intl.NumberFormat("id-ID", {
            style: "currency",
            currency: "IDR",
            maximumFractionDigits: 0,
        }).format(num || 0);
    }
</script>

<AdminLayout
    title={isEdit
        ? "Edit Pengeluaran Barang (Stock Out)"
        : "Form Stock Out Complex Engine"}
    breadcrumbs={[
        { name: "Stock Out" },
        { name: isEdit ? "Edit Form" : "Input Complex Form" },
    ]}
>
    <div class="max-w-8xl mx-auto space-y-6 pb-6">
        <!-- Header Title Bar -->
        <div
            class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm"
        >
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <Link
                        href="/superadmin/warehouse/stock-out"
                        class="text-xs font-semibold text-rose-600 dark:text-rose-400 hover:underline flex items-center gap-1"
                    >
                        <ArrowLeft class="w-3.5 h-3.5" />
                        Kembali ke Index Stock Out
                    </Link>
                </div>
                <h1
                    class="text-2xl font-extrabold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2.5"
                >
                    <Package class="w-7 h-7 text-rose-500" />
                    {isEdit
                        ? "Edit Transaksi Stock Out #" + stockOut.id
                        : "Input Pengeluaran Barang (Stock Out)"}
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Input manual per-seller atau import hasil Data Cleaning JSON
                    (Shopee / TikTok) dengan auto SKU resolution.
                </p>
            </div>

            <!-- Mode Selector Switch -->
            <div
                class="flex items-center gap-2 bg-slate-100 dark:bg-slate-800 p-1.5 rounded-xl border border-slate-200 dark:border-slate-700"
            >
                <button
                    onclick={() => (inputMode = "manual")}
                    class="px-4 py-2 text-xs font-bold rounded-lg transition-all flex items-center gap-2 {inputMode ===
                    'manual'
                        ? 'bg-white dark:bg-slate-900 text-rose-600 dark:text-rose-400 shadow-sm'
                        : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'}"
                >
                    <Layers class="w-4 h-4" />
                    <span>Mode Form Manual</span>
                </button>
                <button
                    onclick={() => (inputMode = "import")}
                    class="px-4 py-2 text-xs font-bold rounded-lg transition-all flex items-center gap-2 {inputMode ===
                    'import'
                        ? 'bg-white dark:bg-slate-900 text-rose-600 dark:text-rose-400 shadow-sm'
                        : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'}"
                >
                    <Upload class="w-4 h-4" />
                    <span>Import JSON Marketplace</span>
                </button>
            </div>
        </div>

        <!-- Section 1: Form Header Meta Data -->
        <div
            class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-4"
        >
            <h3
                class="text-sm font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2 border-b border-slate-100 dark:border-slate-800 pb-3"
            >
                <Store class="w-4 h-4 text-rose-500" />
                Metadata Informasi Pengeluaran
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <!-- Gudang -->
                <div class="space-y-1.5">
                    <label
                        for="warehouse-select"
                        class="text-xs font-bold text-slate-700 dark:text-slate-300"
                        >1. Gudang Asal <span class="text-rose-500">*</span></label
                    >
                    <select
                        id="warehouse-select"
                        bind:value={warehouseId}
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-xs font-semibold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-rose-500"
                    >
                        <option value="">-- Pilih Gudang --</option>
                        {#each warehouses as w}
                            <option value={w.id}>{w.name}</option>
                        {/each}
                    </select>
                </div>

                <!-- Mitra Seller -->
                <div class="space-y-1.5">
                    <label
                        for="seller-select"
                        class="text-xs font-bold text-slate-700 dark:text-slate-300"
                        >2. Mitra Seller <span class="text-rose-500">*</span></label
                    >
                    <select
                        id="seller-select"
                        bind:value={sellerId}
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-xs font-semibold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-rose-500"
                    >
                        <option value="">-- Pilih Mitra Seller --</option>
                        {#each sellers as s}
                            <option value={s.id}>{s.seller_name}</option>
                        {/each}
                    </select>
                </div>

                <!-- Toko Marketplace -->
                <div class="space-y-1.5">
                    <label
                        for="store-select"
                        class="text-xs font-bold text-slate-700 dark:text-slate-300"
                        >Toko Marketplace (Opsional)</label
                    >
                    <select
                        id="store-select"
                        bind:value={storeId}
                        onchange={handleStoreChange}
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-rose-500"
                    >
                        <option value="">Semua / Toko Umum</option>
                        {#each stores as st}
                            <option value={st.id}
                                >{st.store_name} ({st.marketplace})</option
                            >
                        {/each}
                    </select>
                </div>

                <!-- Periode Pembukuan -->
                <div class="space-y-1.5">
                    <label
                        for="period-select"
                        class="text-xs font-bold text-slate-700 dark:text-slate-300"
                        >Periode Pembukuan</label
                    >
                    <select
                        id="period-select"
                        bind:value={periodId}
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-rose-500"
                    >
                        <option value="">Pilih Periode</option>
                        {#each periods as p}
                            <option value={p.id}
                                >{p.name} {p.is_active ? "(Aktif)" : ""}</option
                            >
                        {/each}
                    </select>
                </div>

                <!-- Tanggal -->
                <div class="space-y-1.5">
                    <label
                        for="stock-out-date"
                        class="text-xs font-bold text-slate-700 dark:text-slate-300"
                        >Tanggal Pengeluaran <span class="text-rose-500">*</span></label
                    >
                    <input
                        id="stock-out-date"
                        type="date"
                        bind:value={date}
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-rose-500"
                    />
                </div>
            </div>

            {#if !warehouseId || !sellerId}
                <div class="p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-xl flex items-center gap-3">
                    <AlertTriangle class="w-4 h-4 text-amber-600 shrink-0" />
                    <p class="text-xs font-semibold text-amber-800 dark:text-amber-300">
                        Harap tentukan <strong>1. Gudang Asal</strong> dan <strong>2. Mitra Seller</strong> terlebih dahulu di atas sebelum menambah / meng-import item barang keluar.
                    </p>
                </div>
            {/if}

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <!-- Total Bruto -->
                <div class="space-y-1.5">
                    <label
                        for="bruto-input"
                        class="text-xs font-bold text-slate-700 dark:text-slate-300"
                        >Total Bruto Penjualan (Rp)</label
                    >
                    <input
                        id="bruto-input"
                        type="number"
                        bind:value={bruto}
                        placeholder="Otomatis terisi dari item / import"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-rose-500 font-semibold"
                    />
                </div>

                <!-- Catatan -->
                <div class="space-y-1.5">
                    <label
                        for="note-input"
                        class="text-xs font-bold text-slate-700 dark:text-slate-300"
                        >Catatan Transaksi</label
                    >
                    <input
                        id="note-input"
                        type="text"
                        bind:value={note}
                        placeholder="Keterangan transaksi pengeluaran barang..."
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-rose-500"
                    />
                </div>
            </div>
        </div>

        <!-- Section 2: Import Engine Dropzone (If Import Mode) -->
        {#if inputMode === "import"}
            <div
                class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-4"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3"
                >
                    <h3
                        class="text-sm font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2"
                    >
                        <Upload class="w-4 h-4 text-cyan-500" />
                        Import Cleaned Marketplace JSON (Shopee / TikTok Engine Output)
                    </h3>
                    {#if importedFileName}
                        <span
                            class="px-2.5 py-0.5 text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 rounded-full flex items-center gap-1"
                        >
                            <Check class="w-3.5 h-3.5" /> File Loaded ({formItems.length}
                            items matched)
                        </span>
                    {/if}
                </div>

                <div
                    class="relative border-2 border-dashed border-slate-300 dark:border-slate-700 hover:border-cyan-500 dark:hover:border-cyan-500 rounded-2xl p-8 text-center transition-all bg-slate-50/50 dark:bg-slate-950/40"
                >
                    <input
                        type="file"
                        accept=".json"
                        onchange={handleJsonFileUpload}
                        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                    />
                    <div class="space-y-3 pointer-events-none">
                        <div
                            class="w-12 h-12 rounded-2xl bg-cyan-50 dark:bg-cyan-950/50 text-cyan-500 mx-auto flex items-center justify-center"
                        >
                            <Upload class="w-6 h-6" />
                        </div>
                        <div>
                            {#if importedFileName}
                                <span
                                    class="font-bold text-sm text-cyan-600 dark:text-cyan-400"
                                    >{importedFileName}</span
                                >
                            {:else}
                                <p
                                    class="text-xs font-bold text-slate-700 dark:text-slate-300"
                                >
                                    Drop File JSON Data Cleaning di sini, atau <span
                                        class="text-cyan-600 dark:text-cyan-400 underline"
                                        >Browse File</span
                                    >
                                </p>
                                <p class="text-[11px] text-slate-400 mt-1">
                                    File hasil export dari menu Cleaning Data
                                    Shopee / TikTok Shop Warehub
                                </p>
                            {/if}
                        </div>
                    </div>
                </div>

                {#if isImporting}
                    <div class="space-y-1.5 pt-2">
                        <div
                            class="flex justify-between text-xs font-semibold text-slate-600 dark:text-slate-400"
                        >
                            <span>Proses Chunked Import Streaming...</span>
                            <span>{importProgress}%</span>
                        </div>
                        <div
                            class="w-full bg-slate-100 dark:bg-slate-800 h-2.5 rounded-full overflow-hidden"
                        >
                            <div
                                class="bg-gradient-to-r from-cyan-500 to-rose-500 h-full transition-all duration-300 rounded-full"
                                style="width: {importProgress}%"
                            ></div>
                        </div>
                    </div>
                {/if}

                {#if missingSkusList.length > 0}
                    <div
                        class="p-4 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-xl flex items-center justify-between gap-4"
                    >
                        <div class="flex items-center gap-3">
                            <AlertTriangle
                                class="w-5 h-5 text-amber-600 shrink-0"
                            />
                            <div>
                                <h4
                                    class="text-xs font-bold text-amber-900 dark:text-amber-300"
                                >
                                    Ditemukan {missingSkusList.length} SKU yang Belum
                                    Terdaftar di Database Gudang
                                </h4>
                                <p
                                    class="text-[11px] text-amber-700 dark:text-amber-400"
                                >
                                    Gunakan fitur Auto-Generate untuk membuat
                                    produk & varian secara massal instan.
                                </p>
                            </div>
                        </div>
                        <button
                            onclick={() => (isQuickCreateOpen = true)}
                            class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-xl shadow-sm flex items-center gap-1.5 transition-all shrink-0"
                        >
                            <Sparkles class="w-3.5 h-3.5" />
                            <span
                                >Auto-Generate Missing Products ({missingSkusList.length})</span
                            >
                        </button>
                    </div>
                {/if}
            </div>
        {/if}

        <!-- Section 3: Live Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <div
                class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1"
            >
                <span
                    class="text-xs font-bold uppercase tracking-wider text-slate-400"
                    >Total Qty Out</span
                >
                <div
                    class="text-xl font-extrabold text-slate-800 dark:text-slate-100"
                >
                    {liveTotalQty.toLocaleString("id-ID")}
                    <span class="text-xs text-slate-400 font-semibold">pcs</span>
                </div>
                <p class="text-[10px] text-slate-400">Total unit barang keluar</p>
            </div>
            <div
                class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1"
            >
                <span
                    class="text-xs font-bold uppercase tracking-wider text-slate-400"
                    >Total HPP (Modal)</span
                >
                <div
                    class="text-xl font-extrabold text-slate-800 dark:text-slate-100 truncate"
                >
                    {formatRupiah(liveTotalHpp)}
                </div>
                <p class="text-[10px] text-slate-400">Σ (HPP Modal × Qty)</p>
            </div>
            <div
                class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1"
            >
                <span
                    class="text-xs font-bold uppercase tracking-wider text-slate-400"
                    >Total Harga Jual</span
                >
                <div
                    class="text-xl font-extrabold text-cyan-600 dark:text-cyan-400 truncate"
                >
                    {formatRupiah(liveTotalSelling)}
                </div>
                <p class="text-[10px] text-slate-400">Σ (Harga Jual × Qty)</p>
            </div>
            <div
                class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1"
            >
                <span
                    class="text-xs font-bold uppercase tracking-wider text-slate-400"
                    >Total Bruto</span
                >
                <div
                    class="text-xl font-extrabold text-emerald-600 dark:text-emerald-400 truncate"
                >
                    {formatRupiah(liveBruto)}
                </div>
                <p class="text-[10px] text-slate-400">Omset Penjualan Kotor</p>
            </div>
            <div
                class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1"
            >
                <span
                    class="text-xs font-bold uppercase tracking-wider text-violet-500"
                    >Estimasi Profit</span
                >
                <div
                    class="text-xl font-extrabold text-violet-600 dark:text-violet-400 truncate"
                >
                    {formatRupiah(liveEstProfit)}
                </div>
                <p class="text-[10px] font-semibold text-violet-500 dark:text-violet-400 truncate" title="Bruto ({formatRupiah(liveBruto)}) - HPP ({formatRupiah(liveTotalHpp)})">
                    Bruto - Total HPP Modal
                </p>
            </div>
        </div>

        <!-- Section 4: Items Form Table (Grouped Per Seller) -->
        <div
            class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden space-y-6 p-6"
        >
            <div
                class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4"
            >
                <div>
                    <h3
                        class="text-sm font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2"
                    >
                        <Layers class="w-4 h-4 text-rose-500" />
                        Rincian Item Barang Keluar ({formItems.length} Baris)
                    </h3>
                    <p class="text-xs text-slate-400">
                        Dikelompokkan per-Mitra Seller dengan penentuan Vendor
                        Konveksi & Harga Modal/Jual.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    {#if formItems.length > 0}
                        <button
                            type="button"
                            onclick={() => (formItems = consolidateFormItems(formItems))}
                            class="px-3 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-xl shadow-sm flex items-center gap-1.5 transition-all"
                            title="Ringkaskan item varian yang sama menjadi 1 baris"
                        >
                            <Layers class="w-3.5 h-3.5 text-slate-500" />
                            <span>Ringkaskan Varian Sama</span>
                        </button>
                    {/if}

                    <button
                        onclick={() => addManualItem()}
                        class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl shadow-sm flex items-center gap-1.5 transition-all"
                    >
                        <Plus class="w-4 h-4" />
                        <span>Tambah Baris Item</span>
                    </button>
                </div>
            </div>

            <!-- Loop Grouped Sellers -->
            {#each groupedBySeller() as group}
                <div
                    class="border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden bg-slate-50/50 dark:bg-slate-950/30"
                >
                    <!-- Seller Header Bar with Bulk Konveksi Setter -->
                    <div
                        class="p-4 bg-slate-100 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                    >
                        <div class="flex items-center gap-2">
                            <span
                                class="px-3 py-1 bg-slate-800 text-white dark:bg-white dark:text-slate-900 text-xs font-extrabold rounded-lg"
                                >{group.seller_name}</span
                            >
                            <span
                                class="text-xs text-slate-500 dark:text-slate-400"
                                >({group.items.length} Item)</span
                            >
                        </div>

                        <!-- Bulk Konveksi Tool -->
                        <div class="flex items-center gap-2">
                            <span
                                class="text-xs font-semibold text-slate-600 dark:text-slate-400"
                                >Set Bulk Konveksi:</span
                            >
                            <select
                                onchange={(e) => {
                                    const val = Number(
                                        (e.target as HTMLSelectElement).value,
                                    );
                                    if (val)
                                        setBulkKonveksiForSeller(
                                            group.seller_id,
                                            val,
                                        );
                                }}
                                class="rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-1.5 text-xs text-slate-800 dark:text-slate-100"
                            >
                                <option value=""
                                    >-- Pilih Vendor Konveksi --</option
                                >
                                {#each konveksis as k}
                                    <option value={k.id}>{k.name}</option>
                                {/each}
                            </select>
                        </div>
                    </div>

                    <!-- Items Table for this Seller -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead
                                class="bg-slate-100/50 dark:bg-slate-900/50 text-slate-500 font-semibold border-b border-slate-200 dark:border-slate-800"
                            >
                                <tr>
                                    <th class="p-3 w-10">No</th>
                                    <th class="p-3 min-w-[200px]"
                                        >Pilih Produk Varian SKU</th
                                    >
                                    <th class="p-3 w-36">Vendor Konveksi</th>
                                    <th class="p-3 text-center w-24"
                                        >Stok Gudang</th
                                    >
                                    <th class="p-3 text-center w-20">Qty Out</th>
                                    <th class="p-3 text-right w-28"
                                        >HPP Modal (Rp)</th
                                    >
                                    <th class="p-3 text-right w-32"
                                        >Subtotal HPP</th
                                    >
                                    <th class="p-3 text-right w-28"
                                        >Harga Jual (Rp)</th
                                    >
                                    <th class="p-3 text-right w-32"
                                        >Subtotal Jual</th
                                    >
                                    <th class="p-3 text-right w-32"
                                        >Est. Profit</th
                                    >
                                    <th class="p-3 text-center w-12">Aksi</th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-200 dark:divide-slate-800"
                            >
                                {#each group.items.slice(0, displayLimit) as { item, index }}
                                    <tr
                                        class="hover:bg-white dark:hover:bg-slate-900 transition-colors"
                                    >
                                        <td class="p-3 text-slate-400"
                                            >{index + 1}</td>
                                        <td class="p-3">
                                            {#if editingVariantIndex === index || !item.product_variant_id}
                                                <div class="relative z-20 space-y-1.5 min-w-[280px]">
                                                    <div class="flex items-center gap-1.5">
                                                        <div class="relative flex-1">
                                                            <Search class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-rose-500" />
                                                            <input
                                                                type="text"
                                                                bind:value={variantSearchQuery}
                                                                placeholder="Cari SKU / Nama / Size..."
                                                                class="w-full rounded-xl border border-rose-400 dark:border-rose-600 bg-white dark:bg-slate-900 pl-8 pr-3 py-1.5 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-rose-500 shadow-sm font-medium"
                                                            />
                                                        </div>
                                                        {#if item.product_variant_id}
                                                            <button
                                                                type="button"
                                                                onclick={() => {
                                                                    editingVariantIndex = null;
                                                                    variantSearchQuery = "";
                                                                }}
                                                                class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 shrink-0"
                                                                title="Batal"
                                                            >
                                                                <X class="w-4 h-4" />
                                                            </button>
                                                        {/if}
                                                    </div>

                                                    <!-- Filtered Variants Search List -->
                                                    <div class="max-h-48 overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xl divide-y divide-slate-100 dark:divide-slate-800">
                                                        {#each getSearchableVariants(item.seller_id || group.seller_id || sellerId, variantSearchQuery) as v}
                                                            <button
                                                                type="button"
                                                                onclick={() => {
                                                                    handleVariantSelect(index, v.id);
                                                                    editingVariantIndex = null;
                                                                    variantSearchQuery = "";
                                                                }}
                                                                class="w-full text-left p-2 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition-colors flex items-center justify-between gap-2"
                                                            >
                                                                <div class="truncate">
                                                                    <div class="font-bold text-slate-800 dark:text-slate-100 truncate">
                                                                        {v.product_name}
                                                                    </div>
                                                                    <div class="font-mono text-[11px] text-rose-600 dark:text-rose-400 font-semibold flex items-center gap-1.5">
                                                                        <span>{v.sku}</span>
                                                                        <span class="px-1.5 py-0.2 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-[10px] font-bold">
                                                                            {v.size}
                                                                        </span>
                                                                        {#if v.color}
                                                                            <span class="text-slate-400 text-[10px]">({v.color})</span>
                                                                        {/if}
                                                                    </div>
                                                                </div>
                                                                <span class="text-[10px] font-extrabold px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-500 shrink-0">
                                                                    {v.stocks[Number(warehouseId)] ?? 0} pcs
                                                                </span>
                                                            </button>
                                                        {:else}
                                                            <div class="p-3 text-center text-slate-400 text-xs italic">
                                                                Tidak ada varian cocok dengan "{variantSearchQuery}"
                                                            </div>
                                                        {/each}
                                                    </div>
                                                </div>
                                            {:else}
                                                <div class="flex items-center justify-between gap-2 group">
                                                    <div>
                                                        <div
                                                            class="font-bold text-slate-800 dark:text-slate-100"
                                                        >
                                                            {item.product_name || "Varian Tidak Dikenal"}
                                                        </div>
                                                        <div
                                                            class="font-mono text-rose-600 dark:text-rose-400 font-semibold text-[11px] flex items-center gap-1.5 mt-0.5"
                                                        >
                                                            <span>{item.sku}</span>
                                                            {#if item.size}
                                                                <span
                                                                    class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-[10px] font-bold"
                                                                >
                                                                    {item.size}
                                                                </span>
                                                            {/if}
                                                            {#if item.color}
                                                                <span class="text-slate-400 text-[10px]"
                                                                    >({item.color})</span
                                                                >
                                                            {/if}
                                                        </div>
                                                    </div>

                                                    <button
                                                        type="button"
                                                        onclick={() => (editingVariantIndex = index)}
                                                        class="px-2 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-rose-50 dark:hover:bg-rose-950/50 text-slate-600 hover:text-rose-600 dark:text-slate-400 dark:hover:text-rose-400 text-[11px] font-bold rounded-lg transition-all flex items-center gap-1 shrink-0 border border-slate-200 dark:border-slate-700"
                                                        title="Ubah Produk / Size"
                                                    >
                                                        <Edit2 class="w-3 h-3 text-rose-500" />
                                                        <span>Ubah</span>
                                                    </button>
                                                </div>
                                            {/if}
                                        </td>
                                        <td class="p-3">
                                            <select
                                                bind:value={item.konveksi_id}
                                                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-2.5 py-2 text-xs text-slate-800 dark:text-slate-100"
                                            >
                                                <option value={null}
                                                    >Standard</option
                                                >
                                                {#each konveksis as k}
                                                    <option value={k.id}
                                                        >{k.name}</option
                                                    >
                                                {/each}
                                            </select>
                                        </td>
                                        <td class="p-3 text-center font-bold">
                                            {#if item.available_stock !== undefined}
                                                <span
                                                    class="px-2 py-1 rounded-md text-[11px] {item.qty >
                                                    item.available_stock
                                                        ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300'
                                                        : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'}"
                                                >
                                                    {item.available_stock} pcs
                                                </span>
                                            {:else}
                                                <span class="text-slate-400"
                                                    >-</span
                                                >
                                            {/if}
                                        </td>
                                        <td class="p-3 text-center">
                                            <input
                                                type="number"
                                                min="1"
                                                bind:value={item.qty}
                                                class="w-16 text-center rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 py-1.5 text-xs font-bold text-rose-600 dark:text-rose-400"
                                            />
                                        </td>
                                        <td class="p-3 text-right font-medium text-slate-600 dark:text-slate-400">
                                            {formatRupiah(item.price)}
                                        </td>
                                        <td
                                            class="p-3 text-right font-semibold text-slate-700 dark:text-slate-300"
                                        >
                                            {formatRupiah(item.qty * item.price)}
                                        </td>
                                        <td class="p-3 text-right font-semibold text-cyan-600 dark:text-cyan-400">
                                            {formatRupiah(item.selling_price)}
                                        </td>
                                        <td
                                            class="p-3 text-right font-bold text-slate-800 dark:text-slate-100"
                                        >
                                            {formatRupiah(
                                                item.qty * item.selling_price,
                                            )}
                                        </td>
                                        <td
                                            class="p-3 text-right font-extrabold {(item.qty * item.selling_price) - (item.qty * item.price) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'}"
                                        >
                                            {formatRupiah((item.qty * item.selling_price) - (item.qty * item.price))}
                                        </td>
                                        <td class="p-3 text-center">
                                            <button
                                                onclick={() =>
                                                    removeManualItem(index)}
                                                class="p-1.5 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950 rounded-lg transition-colors"
                                            >
                                                <Trash2 class="w-4 h-4" />
                                            </button>
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </div>
            {/each}

            {#if formItems.length > displayLimit}
                <div class="text-center pt-2">
                    <button
                        onclick={loadMoreItems}
                        class="px-5 py-2.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs rounded-xl hover:bg-slate-200 transition-all"
                    >
                        Tampilkan 50 Produk Lagi ({formItems.length -
                            displayLimit} tersisa)
                    </button>
                </div>
            {/if}
        </div>

        <!-- Section 5: Resi & Order Summary Preview Table -->
        {#if resiList.length > 0}
            <div
                class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-4"
            >
                <div
                    class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-3"
                >
                    <div>
                        <h3
                            class="text-sm font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2"
                        >
                            <FileText class="w-4 h-4 text-emerald-500" />
                            Daftar Summary No. Resi & Order ({resiList.length} Transaksi)
                        </h3>
                        <p class="text-xs text-slate-400">
                            Daftar resi pesanan yang terasosiasi dengan
                            pengeluaran stok ini.
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="relative w-full sm:w-64">
                            <Search
                                class="w-3.5 h-3.5 absolute left-3 top-2.5 text-slate-400"
                            />
                            <input
                                type="text"
                                bind:value={resiSearch}
                                placeholder="Cari Resi / Order ID..."
                                class="w-full pl-8 pr-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl"
                            />
                        </div>

                        <button
                            onclick={addResiRow}
                            class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl flex items-center gap-1"
                        >
                            <Plus class="w-3.5 h-3.5" />
                            <span>Tambah Resi</span>
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr
                                class="border-b border-slate-200 dark:border-slate-800 text-slate-500 font-bold bg-slate-50 dark:bg-slate-950"
                            >
                                <th class="p-2.5 w-12">No</th>
                                <th class="p-2.5">Order ID / No Pesanan</th>
                                <th class="p-2.5">No. Resi Marketplace</th>
                                <th class="p-2.5 text-right"
                                    >Nilai Pembayaran</th
                                >
                                <th class="p-2.5 text-center w-12">Aksi</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-slate-800"
                        >
                            {#each paginatedResis as r, idx}
                                <tr
                                    class="hover:bg-slate-50 dark:hover:bg-slate-800/40"
                                >
                                    <td class="p-2.5 text-slate-400"
                                        >{(resiPage - 1) * resiPerPage +
                                            idx +
                                            1}</td
                                    >
                                    <td
                                        class="p-2.5 font-mono font-bold text-slate-800 dark:text-slate-200"
                                        >{r.order_id || "-"}</td
                                    >
                                    <td
                                        class="p-2.5 font-mono text-slate-600 dark:text-slate-400"
                                        >{r.no_resi || "-"}</td
                                    >
                                    <td
                                        class="p-2.5 text-right font-bold text-emerald-600 dark:text-emerald-400"
                                        >{formatRupiah(r.payment || 0)}</td
                                    >
                                    <td class="p-2.5 text-center">
                                        <button
                                            onclick={() =>
                                                removeResiRow(
                                                    (resiPage - 1) *
                                                        resiPerPage +
                                                        idx,
                                                )}
                                            class="text-rose-500 hover:text-rose-700"
                                        >
                                            <X class="w-4 h-4" />
                                        </button>
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>

                <!-- Resi Pagination Controls -->
                {#if totalResiPages > 1}
                    <div class="flex items-center justify-between text-xs pt-2">
                        <span class="text-slate-400"
                            >Menampilkan Halaman {resiPage} dari {totalResiPages}</span
                        >
                        <div class="flex items-center gap-1">
                            <button
                                disabled={resiPage === 1}
                                onclick={() => resiPage--}
                                class="px-3 py-1 bg-slate-100 dark:bg-slate-800 rounded-lg disabled:opacity-50 font-bold"
                                >Prev</button
                            >
                            <button
                                disabled={resiPage === totalResiPages}
                                onclick={() => resiPage++}
                                class="px-3 py-1 bg-slate-100 dark:bg-slate-800 rounded-lg disabled:opacity-50 font-bold"
                                >Next</button
                            >
                        </div>
                    </div>
                {/if}
            </div>
        {/if}

        <!-- Sticky Action Footer Bar -->
        <div
            class="sticky bottom-0 z-30 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-t border-slate-200 dark:border-slate-800 p-4 shadow-2xl rounded-t-2xl"
        >
            <div
                class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4"
            >
                <div
                    class="flex items-center gap-4 text-xs font-semibold text-slate-600 dark:text-slate-400"
                >
                    <span
                        >Total Qty: <strong
                            class="text-rose-600 dark:text-rose-400 text-sm font-black"
                            >{liveTotalQty} pcs</strong
                        ></span
                    >
                    <span class="hidden md:inline">|</span>
                    <span class="hidden md:inline"
                        >Est. Omset Bruto: <strong
                            class="text-emerald-600 dark:text-emerald-400 font-black"
                            >{formatRupiah(liveBruto)}</strong
                        ></span
                    >
                </div>

                <div
                    class="flex items-center gap-3 w-full sm:w-auto justify-end"
                >
                    <Link
                        href="/superadmin/warehouse/stock-out"
                        class="px-5 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-200 font-bold text-xs rounded-xl transition-all"
                    >
                        Batal
                    </Link>

                    <button
                        onclick={submitForm}
                        disabled={isSubmitting || formItems.length === 0}
                        class="px-6 py-2.5 bg-gradient-to-r from-rose-600 to-pink-600 hover:from-rose-500 hover:to-pink-500 disabled:opacity-50 text-white font-extrabold text-xs rounded-xl shadow-lg shadow-rose-600/30 flex items-center gap-2 transition-all"
                    >
                        {#if isSubmitting}
                            <RefreshCw class="w-4 h-4 animate-spin" />
                            <span>Menyimpan Transaksi...</span>
                        {:else}
                            <Save class="w-4 h-4" />
                            <span
                                >{isEdit
                                    ? "Perbarui Transaksi Stock Out"
                                    : "Simpan Transaksi Stock Out"}</span
                            >
                        {/if}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Bulk Create Missing Products Modal -->
    {#if isQuickCreateOpen}
        <div
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
        >
            <div
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 max-w-2xl w-full shadow-2xl space-y-6"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4"
                >
                    <div class="flex items-center gap-2">
                        <Sparkles class="w-6 h-6 text-amber-500" />
                        <div>
                            <h3
                                class="text-base font-bold text-slate-800 dark:text-slate-100"
                            >
                                Auto-Generate Missing Products & Variants
                            </h3>
                            <p class="text-xs text-slate-400">
                                Buat {missingSkusList.length} produk & varian baru
                                secara massal instan
                            </p>
                        </div>
                    </div>
                    <button
                        onclick={() => (isQuickCreateOpen = false)}
                        class="text-slate-400 hover:text-slate-600"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div>
                        <label
                            for="quick-seller"
                            class="font-bold text-slate-700 dark:text-slate-300"
                            >Pilih Mitra Seller <span class="text-rose-500"
                                >*</span
                            ></label
                        >
                        <select
                            id="quick-seller"
                            bind:value={quickSellerId}
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 p-2.5 mt-1 font-semibold"
                        >
                            {#each sellers as s}
                                <option value={s.id}>{s.seller_name}</option>
                            {/each}
                        </select>
                    </div>

                    <div>
                        <label
                            for="quick-category"
                            class="font-bold text-slate-700 dark:text-slate-300"
                            >Kategori Produk</label
                        >
                        <select
                            id="quick-category"
                            bind:value={quickCategoryId}
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 p-2.5 mt-1"
                        >
                            <option value="">Pilih Kategori</option>
                            {#each categories as c}
                                <option value={c.id}>{c.name}</option>
                            {/each}
                        </select>
                    </div>

                    <div>
                        <label
                            for="quick-brand"
                            class="font-bold text-slate-700 dark:text-slate-300"
                            >Brand</label
                        >
                        <select
                            id="quick-brand"
                            bind:value={quickBrandId}
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 p-2.5 mt-1"
                        >
                            <option value="">Pilih Brand</option>
                            {#each brands as b}
                                <option value={b.id}>{b.name}</option>
                            {/each}
                        </select>
                    </div>

                    <div>
                        <label
                            for="quick-template"
                            class="font-bold text-slate-700 dark:text-slate-300"
                            >Template Size/Varian</label
                        >
                        <select
                            id="quick-template"
                            bind:value={quickTemplateId}
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 p-2.5 mt-1"
                        >
                            <option value="">Standard (ALL SIZE)</option>
                            {#each templates as t}
                                <option value={t.id}>{t.template_name}</option>
                            {/each}
                        </select>
                    </div>
                </div>

                <!-- Missing SKUs Preview Table -->
                <div
                    class="max-h-48 overflow-y-auto border border-slate-200 dark:border-slate-800 rounded-xl p-3 bg-slate-50 dark:bg-slate-950 space-y-2"
                >
                    <span class="text-[11px] font-bold text-slate-500 uppercase"
                        >Daftar SKU yang Akan Dibuat:</span
                    >
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                        {#each missingSkusList as m}
                            <div
                                class="p-2 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800"
                            >
                                <div
                                    class="font-mono font-bold text-rose-600 dark:text-rose-400"
                                >
                                    {m.sku}
                                </div>
                                <div
                                    class="text-[11px] text-slate-600 dark:text-slate-400 truncate"
                                >
                                    {m.product_name}
                                </div>
                            </div>
                        {/each}
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button
                        onclick={() => (isQuickCreateOpen = false)}
                        class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs rounded-xl"
                        >Batal</button
                    >
                    <button
                        onclick={handleQuickCreateProducts}
                        disabled={isQuickCreating}
                        class="px-5 py-2 bg-gradient-to-r from-amber-500 to-rose-600 text-white font-extrabold text-xs rounded-xl shadow-md flex items-center gap-1.5"
                    >
                        {#if isQuickCreating}
                            <RefreshCw class="w-4 h-4 animate-spin" />
                            <span>Memproses Dibuat...</span>
                        {:else}
                            <Sparkles class="w-4 h-4" />
                            <span>Generate Products Instan</span>
                        {/if}
                    </button>
                </div>
            </div>
        </div>
        {#if false}{/if}
    {/if}
</AdminLayout>
