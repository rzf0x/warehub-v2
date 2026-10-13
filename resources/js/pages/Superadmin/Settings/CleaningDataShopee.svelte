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
        TrendingUp,
        Box,
        ListFilter,
        XCircle,
    } from "@lucide/svelte";

    // State Variables
    let masterFileName = $state<string>("");
    let masterRawData = $state<any>(null);
    let masterJsonData = $state<any[]>([]);

    let rawFileNames = $state<string[]>([]);
    let rawJsonsData = $state<any[]>([]);

    let isProcessing = $state<boolean>(false);
    let processProgress = $state<number>(0);
    let activeTab = $state<"matched_data" | "sku_breakdown" | "missing">(
        "matched_data",
    );
    let searchFilter = $state<string>("");
    let exportFileName = $state<string>("baban_cleaned");

    // Specification Metrics Payload
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
        bruto: 0,
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
    let skuBreakdownMore = $state<number>(0);

    let missingOrderIds = $state<Array<{ order_id: string; amount: number }>>(
        [],
    );

    // ==========================================
    // HELPER FUNCTIONS (100% SPEC MATCH)
    // ==========================================

    function parseNum(val: any): number {
        if (val === null || val === undefined || val === "") return 0;
        if (typeof val === "number") return isNaN(val) ? 0 : val;
        let s = String(val).trim().replace(/[Rp\s]/gi, "");
        if (!s) return 0;

        // Format Indonesia: hapus titik sebagai ribuan, ganti koma menjadi titik desimal
        s = s.replace(/\./g, "").replace(",", ".");
        const num = parseFloat(s);
        return isNaN(num) ? 0 : num;
    }

    function unwrapData(parsed: any, keys: string[]): any[] {
        if (!parsed) return [];
        if (Array.isArray(parsed)) return parsed;

        if (typeof parsed === "object") {
            for (const k of keys) {
                if (Array.isArray(parsed[k])) return parsed[k];
            }
        }

        return [parsed];
    }

    function extractMasterOrderId(row: Record<string, any>): string | null {
        const ORDER_ID_REGEX = /^[A-Z0-9_\-]{8,25}$/i;
        const priorityKeys = [
            "Dari",
            "Column3",
            "No. Pesanan",
            "Order/Adjustment ID",
            "order_id",
            "Order ID",
        ];

        for (const k of priorityKeys) {
            if (row[k] !== undefined && row[k] !== null) {
                const val = String(row[k]).trim();
                if (ORDER_ID_REGEX.test(val)) {
                    return val.toUpperCase();
                }
            }
        }

        for (const k of Object.keys(row)) {
            if (row[k] === null || row[k] === undefined) continue;
            const val = String(row[k]).trim();
            if (ORDER_ID_REGEX.test(val) && isNaN(Number(val))) {
                return val.toUpperCase();
            }
        }

        return null;
    }

    function extractMasterAmount(row: Record<string, any>): number {
        if ("Rincian Jumlah Pelepasan Dana" in row || "rincian_jumlah_pelepasan_dana" in row) {
            const col2 = String(row["Column2"] || row["column2"] || "").trim().toUpperCase();
            if (col2 === "ORDER") {
                const val = row["Rincian Jumlah Pelepasan Dana"] ?? row["rincian_jumlah_pelepasan_dana"];
                return parseNum(val);
            }
            return 0;
        }

        if ("Column33" in row || "column33" in row) {
            return parseNum(row["Column33"] ?? row["column33"]);
        }

        for (const k of ["Total Penghasilan", "total_penghasilan", "Jumlah Pencairan", "pencairan", "Total Pembayaran", "amount"]) {
            if (row[k] !== undefined && row[k] !== null) {
                return parseNum(row[k]);
            }
        }

        return 0;
    }

    function extractBruto(masterRaw: any): number {
        let summary: any[] = [];
        if (masterRaw && typeof masterRaw === "object") {
            if (Array.isArray(masterRaw.Summary)) summary = masterRaw.Summary;
            else if (Array.isArray(masterRaw.summary)) summary = masterRaw.summary;
        }

        for (const item of summary) {
            if (!item || typeof item !== "object") continue;
            const lap = String(
                item["Laporan Penghasilan"] || item["laporan_penghasilan"] || item["Column1"] || "",
            ).toLowerCase();
            if (lap.includes("total pendapatan") || lap.includes("1. total pendapatan")) {
                const col4 = item["Column4"] ?? item["column4"] ?? 0;
                return parseNum(col4);
            }
        }

        return 0;
    }

    function extractSize(variationStr: string, skuStr: string): string {
        const text = `${variationStr} ${skuStr}`.trim();
        const match = text.match(
            /(?<![A-Z0-9])(S|M|L|XL|XXL|2XL|3XL|4XL|5XL|XXXL|XXXXL|XXXXXL)(?![A-Z0-9])/i,
        );
        return match ? match[1].toUpperCase() : "-";
    }

    function splitBundleSku(skuRaw: string, variationRaw: string): string[] {
        let sku = skuRaw.trim();
        let variation = variationRaw.trim();

        // 1. Remove bundle marker prefix
        sku = sku.replace(/^(SET|PAKET\d*|BDL|BND|BUNDLE)[-\s]+/i, "");

        // 2. BDL-XXXX -> XXXX
        if (/^BDL-/i.test(sku)) {
            sku = sku.replace(/^BDL-/i, "");
        }

        // 3. Special handling for KAOS POLOS / POLOS variations
        if (sku.toUpperCase() === "KAOS POLOS" && /^polos\s+/i.test(variation)) {
            variation = variation.replace(/^polos\s+/i, "POLOS - ");
        }

        // 4. POLOS - COLOR pattern
        const polosMatches = sku.match(/POLOS\s*-\s*[A-Z]+/gi);
        if (polosMatches && polosMatches.length > 1) {
            return polosMatches.map((s) => s.trim());
        }

        // 5. Replace delimiters ['/', '&', ',', '|', '+'] with '-'
        const cleanStr = sku.replace(/[\/&,|+]/g, "-");
        const parts = cleanStr
            .split("-")
            .map((s) => s.trim())
            .filter(Boolean);

        if (parts.length > 0) {
            return parts;
        }

        return [sku || "NO-SKU"];
    }

    function getUnitCount(skuRaw: string, variationRaw: string): number {
        const codes = splitBundleSku(skuRaw, variationRaw);
        return Math.max(1, codes.length);
    }

    // ==========================================
    // VALIDATION & FILE UPLOAD HANDLERS
    // ==========================================

    let uploadErrorMessage = $state<string>("");

    function parseAndValidateMaster(text: string, fileName: string): { raw: any; items: any[] } {
        let parsed: any;
        try {
            parsed = JSON.parse(text);
        } catch (err: any) {
            const trimmed = text.trim();
            if (trimmed.startsWith("{") && trimmed.endsWith("}")) {
                try {
                    parsed = JSON.parse(`[${trimmed}]`);
                } catch {
                    throw new Error(`Master JSON tidak valid (${fileName}): ${err?.message || "SyntaxError"}`);
                }
            } else {
                throw new Error(`Master JSON tidak valid (${fileName}): ${err?.message || "SyntaxError"}`);
            }
        }

        const unwrapped = unwrapData(parsed, ["Income", "Penghasilan", "orders", "data", "items", "rows"]);
        if (!Array.isArray(unwrapped) || unwrapped.length === 0) {
            throw new Error(`Master Data (${fileName}) kosong atau format tidak sesuai (tidak berisi data settlement).`);
        }

        let hasValidOrderId = false;
        for (const row of unwrapped) {
            if (row && typeof row === "object" && extractMasterOrderId(row)) {
                hasValidOrderId = true;
                break;
            }
        }

        if (!hasValidOrderId) {
            throw new Error(`Master Data (${fileName}) tidak memiliki kolom Order ID yang valid.`);
        }

        return { raw: parsed, items: unwrapped };
    }

    function parseAndValidateRaw(text: string, fileName: string): any[] {
        let parsed: any;
        try {
            parsed = JSON.parse(text);
        } catch (err: any) {
            const trimmed = text.trim();
            if (trimmed.startsWith("{") && trimmed.endsWith("}")) {
                try {
                    parsed = JSON.parse(`[${trimmed}]`);
                } catch {
                    throw new Error(`Raw JSON tidak valid (${fileName}): ${err?.message || "SyntaxError"}`);
                }
            } else {
                throw new Error(`Raw JSON tidak valid (${fileName}): ${err?.message || "SyntaxError"}`);
            }
        }

        const unwrapped = unwrapData(parsed, ["data", "orders", "items", "rows"]);
        if (!Array.isArray(unwrapped) || unwrapped.length === 0) {
            throw new Error(`Raw JSON (${fileName}) kosong atau format tidak sesuai.`);
        }

        let hasOrderId = false;
        for (const item of unwrapped) {
            if (!item || typeof item !== "object") continue;
            const oId = item["No. Pesanan"] || item["Order ID"] || item["order_id"] || item["No Pesanan"] || item["Nomor Pesanan"];
            if (oId && String(oId).trim().length > 0) {
                hasOrderId = true;
                break;
            }
        }

        if (!hasOrderId) {
            throw new Error(`Raw JSON tidak valid (${fileName}): Kolom 'No. Pesanan' / 'Order ID' tidak ditemukan.`);
        }

        return unwrapped;
    }

    function handleMasterFileUpload(event: Event) {
        const input = event.target as HTMLInputElement;
        if (!input.files || input.files.length === 0) return;

        const file = input.files[0];
        uploadErrorMessage = "";

        const reader = new FileReader();
        reader.onload = (e) => {
            try {
                const text = e.target?.result as string;
                const { raw, items } = parseAndValidateMaster(text, file.name);
                masterFileName = file.name;
                masterRawData = raw;
                masterJsonData = items;
            } catch (err: any) {
                const msg = err.message || `Master JSON tidak valid (${file.name})`;
                uploadErrorMessage = msg;
                alert(msg);
                masterFileName = "";
                masterRawData = null;
                masterJsonData = [];
                input.value = "";
            }
        };
        reader.readAsText(file);
    }

    async function handleRawFilesUpload(event: Event) {
        const input = event.target as HTMLInputElement;
        if (!input.files || input.files.length === 0) return;

        const files = Array.from(input.files);
        uploadErrorMessage = "";

        const validFileNames: string[] = [];
        const validJsonsData: any[] = [];
        let errorEncountered = false;

        const promises = files.map((file) => {
            return new Promise<void>((resolve) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    try {
                        const text = e.target?.result as string;
                        const items = parseAndValidateRaw(text, file.name);
                        validFileNames.push(file.name);
                        validJsonsData.push(...items);
                    } catch (err: any) {
                        errorEncountered = true;
                        const msg = err.message || `Raw JSON tidak valid (${file.name})`;
                        uploadErrorMessage = msg;
                        alert(msg);
                    } finally {
                        resolve();
                    }
                };
                reader.onerror = () => {
                    errorEncountered = true;
                    resolve();
                };
                reader.readAsText(file);
            });
        });

        await Promise.all(promises);

        if (validJsonsData.length > 0) {
            rawFileNames = validFileNames;
            rawJsonsData = validJsonsData;
        } else {
            rawFileNames = [];
            rawJsonsData = [];
            input.value = "";
        }
    }

    function resetFiles() {
        masterFileName = "";
        masterRawData = null;
        masterJsonData = [];
        rawFileNames = [];
        rawJsonsData = [];
        exportPayload = {
            total_order_ids_master: 0,
            total_master_amount: 0,
            matched_master_amount: 0,
            total_data_cocok: 0,
            total_pembayaran_kalkulasi: 0,
            total_produk_ditemukan: 0,
            total_qty_product_ditemukan: 0,
            total_qty_satuan: 0,
            total_pesanan_ditemukan: 0,
            bruto: 0,
            data: [],
        };
        skuBreakdown = [];
        skuBreakdownMore = 0;
        missingOrderIds = [];
    }

    // ==========================================
    // DATA CLEANING ENGINE
    // ==========================================

    function runDataCleaning() {
        if (masterJsonData.length === 0) {
            alert("Silakan upload file Master JSON / Settlement terlebih dahulu.");
            return;
        }

        isProcessing = true;
        processProgress = 10;

        setTimeout(() => {
            // 1. Ekstraksi Bruto dari Summary Master Data
            const brutoValue = extractBruto(masterRawData);

            processProgress = 25;

            // 2. Map Master Data Orders & Accumulated Nominals
            const masterMapping: Record<string, number> = {};
            let totalMasterAmt = 0;

            masterJsonData.forEach((row) => {
                if (!row || typeof row !== "object") return;

                const orderId = extractMasterOrderId(row);
                if (!orderId) return;

                const amt = extractMasterAmount(row);

                if (masterMapping[orderId] === undefined) {
                    masterMapping[orderId] = 0;
                }
                masterMapping[orderId] += amt;
                totalMasterAmt += amt;
            });

            const totalOrderIdsMasterCount = Object.keys(masterMapping).length;

            processProgress = 55;

            // 3. Process & Deduplicate Raw Shopee Data
            const dedupSet = new Set<string>();
            const orderRawItemsMap: Record<string, any[]> = {};

            rawJsonsData.forEach((item) => {
                if (!item || typeof item !== "object") return;

                const orderId = String(
                    item["No. Pesanan"] ||
                        item["Order ID"] ||
                        item["order_id"] ||
                        "",
                ).trim().toUpperCase();

                if (!orderId) return;

                const productName = String(
                    item["Nama Produk"] || item["product_name"] || "",
                ).trim();
                const variantName = String(
                    item["Nama Variasi"] || item["variant_name"] || "",
                ).trim();

                // Deduplikasi Baris Mentah: No. Pesanan + '||' + Nama Produk + '||' + Nama Variasi
                const dedupKey = `${orderId}||${productName}||${variantName}`;
                if (dedupSet.has(dedupKey)) return;
                dedupSet.add(dedupKey);

                // Check if Order ID exists in Master
                if (masterMapping[orderId] === undefined) return;

                if (!orderRawItemsMap[orderId]) {
                    orderRawItemsMap[orderId] = [];
                }
                orderRawItemsMap[orderId].push(item);
            });

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

            Object.keys(orderRawItemsMap).forEach((orderId) => {
                const rawItems = orderRawItemsMap[orderId];
                const masterAmt = masterMapping[orderId] || 0;
                const itemCount = rawItems.length;

                // Hitung total raw payment per item untuk rasio pembagian
                let rawPaySums = 0;
                const rawPays: number[] = [];
                rawItems.forEach((rawItem, idx) => {
                    const p = parseNum(
                        rawItem["Total Pembayaran"] ??
                            rawItem["Subtotal Pesanan"] ??
                            0,
                    );
                    rawPays[idx] = p;
                    rawPaySums += Math.max(0, p);
                });

                // Alokasikan nominal master secara proporsional ke tiap baris item
                const allocatedPays: number[] = [];
                if (masterAmt > 0) {
                    let runningAlloc = 0;
                    for (let i = 0; i < itemCount; i++) {
                        if (i === itemCount - 1) {
                            allocatedPays[i] = masterAmt - runningAlloc;
                        } else {
                            if (rawPaySums > 0) {
                                const ratio = Math.max(0, rawPays[i]) / rawPaySums;
                                const alloc = Math.round(masterAmt * ratio);
                                allocatedPays[i] = alloc;
                                runningAlloc += alloc;
                            } else {
                                const alloc = Math.round(masterAmt / itemCount);
                                allocatedPays[i] = alloc;
                                runningAlloc += alloc;
                            }
                        }
                    }
                } else {
                    rawItems.forEach((_, idx) => {
                        allocatedPays[idx] = rawPays[idx];
                    });
                }

                let orderMatchedAny = false;

                rawItems.forEach((item, idx) => {
                    const itemPembayaran = allocatedPays[idx] || 0;

                    // STRICT FILTERING: Abaikan baris dengan Total Pembayaran 0 atau minus
                    if (itemPembayaran <= 0) return;

                    orderMatchedAny = true;

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
                    const resi = String(
                        item["No. Resi"] ||
                            item["Tracking No"] ||
                            item["resi"] ||
                            "-",
                    ).trim();
                    const rawQty = Math.max(
                        1,
                        parseInt(
                            String(
                                item["Jumlah"] ||
                                    item["Qty"] ||
                                    item["quantity"] ||
                                    "1",
                            ),
                            10,
                        ) || 1,
                    );

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

                    // Bundle SKU parsing & unit count calculation
                    const unitCount = getUnitCount(rawSku, variantName);
                    const effectiveLineQuantity = rawQty * unitCount;
                    totalQtySatuanSum += effectiveLineQuantity;

                    // Accumulate SKU Breakdown
                    const subCodes = splitBundleSku(rawSku, variantName);
                    const size = extractSize(variantName, rawSku);

                    subCodes.forEach((code) => {
                        const bKey = `${code.toUpperCase()}||${size}`;
                        if (!skuMap[bKey]) {
                            skuMap[bKey] = {
                                sku: code,
                                size: size,
                                total_qty: 0,
                                order_count: 0,
                            };
                        }
                        skuMap[bKey].total_qty += rawQty;
                        skuMap[bKey].order_count += 1;
                    });
                });

                if (orderMatchedAny) {
                    matchedOrdersMap[orderId] = masterAmt;
                }
            });

            processProgress = 85;

            // 4. Missing Order IDs
            const missingList: Array<{ order_id: string; amount: number }> = [];
            Object.keys(masterMapping).forEach((orderId) => {
                if (matchedOrdersMap[orderId] === undefined) {
                    missingList.push({
                        order_id: orderId,
                        amount: masterMapping[orderId],
                    });
                }
            });

            const matchedMasterAmountSum = Object.values(
                matchedOrdersMap,
            ).reduce((a, b) => a + b, 0);

            // Sort SKU breakdown descending
            const sortedBreakdown = Object.values(skuMap).sort(
                (a, b) => b.total_qty - a.total_qty,
            );

            // Update Target Export Payload
            exportPayload = {
                total_order_ids_master: totalOrderIdsMasterCount,
                total_master_amount: Math.round(totalMasterAmt),
                matched_master_amount: Math.round(matchedMasterAmountSum),
                total_data_cocok: matchedDataItems.length,
                total_pembayaran_kalkulasi: Math.round(
                    totalPembayaranKalkulasiSum,
                ),
                total_produk_ditemukan: matchedDataItems.length,
                total_qty_product_ditemukan: totalQtyProductDitemukanSum,
                total_qty_satuan: totalQtySatuanSum,
                total_pesanan_ditemukan: Object.keys(matchedOrdersMap).length,
                bruto: Math.round(brutoValue),
                data: matchedDataItems,
            };

            skuBreakdown = sortedBreakdown.slice(0, 150);
            skuBreakdownMore = Math.max(0, sortedBreakdown.length - 150);
            missingOrderIds = missingList;

            processProgress = 100;
            setTimeout(() => {
                isProcessing = false;
            }, 300);
        }, 50);
    }

    // ==========================================
    // EXPORT DOWNLOAD HANDLERS
    // ==========================================

    function downloadCleanedJson() {
        if (exportPayload.data.length === 0) {
            alert("Belum ada data hasil cleaning untuk di-export.");
            return;
        }

        const jsonString = JSON.stringify(exportPayload, null, 2);
        const blob = new Blob([jsonString], { type: "application/json" });
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        const name = exportFileName.trim() || "cleaned_data";
        a.download = `CD_Shopee_${name}.json`;
        a.click();
        URL.revokeObjectURL(url);
    }

    function downloadCleanedTxt() {
        if (exportPayload.data.length === 0) {
            alert("Belum ada data hasil cleaning untuk di-export.");
            return;
        }

        const headers = [
            "No. Pesanan",
            "No. Resi",
            "Nomor Referensi SKU",
            "Nama Variasi",
            "Jumlah",
            "Total Pembayaran",
            "Nama Produk",
        ];

        let txtContent = headers.join("\t") + "\n";
        exportPayload.data.forEach((row) => {
            const line = [
                row["No. Pesanan"],
                row["No. Resi"],
                row["Nomor Referensi SKU"],
                row["Nama Variasi"],
                row["Jumlah"],
                row["Total Pembayaran"],
                row["Nama Produk"],
            ].join("\t");
            txtContent += line + "\n";
        });

        const blob = new Blob([txtContent], { type: "text/plain;charset=utf-8" });
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        const name = exportFileName.trim() || "cleaned_data";
        a.download = `CD_Shopee_${name}.txt`;
        a.click();
        URL.revokeObjectURL(url);
    }

    function formatRupiah(num: number) {
        return new Intl.NumberFormat("id-ID", {
            style: "currency",
            currency: "IDR",
            maximumFractionDigits: 0,
        }).format(num || 0);
    }

    // Filtered lists for table search
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

    let filteredMissingOrderIds = $derived(
        missingOrderIds.filter(
            (item) =>
                !searchFilter.trim() ||
                item.order_id
                    .toLowerCase()
                    .includes(searchFilter.toLowerCase()),
        ),
    );

    let paymentDiff = $derived(
        exportPayload.total_pembayaran_kalkulasi - exportPayload.matched_master_amount,
    );

    let isPaymentMatched = $derived(
        exportPayload.matched_master_amount > 0 &&
            exportPayload.total_pembayaran_kalkulasi > 0 &&
            Math.abs(paymentDiff) === 0,
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
                        >Shopee Reconciliation Engine</span
                    >
                    <span class="text-xs text-orange-200"
                        >Revisi 100% Fitur Optimal & Efisien</span
                    >
                </div>
                <h1
                    class="text-2xl md:text-3xl font-extrabold tracking-tight flex items-center gap-3"
                >
                    <FileCheck class="w-7 h-7 text-orange-200" />
                    Shopee Data Cleaning & Financial Reconciliation Tool
                </h1>
                <p class="text-orange-100 text-sm max-w-xl">
                    Rekonsiliasi transaksi mentah marketplace dengan Master Data Akuntansi. Mendukung pemecahan SKU Bundle, alokasi pembayaran single-row, dan ekspor JSON/TXT.
                </p>
            </div>
            {#if masterFileName || rawFileNames.length > 0}
                <button
                    onclick={resetFiles}
                    class="relative z-10 px-4 py-2 bg-white/10 hover:bg-white/20 text-white text-xs font-bold rounded-xl backdrop-blur-md flex items-center gap-2 self-start md:self-auto transition-all cursor-pointer"
                >
                    <Trash2 class="w-4 h-4" />
                    <span>Reset Data</span>
                </button>
            {/if}
        </div>

        <!-- File Upload Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Master File Upload -->
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
                        1. Master Data File (Pencairan / Settlement)
                    </h3>
                    {#if masterFileName}
                        <span
                            class="px-2.5 py-1 bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 text-xs font-bold rounded-lg flex items-center gap-1"
                        >
                            <CheckCircle2 class="w-3.5 h-3.5" /> Ready ({masterJsonData.length} records)
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
                                "Upload Master Data File (.JSON / .TXT)"}
                        </span>
                        <span class="text-xs text-slate-400 mt-1 block"
                            >File tunggal settlement / pencairan dari sistem akuntansi (maks 30MB)</span
                        >
                    </div>
                    <input
                        type="file"
                        accept=".json,.txt"
                        onchange={handleMasterFileUpload}
                        class="hidden"
                    />
                </label>
            </div>

            <!-- Raw Shopee Files Upload -->
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
                        2. Raw Shopee Files (Data Pesanan Mentah)
                    </h3>
                    {#if rawFileNames.length > 0}
                        <span
                            class="px-2.5 py-1 bg-indigo-100 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 text-xs font-bold rounded-lg flex items-center gap-1"
                        >
                            <CheckCircle2 class="w-3.5 h-3.5" />
                            {rawFileNames.length} Files Loaded ({rawJsonsData.length} items)
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
                                : "Upload Raw Shopee Files (.JSON / .TXT)"}
                        </span>
                        <span class="text-xs text-slate-400 mt-1 block"
                            >Bisa upload beberapa file sekaligus (maks 30MB per file)</span
                        >
                    </div>
                    <input
                        type="file"
                        accept=".json,.txt"
                        multiple
                        onchange={handleRawFilesUpload}
                        class="hidden"
                    />
                </label>
            </div>
        </div>

        <!-- Action & Export Control Bar -->
        <div
            class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm flex flex-col lg:flex-row items-center justify-between gap-4"
        >
            <div class="flex items-center gap-3 w-full lg:w-auto">
                <button
                    onclick={runDataCleaning}
                    disabled={isProcessing || masterJsonData.length === 0}
                    class="px-6 py-3 bg-gradient-to-r from-orange-600 to-red-600 hover:from-orange-700 hover:to-red-700 text-white font-bold text-sm rounded-xl shadow-lg shadow-orange-600/30 disabled:opacity-50 flex items-center gap-2 cursor-pointer transition-all w-full lg:w-auto justify-center"
                >
                    {#if isProcessing}
                        <RefreshCw class="w-4 h-4 animate-spin" />
                        <span>Memproses ({processProgress}%)...</span>
                    {:else}
                        <RefreshCw class="w-4 h-4" />
                        <span>Proses Data Cleaning Now</span>
                    {/if}
                </button>
            </div>

            <!-- Custom Filename & Export Downloads -->
            <div class="flex flex-col sm:flex-row items-center gap-3 w-full lg:w-auto">
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 whitespace-nowrap">
                        Prefix: CD_Shopee_
                    </span>
                    <input
                        type="text"
                        bind:value={exportFileName}
                        placeholder="Filename..."
                        class="px-3 py-2 border border-slate-200 dark:border-slate-800 rounded-xl bg-slate-50 dark:bg-slate-950 text-xs font-semibold text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-orange-500 w-full sm:w-40"
                    />
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <button
                        onclick={downloadCleanedJson}
                        disabled={exportPayload.data.length === 0 || !exportFileName.trim()}
                        class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-md shadow-emerald-600/20 disabled:opacity-50 flex items-center gap-2 cursor-pointer transition-all flex-1 sm:flex-initial justify-center"
                        title="Export JSON Format"
                    >
                        <Download class="w-4 h-4" />
                        <span>Export JSON</span>
                    </button>

                    <button
                        onclick={downloadCleanedTxt}
                        disabled={exportPayload.data.length === 0 || !exportFileName.trim()}
                        class="px-4 py-2.5 bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs rounded-xl shadow-md shadow-sky-600/20 disabled:opacity-50 flex items-center gap-2 cursor-pointer transition-all flex-1 sm:flex-initial justify-center"
                        title="Export TXT Format (TSV Table)"
                    >
                        <Download class="w-4 h-4" />
                        <span>Export TXT</span>
                    </button>
                </div>
            </div>
        </div>

        {#if exportPayload.data.length > 0}
            <!-- Financial Reconciliation Match Banner -->
            <div
                class="p-5 rounded-2xl border flex flex-col sm:flex-row items-center justify-between gap-4 transition-all shadow-md {isPaymentMatched
                    ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-900 dark:text-emerald-100'
                    : 'bg-amber-500/10 border-amber-500/30 text-amber-900 dark:text-amber-100'}"
            >
                <div class="flex items-center gap-3.5">
                    <div
                        class="p-3 rounded-xl shadow-xs {isPaymentMatched
                            ? 'bg-emerald-600 text-white'
                            : 'bg-amber-600 text-white'}"
                    >
                        {#if isPaymentMatched}
                            <CheckCircle2 class="w-6 h-6" />
                        {:else}
                            <AlertTriangle class="w-6 h-6" />
                        {/if}
                    </div>
                    <div class="space-y-0.5">
                        <h4 class="font-extrabold text-sm sm:text-base flex items-center gap-2">
                            Hasil Rekonsiliasi Pembayaran: {isPaymentMatched
                                ? '100% MATCHED (Sesuai Master Pencairan)'
                                : 'Ada Selisih Perhitungan'}
                        </h4>
                        <p class="text-xs opacity-90">
                            Total Pembayaran Hasil Kalkulasi: <span class="font-black text-emerald-600 dark:text-emerald-400">{formatRupiah(exportPayload.total_pembayaran_kalkulasi)}</span>
                            &nbsp;•&nbsp;
                            Matched Master Pencairan: <span class="font-black">{formatRupiah(exportPayload.matched_master_amount)}</span>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    {#if isPaymentMatched}
                        <span
                            class="px-4 py-2 bg-emerald-600 text-white text-xs font-black rounded-xl shadow-md flex items-center gap-1.5"
                        >
                            <Check class="w-4 h-4" /> 100% MATCHED (Selisih Rp 0)
                        </span>
                    {:else if exportPayload.total_pembayaran_kalkulasi > 0}
                        <span
                            class="px-4 py-2 bg-amber-600 text-white text-xs font-black rounded-xl shadow-md flex items-center gap-1.5"
                        >
                            <AlertTriangle class="w-4 h-4" /> Selisih: {formatRupiah(paymentDiff)}
                        </span>
                    {/if}
                </div>
            </div>
        {/if}

        <!-- Target Metric Cards -->
        <div
            class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4"
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
                    {exportPayload.total_order_ids_master}
                </div>
                <div
                    class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 flex items-center gap-1"
                >
                    <CheckCircle2 class="w-3.5 h-3.5" /> Matched: {exportPayload.total_pesanan_ditemukan}
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
                    class="text-base font-black text-slate-800 dark:text-slate-100"
                >
                    {formatRupiah(exportPayload.total_master_amount)}
                </div>
                <div class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                    Matched: {formatRupiah(exportPayload.matched_master_amount)}
                </div>
            </div>

            <div
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl shadow-xs space-y-1"
            >
                <span
                    class="text-[11px] font-bold uppercase tracking-wider text-slate-400"
                    >Total Pembayaran</span
                >
                <div
                    class="text-base font-black text-emerald-600 dark:text-emerald-400"
                >
                    {formatRupiah(exportPayload.total_pembayaran_kalkulasi)}
                </div>
                <div class="text-[11px] font-semibold text-slate-500">
                    {#if isPaymentMatched}
                        <span class="text-emerald-600 dark:text-emerald-400 font-bold">✓ Match dengan Master</span>
                    {:else if exportPayload.total_pembayaran_kalkulasi > 0}
                        <span class="text-amber-600 dark:text-amber-400 font-bold">Selisih: {formatRupiah(paymentDiff)}</span>
                    {:else}
                        <span>Kalkulasi Hasil Data</span>
                    {/if}
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
                    Raw Qty Sum
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
                    class="text-xl font-black text-rose-600 dark:text-rose-400"
                >
                    {exportPayload.total_qty_satuan} Pcs
                </div>
                <div class="text-[11px] font-semibold text-rose-500">
                    Bundle Unit Split
                </div>
            </div>

            <div
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl shadow-xs space-y-1"
            >
                <span
                    class="text-[11px] font-bold uppercase tracking-wider text-slate-400"
                    >Pendapatan Bruto</span
                >
                <div
                    class="text-base font-black text-purple-600 dark:text-purple-400"
                >
                    {formatRupiah(exportPayload.bruto)}
                </div>
                <div class="text-[11px] font-semibold text-slate-500">
                    Master Summary
                </div>
            </div>
        </div>

        <!-- Result Tables Section -->
        <div
            class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-6"
        >
            <!-- Navigation Tabs & Search -->
            <div
                class="flex flex-col sm:flex-row justify-between items-center gap-4 border-b border-slate-100 dark:border-slate-800 pb-4"
            >
                <div class="flex items-center gap-2 flex-wrap">
                    <button
                        onclick={() => (activeTab = "matched_data")}
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer {activeTab ===
                        'matched_data'
                            ? 'bg-orange-600 text-white shadow-md shadow-orange-600/20'
                            : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'}"
                    >
                        Data Cocok Cleaned ({filteredMatchedData.length})
                    </button>
                    <button
                        onclick={() => (activeTab = "sku_breakdown")}
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer {activeTab ===
                        'sku_breakdown'
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'}"
                    >
                        Breakdown Qty SKU Satuan ({filteredSkuBreakdown.length})
                        {#if skuBreakdownMore > 0}
                            <span class="ml-1 text-[10px] opacity-80">(+{skuBreakdownMore} lainnya)</span>
                        {/if}
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
                <div class="overflow-x-auto max-h-[480px]">
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
                                        class="p-8 text-center text-slate-400 font-sans"
                                        >Belum ada data cocok. Silakan upload &amp; proses file.</td
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
                                            class="p-3 text-right font-bold {row['Total Pembayaran'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'}"
                                        >
                                            {formatRupiah(row["Total Pembayaran"])}
                                        </td>
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
                <div class="overflow-x-auto max-h-[480px]">
                    <table class="w-full text-left text-xs">
                        <thead
                            class="bg-slate-50 dark:bg-slate-950 text-slate-500 font-semibold uppercase sticky top-0 border-b border-slate-200 dark:border-slate-800"
                        >
                            <tr>
                                <th class="p-3">SKU Satuan (Sub-Code)</th>
                                <th class="p-3">Ukuran / Size</th>
                                <th class="p-3 text-center">Frekuensi Order</th>
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
                                        class="p-8 text-center text-slate-400 font-sans"
                                        >Belum ada data breakdown SKU. Silakan upload &amp; proses file.</td
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
                <div class="overflow-x-auto max-h-[480px]">
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
                            {#if filteredMissingOrderIds.length === 0}
                                <tr>
                                    <td
                                        colspan="3"
                                        class="p-8 text-center text-emerald-500 font-semibold font-sans"
                                    >
                                        {#if missingOrderIds.length === 0}
                                            Semua Order ID berhasil terhubung 100%! Tidak ada Order ID missing.
                                        {:else}
                                            Tidak ada hasil pencarian Order ID missing.
                                        {/if}
                                    </td>
                                </tr>
                            {:else}
                                {#each filteredMissingOrderIds as row}
                                    <tr
                                        class="hover:bg-rose-50/30 dark:hover:bg-rose-950/20"
                                    >
                                        <td
                                            class="p-3 font-bold text-rose-600 dark:text-rose-400"
                                            >{row.order_id}</td
                                        >
                                        <td class="p-3 text-right font-bold text-slate-700 dark:text-slate-200"
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
