<script lang="ts">
    import AdminLayout from "@/layouts/AdminLayout.svelte";
    import {
        FileCheck,
        Upload,
        Trash2,
        Download,
        Search,
        RefreshCw,
        CheckCircle2,
        AlertTriangle,
        Layers,
        DollarSign,
        Package,
        Filter,
        FileText,
        ShoppingBag,
        Check,
    } from "@lucide/svelte";

    let masterFileName = $state<string>("");
    let masterJsonData = $state<any[]>([]);
    let rawFileNames = $state<string[]>([]);
    let rawJsonsData = $state<any[]>([]);

    let isProcessing = $state<boolean>(false);
    let processProgress = $state<number>(0);
    let activeTab = $state<"matched_data" | "sku_breakdown" | "missing">(
        "matched_data",
    );
    let searchFilter = $state<string>("");
    let exportFileName = $state<string>("CD_Shopee_baban_cleaned");

    // Schema Metrics Matching User Target
    let exportPayload = $state({
        total_order_ids_master: 0,
        total_master_amount: 0,
        matched_master_amount: 0,
        total_data_cocok: 0,
        total_pembayaran_kalkulasi: 0,
        total_produk_ditemukan: 0,
        total_qty_product_ditemukan: 0,
        total_qty_satuan: 0,
        total_pesanan_ditemukan: 0,
        data: [] as Array<{
            "No. Pesanan": string;
            "No. Resi": string;
            "Nomor Referensi SKU": string;
            "Nama Variasi": string;
            Jumlah: number;
            "Total Pembayaran": number;
            "Nama Produk": string;
        }>,
    });

    let skuBreakdown = $state<
        Array<{
            sku: string;
            size: string;
            total_qty: number;
            order_count: number;
        }>
    >([]);
    let missingOrderIds = $state<Array<{ order_id: string; amount: number }>>(
        [],
    );

    // Helper to unwrap JSON payload if wrapped in { Income: [...] } or { orders: [...] }
    function unwrapData(parsed: any): any[] {
        if (!parsed) return [];
        if (Array.isArray(parsed)) return parsed;

        if (typeof parsed === "object") {
            if (Array.isArray(parsed.Income)) return parsed.Income;
            if (Array.isArray(parsed.orders)) return parsed.orders;
            if (Array.isArray(parsed.data)) return parsed.data;
            if (Array.isArray(parsed.items)) return parsed.items;
            if (Array.isArray(parsed.rows)) return parsed.rows;
        }

        return [parsed];
    }

    // File Handlers
    function handleMasterFileUpload(event: Event) {
        const input = event.target as HTMLInputElement;
        if (!input.files || input.files.length === 0) return;

        const file = input.files[0];
        masterFileName = file.name;

        const reader = new FileReader();
        reader.onload = (e) => {
            try {
                const parsed = JSON.parse(e.target?.result as string);
                masterJsonData = unwrapData(parsed);
            } catch (err) {
                alert("Format file Master JSON tidak valid!");
                masterFileName = "";
                masterJsonData = [];
            }
        };
        reader.readAsText(file);
    }

    function handleRawFilesUpload(event: Event) {
        const input = event.target as HTMLInputElement;
        if (!input.files || input.files.length === 0) return;

        const files = Array.from(input.files);
        rawFileNames = files.map((f) => f.name);
        rawJsonsData = [];

        files.forEach((file) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                try {
                    const parsed = JSON.parse(e.target?.result as string);
                    const unwrapped = unwrapData(parsed);
                    rawJsonsData.push(...unwrapped);
                } catch (err) {
                    console.error("Err parsing raw file:", file.name);
                }
            };
            reader.readAsText(file);
        });
    }

    // Processing Logic Target Schema Matching
    function runDataCleaning() {
        if (masterJsonData.length === 0) {
            alert(
                "Silakan upload file Master JSON (Settlement) terlebih dahulu.",
            );
            return;
        }

        isProcessing = true;
        processProgress = 20;

        setTimeout(() => {
            // 1. Process Master Orders & Amounts
            const masterOrdersMap: Record<string, number> = {};
            let masterAmountSum = 0;

            masterJsonData.forEach((row) => {
                if (!row || typeof row !== "object") return;
                let orderId = "";
                let amt = 0;

                Object.keys(row).forEach((k) => {
                    const val = String(row[k] || "").trim();
                    if (/^[0-9A-Z]{14,16}$/i.test(val) && isNaN(Number(val))) {
                        orderId = val;
                    }
                    const lowerKey = k.toLowerCase();
                    if (
                        [
                            "column33",
                            "total penghasilan",
                            "jumlah pencairan",
                            "pencairan",
                            "total_pembayaran",
                            "total no. pesanan",
                            "amount",
                        ].includes(lowerKey)
                    ) {
                        const cleanVal = val
                            .replace(/[Rp.\s]/g, "")
                            .replace(",", ".");
                        if (!isNaN(Number(cleanVal)) && cleanVal !== "") {
                            amt = parseFloat(cleanVal);
                        }
                    }
                });

                if (orderId) {
                    if (masterOrdersMap[orderId] === undefined) {
                        masterOrdersMap[orderId] = amt;
                        masterAmountSum += amt;
                    }
                }
            });

            processProgress = 50;

            // 2. Deduplication (No. Pesanan + Nama Produk + Nama Variasi)
            const dedupMap: Record<string, boolean> = {};
            const matchedOrdersMap: Record<string, number> = {};
            const matchedDataItems: Array<any> = [];
            const skuMap: Record<
                string,
                {
                    sku: string;
                    size: string;
                    total_qty: number;
                    order_count: number;
                }
            > = {};

            let totalPembayaranKalkulasiSum = 0;
            let totalQtyProductDitemukanSum = 0;
            let totalQtySatuanSum = 0;

            rawJsonsData.forEach((item) => {
                if (!item || typeof item !== "object") return;
                const orderId = String(
                    item["No. Pesanan"] ||
                        item["Order ID"] ||
                        item["order_id"] ||
                        "",
                ).trim();
                const resi = String(
                    item["No. Resi"] ||
                        item["Tracking No"] ||
                        item["resi"] ||
                        "-",
                ).trim();
                const productName = String(
                    item["Nama Produk"] || item["product_name"] || "",
                ).trim();
                const variantName = String(
                    item["Nama Variasi"] || item["variant_name"] || "",
                ).trim();
                const rawSku = String(
                    item["Nomor Referensi SKU"] ||
                        item["SKU Penjual"] ||
                        item["SKU Induk"] ||
                        item["sku"] ||
                        "NO-SKU",
                ).trim();
                const rawQty =
                    parseInt(
                        item["Jumlah"] ||
                            item["Qty"] ||
                            item["quantity"] ||
                            "1",
                        10,
                    ) || 1;

                const rawPembayaran =
                    item["Total Pembayaran"] ?? item["Subtotal Pesanan"] ?? 0;
                const totalPembayaran =
                    parseFloat(
                        String(rawPembayaran)
                            .replace(/[Rp.\s]/g, "")
                            .replace(",", "."),
                    ) || 0;

                if (!orderId) return;

                const dedupKey = `${orderId}_${productName}_${variantName}`;
                if (dedupMap[dedupKey]) return;
                dedupMap[dedupKey] = true;

                // Match with Master Order ID
                if (masterOrdersMap[orderId] !== undefined) {
                    const masterAmt = masterOrdersMap[orderId];
                    const itemPembayaran =
                        masterAmt > 0 ? masterAmt : totalPembayaran;

                    matchedOrdersMap[orderId] = masterAmt;

                    matchedDataItems.push({
                        "No. Pesanan": orderId,
                        "No. Resi": resi,
                        "Nomor Referensi SKU": rawSku,
                        "Nama Variasi": variantName,
                        Jumlah: rawQty,
                        "Total Pembayaran": itemPembayaran,
                        "Nama Produk": productName,
                    });

                    totalPembayaranKalkulasiSum += itemPembayaran;
                    totalQtyProductDitemukanSum += rawQty;

                    // Parse Shopee Bundle Engine
                    const parsedItems = parseBundleSku(
                        rawSku,
                        variantName,
                        rawQty,
                    );
                    parsedItems.forEach((p) => {
                        totalQtySatuanSum += p.qty;
                        const bKey = `${p.sku}_${p.size}`;
                        if (!skuMap[bKey]) {
                            skuMap[bKey] = {
                                sku: p.sku,
                                size: p.size,
                                total_qty: 0,
                                order_count: 0,
                            };
                        }
                        skuMap[bKey].total_qty += p.qty;
                        skuMap[bKey].order_count += 1;
                    });
                }
            });

            processProgress = 85;

            // 3. Compute Unmatched Missing Order IDs
            const missingList: Array<{ order_id: string; amount: number }> = [];
            Object.keys(masterOrdersMap).forEach((orderId) => {
                if (matchedOrdersMap[orderId] === undefined) {
                    missingList.push({
                        order_id: orderId,
                        amount: masterOrdersMap[orderId],
                    });
                }
            });

            const totalOrderIdsMasterCount =
                Object.keys(masterOrdersMap).length;
            const matchedMasterAmountSum = Object.values(
                matchedOrdersMap,
            ).reduce((a, b) => a + b, 0);

            // Update Target Export Schema
            exportPayload = {
                total_order_ids_master: totalOrderIdsMasterCount,
                total_master_amount: Math.round(masterAmountSum),
                matched_master_amount: Math.round(matchedMasterAmountSum),
                total_data_cocok: matchedDataItems.length,
                total_pembayaran_kalkulasi: Math.round(
                    totalPembayaranKalkulasiSum,
                ),
                total_produk_ditemukan: matchedDataItems.length,
                total_qty_product_ditemukan: totalQtyProductDitemukanSum,
                total_qty_satuan: totalQtySatuanSum,
                total_pesanan_ditemukan: Object.keys(matchedOrdersMap).length,
                data: matchedDataItems,
            };

            skuBreakdown = Object.values(skuMap);
            missingOrderIds = missingList;

            processProgress = 100;
            setTimeout(() => {
                isProcessing = false;
            }, 400);
        }, 100);
    }

    function parseBundleSku(skuStr: string, variationStr: string, qty: number) {
        const results: Array<{ sku: string; size: string; qty: number }> = [];
        const cleanSku = skuStr.trim();
        const cleanVar = variationStr.trim();

        // Extract Size
        let size = "ALL SIZE";
        const sizeMatch = (cleanVar + " " + cleanSku).match(
            /\b(3XL|2XL|XL|L|M|S|XS|XXL|XXXL)\b/i,
        );
        if (sizeMatch) {
            size = sizeMatch[1].toUpperCase();
        }

        // Check delimiter: +, comma, or dash
        let delimiter: string | null = null;
        if (cleanSku.includes("+")) delimiter = "+";
        else if (cleanSku.includes(",")) delimiter = ",";
        else if (/^[A-Z0-9]+-[A-Z0-9]+/i.test(cleanSku)) delimiter = "-";

        if (delimiter) {
            const parts = cleanSku.split(delimiter);
            let prefix = "";
            parts.forEach((part, idx) => {
                let itemSku = part.trim();
                if (idx === 0) {
                    const pm = itemSku.match(/^([A-Z0-9\s]+)\s+([A-Z0-9]+)$/i);
                    if (pm) prefix = pm[1].trim();
                }
                if (
                    idx > 0 &&
                    prefix &&
                    !itemSku.includes(" ") &&
                    !/^[A-Z]{2,}/i.test(itemSku)
                ) {
                    itemSku = `${prefix} ${itemSku}`;
                }

                let pQty = qty;
                const multiplierMatch = itemSku.match(/x(\d+)/i);
                if (multiplierMatch) {
                    pQty *= parseInt(multiplierMatch[1], 10);
                    itemSku = itemSku.replace(/x\d+/i, "").trim();
                }
                results.push({ sku: itemSku, size, qty: pQty });
            });
        } else {
            results.push({ sku: cleanSku, size, qty });
        }

        return results;
    }

    function formatRupiah(num: number) {
        return new Intl.NumberFormat("id-ID", {
            style: "currency",
            currency: "IDR",
            maximumFractionDigits: 0,
        }).format(num || 0);
    }

    function downloadCleanedExport() {
        if (exportPayload.data.length === 0) {
            alert("Belum ada data hasil cleaning untuk di-export.");
            return;
        }

        const jsonString = JSON.stringify(exportPayload, null, 2);
        const blob = new Blob([jsonString], { type: "application/json" });
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = `${exportFileName}.json`;
        a.click();
        URL.revokeObjectURL(url);
    }

    let filteredMatchedData = $derived(
        exportPayload.data.filter(
            (item) =>
                !searchFilter.trim() ||
                item["No. Pesanan"]
                    .toLowerCase()
                    .includes(searchFilter.toLowerCase()) ||
                item["No. Resi"]
                    .toLowerCase()
                    .includes(searchFilter.toLowerCase()) ||
                item["Nomor Referensi SKU"]
                    .toLowerCase()
                    .includes(searchFilter.toLowerCase()) ||
                item["Nama Produk"]
                    .toLowerCase()
                    .includes(searchFilter.toLowerCase()),
        ),
    );

    let filteredSkuBreakdown = $derived(
        skuBreakdown.filter(
            (item) =>
                !searchFilter.trim() ||
                item.sku.toLowerCase().includes(searchFilter.toLowerCase()) ||
                item.size.toLowerCase().includes(searchFilter.toLowerCase()),
        ),
    );
