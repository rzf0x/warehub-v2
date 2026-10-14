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
        Copy,
        CheckCheck,
        Lock,
        Building2,
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
        variation?: string;
        variations?: string[];
        sizes?: string[];
        sizes_count?: number;
        has_multiple_sizes?: boolean;
        total_qty?: number;
        order_count?: number;
        missing_parts?: string[];
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
    let isSearchingVariants = $state<boolean>(false);
    let searchResults = $state<VariantData[]>([]);
    let searchDebounceTimeout: any = null;
    let localVariants = $state<VariantData[]>([...variants]);

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
                    selling_price: Number(Number(item.selling_price || 0).toFixed(2)),
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
                        ? Number((totalSelling / combinedQty).toFixed(2))
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

    // Stores filtered by selected seller
    let filteredStores = $derived(
        sellerId
            ? stores.filter((st) => Number(st.seller_id) === Number(sellerId))
            : stores,
    );

    function handleSellerChange() {
        if (storeId) {
            const currentStore = stores.find((s) => s.id === Number(storeId));
            if (
                currentStore &&
                currentStore.seller_id &&
                Number(currentStore.seller_id) !== Number(sellerId)
            ) {
                storeId = "";
            }
        }
    }

    // Manual Items Management
    function addManualItem(paramSellerId?: number) {
        const sId = paramSellerId ?? (sellerId ? Number(sellerId) : (sellers[0]?.id ?? null));
        const sName = sellers.find((s) => s.id === sId)?.seller_name ?? "Mitra";
        const newIndex = formItems.length;
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
        openVariantSelector(newIndex, sId);
    }

    function removeManualItem(index: number) {
        if (editingVariantIndex === index) {
            editingVariantIndex = null;
        } else if (editingVariantIndex !== null && editingVariantIndex > index) {
            editingVariantIndex--;
        }
        formItems = formItems.filter((_, idx) => idx !== index);
    }

    function handleVariantSelect(index: number, variant: VariantData | number) {
        let v: VariantData | undefined;
        if (typeof variant === "object" && variant !== null) {
            v = variant;
        } else {
            const vId = Number(variant);
            v = searchResults.find((i) => i.id === vId) ||
                localVariants.find((i) => i.id === vId) ||
                variants.find((i) => i.id === vId);
        }
        if (!v) return;

        if (!localVariants.some((item) => item.id === v!.id)) {
            localVariants.push(v);
        }

        const wId = Number(warehouseId);
        const avail = v.stocks ? (v.stocks[wId] ?? 0) : 0;
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

    async function fetchVariants(query: string, targetSellerId?: number | null) {
        isSearchingVariants = true;
        try {
            const params = new URLSearchParams();
            if (query && query.trim()) params.append("q", query.trim());
            const sId = targetSellerId || (sellerId ? Number(sellerId) : null);
            if (sId) params.append("seller_id", String(sId));
            if (warehouseId) params.append("warehouse_id", String(warehouseId));

            const res = await fetch(
                `/superadmin/warehouse/stock-out/search-variants?${params.toString()}`,
                {
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                    },
                },
            );
            if (res.ok) {
                searchResults = await res.json();
            }
        } catch (e) {
            console.error("Error fetching variants:", e);
        } finally {
            isSearchingVariants = false;
        }
    }

    function onVariantSearchInput(query: string, targetSellerId?: number | null) {
        variantSearchQuery = query;
        if (searchDebounceTimeout) clearTimeout(searchDebounceTimeout);
        searchDebounceTimeout = setTimeout(() => {
            fetchVariants(query, targetSellerId);
        }, 250);
    }

    function openVariantSelector(index: number, targetSellerId?: number | null) {
        editingVariantIndex = index;
        variantSearchQuery = "";
        searchResults = [];
        fetchVariants("", targetSellerId);
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
    let importStatusText = $state<string>("");
    let importCurrentChunk = $state<number>(0);
    let importTotalChunks = $state<number>(0);
    let importTotalItems = $state<number>(0);
    let importMatchedCount = $state<number>(0);
    let importMissingCount = $state<number>(0);
    let missingSkusList = $state<MissingSkuItem[]>([]);

    // Missing SKUs statistics & copy state
    let missingWithMultipleSizesCount = $derived(
        missingSkusList.filter(
            (m) => (m.sizes && m.sizes.length > 1) || m.has_multiple_sizes,
        ).length,
    );
    let totalMissingPcs = $derived(
        missingSkusList.reduce((acc, m) => acc + (m.total_qty || 1), 0),
    );

    let missingSearchQuery = $state<string>("");
    let isCopiedAllJson = $state<boolean>(false);
    let isCopiedAllSkus = $state<boolean>(false);
    let isCopiedAllSummary = $state<boolean>(false);
    let copiedRowKey = $state<string | null>(null);

    let filteredMissingSkus = $derived(
        missingSkusList.filter(
            (m) =>
                !missingSearchQuery.trim() ||
                (m.sku || "").toLowerCase().includes(missingSearchQuery.toLowerCase()) ||
                (m.product_name || "").toLowerCase().includes(missingSearchQuery.toLowerCase()) ||
                (m.sizes || []).some((sz) => sz.toLowerCase().includes(missingSearchQuery.toLowerCase())) ||
                (m.variation || "").toLowerCase().includes(missingSearchQuery.toLowerCase()),
        ),
    );

    function formatJsonSkuLine(sku: string): string {
        return `"Nomor Referensi SKU": "${sku}",`;
    }

    async function copyAllJsonLines() {
        if (missingSkusList.length === 0) return;
        const text = missingSkusList
            .map((m) => `      ${formatJsonSkuLine(m.sku)}`)
            .join("\n");
        await navigator.clipboard.writeText(text);
        isCopiedAllJson = true;
        setTimeout(() => (isCopiedAllJson = false), 2000);
    }

    async function copyAllSkusOnly() {
        if (missingSkusList.length === 0) return;
        const text = missingSkusList
            .map((m) => {
                if (!m.sku) return null;
                const sizesStr = m.sizes && m.sizes.length > 0 ? m.sizes.join(", ") : (m.variation || "");
                return sizesStr ? `${m.sku} (Size: ${sizesStr})` : m.sku;
            })
            .filter(Boolean)
            .join("\n");
        await navigator.clipboard.writeText(text);
        isCopiedAllSkus = true;
        setTimeout(() => (isCopiedAllSkus = false), 2000);
    }

    async function copyAllSummary() {
        if (missingSkusList.length === 0) return;
        const text = missingSkusList
            .map((m, idx) => {
                const sizesStr = m.sizes && m.sizes.length > 0 ? m.sizes.join(", ") : (m.variation || "-");
                const multiTag = (m.sizes && m.sizes.length > 1) || m.has_multiple_sizes ? ` [${m.sizes?.length ?? 2} UKURAN]` : "";
                return `${idx + 1}. SKU: ${m.sku}${multiTag} | Ukuran: [${sizesStr}] | Produk: ${m.product_name || "-"}`;
            })
            .join("\n");
        await navigator.clipboard.writeText(text);
        isCopiedAllSummary = true;
        setTimeout(() => (isCopiedAllSummary = false), 2000);
    }

    async function copyJsonLine(sku: string) {
        if (!sku) return;
        const line = formatJsonSkuLine(sku);
        await navigator.clipboard.writeText(line);
        copiedRowKey = sku + "_line";
        setTimeout(() => {
            if (copiedRowKey === sku + "_line") copiedRowKey = null;
        }, 2000);
    }

    async function copySkuValueOnly(sku: string) {
        if (!sku) return;
        await navigator.clipboard.writeText(sku);
        copiedRowKey = sku + "_raw";
        setTimeout(() => {
            if (copiedRowKey === sku + "_raw") copiedRowKey = null;
        }, 2000);
    }

    async function copySkuWithSizes(m: MissingSkuItem) {
        const sizesStr = m.sizes && m.sizes.length > 0 ? m.sizes.join(", ") : (m.variation || "");
        const text = `${m.sku} (Size: ${sizesStr})`;
        await navigator.clipboard.writeText(text);
        copiedRowKey = m.sku + "_info";
        setTimeout(() => {
            if (copiedRowKey === m.sku + "_info") copiedRowKey = null;
        }, 2000);
    }

    async function handleJsonFileUpload(event: Event) {
        if (!warehouseId || !sellerId) {
            alert("Silakan pilih 1. Gudang Asal dan 2. Mitra Seller terlebih dahulu sebelum mengunggah file JSON!");
            const input = event.target as HTMLInputElement;
            input.value = "";
            return;
        }

        const input = event.target as HTMLInputElement;
        if (!input.files || input.files.length === 0) return;

        const file = input.files[0];
        importedFileName = file.name;

        // Tampilkan modal progress
        isImporting = true;
        importProgress = 15;
        importStatusText = `Mengunggah & menganalisis file ${file.name}...`;
        missingSkusList = [];
        importMatchedCount = 0;
        importMissingCount = 0;
        importTotalChunks = 1;
        importCurrentChunk = 1;

        try {
            const formData = new FormData();
            formData.append("file", file);
            if (warehouseId) formData.append("warehouse_id", String(warehouseId));
            formData.append("seller_id", String(sellerId));

            importProgress = 40;
            importStatusText = "Mencocokkan SKU pesanan dengan Database Master Gudang...";

            const response = await fetch("/superadmin/warehouse/stock-out/process-orders", {
                method: "POST",
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    "X-CSRF-TOKEN": getCsrfHeader(),
                },
                body: formData,
            });

            const resText = await response.text();
            let resData: any;
            try {
                resData = JSON.parse(resText);
            } catch (parseErr) {
                console.error("Respon bukan JSON:", resText);
                if (response.status === 403) {
                    throw new Error("Akses ditolak (HTTP 403 Forbidden). Firewall server/ModSecurity memblokir request atau akun belum diverifikasi.");
                }
                if (response.status === 419) {
                    throw new Error("Sesi browser kedaluwarsa (419). Silakan refresh halaman.");
                }
                throw new Error(`Server mengembalikan status HTTP ${response.status}`);
            }

            if (!resData.success) {
                alert(resData.message || "Gagal memproses file pesanan.");
                return;
            }

            importProgress = 85;
            importStatusText = "Menyusun rincian item barang keluar...";

            const resolvedMatchedItems: FormItem[] = [];
            if (Array.isArray(resData.matched_items)) {
                resData.matched_items.forEach((m: any) => {
                    resolvedMatchedItems.push({
                        product_variant_id: m.product_variant_id,
                        konveksi_id: null,
                        seller_id: m.seller_id || Number(sellerId),
                        seller_name: m.seller_name || (sellers.find((s) => s.id === Number(sellerId))?.seller_name ?? "Mitra"),
                        sku: m.sku,
                        product_name: m.product_name,
                        size: m.size,
                        color: m.color,
                        qty: m.qty,
                        price: Number(m.price || 0),
                        selling_price: Number(Number(m.selling_price || 0).toFixed(2)),
                        available_stock: m.available_stock,
                    });
                });
            }

            if (Array.isArray(resData.missing_skus)) {
                missingSkusList = resData.missing_skus;
                importMissingCount = resData.missing_skus.length;
            }

            if (Array.isArray(resData.resis)) {
                resiList = resData.resis.map((r: any) => ({
                    order_id: r.order_id,
                    no_resi: r.no_resi,
                    payment: r.payment,
                }));
            }

            if (resData.suggested_bruto) {
                bruto = Number(resData.suggested_bruto);
            }

            formItems = consolidateFormItems(resolvedMatchedItems);
            importMatchedCount = formItems.length;
            importProgress = 100;
            importStatusText = `Selesai! Berhasil mencocokkan ${formItems.length} produk varian.`;

            setTimeout(() => {
                isImporting = false;
            }, 600);
        } catch (err: any) {
            console.error("Import error:", err);
            alert("Terjadi kesalahan saat memproses file JSON: " + (err?.message || "Koneksi bermasalah"));
        } finally {
            isImporting = false;
            input.value = "";
        }
    }

    // Calculations & Validation Metrics
    let liveTotalQty = $derived(
        formItems.reduce((acc, i) => acc + (Number(i.qty) || 0), 0),
    );
    let liveTotalHpp = $derived(
        Math.round(
            formItems.reduce(
                (acc, i) => acc + (Number(i.qty) || 0) * (Number(i.price) || 0),
                0,
            ),
        ),
    );
    let liveTotalSelling = $derived(
        Math.round(
            formItems.reduce(
                (acc, i) =>
                    acc + (Number(i.qty) || 0) * (Number(i.selling_price) || 0),
                0,
            ),
        ),
    );
    let liveBruto = $derived(bruto > 0 ? bruto : liveTotalSelling);
    let liveMarketplaceFees = $derived(Math.max(0, liveBruto - liveTotalSelling));
    let liveEstProfit = $derived(liveTotalSelling - liveTotalHpp);

    // Deficit Items (Stok 0 atau Qty Keluar > Stok Gudang)
    let adjustInputMap = $state<Record<number, number>>({});
    let adjustingVariantId = $state<number | null>(null);
    let isAdjustingAll = $state<boolean>(false);
    let adjustSuccessToast = $state<string | null>(null);
    let filterOnlyDeficit = $state<boolean>(false);

    let deficitItems = $derived(
        formItems
            .map((item, index) => {
                const avail = Number(item.available_stock ?? 0);
                const qtyOut = Number(item.qty || 0);
                const diff = qtyOut - avail;
                const isZero = avail <= 0;
                return {
                    index,
                    item,
                    avail,
                    qtyOut,
                    diff: diff > 0 ? diff : (isZero ? qtyOut : 0),
                    isZero,
                    isDeficit: diff > 0 || isZero,
                };
            })
            .filter((x) => x.isDeficit && x.item.product_variant_id),
    );

    function getCsrfHeader(): string {
        const meta = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
        if (meta) return meta;
        const cookieMatch = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        if (cookieMatch) return decodeURIComponent(cookieMatch[1]);
        return "";
    }

    async function handleQuickAdjustSingle(variantId: number, addQty: number, itemIndex: number) {
        if (!warehouseId) {
            alert("Harap tentukan 1. Gudang Asal terlebih dahulu di atas!");
            return;
        }
        if (!addQty || addQty < 1) {
            alert("Jumlah penambahan stok minimal 1 pcs!");
            return;
        }

        adjustingVariantId = variantId;
        try {
            const res = await fetch("/superadmin/warehouse/stock-out/quick-adjust-stock", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                    "X-CSRF-TOKEN": getCsrfHeader(),
                },
                body: JSON.stringify({
                    warehouse_id: Number(warehouseId),
                    variant_id: variantId,
                    add_qty: addQty,
                }),
            });

            const text = await res.text();
            let data: any;
            try {
                data = JSON.parse(text);
            } catch (err) {
                console.error("Non-JSON response:", text);
                if (res.status === 419) {
                    alert("Sesi browser kedaluwarsa (419). Harap refresh halaman terlebih dahulu.");
                    return;
                }
                alert(`Gagal (HTTP ${res.status}): ${text.substring(0, 120)}`);
                return;
            }

            if (res.ok && data.success) {
                formItems = formItems.map((fi) => {
                    if (fi.product_variant_id === variantId) {
                        return { ...fi, available_stock: data.new_stock };
                    }
                    return fi;
                });
                localVariants = localVariants.map((v) => {
                    if (v.id === variantId) {
                        return {
                            ...v,
                            stocks: {
                                ...v.stocks,
                                [Number(warehouseId)]: data.new_stock,
                            },
                        };
                    }
                    return v;
                });
                adjustSuccessToast = data.message;
                setTimeout(() => (adjustSuccessToast = null), 3000);
            } else {
                alert(data.message || "Gagal menambahkan stok.");
            }
        } catch (e: any) {
            console.error("Quick adjust error:", e);
            alert("Terjadi kesalahan saat menambahkan stok: " + (e?.message || "Koneksi bermasalah"));
        } finally {
            adjustingVariantId = null;
        }
    }

    async function handleQuickAdjustBulk() {
        if (!warehouseId) {
            alert("Harap tentukan 1. Gudang Asal terlebih dahulu di atas!");
            return;
        }
        if (deficitItems.length === 0) return;

        const payloadItems = deficitItems.map((d) => {
            const manualAdd =
                adjustInputMap[d.item.product_variant_id!] ??
                (d.diff > 0 ? d.diff : d.qtyOut);
            return {
                variant_id: d.item.product_variant_id!,
                add_qty: Math.max(1, manualAdd),
            };
        });

        isAdjustingAll = true;
        try {
            const res = await fetch("/superadmin/warehouse/stock-out/quick-adjust-stock-bulk", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                    "X-CSRF-TOKEN": getCsrfHeader(),
                },
                body: JSON.stringify({
                    warehouse_id: Number(warehouseId),
                    items: payloadItems,
                }),
            });

            const text = await res.text();
            let data: any;
            try {
                data = JSON.parse(text);
            } catch (err) {
                console.error("Non-JSON response:", text);
                if (res.status === 419) {
                    alert("Sesi browser kedaluwarsa (419). Harap refresh halaman terlebih dahulu.");
                    return;
                }
                alert(`Gagal (HTTP ${res.status}): ${text.substring(0, 120)}`);
                return;
            }

            if (res.ok && data.success && data.updated_stocks) {
                const stockMap = data.updated_stocks;
                formItems = formItems.map((fi) => {
                    if (
                        fi.product_variant_id &&
                        stockMap[fi.product_variant_id] !== undefined
                    ) {
                        return {
                            ...fi,
                            available_stock: stockMap[fi.product_variant_id],
                        };
                    }
                    return fi;
                });
                localVariants = localVariants.map((v) => {
                    if (stockMap[v.id] !== undefined) {
                        return {
                            ...v,
                            stocks: {
                                ...v.stocks,
                                [Number(warehouseId)]: stockMap[v.id],
                            },
                        };
                    }
                    return v;
                });
                adjustSuccessToast = data.message;
                setTimeout(() => (adjustSuccessToast = null), 3500);
            } else {
                alert(data.message || "Gagal menambahkan stok massal.");
            }
        } catch (e: any) {
            console.error("Bulk quick adjust error:", e);
            alert("Terjadi kesalahan saat menambahkan stok massal: " + (e?.message || "Koneksi bermasalah"));
        } finally {
            isAdjustingAll = false;
        }
    }

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
            if (filterOnlyDeficit) {
                const avail = Number(item.available_stock ?? 0);
                const qtyOut = Number(item.qty || 0);
                if (avail > 0 && qtyOut <= avail) return;
            }

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
            class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-6"
        >
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    <Store class="w-4 h-4 text-rose-500" />
                    <span>Informasi Gudang & Pengeluaran</span>
                </h3>
                <span class="text-[11px] font-semibold px-2.5 py-1 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 rounded-lg">
                    Langkah 1: Tentukan Asal & Mitra
                </span>
            </div>

            <!-- Tier 1: 2 Kolom Lebar untuk Input Wajib Utama -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- 1. Gudang Asal * -->
                <div class="p-4 rounded-xl bg-slate-50/70 dark:bg-slate-950/50 border border-slate-200/80 dark:border-slate-800/80 space-y-2">
                    <div class="flex items-center justify-between">
                        <label
                            for="warehouse-select"
                            class="text-xs font-extrabold text-slate-800 dark:text-slate-200 flex items-center gap-1.5"
                        >
                            <Building2 class="w-4 h-4 text-rose-500" />
                            <span>1. Gudang Asal</span>
                            <span class="text-rose-500">*</span>
                        </label>
                        {#if warehouseId}
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400">Terpilih</span>
                        {:else}
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400">Wajib Dipilih</span>
                        {/if}
                    </div>
                    <select
                        id="warehouse-select"
                        bind:value={warehouseId}
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3.5 py-2.5 text-xs font-bold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-rose-500 shadow-xs"
                    >
                        <option value="">-- Pilih Gudang Asal --</option>
                        {#each warehouses as w}
                            <option value={w.id}>{w.name}</option>
                        {/each}
                    </select>
                    <p class="text-[11px] text-slate-400 dark:text-slate-500">
                        Lokasi fisik stok barang yang akan dikeluarkan / berkurang.
                    </p>
                </div>

                <!-- 2. Mitra Seller * -->
                <div class="p-4 rounded-xl bg-slate-50/70 dark:bg-slate-950/50 border border-slate-200/80 dark:border-slate-800/80 space-y-2">
                    <div class="flex items-center justify-between">
                        <label
                            for="seller-select"
                            class="text-xs font-extrabold text-slate-800 dark:text-slate-200 flex items-center gap-1.5"
                        >
                            <Store class="w-4 h-4 text-rose-500" />
                            <span>2. Mitra Seller</span>
                            <span class="text-rose-500">*</span>
                        </label>
                        {#if sellerId}
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400">Terpilih</span>
                        {:else}
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400">Wajib Dipilih</span>
                        {/if}
                    </div>
                    <select
                        id="seller-select"
                        bind:value={sellerId}
                        onchange={handleSellerChange}
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3.5 py-2.5 text-xs font-bold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-rose-500 shadow-xs"
                    >
                        <option value="">-- Pilih Mitra Seller --</option>
                        {#each sellers as s}
                            <option value={s.id}>{s.seller_name}</option>
                        {/each}
                    </select>
                    <p class="text-[11px] text-slate-400 dark:text-slate-500">
                        Mitra pemilik produk yang akan memfilter daftar toko marketplace.
                    </p>
                </div>
            </div>

            <!-- Tier 2: 3 Kolom untuk Detail Toko, Periode, Tanggal -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 pt-1">
                <!-- Toko Marketplace (Opsional) - Terfilter berdasarkan sellerId -->
                <div class="space-y-1.5">
                    <label
                        for="store-select"
                        class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center justify-between"
                    >
                        <span>Toko Marketplace (Opsional)</span>
                        {#if sellerId}
                            <span class="text-[10px] font-normal text-slate-400">
                                {filteredStores.length} toko tersedia
                            </span>
                        {/if}
                    </label>
                    <select
                        id="store-select"
                        bind:value={storeId}
                        onchange={handleStoreChange}
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-rose-500 font-medium"
                    >
                        <option value="">Semua / Toko Umum</option>
                        {#each filteredStores as st}
                            <option value={st.id}>
                                {st.store_name} ({st.marketplace})
                            </option>
                        {/each}
                    </select>
                    {#if sellerId && filteredStores.length === 0}
                        <p class="text-[10px] text-amber-500">
                            * Mitra ini belum memiliki toko marketplace yang ditautkan.
                        </p>
                    {/if}
                </div>

                <!-- Periode Pembukuan -->
                <div class="space-y-1.5">
                    <label
                        for="period-select"
                        class="text-xs font-bold text-slate-700 dark:text-slate-300"
                    >
                        Periode Pembukuan (Opsional)
                    </label>
                    <select
                        id="period-select"
                        bind:value={periodId}
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-rose-500 font-medium"
                    >
                        <option value="">-- Pilih Periode --</option>
                        {#each periods as p}
                            <option value={p.id}>
                                {p.name} {p.is_active ? "(Aktif)" : ""}
                            </option>
                        {/each}
                    </select>
                </div>

                <!-- Tanggal Pengeluaran * -->
                <div class="space-y-1.5">
                    <label
                        for="stock-out-date"
                        class="text-xs font-bold text-slate-700 dark:text-slate-300"
                    >
                        Tanggal Pengeluaran <span class="text-rose-500">*</span>
                    </label>
                    <input
                        id="stock-out-date"
                        type="date"
                        bind:value={date}
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-rose-500 font-medium"
                    />
                </div>
            </div>

            <!-- Tier 3: Total Bruto & Catatan -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-slate-100 dark:border-slate-800">
                <!-- Total Bruto -->
                <div class="space-y-1.5">
                    <label
                        for="bruto-input"
                        class="text-xs font-bold text-slate-700 dark:text-slate-300"
                    >
                        Total Bruto Penjualan (Rp)
                    </label>
                    <input
                        id="bruto-input"
                        type="number"
                        bind:value={bruto}
                        placeholder="Otomatis terisi dari JSON / item..."
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-rose-500 font-semibold"
                    />
                </div>

                <!-- Catatan -->
                <div class="space-y-1.5">
                    <label
                        for="note-input"
                        class="text-xs font-bold text-slate-700 dark:text-slate-300"
                    >
                        Catatan Transaksi
                    </label>
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
                        <span>Import Cleaned Marketplace JSON (Shopee / TikTok Engine Output)</span>
                    </h3>
                    {#if importedFileName}
                        <span
                            class="px-2.5 py-0.5 text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 rounded-full flex items-center gap-1"
                        >
                            <Check class="w-3.5 h-3.5" /> File: {importedFileName} ({formItems.length} matched)
                        </span>
                    {/if}
                </div>

                <!-- Dropzone Area -->
                {#if !warehouseId || !sellerId}
                    <!-- Locked / Readonly State -->
                    <div
                        class="relative border-2 border-dashed border-amber-300 dark:border-amber-700/60 bg-amber-50/40 dark:bg-amber-950/20 rounded-2xl p-8 text-center transition-all cursor-not-allowed select-none"
                    >
                        <div class="space-y-3">
                            <div
                                class="w-12 h-12 rounded-2xl bg-amber-100 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 mx-auto flex items-center justify-center shadow-xs"
                            >
                                <Lock class="w-6 h-6" />
                            </div>
                            <div class="max-w-md mx-auto space-y-1">
                                <h4 class="text-xs font-extrabold text-amber-900 dark:text-amber-200">
                                    Upload File JSON Terkunci
                                </h4>
                                <p class="text-xs font-semibold text-amber-700 dark:text-amber-300">
                                    Silakan pilih <strong>1. Gudang Asal</strong> dan <strong>2. Mitra Seller</strong> terlebih dahulu di atas sebelum mengunggah file JSON.
                                </p>
                            </div>
                        </div>
                    </div>
                {:else}
                    <!-- Active Dropzone State -->
                    <div
                        class="relative border-2 border-dashed border-slate-300 dark:border-slate-700 hover:border-cyan-500 dark:hover:border-cyan-500 rounded-2xl p-8 text-center transition-all bg-slate-50/50 dark:bg-slate-950/40 group hover:bg-cyan-50/20"
                    >
                        <input
                            type="file"
                            accept=".json"
                            onchange={handleJsonFileUpload}
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                        />
                        <div class="space-y-3 pointer-events-none">
                            <div
                                class="w-12 h-12 rounded-2xl bg-cyan-50 dark:bg-cyan-950/50 text-cyan-500 mx-auto flex items-center justify-center group-hover:scale-110 transition-transform"
                            >
                                <Upload class="w-6 h-6" />
                            </div>
                            <div>
                                {#if importedFileName}
                                    <div class="space-y-1">
                                        <span class="font-bold text-sm text-cyan-600 dark:text-cyan-400">
                                            {importedFileName}
                                        </span>
                                        <p class="text-[11px] text-slate-400">
                                            Klik atau drag file JSON lain untuk mengganti data.
                                        </p>
                                    </div>
                                {:else}
                                    <p class="text-xs font-bold text-slate-700 dark:text-slate-300">
                                        Drop File JSON Data Cleaning di sini, atau{" "}
                                        <span class="text-cyan-600 dark:text-cyan-400 underline underline-offset-2">Browse File</span>
                                    </p>
                                    <p class="text-[11px] text-slate-400 mt-1">
                                        File hasil export dari menu Cleaning Data Shopee / TikTok Shop Warehub
                                    </p>
                                {/if}
                            </div>
                        </div>
                    </div>
                {/if}

                <!-- Section Card Tersendiri: Missing SKUs yang Tidak Ada di Database -->
                {#if missingSkusList.length > 0}
                    <div
                        class="p-5 bg-white dark:bg-slate-900 border-2 border-amber-300 dark:border-amber-700/60 rounded-2xl shadow-sm space-y-4"
                    >
                        <!-- Header Card -->
                        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 border-b border-amber-100 dark:border-amber-900/40 pb-3">
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0 mt-0.5">
                                    <AlertTriangle class="w-5 h-5" />
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-amber-900 dark:text-amber-200 flex items-center gap-2 flex-wrap">
                                        <span>Ditemukan {missingSkusList.length} SKU Belum Terdaftar di Database Gudang</span>
                                        <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-amber-200 dark:bg-amber-900/80 text-amber-800 dark:text-amber-200">
                                            {missingSkusList.length} SKU
                                        </span>
                                        {#if missingWithMultipleSizesCount > 0}
                                            <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-purple-100 dark:bg-purple-950/80 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800 flex items-center gap-1">
                                                <Sparkles class="w-3 h-3 text-purple-500" />
                                                <span>{missingWithMultipleSizesCount} SKU memiliki &gt; 1 Ukuran</span>
                                            </span>
                                        {/if}
                                        {#if totalMissingPcs > 0}
                                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                                Total {totalMissingPcs} pcs tertahan
                                            </span>
                                        {/if}
                                    </h4>
                                    <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">
                                        Daftar referensi SKU beserta ukuran (size) dan variasi yang terdeteksi dari file JSON. Kamu bisa copy baris untuk edit file JSON, atau klik <strong>+ Tambah Produk</strong> untuk mendaftarkan langsung ke Master Data.
                                    </p>
                                </div>
                            </div>

                            <!-- Action Buttons Copy All -->
                            <div class="flex items-center gap-2 shrink-0 flex-wrap">
                                <button
                                    type="button"
                                    onclick={copyAllSummary}
                                    class="px-3 py-2 bg-purple-50 hover:bg-purple-100 dark:bg-purple-950/60 dark:hover:bg-purple-900/60 text-purple-700 dark:text-purple-200 text-xs font-bold rounded-xl border border-purple-200 dark:border-purple-800 flex items-center gap-1.5 transition-all"
                                    title="Copy ringkasan SKU dan daftar ukuran semua produk yang belum terdaftar"
                                >
                                    {#if isCopiedAllSummary}
                                        <CheckCheck class="w-4 h-4 text-emerald-500" />
                                        <span class="text-emerald-700 dark:text-emerald-300">Tersalin!</span>
                                    {:else}
                                        <Copy class="w-4 h-4 text-purple-600" />
                                        <span>Copy Ringkasan SKU & Ukuran</span>
                                    {/if}
                                </button>
                                <button
                                    type="button"
                                    onclick={copyAllJsonLines}
                                    class="px-3.5 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-xl shadow-xs flex items-center gap-1.5 transition-all"
                                    title="Copy semua baris dalam format JSON: &quot;Nomor Referensi SKU&quot;: &quot;...&quot;"
                                >
                                    {#if isCopiedAllJson}
                                        <CheckCheck class="w-4 h-4 text-emerald-200" />
                                        <span>Tersalin Semua!</span>
                                    {:else}
                                        <Copy class="w-4 h-4" />
                                        <span>Copy Semua Format JSON</span>
                                    {/if}
                                </button>
                                <button
                                    type="button"
                                    onclick={copyAllSkusOnly}
                                    class="px-3 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl flex items-center gap-1.5 transition-all"
                                    title="Copy daftar SKU beserta ukuran (size)"
                                >
                                    {#if isCopiedAllSkus}
                                        <CheckCheck class="w-4 h-4 text-emerald-500" />
                                        <span>Tersalin!</span>
                                    {:else}
                                        <Copy class="w-4 h-4" />
                                        <span>List SKU & Ukuran</span>
                                    {/if}
                                </button>
                            </div>
                        </div>

                        <!-- Search Bar -->
                        <div class="flex items-center gap-2">
                            <div class="relative flex-1">
                                <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                <input
                                    type="text"
                                    bind:value={missingSearchQuery}
                                    placeholder="Cari berdasarkan SKU, Nama Produk, Ukuran (contoh: 4XL, L, BKA 44)..."
                                    class="w-full pl-9 pr-3.5 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-amber-500 font-sans"
                                />
                            </div>
                            {#if missingSearchQuery}
                                <button
                                    type="button"
                                    onclick={() => (missingSearchQuery = "")}
                                    class="text-xs text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 px-2"
                                >
                                    Reset
                                </button>
                            {/if}
                        </div>

                        <!-- Rich Cards List for Missing SKUs -->
                        <div class="max-h-96 overflow-y-auto space-y-3 p-1.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/40">
                            {#each filteredMissingSkus as m, idx}
                                <div
                                    class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-amber-400 dark:hover:border-amber-600 transition-all shadow-xs space-y-3 group"
                                >
                                    <!-- Header Row: SKU, Size Badges & Action Buttons -->
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-xs font-mono text-slate-400 font-bold select-none">
                                                {idx + 1}.
                                            </span>
                                            <code class="px-2.5 py-1 bg-amber-50 dark:bg-amber-950/70 text-amber-700 dark:text-amber-300 font-mono font-bold text-xs rounded-lg border border-amber-200 dark:border-amber-800/80 select-all">
                                                {m.sku}
                                            </code>

                                            <!-- Size Badge Highlight -->
                                            {#if (m.sizes && m.sizes.length > 1) || m.has_multiple_sizes}
                                                <span class="px-2.5 py-0.5 text-[11px] font-extrabold rounded-full bg-purple-100 dark:bg-purple-950/80 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800 flex items-center gap-1 shadow-2xs">
                                                    <Sparkles class="w-3 h-3 text-purple-500" />
                                                    <span>{m.sizes ? m.sizes.length : 2} Ukuran:</span>
                                                    <span class="underline decoration-purple-400">{m.sizes ? m.sizes.join(", ") : ""}</span>
                                                </span>
                                            {:else if m.sizes && m.sizes.length === 1}
                                                <span class="px-2.5 py-0.5 text-[11px] font-bold rounded-full bg-sky-100 dark:bg-sky-950/80 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800 flex items-center gap-1">
                                                    <span>Ukuran:</span>
                                                    <span class="font-extrabold">{m.sizes[0]}</span>
                                                </span>
                                            {:else}
                                                <span class="px-2 py-0.5 text-[10px] font-medium rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                                                    Ukuran Standar
                                                </span>
                                            {/if}

                                            {#if m.total_qty}
                                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                                    {m.total_qty} pcs ({m.order_count || 1} pesanan)
                                                </span>
                                            {/if}
                                        </div>

                                        <!-- Action Buttons per Item -->
                                        <div class="flex items-center gap-1.5 shrink-0 flex-wrap">
                                            <button
                                                type="button"
                                                onclick={() => copyJsonLine(m.sku)}
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-bold rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-800 dark:text-amber-200 hover:bg-amber-100 dark:hover:bg-amber-900/60 border border-amber-200 dark:border-amber-800 transition-all shadow-xs"
                                                title="Copy satu baris JSON: &quot;Nomor Referensi SKU&quot;: &quot;{m.sku}&quot;,"
                                            >
                                                {#if copiedRowKey === m.sku + "_line"}
                                                    <CheckCheck class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
                                                    <span class="text-emerald-700 dark:text-emerald-300">Tersalin!</span>
                                                {:else}
                                                    <Copy class="w-3.5 h-3.5 text-amber-600" />
                                                    <span>Copy Baris JSON</span>
                                                {/if}
                                            </button>

                                            <button
                                                type="button"
                                                onclick={() => copySkuValueOnly(m.sku)}
                                                class="inline-flex items-center px-2 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition-all"
                                                title="Copy hanya nilai SKU: {m.sku}"
                                            >
                                                {#if copiedRowKey === m.sku + "_raw"}
                                                    <CheckCheck class="w-3.5 h-3.5 text-emerald-500" />
                                                    <span class="text-emerald-600 dark:text-emerald-400 ml-1">Tersalin!</span>
                                                {:else}
                                                    <span>SKU</span>
                                                {/if}
                                            </button>

                                            <button
                                                type="button"
                                                onclick={() => copySkuWithSizes(m)}
                                                class="inline-flex items-center gap-1 px-2 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition-all"
                                                title="Copy SKU beserta daftar ukuran: {m.sku} (Size: {(m.sizes || []).join(', ')})"
                                            >
                                                {#if copiedRowKey === m.sku + "_info"}
                                                    <CheckCheck class="w-3.5 h-3.5 text-emerald-500" />
                                                    <span class="text-emerald-600 dark:text-emerald-400 ml-1">Tersalin!</span>
                                                {:else}
                                                    <Tag class="w-3 h-3 text-slate-500" />
                                                    <span>Info Size</span>
                                                {/if}
                                            </button>

                                            <a
                                                href={`/superadmin/products/create?sku=${encodeURIComponent(m.sku)}&name=${encodeURIComponent(m.product_name || '')}&seller_id=${sellerId || ''}&sizes=${encodeURIComponent((m.sizes || []).join(','))}`}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-bold rounded-lg bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100 dark:hover:bg-emerald-900/50 border border-emerald-200 dark:border-emerald-800 transition-all shadow-xs"
                                                title="Buka form Tambah Produk di tab baru dengan SKU dan ukuran otomatis terisi"
                                            >
                                                <Plus class="w-3.5 h-3.5" />
                                                <span>+ Tambah Produk</span>
                                            </a>
                                        </div>
                                    </div>

                                    <!-- Product Name -->
                                    <div>
                                        <p class="text-xs font-semibold text-slate-800 dark:text-slate-100">
                                            {m.product_name || "Produk Tanpa Nama di JSON"}
                                        </p>
                                    </div>

                                    <!-- Sizes & Variations Details Box -->
                                    <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/60 space-y-2 text-xs">
                                        <!-- List Ukuran Terdeteksi -->
                                        {#if m.sizes && m.sizes.length > 0}
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400">
                                                    Ukuran Terdeteksi ({m.sizes.length}):
                                                </span>
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    {#each m.sizes as sz}
                                                        <span class="px-2.5 py-0.5 rounded-md font-mono font-black text-[11px] bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 shadow-2xs">
                                                            {sz}
                                                        </span>
                                                    {/each}
                                                </div>
                                            </div>
                                        {/if}

                                        <!-- Variasi Asli dari JSON -->
                                        {#if m.variations && m.variations.length > 0}
                                            <div class="flex items-start gap-2 flex-wrap">
                                                <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 mt-0.5">
                                                    Variasi di JSON:
                                                </span>
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    {#each m.variations as vText}
                                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-medium bg-amber-50/70 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 border border-amber-200/60 dark:border-amber-800/40">
                                                            {vText}
                                                        </span>
                                                    {/each}
                                                </div>
                                            </div>
                                        {:else if m.variation}
                                            <div class="text-[11px] text-slate-600 dark:text-slate-300">
                                                <span class="font-bold text-slate-500">Variasi:</span> {m.variation}
                                            </div>
                                        {/if}

                                        <!-- Alert Info jika Lebih dari 1 Ukuran -->
                                        {#if (m.sizes && m.sizes.length > 1) || m.has_multiple_sizes}
                                            <div class="flex items-center gap-1.5 text-[11px] font-medium text-purple-700 dark:text-purple-300 bg-purple-50 dark:bg-purple-950/40 px-2.5 py-1.5 rounded-md border border-purple-200 dark:border-purple-900/60">
                                                <Sparkles class="w-3.5 h-3.5 shrink-0 text-purple-500" />
                                                <span>
                                                    Perhatian: Ditemukan <strong>{m.sizes ? m.sizes.length : 2} ukuran berbeda</strong> ({m.sizes ? m.sizes.join(", ") : ""}) untuk produk ini. Pastikan menambahkan masing-masing varian ukuran tersebut ke dalam produk manual ya bwang!
                                                </span>
                                            </div>
                                        {/if}

                                        {#if m.missing_parts && m.missing_parts.length > 0}
                                            <div class="text-[11px] text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/40 px-2.5 py-1.5 rounded-md border border-rose-200 dark:border-rose-900/60">
                                                Komponen bundle yang belum ada di database: <strong>{m.missing_parts.join(", ")}</strong>
                                            </div>
                                        {/if}
                                    </div>
                                </div>
                            {:else}
                                <div class="py-8 text-center text-slate-400 text-xs">
                                    Tidak ada SKU yang cocok dengan pencarian "{missingSearchQuery}".
                                </div>
                            {/each}
                        </div>
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
                    class="text-xs font-bold uppercase tracking-wider text-cyan-600 dark:text-cyan-400"
                    >Total Pencairan (Netto)</span
                >
                <div
                    class="text-xl font-extrabold text-cyan-600 dark:text-cyan-400 truncate"
                >
                    {formatRupiah(liveTotalSelling)}
                </div>
                <p class="text-[10px] text-slate-400">Pencairan Bersih (Σ Jual × Qty)</p>
            </div>
            <div
                class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400"
                        >Total Bruto</span
                    >
                    {#if liveMarketplaceFees > 0}
                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400" title="Potongan Biaya Admin / Voucher Marketplace">
                            Potongan: -{formatRupiah(liveMarketplaceFees)}
                        </span>
                    {/if}
                </div>
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
                    >Estimasi Profit Netto</span
                >
                <div
                    class="text-xl font-extrabold text-violet-600 dark:text-violet-400 truncate"
                >
                    {formatRupiah(liveEstProfit)}
                </div>
                <p class="text-[10px] font-semibold text-violet-500 dark:text-violet-400 truncate" title="Pencairan ({formatRupiah(liveTotalSelling)}) - Total HPP Modal ({formatRupiah(liveTotalHpp)})">
                    Pencairan - Total HPP Modal
                </p>
            </div>
        </div>

        <!-- Section: Peringatan & Penyesuaian Manual Produk Stok Kosong (0 pcs) / Defisit -->
        {#if deficitItems.length > 0}
            <div
                class="bg-white dark:bg-slate-900 rounded-2xl border-2 border-rose-300 dark:border-rose-800/80 shadow-md p-6 space-y-4"
            >
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-rose-100 dark:border-rose-950 pb-3">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-rose-100 dark:bg-rose-950/70 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0 shadow-xs">
                            <AlertTriangle class="w-5 h-5 animate-pulse" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-extrabold text-rose-950 dark:text-rose-200">
                                    Perhatian: Ditemukan {deficitItems.length} Produk dengan Stok Kosong (0 pcs) / Selisih Gudang
                                </h3>
                                <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                                    {deficitItems.length} Item Defisit
                                </span>
                            </div>
                            <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">
                                Produk berikut memiliki stok 0 atau jumlah barang keluar melebihi stok yang ada di sistem gudang. Kamu bisa menambahkan stok secara manual ke sistem di sini atau menyesuaikan jumlah pcs keluar.
                            </p>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center gap-2 shrink-0">
                        <button
                            type="button"
                            onclick={handleQuickAdjustBulk}
                            disabled={isAdjustingAll || !warehouseId}
                            class="px-3.5 py-2 bg-gradient-to-r from-rose-600 to-pink-600 hover:from-rose-500 hover:to-pink-500 disabled:opacity-50 text-white text-xs font-bold rounded-xl shadow-xs flex items-center gap-1.5 transition-all"
                            title="Tambahkan stok fisik untuk semua item yang defisit sesuai kekurangannya ke gudang aktif"
                        >
                            {#if isAdjustingAll}
                                <RefreshCw class="w-4 h-4 animate-spin" />
                                <span>Menyesuaikan Stok...</span>
                            {:else}
                                <Sparkles class="w-4 h-4" />
                                <span>Top-Up Semua Stok Selisih</span>
                            {/if}
                        </button>
                    </div>
                </div>

                {#if adjustSuccessToast}
                    <div class="p-3 bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 rounded-xl flex items-center gap-2 text-xs font-bold text-emerald-800 dark:text-emerald-300 animate-in fade-in">
                        <CheckCheck class="w-4 h-4 text-emerald-600" />
                        <span>{adjustSuccessToast}</span>
                    </div>
                {/if}

                <!-- Tabel Defisit & Penambahan Manual -->
                <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/40 dark:bg-slate-950/30">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-rose-50/70 dark:bg-rose-950/40 text-rose-900 dark:text-rose-200 font-bold border-b border-rose-100 dark:border-rose-900/50">
                            <tr>
                                <th class="p-3 w-10 text-center">No</th>
                                <th class="p-3">SKU & Varian Produk</th>
                                <th class="p-3 w-32">Mitra Seller</th>
                                <th class="p-3 text-center w-28">Stok Sistem</th>
                                <th class="p-3 text-center w-28">Qty Keluar</th>
                                <th class="p-3 text-center w-32">Status Selisih</th>
                                <th class="p-3 w-60 text-right">Tambah Stok Manual ke Gudang</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800 font-medium">
                            {#each deficitItems as def, idx}
                                <tr class="hover:bg-rose-50/30 dark:hover:bg-rose-950/20 transition-colors">
                                    <td class="p-3 text-center text-slate-400 text-[11px]">{idx + 1}</td>
                                    <td class="p-3">
                                        <div class="font-bold text-slate-800 dark:text-slate-100 truncate max-w-sm">
                                            {def.item.product_name || "Produk"}
                                        </div>
                                        <div class="font-mono text-rose-600 dark:text-rose-400 font-semibold text-[11px] flex items-center gap-1.5 mt-0.5">
                                            <span>{def.item.sku}</span>
                                            {#if def.item.size}
                                                <span class="px-1.5 py-0.2 rounded bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-[10px] font-bold">
                                                    {def.item.size}
                                                </span>
                                            {/if}
                                            {#if def.item.color}
                                                <span class="text-slate-400 text-[10px]">({def.item.color})</span>
                                            {/if}
                                        </div>
                                    </td>
                                    <td class="p-3 text-slate-600 dark:text-slate-400 truncate">
                                        {def.item.seller_name || "Mitra"}
                                    </td>
                                    <td class="p-3 text-center">
                                        <span class="px-2 py-1 rounded-md text-[11px] font-extrabold {def.isZero ? 'bg-rose-200 text-rose-900 dark:bg-rose-900/80 dark:text-rose-200' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'}">
                                            {def.avail} pcs
                                        </span>
                                    </td>
                                    <td class="p-3 text-center">
                                        <input
                                            type="number"
                                            min="1"
                                            bind:value={formItems[def.index].qty}
                                            class="w-16 text-center rounded-xl border border-rose-300 dark:border-rose-700 bg-white dark:bg-slate-900 py-1 text-xs font-extrabold text-rose-600 dark:text-rose-400 focus:ring-2 focus:ring-rose-500"
                                            title="Ubah Qty Keluar jika diperlukan"
                                        />
                                    </td>
                                    <td class="p-3 text-center">
                                        {#if def.isZero}
                                            <span class="text-[11px] font-extrabold text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/60 px-2 py-0.5 rounded-full border border-rose-200 dark:border-rose-800/60">
                                                Stok 0 (Kosong)
                                            </span>
                                        {:else}
                                            <span class="text-[11px] font-extrabold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/60 px-2 py-0.5 rounded-full border border-amber-200 dark:border-amber-800/60">
                                                Kurang {def.diff} pcs
                                            </span>
                                        {/if}
                                    </td>
                                    <td class="p-3 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <div class="relative w-20">
                                                <input
                                                    type="number"
                                                    min="1"
                                                    placeholder="Qty"
                                                    value={adjustInputMap[def.item.product_variant_id!] ?? (def.diff > 0 ? def.diff : def.qtyOut)}
                                                    oninput={(e) => {
                                                        adjustInputMap[def.item.product_variant_id!] = Number((e.target as HTMLInputElement).value);
                                                    }}
                                                    class="w-full text-center rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 py-1 text-xs font-bold text-slate-800 dark:text-slate-100"
                                                />
                                            </div>
                                            <button
                                                type="button"
                                                disabled={adjustingVariantId === def.item.product_variant_id || !warehouseId}
                                                onclick={() => {
                                                    const addQty = adjustInputMap[def.item.product_variant_id!] ?? (def.diff > 0 ? def.diff : def.qtyOut);
                                                    handleQuickAdjustSingle(def.item.product_variant_id!, addQty, def.index);
                                                }}
                                                class="px-2.5 py-1 text-xs font-bold rounded-xl bg-rose-600 hover:bg-rose-700 disabled:opacity-50 text-white flex items-center gap-1 transition-all shadow-xs shrink-0"
                                                title="Tambah stok ke gudang asal sekarang"
                                            >
                                                {#if adjustingVariantId === def.item.product_variant_id}
                                                    <RefreshCw class="w-3.5 h-3.5 animate-spin" />
                                                {:else}
                                                    <Plus class="w-3.5 h-3.5" />
                                                    <span>Tambah Stok</span>
                                                {/if}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            </div>
        {/if}

        <!-- Section 4: Items Form Table (Grouped Per Seller) -->
        <div
            class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden space-y-6 p-6"
        >
            <div
                class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4 gap-3"
            >
                <div>
                    <h3
                        class="text-sm font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2"
                    >
                        <Layers class="w-4 h-4 text-rose-500" />
                        <span>Rincian Item Barang Keluar ({formItems.length} Baris)</span>
                        {#if deficitItems.length > 0}
                            <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                                {deficitItems.length} Selisih / Stok 0
                            </span>
                        {/if}
                    </h3>
                    <p class="text-xs text-slate-400">
                        {#if filterOnlyDeficit}
                            <span class="text-rose-600 dark:text-rose-400 font-bold">⚠️ Menampilkan hanya {deficitItems.length} produk dengan stok kosong / defisit.</span>
                        {:else}
                            Dikelompokkan per-Mitra Seller dengan penentuan Vendor Konveksi & Harga Modal/Jual.
                        {/if}
                    </p>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    {#if deficitItems.length > 0}
                        <button
                            type="button"
                            onclick={() => (filterOnlyDeficit = !filterOnlyDeficit)}
                            class="px-3 py-2 text-xs font-bold rounded-xl transition-all flex items-center gap-1.5 {filterOnlyDeficit
                                ? 'bg-rose-600 text-white shadow-sm'
                                : 'bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800'}"
                        >
                            <AlertTriangle class="w-3.5 h-3.5" />
                            <span>{filterOnlyDeficit ? 'Tampilkan Semua Item' : `Hanya Stok 0 / Selisih (${deficitItems.length})`}</span>
                        </button>
                    {/if}

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
                                            {#if editingVariantIndex === index}
                                                <div class="relative z-20 space-y-1.5 min-w-[280px]">
                                                    <div class="flex items-center gap-1.5">
                                                        <div class="relative flex-1">
                                                            {#if isSearchingVariants}
                                                                <RefreshCw class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-rose-500 animate-spin" />
                                                            {:else}
                                                                <Search class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-rose-500" />
                                                            {/if}
                                                            <input
                                                                type="text"
                                                                bind:value={variantSearchQuery}
                                                                oninput={(e) => onVariantSearchInput((e.target as HTMLInputElement).value, item.seller_id || group.seller_id || sellerId)}
                                                                placeholder="Cari SKU / Nama / Size..."
                                                                class="w-full rounded-xl border border-rose-400 dark:border-rose-600 bg-white dark:bg-slate-900 pl-8 pr-3 py-1.5 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-rose-500 shadow-sm font-medium"
                                                            />
                                                        </div>
                                                        <button
                                                            type="button"
                                                            onclick={() => {
                                                                editingVariantIndex = null;
                                                                variantSearchQuery = "";
                                                            }}
                                                            class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 shrink-0"
                                                            title="Tutup"
                                                        >
                                                            <X class="w-4 h-4" />
                                                        </button>
                                                    </div>

                                                    <!-- Filtered Variants Search List -->
                                                    <div class="max-h-48 overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xl divide-y divide-slate-100 dark:divide-slate-800">
                                                        {#each searchResults.slice(0, 30) as v}
                                                            <button
                                                                type="button"
                                                                onclick={() => {
                                                                    handleVariantSelect(index, v);
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
                                                                {#if isSearchingVariants}
                                                                    Sedang mencari data varian...
                                                                {:else if variantSearchQuery.trim()}
                                                                    Tidak ada varian cocok dengan "{variantSearchQuery}"
                                                                {:else}
                                                                    Ketik SKU atau nama produk untuk mencari varian...
                                                                {/if}
                                                            </div>
                                                        {/each}
                                                    </div>
                                                </div>
                                            {:else if item.product_variant_id}
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
                                                        onclick={() => openVariantSelector(index, item.seller_id || group.seller_id || sellerId)}
                                                        class="px-2 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-rose-50 dark:hover:bg-rose-950/50 text-slate-600 hover:text-rose-600 dark:text-slate-400 dark:hover:text-rose-400 text-[11px] font-bold rounded-lg transition-all flex items-center gap-1 shrink-0 border border-slate-200 dark:border-slate-700"
                                                        title="Ubah Produk / Size"
                                                    >
                                                        <Edit2 class="w-3 h-3 text-rose-500" />
                                                        <span>Ubah</span>
                                                    </button>
                                                </div>
                                            {:else}
                                                <button
                                                    type="button"
                                                    onclick={() => openVariantSelector(index, item.seller_id || group.seller_id || sellerId)}
                                                    class="w-full py-2 px-3 border border-dashed border-rose-300 dark:border-rose-700 hover:border-rose-500 rounded-xl text-rose-600 dark:text-rose-400 hover:bg-rose-50/50 dark:hover:bg-rose-950/30 flex items-center justify-center gap-2 text-xs font-semibold transition-all"
                                                >
                                                    <Search class="w-3.5 h-3.5" />
                                                    <span>Pilih Varian SKU</span>
                                                </button>
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
                    class="flex flex-wrap items-center gap-3 text-xs font-semibold text-slate-600 dark:text-slate-400"
                >
                    <span
                        >Total Qty: <strong
                            class="text-rose-600 dark:text-rose-400 text-sm font-black"
                            >{liveTotalQty} pcs</strong
                        ></span
                    >
                    <span class="hidden md:inline">|</span>
                    <span class="hidden md:inline"
                        >Bruto: <strong
                            class="text-emerald-600 dark:text-emerald-400 font-black"
                            >{formatRupiah(liveBruto)}</strong
                        ></span
                    >
                    <span class="hidden md:inline">|</span>
                    <span class="hidden md:inline"
                        >Pencairan: <strong
                            class="text-cyan-600 dark:text-cyan-400 font-black"
                            >{formatRupiah(liveTotalSelling)}</strong
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

    <!-- Modal Spinner Loading: Chunked Import Streaming -->
    {#if isImporting}
        <div
            class="fixed inset-0 z-50 bg-slate-950/75 backdrop-blur-sm flex items-center justify-center p-4"
        >
            <div
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 max-w-md w-full shadow-2xl text-center space-y-6"
            >
                <!-- Animated Spinner & Pulse Ring -->
                <div class="relative w-20 h-20 mx-auto flex items-center justify-center">
                    <div class="absolute inset-0 rounded-full bg-cyan-500/20 animate-ping"></div>
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-cyan-500 to-rose-500 p-0.5 flex items-center justify-center shadow-lg shadow-cyan-500/30">
                        <div class="w-full h-full bg-white dark:bg-slate-900 rounded-[14px] flex items-center justify-center">
                            <RefreshCw class="w-8 h-8 text-cyan-500 animate-spin" />
                        </div>
                    </div>
                </div>

                <!-- Text & Subtitle -->
                <div class="space-y-1.5">
                    <h3 class="text-base font-extrabold text-slate-800 dark:text-slate-100">
                        Proses Chunked Import Streaming...
                    </h3>
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400 leading-relaxed">
                        {importStatusText || "Memproses dan mencocokkan SKU pesanan dengan stok gudang..."}
                    </p>
                </div>

                <!-- Progress Bar & Percentage -->
                <div class="space-y-2">
                    <div class="flex justify-between items-center text-xs font-bold">
                        <span class="text-slate-600 dark:text-slate-300">
                            Chunk {importCurrentChunk} dari {importTotalChunks || 1}
                        </span>
                        <span class="text-cyan-600 dark:text-cyan-400 text-sm">
                            {importProgress}%
                        </span>
                    </div>
                    <div class="w-full bg-slate-100 dark:bg-slate-800 h-3 rounded-full overflow-hidden p-0.5 border border-slate-200/50 dark:border-slate-700/50">
                        <div
                            class="bg-gradient-to-r from-cyan-500 via-rose-500 to-pink-500 h-full transition-all duration-300 rounded-full"
                            style="width: {importProgress}%"
                        ></div>
                    </div>
                </div>

                <!-- Live Metrics Grid -->
                <div class="grid grid-cols-3 gap-2 pt-2 border-t border-slate-100 dark:border-slate-800 text-center">
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800/60">
                        <span class="text-[10px] font-bold text-slate-400 block uppercase">Total Data</span>
                        <span class="text-xs font-extrabold text-slate-700 dark:text-slate-200 font-mono">
                            {importTotalItems}
                        </span>
                    </div>
                    <div class="p-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-100 dark:border-emerald-900/40">
                        <span class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 block uppercase">Matched</span>
                        <span class="text-xs font-extrabold text-emerald-700 dark:text-emerald-300 font-mono">
                            {importMatchedCount}
                        </span>
                    </div>
                    <div class="p-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-100 dark:border-amber-900/40">
                        <span class="text-[10px] font-bold text-amber-600 dark:text-amber-400 block uppercase">Missing</span>
                        <span class="text-xs font-extrabold text-amber-700 dark:text-amber-300 font-mono">
                            {importMissingCount}
                        </span>
                    </div>
                </div>

                <p class="text-[11px] text-slate-400 dark:text-slate-500 italic">
                    Mohon tunggu sebentar, jangan menutup atau memuat ulang halaman...
                </p>
            </div>
        </div>
    {/if}
</AdminLayout>