</script>

<AdminLayout
    title="Data Cleaning Shopee - Warehub v2"
    breadcrumbs={[
        { name: "Tools" },
        { name: "Data Cleaning" },
        { name: "Shopee Engine" },
    ]}
>
    <div class="max-w-8xl mx-auto space-y-6">
        <!-- Page Header -->
        <div
            class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-r from-orange-600 via-amber-600 to-red-600 dark:from-slate-900 dark:via-orange-950 dark:to-slate-900 p-6 rounded-2xl text-white shadow-xl relative overflow-hidden"
        >
            <div
                class="absolute right-0 top-0 translate-x-4 -translate-y-4 opacity-10 pointer-events-none"
            >
                <FileCheck class="w-64 h-64" />
            </div>
            <div class="relative z-10 space-y-1">
                <div class="flex items-center gap-2">
                    <span
                        class="px-3 py-1 bg-white/20 text-white text-xs font-semibold rounded-full backdrop-blur-md"
                        >Shopee Engine</span
                    >
                    <span class="text-xs text-orange-200"
                        >Data Cleaning & Settlement Target Format</span
                    >
                </div>
                <h1
                    class="text-2xl md:text-3xl font-extrabold tracking-tight flex items-center gap-3"
                >
                    <FileCheck class="w-7 h-7 text-orange-200" />
                    Data Cleaning Settlement Shopee
                </h1>
                <p class="text-orange-100 text-sm max-w-xl">
                    Deduplikasi otomatis, Order ID matching, kalkulasi
                    pembayaran settlement, parse bundle SKU, dan penyesuaian
                    format JSON export.
                </p>
            </div>
        </div>

        <!-- File Upload Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Master JSON Upload -->
            <div
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3"
                >
                    <h3
                        class="font-bold text-slate-800 dark:text-slate-100 text-base flex items-center gap-2"
                    >
                        <FileText class="w-5 h-5 text-orange-500" />
                        1. Master JSON (Pencairan / Settlement)
                    </h3>
                    {#if masterFileName}
                        <span
                            class="px-2.5 py-1 bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 text-xs font-bold rounded-lg flex items-center gap-1"
                        >
                            <CheckCircle2 class="w-3.5 h-3.5" /> Ready ({masterJsonData.length}
                            records)
                        </span>
                    {/if}
                </div>

                <label class="block cursor-pointer">
                    <div
                        class="border-2 border-dashed border-slate-300 dark:border-slate-700 hover:border-orange-500 dark:hover:border-orange-500 rounded-xl p-6 text-center transition-all bg-slate-50/50 dark:bg-slate-950/40"
                    >
                        <Upload class="w-8 h-8 mx-auto text-orange-500 mb-2" />
                        <span
                            class="block text-sm font-semibold text-slate-700 dark:text-slate-200"
                        >
                            {masterFileName ||
                                "Upload Master JSON File (.json)"}
                        </span>
                        <span class="text-xs text-slate-400 mt-1 block"
                            >Klik untuk memilih file pencairan Shopee</span
                        >
                    </div>
                    <input
                        type="file"
                        accept=".json"
                        onchange={handleMasterFileUpload}
                        class="hidden"
                    />
                </label>
            </div>

            <!-- Raw JSONs Upload -->
            <div
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3"
                >
                    <h3
                        class="font-bold text-slate-800 dark:text-slate-100 text-base flex items-center gap-2"
                    >
                        <Layers class="w-5 h-5 text-indigo-500" />
                        2. Raw JSON Files (Data Pesanan Mentah)
                    </h3>
                    {#if rawFileNames.length > 0}
                        <span
                            class="px-2.5 py-1 bg-indigo-100 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 text-xs font-bold rounded-lg flex items-center gap-1"
                        >
                            <CheckCircle2 class="w-3.5 h-3.5" />
                            {rawFileNames.length} Files Loaded ({rawJsonsData.length}
                            items)
                        </span>
                    {/if}
                </div>

                <label class="block cursor-pointer">
                    <div
                        class="border-2 border-dashed border-slate-300 dark:border-slate-700 hover:border-indigo-500 dark:hover:border-indigo-500 rounded-xl p-6 text-center transition-all bg-slate-50/50 dark:bg-slate-950/40"
                    >
                        <Upload class="w-8 h-8 mx-auto text-indigo-500 mb-2" />
                        <span
                            class="block text-sm font-semibold text-slate-700 dark:text-slate-200"
                        >
                            {rawFileNames.length > 0
                                ? `${rawFileNames.length} File Pesanan Dipilih`
                                : "Upload Multiple Raw JSON Files (.json)"}
                        </span>
                        <span class="text-xs text-slate-400 mt-1 block"
                            >Pilih 1 atau beberapa file pesanan mentah</span
                        >
                    </div>
                    <input
                        type="file"
                        accept=".json"
                        multiple
                        onchange={handleRawFilesUpload}
                        class="hidden"
                    />
                </label>
            </div>
        </div>

        <!-- Action & Processing Control -->
        <div
            class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4"
        >
            <div class="flex items-center gap-3">
                <button
                    onclick={runDataCleaning}
                    disabled={isProcessing || masterJsonData.length === 0}
                    class="px-6 py-3 bg-gradient-to-r from-orange-600 to-red-600 hover:from-orange-700 hover:to-red-700 text-white font-bold text-sm rounded-xl shadow-lg shadow-orange-600/30 disabled:opacity-50 flex items-center gap-2 cursor-pointer transition-all"
                >
                    {#if isProcessing}
                        <RefreshCw class="w-4 h-4 animate-spin" />
                        <span>Memproses Data ({processProgress}%)...</span>
                    {:else}
                        <RefreshCw class="w-4 h-4" />
                        <span>Proses Data Cleaning Now</span>
                    {/if}
                </button>
            </div>

            <!-- Export Controls -->
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <input
                    type="text"
                    bind:value={exportFileName}
                    placeholder="Nama file export..."
                    class="px-3 py-2 border border-slate-200 dark:border-slate-800 rounded-xl bg-slate-50 dark:bg-slate-950 text-xs font-semibold text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-orange-500"
                />

                <button
                    onclick={downloadCleanedExport}
                    disabled={exportPayload.data.length === 0}
                    class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-md shadow-emerald-600/20 disabled:opacity-50 flex items-center gap-2 cursor-pointer transition-all"
                >
                    <Download class="w-4 h-4" />
                    <span>Export Result (.JSON Target Format)</span>
                </button>
            </div>
        </div>

        <!-- Target KPI Metrics Cards (Matching Target Schema) -->
        <div
            class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4"
        >
            <div
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl shadow-xs space-y-1"
            >
                <span
                    class="text-[11px] font-bold uppercase tracking-wider text-slate-400"
                    >Order ID Master</span
                >
                <div
                    class="text-xl font-black text-slate-800 dark:text-slate-100"
                >
                    {exportPayload.total_order_ids_master} Orders
                </div>
                <div
                    class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 flex items-center gap-1"
                >
                    <CheckCircle2 class="w-3.5 h-3.5" /> Found: {exportPayload.total_pesanan_ditemukan}
                </div>
            </div>

            <div
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl shadow-xs space-y-1"
            >
                <span
                    class="text-[11px] font-bold uppercase tracking-wider text-slate-400"
                    >Master Pencairan</span
                >
                <div
                    class="text-lg font-black text-emerald-600 dark:text-emerald-400"
                >
                    {formatRupiah(exportPayload.total_master_amount)}
                </div>
                <div class="text-[11px] font-semibold text-slate-500">
                    Matched: {formatRupiah(exportPayload.matched_master_amount)}
                </div>
            </div>

            <div
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl shadow-xs space-y-1"
            >
                <span
                    class="text-[11px] font-bold uppercase tracking-wider text-slate-400"
                    >Total Data Cocok</span
                >
                <div
                    class="text-xl font-black text-indigo-600 dark:text-indigo-400"
                >
                    {exportPayload.total_data_cocok} Baris
                </div>
                <div class="text-[11px] font-semibold text-slate-500">
                    Kalkulasi: {formatRupiah(
                        exportPayload.total_pembayaran_kalkulasi,
                    )}
                </div>
            </div>

            <div
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl shadow-xs space-y-1"
            >
                <span
                    class="text-[11px] font-bold uppercase tracking-wider text-slate-400"
                    >Qty Product Paket</span
                >
                <div
                    class="text-xl font-black text-amber-600 dark:text-amber-400"
                >
                    {exportPayload.total_qty_product_ditemukan} Paket
                </div>
                <div class="text-[11px] font-semibold text-slate-500">
                    Raw Items Count
                </div>
            </div>

            <div
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl shadow-xs space-y-1"
            >
                <span
                    class="text-[11px] font-bold uppercase tracking-wider text-slate-400"
                    >Qty Satuan (Fisik)</span
                >
                <div
                    class="text-2xl font-black text-rose-600 dark:text-rose-400"
                >
                    {exportPayload.total_qty_satuan} Pcs
                </div>
                <div class="text-[11px] font-semibold text-rose-500">
                    Bundle Breakdown
                </div>
            </div>
        </div>

        <!-- Breakdown & Result Tables -->
        <div
            class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-6"
        >
            <!-- Navigation Tabs -->
            <div
                class="flex flex-col sm:flex-row justify-between items-center gap-4 border-b border-slate-100 dark:border-slate-800 pb-4"
            >
                <div class="flex items-center gap-2">
                    <button
                        onclick={() => (activeTab = "matched_data")}
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer {activeTab ===
                        'matched_data'
                            ? 'bg-orange-600 text-white shadow-md shadow-orange-600/20'
                            : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'}"
                    >
                        Data Cocok Target Format ({filteredMatchedData.length})
                    </button>
                    <button
                        onclick={() => (activeTab = "sku_breakdown")}
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer {activeTab ===
                        'sku_breakdown'
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'}"
                    >
                        Breakdown Qty SKU Satuan ({filteredSkuBreakdown.length})
                    </button>
                    <button
                        onclick={() => (activeTab = "missing")}
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer {activeTab ===
                        'missing'
                            ? 'bg-rose-600 text-white shadow-md shadow-rose-600/20'
                            : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'}"
                    >
                        Missing Order IDs ({missingOrderIds.length})
                    </button>
                </div>

                <div class="relative w-full sm:w-64">
                    <Search
                        class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"
                    />
                    <input
                        type="text"
                        bind:value={searchFilter}
                        placeholder="Cari Order ID / Resi / SKU..."
                        class="w-full pl-9 pr-3 py-1.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-orange-500"
                    />
                </div>
            </div>

            <!-- Tab Content -->
            {#if activeTab === "matched_data"}
                <div class="overflow-x-auto max-h-[450px]">
                    <table class="w-full text-left text-xs">
                        <thead
                            class="bg-slate-50 dark:bg-slate-950 text-slate-500 font-semibold uppercase sticky top-0 border-b border-slate-200 dark:border-slate-800"
                        >
                            <tr>
                                <th class="p-3">No. Pesanan</th>
                                <th class="p-3">No. Resi</th>
                                <th class="p-3">Nomor Referensi SKU</th>
                                <th class="p-3">Nama Variasi</th>
                                <th class="p-3 text-center">Jumlah</th>
                                <th class="p-3 text-right">Total Pembayaran</th>
                                <th class="p-3">Nama Produk</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-slate-800 font-mono"
                        >
                            {#if filteredMatchedData.length === 0}
                                <tr>
                                    <td
                                        colspan="7"
                                        class="p-8 text-center text-slate-400"
                                        >Belum ada data cocok. Silakan upload &
                                        proses file.</td
                                    >
                                </tr>
                            {:else}
                                {#each filteredMatchedData as row}
                                    <tr
                                        class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40"
                                    >
                                        <td
                                            class="p-3 font-bold text-slate-800 dark:text-slate-100"
                                            >{row["No. Pesanan"]}</td
                                        >
                                        <td class="p-3 text-slate-500"
                                            >{row["No. Resi"]}</td
                                        >
                                        <td
                                            class="p-3 font-bold text-orange-600 dark:text-orange-400"
                                            >{row["Nomor Referensi SKU"]}</td
                                        >
                                        <td
                                            class="p-3 font-sans text-slate-600 dark:text-slate-300"
                                            >{row["Nama Variasi"]}</td
                                        >
                                        <td class="p-3 text-center font-bold"
                                            >{row["Jumlah"]}</td
                                        >
                                        <td
                                            class="p-3 text-right font-bold text-emerald-600 dark:text-emerald-400"
                                            >{formatRupiah(
                                                row["Total Pembayaran"],
                                            )}</td
                                        >
                                        <td
                                            class="p-3 font-sans truncate max-w-xs"
                                            >{row["Nama Produk"]}</td
                                        >
                                    </tr>
                                {/each}
                            {/if}
                        </tbody>
                    </table>
                </div>
            {:else if activeTab === "sku_breakdown"}
                <div class="overflow-x-auto max-h-[450px]">
                    <table class="w-full text-left text-xs">
                        <thead
                            class="bg-slate-50 dark:bg-slate-950 text-slate-500 font-semibold uppercase sticky top-0 border-b border-slate-200 dark:border-slate-800"
                        >
                            <tr>
                                <th class="p-3">SKU Penjual (Satuan)</th>
                                <th class="p-3">Ukuran / Size</th>
                                <th class="p-3 text-center">Total Order</th>
                                <th class="p-3 text-right">Total Qty Fisik</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-slate-800 font-mono"
                        >
                            {#if filteredSkuBreakdown.length === 0}
                                <tr>
                                    <td
                                        colspan="4"
                                        class="p-8 text-center text-slate-400"
                                        >Belum ada data breakdown SKU. Silakan
                                        upload & proses file.</td
                                    >
                                </tr>
                            {:else}
                                {#each filteredSkuBreakdown as row}
                                    <tr
                                        class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40"
                                    >
                                        <td
                                            class="p-3 font-bold text-slate-800 dark:text-slate-100"
                                            >{row.sku}</td
                                        >
                                        <td class="p-3">
                                            <span
                                                class="px-2 py-0.5 bg-indigo-100 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 font-sans font-bold rounded text-[11px]"
                                            >
                                                {row.size}
                                            </span>
                                        </td>
                                        <td class="p-3 text-center"
                                            >{row.order_count}</td
                                        >
                                        <td
                                            class="p-3 text-right font-bold text-emerald-600 dark:text-emerald-400"
                                            >{row.total_qty} Pcs</td
                                        >
                                    </tr>
                                {/each}
                            {/if}
                        </tbody>
                    </table>
                </div>
            {:else if activeTab === "missing"}
                <div class="overflow-x-auto max-h-[450px]">
                    <table class="w-full text-left text-xs">
                        <thead
                            class="bg-slate-50 dark:bg-slate-950 text-slate-500 font-semibold uppercase sticky top-0 border-b border-slate-200 dark:border-slate-800"
                        >
                            <tr>
                                <th class="p-3">Order ID (Master)</th>
                                <th class="p-3 text-right">Nilai Pencairan</th>
                                <th class="p-3 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-slate-800 font-mono"
                        >
                            {#if missingOrderIds.length === 0}
                                <tr>
                                    <td
                                        colspan="3"
                                        class="p-8 text-center text-emerald-500 font-semibold font-sans"
                                        >Semua Order ID berhasil terhubung 100%!
                                        Tidak ada Order ID missing.</td
                                    >
                                </tr>
                            {:else}
                                {#each missingOrderIds as row}
                                    <tr
                                        class="hover:bg-rose-50/30 dark:hover:bg-rose-950/20"
                                    >
                                        <td
                                            class="p-3 font-bold text-rose-600 dark:text-rose-400"
                                            >{row.order_id}</td
                                        >
                                        <td class="p-3 text-right font-bold"
                                            >{formatRupiah(row.amount)}</td
                                        >
                                        <td class="p-3 text-center">
                                            <span
                                                class="px-2 py-0.5 bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 font-sans text-[10px] font-bold rounded"
                                                >Unmatched</span
                                            >
                                        </td>
                                    </tr>
                                {/each}
                            {/if}
                        </tbody>
                    </table>
                </div>
            {/if}
        </div>
    </div>
</AdminLayout>
