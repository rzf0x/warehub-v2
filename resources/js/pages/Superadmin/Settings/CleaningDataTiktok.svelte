<script lang="ts">
    import AdminLayout from "@/layouts/AdminLayout.svelte";
    import {
        Video,
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
        Edit3,
        Plus,
        ArrowUpDown,
        Save,
        RotateCcw,
        Copy,
    } from "@lucide/svelte";

    // State Variables
    let masterFileName = $state<string>("");
    let masterRawData = $state<any>(null);
    let masterJsonData = $state<any[]>([]);

    let rawFileNames = $state<string[]>([]);
    let rawJsonsData = $state<any[]>([]);

    let isProcessing = $state<boolean>(false);
    let processProgress = $state<number>(0);

    let activeTab = $state<"matched_data" | "editor" | "sku_breakdown" | "missing">(
        "matched_data",
    );

    let searchFilter = $state<string>("");
    let exportFileName = $state<string>("baban_tiktok_cleaned");
    let uploadErrorMessage = $state<string>("");

    // Sorting state for editor
    let sortMode = $state<"import" | "order_asc" | "order_desc" | "payment_desc">("import");

    // Specification Metrics Target Payload
    let exportPayload = $state({
        total_master: 0,
        total_matched: 0,
        total_payment: 0,
        total_quantity: 0,
        total_produk_ditemukan: 0,
        total_qty_product_ditemukan: 0,
        total_qty_baris_tiktok: 0,
        total_pesanan_ditemukan: 0,
        total_master_amount: 0,
        matched_master_amount: 0,
        total_gmv_ads_amount: 0,
        missing_count: 0,
        bruto: 0,
        data: [] as Array<{
            "No. Pesanan": string;
            "No. Resi": string;
            "Nomor Referensi SKU": string;
            "Nama Variasi": string;
            Jumlah: number;
            "Total Pembayaran": number;
            "Nama Produk": string;
            _import_no?: number;
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

    // Editing State (Matched Rows Editor)
    let editingRowIndex = $state<number | null>(null);

    // ==========================================
    // HELPER FUNCTIONS & BILINGUAL KEYS
    // ==========================================

    function parseNum(val: any): number {
        if (val === null || val === undefined || val === "") return 0;
        if (typeof val === "number") return isNaN(val) ? 0 : val;
        let s = String(val).trim().replace(/[Rp\s]/gi, "");
        if (!s) return 0;

        s = s.replace(/\./g, "").replace(",", ".");
        const num = parseFloat(s);
        return isNaN(num) ? 0 : num;
    }

    function getFieldVal(row: Record<string, any>, candidates: string[]): any {
        if (!row || typeof row !== "object") return null;
        for (const cand of candidates) {
            if (row[cand] !== undefined && row[cand] !== null) {
                return row[cand];
            }
        }
        const keys = Object.keys(row);
        for (const cand of candidates) {
            const cleanCand = cand.trim().toLowerCase();
            const foundKey = keys.find(
                (k) => k.trim().toLowerCase() === cleanCand,
            );
            if (foundKey && row[foundKey] !== undefined && row[foundKey] !== null) {
                return row[foundKey];
            }
        }
        return null;
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

        const unwrapped = unwrapData(parsed, [
            "Order details",
            "Rincian Pesanan",
            "Detail Pesanan",
            "Detail pesanan",
            "OrderSKUList",
            "orders",
            "data",
            "items",
            "rows",
        ]);

        if (!Array.isArray(unwrapped) || unwrapped.length === 0) {
            throw new Error(`Master Data (${fileName}) kosong atau format tidak sesuai (tidak berisi data settlement TikTok).`);
        }

        let hasValidOrderId = false;
        for (const row of unwrapped) {
            if (!row || typeof row !== "object") continue;
            const oId = getFieldVal(row, [
                "Order/adjustment ID",
                "Order/Adjustment ID",
                "ID Pesanan/Penyesuaian",
                "ID Pesanan/penyesuaian",
                "ID pesanan/penyesuaian",
                "Order ID",
                "ID Pesanan",
            ]);
            if (oId && String(oId).trim().length >= 8) {
                hasValidOrderId = true;
                break;
            }
        }

        if (!hasValidOrderId) {
            throw new Error(`Master Data TikTok (${fileName}) tidak memiliki kolom Order ID yang valid.`);
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

        const unwrapped = unwrapData(parsed, [
            "OrderSKUList",
            "OrderList",
            "Order details",
            "orders",
            "data",
            "items",
            "rows",
        ]);

        if (!Array.isArray(unwrapped) || unwrapped.length === 0) {
            throw new Error(`Raw JSON (${fileName}) kosong atau format tidak sesuai.`);
        }

        let hasOrderId = false;
        for (const item of unwrapped) {
            if (!item || typeof item !== "object") continue;
            const oId = getFieldVal(item, [
                "Order ID",
                "ID Pesanan",
                "No. Pesanan",
                "order_id",
            ]);
            if (oId && String(oId).trim().length > 0) {
                hasOrderId = true;
                break;
            }
        }

        if (!hasOrderId) {
            throw new Error(`Raw JSON tidak valid (${fileName}): Kolom 'Order ID' / 'ID Pesanan' tidak ditemukan.`);
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
            total_master: 0,
            total_matched: 0,
            total_payment: 0,
            total_quantity: 0,
            total_produk_ditemukan: 0,
            total_qty_product_ditemukan: 0,
            total_qty_baris_tiktok: 0,
            total_pesanan_ditemukan: 0,
            total_master_amount: 0,
            matched_master_amount: 0,
            total_gmv_ads_amount: 0,
            missing_count: 0,
            bruto: 0,
            data: [],
        };
        skuBreakdown = [];
        skuBreakdownMore = 0;
        missingOrderIds = [];
        uploadErrorMessage = "";
        editingRowIndex = null;
    }

    // ==========================================
    // TIKTOK CLEANING & RECONCILIATION ENGINE
    // ==========================================

    function runDataCleaning() {
        if (masterJsonData.length === 0) {
            alert("Silakan upload file Master Settlement TikTok terlebih dahulu.");
            return;
        }

        isProcessing = true;
        processProgress = 15;

        setTimeout(() => {
            // 1. Process Master Data & Separate GMV Ads
            const masterOrderMapping: Record<string, number> = {};
            let totalOrderAmt = 0;
            let totalGmvAdsAmt = 0;

            const orderIdCandidates = [
                "Order/adjustment ID",
                "Order/Adjustment ID",
                "ID Pesanan/Penyesuaian",
                "ID Pesanan/penyesuaian",
                "ID pesanan/penyesuaian",
                "Order ID",
                "ID Pesanan",
            ];

            const settlementCandidates = [
                "Total settlement amount",
                "Total dana settlement",
                "Total jumlah penyelesaian",
                "Total penyelesaian",
                "Settlement Amount",
                "Pencairan",
            ];

            const transTypeCandidates = [
                "Transaction type",
                "Tipe transaksi",
                "Tipe Transaksi",
                "Jenis Transaksi",
            ];

            masterJsonData.forEach((row) => {
                if (!row || typeof row !== "object") return;

                const orderIdVal = getFieldVal(row, orderIdCandidates);
                const orderIdStr = String(orderIdVal || "").trim().toUpperCase();

                const transType = String(
                    getFieldVal(row, transTypeCandidates) || "ORDER",
                ).trim().toUpperCase();

                const settlementVal = parseNum(
                    getFieldVal(row, settlementCandidates),
                );

                const isGmvAds =
                    transType.includes("GMV") ||
                    transType.includes("ADS") ||
                    transType.includes("IKLAN") ||
                    transType.includes("ADJUSTMENT");

                if (isGmvAds) {
                    totalGmvAdsAmt += Math.abs(settlementVal);
                } else {
                    if (orderIdStr.length >= 8) {
                        if (masterOrderMapping[orderIdStr] === undefined) {
                            masterOrderMapping[orderIdStr] = 0;
                        }
                        masterOrderMapping[orderIdStr] += settlementVal;
                        totalOrderAmt += settlementVal;
                    }
                }
            });

            const totalMasterAmt = totalOrderAmt + totalGmvAdsAmt;

            processProgress = 45;

            // Extract Bruto
            let brutoVal = 0;
            if (masterRawData && typeof masterRawData === "object") {
                const reports = masterRawData.Reports || masterRawData.Summary;
                if (Array.isArray(reports)) {
                    reports.forEach((item) => {
                        if (!item || typeof item !== "object") return;
                        const vals = Object.values(item).map((v) => String(v));
                        vals.forEach((v) => {
                            if (
                                v.toLowerCase().includes("total pendapatan") ||
                                v.toLowerCase().includes("subtotal after seller discounts")
                            ) {
                                brutoVal = Math.abs(parseNum(vals[vals.length - 1]));
                            }
                        });
                    });
                }
            }

            if (brutoVal === 0) {
                masterJsonData.forEach((row) => {
                    const rev = parseNum(
                        getFieldVal(row, [
                            "Subtotal after seller discounts",
                            "Subtotal after sales discounts",
                            "Subtotal setelah diskon penjual",
                            "Total Revenue",
                            "Total revenue",
                            "Subtotal sebelum diskon",
                        ]),
                    );
                    brutoVal += rev;
                });
            }

            processProgress = 65;

            // 2. Process Raw TikTok Data
            const dedupSet = new Set<string>();
            const orderRawItemsMap: Record<string, any[]> = {};

            rawJsonsData.forEach((item) => {
                if (!item || typeof item !== "object") return;

                const orderId = String(
                    getFieldVal(item, [
                        "Order ID",
                        "ID Pesanan",
                        "No. Pesanan",
                        "order_id",
                    ]) || "",
                ).trim();

                if (!orderId) return;

                // VALIDASI KHUSUS TIKTOK: Order ID HARUS NUMERIK (hanya angka)
                if (/[^0-9]/.test(orderId)) {
                    return;
                }

                const productName = String(
                    getFieldVal(item, [
                        "Product Name",
                        "Nama Produk",
                        "product_name",
                    ]) || "",
                ).trim();
                const variantName = String(
                    getFieldVal(item, [
                        "Variation",
                        "Nama Variasi",
                        "Variasi",
                        "variant",
                    ]) || "",
                ).trim();

                // Composite Key Deduplication: Order ID + '||' + Product Name + '||' + Variation
                const dedupKey = `${orderId}||${productName}||${variantName}`;
                if (dedupSet.has(dedupKey)) return;
                dedupSet.add(dedupKey);

                if (masterOrderMapping[orderId] === undefined) return;

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
            let globalImportCounter = 1;

            Object.keys(orderRawItemsMap).forEach((orderId) => {
                const rawItems = orderRawItemsMap[orderId];
                const masterAmt = masterOrderMapping[orderId] || 0;
                const itemCount = rawItems.length;

                let rawPaySums = 0;
                const rawPays: number[] = [];
                rawItems.forEach((rawItem, idx) => {
                    const p = parseNum(
                        getFieldVal(rawItem, [
                            "Total Pembayaran",
                            "Subtotal Pesanan",
                            "Harga Setelah Diskon",
                            "Harga Awal",
                        ]) || 0,
                    );
                    rawPays[idx] = p;
                    rawPaySums += Math.max(0, p);
                });

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

                    // STRICT FILTERING: Exclude Total Pembayaran <= 0
                    if (itemPembayaran <= 0) return;

                    orderMatchedAny = true;

                    const productName = String(
                        getFieldVal(item, [
                            "Product Name",
                            "Nama Produk",
                            "product_name",
                        ]) || "",
                    ).trim();
                    const variantName = String(
                        getFieldVal(item, [
                            "Variation",
                            "Nama Variasi",
                            "Variasi",
                            "variant",
                        ]) || "",
                    ).trim();
                    const rawSku = String(
                        getFieldVal(item, [
                            "Seller SKU",
                            "Nomor Referensi SKU",
                            "SKU Induk",
                            "sku",
                        ]) || "NO-SKU",
                    ).trim();
                    const resi = String(
                        getFieldVal(item, [
                            "Tracking ID",
                            "No. Resi",
                            "No Resi",
                            "resi",
                        ]) || "-",
                    ).trim();
                    const rawQty = Math.max(
                        1,
                        parseInt(
                            String(
                                getFieldVal(item, [
                                    "Quantity",
                                    "Jumlah",
                                    "Qty",
                                    "quantity",
                                ]) || "1",
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
                        _import_no: globalImportCounter++,
                    });

                    totalPembayaranKalkulasiSum += itemPembayaran;
                    totalQtyProductDitemukanSum += rawQty;

                    const unitCount = getUnitCount(rawSku, variantName);
                    const effectiveLineQuantity = rawQty * unitCount;
                    totalQtySatuanSum += effectiveLineQuantity;

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

            // 3. Missing Order IDs
            const missingList: Array<{ order_id: string; amount: number }> = [];
            Object.keys(masterOrderMapping).forEach((orderId) => {
                if (matchedOrdersMap[orderId] === undefined) {
                    missingList.push({
                        order_id: orderId,
                        amount: masterOrderMapping[orderId],
                    });
                }
            });

            const matchedMasterAmountSum = Object.values(
                matchedOrdersMap,
            ).reduce((a, b) => a + b, 0);

            const sortedBreakdown = Object.values(skuMap).sort(
                (a, b) => b.total_qty - a.total_qty,
            );

            // Update Target Export Payload
            exportPayload = {
                total_master: Object.keys(masterOrderMapping).length,
                total_matched: matchedDataItems.length,
                total_payment: Math.round(totalPembayaranKalkulasiSum),
                total_quantity: totalQtySatuanSum,
                total_produk_ditemukan: totalQtySatuanSum,
                total_qty_product_ditemukan: totalQtyProductDitemukanSum,
                total_qty_baris_tiktok: matchedDataItems.length,
                total_pesanan_ditemukan: Object.keys(matchedOrdersMap).length,
                total_master_amount: Math.round(totalMasterAmt),
                matched_master_amount: Math.round(matchedMasterAmountSum),
                total_gmv_ads_amount: Math.round(totalGmvAdsAmt),
                missing_count: missingList.length,
                bruto: Math.round(brutoVal),
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

    // Recalculate metrics if rows are edited in Matched Rows Editor
    function recalculateFromEditedData() {
        let totalPay = 0;
        let totalRawQty = 0;
        let totalSatuanQty = 0;
        const matchedOrdersSet = new Set<string>();
        const skuMap: Record<string, { sku: string; size: string; total_qty: number; order_count: number }> = {};

        exportPayload.data.forEach((row) => {
            const pay = Number(row["Total Pembayaran"]) || 0;
            const qty = Number(row["Jumlah"]) || 1;
            const sku = String(row["Nomor Referensi SKU"] || "").trim();
            const varName = String(row["Nama Variasi"] || "").trim();
            const orderId = String(row["No. Pesanan"] || "").trim();

            if (pay > 0 && orderId) {
                matchedOrdersSet.add(orderId);
            }

            totalPay += pay;
            totalRawQty += qty;

            const unitCount = getUnitCount(sku, varName);
            totalSatuanQty += qty * unitCount;

            const subCodes = splitBundleSku(sku, varName);
            const size = extractSize(varName, sku);

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
                skuMap[bKey].total_qty += qty;
                skuMap[bKey].order_count += 1;
            });
        });

        exportPayload.total_matched = exportPayload.data.length;
        exportPayload.total_payment = Math.round(totalPay);
        exportPayload.total_quantity = totalSatuanQty;
        exportPayload.total_produk_ditemukan = totalSatuanQty;
        exportPayload.total_qty_product_ditemukan = totalRawQty;
        exportPayload.total_qty_baris_tiktok = exportPayload.data.length;
        exportPayload.total_pesanan_ditemukan = matchedOrdersSet.size;

        const sortedBreakdown = Object.values(skuMap).sort((a, b) => b.total_qty - a.total_qty);
        skuBreakdown = sortedBreakdown.slice(0, 150);
        skuBreakdownMore = Math.max(0, sortedBreakdown.length - 150);
    }

    // Editor Operations
    function deleteRow(idx: number) {
        if (confirm("Hapus baris data ini?")) {
            exportPayload.data.splice(idx, 1);
            recalculateFromEditedData();
        }
    }

    function addNewRow() {
        exportPayload.data.unshift({
            "No. Pesanan": "57890000000000",
            "No. Resi": "JX00000000",
            "Nomor Referensi SKU": "NEW-SKU",
            "Nama Variasi": "NEW, L",
            Jumlah: 1,
            "Total Pembayaran": 100000,
            "Nama Produk": "Produk Baru TikTok",
            _import_no: exportPayload.data.length + 1,
        });
        editingRowIndex = 0;
        recalculateFromEditedData();
    }

    // Order ID Duplicate Map Indicator Badge
    let duplicateOrderCountMap = $derived.by(() => {
        const counts: Record<string, number> = {};
        exportPayload.data.forEach((r) => {
            const id = r["No. Pesanan"];
            counts[id] = (counts[id] || 0) + 1;
        });
        return counts;
    });

    // ==========================================
    // EXPORT DOWNLOAD HANDLERS
    // ==========================================

    function downloadCleanedJson() {
        if (exportPayload.data.length === 0) {
            alert("Belum ada data hasil cleaning untuk di-export.");
            return;
        }

        // Clean internal helper keys before export
        const exportData = exportPayload.data.map(({ _import_no, ...rest }) => rest);
        const finalPayload = { ...exportPayload, data: exportData };

        const jsonString = JSON.stringify(finalPayload, null, 2);
        const blob = new Blob([jsonString], { type: "application/json" });
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        const name = exportFileName.trim() || "cleaned_tiktok";
        a.download = `CD_Tiktok_${name}.json`;
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
        const name = exportFileName.trim() || "cleaned_tiktok";
        a.download = `CD_Tiktok_${name}.txt`;
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

    // Derived States
    let paymentDiff = $derived(
        exportPayload.total_payment - exportPayload.matched_master_amount,
    );

    let isPaymentMatched = $derived(
        exportPayload.matched_master_amount > 0 &&
            exportPayload.total_payment > 0 &&
            Math.abs(paymentDiff) === 0,
    );

    let sortedMatchedData = $derived.by(() => {
        let list = [...exportPayload.data];

        if (searchFilter.trim()) {
            const sf = searchFilter.toLowerCase();
            list = list.filter(
                (item) =>
                    item["No. Pesanan"].toLowerCase().includes(sf) ||
                    item["No. Resi"].toLowerCase().includes(sf) ||
                    item["Nomor Referensi SKU"].toLowerCase().includes(sf) ||
                    item["Nama Produk"].toLowerCase().includes(sf),
            );
        }

        if (sortMode === "order_asc") {
            list.sort((a, b) => a["No. Pesanan"].localeCompare(b["No. Pesanan"]));
        } else if (sortMode === "order_desc") {
            list.sort((a, b) => b["No. Pesanan"].localeCompare(a["No. Pesanan"]));
        } else if (sortMode === "payment_desc") {
            list.sort((a, b) => b["Total Pembayaran"] - a["Total Pembayaran"]);
        } else if (sortMode === "import") {
            list.sort((a, b) => (a._import_no || 0) - (b._import_no || 0));
        }

        return list;
    });

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
</script>

<AdminLayout
    title="Data Cleaning TikTok Shop - Warehub v2"
    breadcrumbs={[
        { name: "Tools" },
        { name: "Data Cleaning" },
        { name: "TikTok Shop Engine" },
    ]}
>
    <div class="max-w-8xl mx-auto space-y-6">
        <!-- Page Header -->
        <div
            class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-r from-teal-600 via-cyan-600 to-indigo-700 dark:from-slate-900 dark:via-teal-950 dark:to-slate-900 p-6 rounded-2xl text-white shadow-xl relative overflow-hidden"
        >
            <div
                class="absolute right-0 top-0 translate-x-4 -translate-y-4 opacity-10 pointer-events-none"
            >
                <Video class="w-64 h-64" />
            </div>
            <div class="relative z-10 space-y-1">
                <div class="flex items-center gap-2">
                    <span
                        class="px-3 py-1 bg-white/20 text-white text-xs font-semibold rounded-full backdrop-blur-md flex items-center gap-1"
                    >
                        <Video class="w-3.5 h-3.5" /> TikTok Shop Reconciliation Engine
                    </span>
                    <span class="text-xs text-teal-200"
                        >Bilingual Key &amp; Matched Rows Editor</span
                    >
                </div>
                <h1
                    class="text-2xl md:text-3xl font-extrabold tracking-tight flex items-center gap-3"
                >
                    TikTok Shop Data Cleaning &amp; Settlement Reconciliation
                </h1>
                <p class="text-teal-100 text-sm max-w-xl">
                    Dukungan struktur bilingual, pemisahan GMV Ads/Iklan, penyaringan Order ID numerik, pemecahan SKU bundle, dan Matched Rows Editor.
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
                        <FileText class="w-5 h-5 text-teal-500" />
                        1. Master Settlement TikTok (`data_akurat_tiktok.json`)
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
                        class="border-2 border-dashed border-slate-300 dark:border-slate-700 hover:border-teal-500 dark:hover:border-teal-500 rounded-xl p-6 text-center transition-all bg-slate-50/50 dark:bg-slate-950/40"
                    >
                        <Upload class="w-8 h-8 mx-auto text-teal-500 mb-2" />
                        <span
                            class="block text-sm font-semibold text-slate-700 dark:text-slate-200"
                        >
                            {masterFileName ||
                                "Upload Master TikTok File (.JSON / .TXT)"}
                        </span>
                        <span class="text-xs text-slate-400 mt-1 block"
                            >Dukungan otomatis struktur bilingual Bahasa Indonesia / Inggris</span
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

            <!-- Raw TikTok Files Upload -->
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
                        2. Raw TikTok Files (Data Pesanan Mentah)
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
                                : "Upload Raw TikTok Files (.JSON / .TXT)"}
                        </span>
                        <span class="text-xs text-slate-400 mt-1 block"
                            >Dukungan `OrderSKUList` / `OrderList` (bisa multiple file)</span
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
                    class="px-6 py-3 bg-gradient-to-r from-teal-600 to-indigo-600 hover:from-teal-700 hover:to-indigo-700 text-white font-bold text-sm rounded-xl shadow-lg shadow-teal-600/30 disabled:opacity-50 flex items-center gap-2 cursor-pointer transition-all w-full lg:w-auto justify-center"
                >
                    {#if isProcessing}
                        <RefreshCw class="w-4 h-4 animate-spin" />
                        <span>Memproses TikTok ({processProgress}%)...</span>
                    {:else}
                        <RefreshCw class="w-4 h-4" />
                        <span>Proses Data Cleaning TikTok</span>
                    {/if}
                </button>
            </div>

            <!-- Custom Filename & Export Downloads -->
            <div class="flex flex-col sm:flex-row items-center gap-3 w-full lg:w-auto">
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 whitespace-nowrap">
                        Prefix: CD_Tiktok_
                    </span>
                    <input
                        type="text"
                        bind:value={exportFileName}
                        placeholder="Filename..."
                        class="px-3 py-2 border border-slate-200 dark:border-slate-800 rounded-xl bg-slate-50 dark:bg-slate-950 text-xs font-semibold text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-teal-500 w-full sm:w-40"
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
                            Hasil Rekonsiliasi Pembayaran TikTok: {isPaymentMatched
                                ? '100% MATCHED (Sesuai Master Pencairan)'
                                : 'Ada Selisih Perhitungan'}
                        </h4>
                        <p class="text-xs opacity-90">
                            Total Pembayaran Hasil Kalkulasi: <span class="font-black text-emerald-600 dark:text-emerald-400">{formatRupiah(exportPayload.total_payment)}</span>
                            &nbsp;•&nbsp;
                            Matched Master Pencairan: <span class="font-black">{formatRupiah(exportPayload.matched_master_amount)}</span>
                            {#if exportPayload.total_gmv_ads_amount > 0}
                                &nbsp;•&nbsp; GMV Ads/Iklan: <span class="font-black text-indigo-600 dark:text-indigo-400">{formatRupiah(exportPayload.total_gmv_ads_amount)}</span>
                            {/if}
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
                    {:else if exportPayload.total_payment > 0}
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
            class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4"
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
                    {exportPayload.total_master}
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
                    {formatRupiah(exportPayload.total_payment)}
                </div>
                <div class="text-[11px] font-semibold text-slate-500">
                    {#if isPaymentMatched}
                        <span class="text-emerald-600 dark:text-emerald-400 font-bold">✓ Match dengan Master</span>
                    {:else if exportPayload.total_payment > 0}
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
                    >GMV Ads / Iklan</span
                >
                <div
                    class="text-base font-black text-indigo-600 dark:text-indigo-400"
                >
                    {formatRupiah(exportPayload.total_gmv_ads_amount)}
                </div>
                <div class="text-[11px] font-semibold text-slate-500">
                    Dipisah Otomatis
                </div>
            </div>

            <div
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl shadow-xs space-y-1"
            >
                <span
                    class="text-[11px] font-bold uppercase tracking-wider text-slate-400"
                    >Data Cocok</span
                >
                <div
                    class="text-xl font-black text-teal-600 dark:text-teal-400"
                >
                    {exportPayload.total_matched} Baris
                </div>
                <div class="text-[11px] font-semibold text-slate-500">
                    Baris Pesanan TikTok
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
                    {exportPayload.total_quantity} Pcs
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
                    Master Revenue
                </div>
            </div>
        </div>

        <!-- Result Tables Section -->
        <div
            class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-6"
        >
            <!-- Navigation Tabs & Actions -->
            <div
                class="flex flex-col lg:flex-row justify-between items-center gap-4 border-b border-slate-100 dark:border-slate-800 pb-4"
            >
                <div class="flex items-center gap-2 flex-wrap">
                    <button
                        onclick={() => (activeTab = "matched_data")}
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer {activeTab ===
                        'matched_data'
                            ? 'bg-teal-600 text-white shadow-md shadow-teal-600/20'
                            : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'}"
                    >
                        Data Cocok TikTok ({exportPayload.data.length})
                    </button>

                    <button
                        onclick={() => (activeTab = "editor")}
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5 {activeTab ===
                        'editor'
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'}"
                    >
                        <Edit3 class="w-3.5 h-3.5" />
                        <span>Matched Rows Editor</span>
                    </button>

                    <button
                        onclick={() => (activeTab = "sku_breakdown")}
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer {activeTab ===
                        'sku_breakdown'
                            ? 'bg-purple-600 text-white shadow-md shadow-purple-600/20'
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

                <div class="flex items-center gap-3 w-full lg:w-auto">
                    {#if activeTab === "editor"}
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-slate-500 font-semibold whitespace-nowrap">Sort:</span>
                            <select
                                bind:value={sortMode}
                                class="px-2.5 py-1.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-100"
                            >
                                <option value="import">Import Order (Default)</option>
                                <option value="order_asc">Order ID (A-Z)</option>
                                <option value="order_desc">Order ID (Z-A)</option>
                                <option value="payment_desc">Total Pembayaran (High-Low)</option>
                            </select>

                            <button
                                onclick={addNewRow}
                                class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs flex items-center gap-1 transition-all"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>Tambah Baris</span>
                            </button>
                        </div>
                    {/if}

                    <div class="relative w-full sm:w-64">
                        <Search
                            class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"
                        />
                        <input
                            type="text"
                            bind:value={searchFilter}
                            placeholder="Cari Order ID / Resi / SKU..."
                            class="w-full pl-9 pr-3 py-1.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-teal-500"
                        />
                    </div>
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
                            {#if sortedMatchedData.length === 0}
                                <tr>
                                    <td
                                        colspan="7"
                                        class="p-8 text-center text-slate-400 font-sans"
                                        >Belum ada data cocok. Silakan upload &amp; proses file.</td
                                    >
                                </tr>
                            {:else}
                                {#each sortedMatchedData as row}
                                    <tr
                                        class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40"
                                    >
                                        <td class="p-3 font-bold text-slate-800 dark:text-slate-100 flex items-center gap-1.5">
                                            <span>{row["No. Pesanan"]}</span>
                                            {#if (duplicateOrderCountMap[row["No. Pesanan"]] || 0) > 1}
                                                <span class="px-1.5 py-0.5 bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 font-sans text-[10px] font-black rounded">
                                                    Multi-item
                                                </span>
                                            {/if}
                                        </td>
                                        <td class="p-3 text-slate-500">{row["No. Resi"]}</td>
                                        <td class="p-3 font-bold text-teal-600 dark:text-teal-400">{row["Nomor Referensi SKU"]}</td>
                                        <td class="p-3 font-sans text-slate-600 dark:text-slate-300">{row["Nama Variasi"]}</td>
                                        <td class="p-3 text-center font-bold">{row["Jumlah"]}</td>
                                        <td class="p-3 text-right font-bold text-emerald-600 dark:text-emerald-400">
                                            {formatRupiah(row["Total Pembayaran"])}
                                        </td>
                                        <td class="p-3 font-sans truncate max-w-xs">{row["Nama Produk"]}</td>
                                    </tr>
                                {/each}
                            {/if}
                        </tbody>
                    </table>
                </div>
            {:else if activeTab === "editor"}
                <!-- MATCHED ROWS EDITOR -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800/50 p-4 rounded-xl text-indigo-900 dark:text-indigo-200 text-xs">
                        <div class="flex items-center gap-2 font-semibold">
                            <Edit3 class="w-4 h-4 text-indigo-600" />
                            <span>Modul Editor Penyesuaian Baris Matched TikTok. Seluruh perubahan pada input akan secara langsung mengkalkulasi ulang total payment &amp; SKU breakdown.</span>
                        </div>
                        <span class="font-bold text-indigo-700 dark:text-indigo-300">{sortedMatchedData.length} Baris</span>
                    </div>

                    <div class="overflow-x-auto max-h-[500px] border border-slate-200 dark:border-slate-800 rounded-xl">
                        <table class="w-full text-left text-xs">
                            <thead
                                class="bg-slate-100 dark:bg-slate-950 text-slate-600 font-bold uppercase sticky top-0 border-b border-slate-200 dark:border-slate-800"
                            >
                                <tr>
                                    <th class="p-3 w-10">No</th>
                                    <th class="p-3">Order ID (Hanya Angka)</th>
                                    <th class="p-3">Tracking ID / Resi</th>
                                    <th class="p-3">Seller SKU</th>
                                    <th class="p-3">Variasi / Size</th>
                                    <th class="p-3 text-center w-20">Qty</th>
                                    <th class="p-3 text-right w-36">Total Pembayaran</th>
                                    <th class="p-3">Product Name</th>
                                    <th class="p-3 text-center w-16">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                {#if sortedMatchedData.length === 0}
                                    <tr>
                                        <td colspan="9" class="p-8 text-center text-slate-400">Belum ada baris data cocok untuk diedit.</td>
                                    </tr>
                                {:else}
                                    {#each sortedMatchedData as row, idx}
                                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/50 transition-colors">
                                            <td class="p-3 font-mono text-slate-400 text-[11px]">{idx + 1}</td>
                                            <td class="p-2">
                                                <input
                                                    type="text"
                                                    bind:value={row["No. Pesanan"]}
                                                    oninput={recalculateFromEditedData}
                                                    class="w-full px-2.5 py-1 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg font-mono font-bold text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-teal-500"
                                                />
                                            </td>
                                            <td class="p-2">
                                                <input
                                                    type="text"
                                                    bind:value={row["No. Resi"]}
                                                    class="w-full px-2.5 py-1 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg font-mono text-xs text-slate-700 dark:text-slate-300"
                                                />
                                            </td>
                                            <td class="p-2">
                                                <input
                                                    type="text"
                                                    bind:value={row["Nomor Referensi SKU"]}
                                                    oninput={recalculateFromEditedData}
                                                    class="w-full px-2.5 py-1 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg font-mono font-bold text-xs text-teal-600 dark:text-teal-400"
                                                />
                                            </td>
                                            <td class="p-2">
                                                <input
                                                    type="text"
                                                    bind:value={row["Nama Variasi"]}
                                                    oninput={recalculateFromEditedData}
                                                    class="w-full px-2.5 py-1 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs text-slate-700 dark:text-slate-300"
                                                />
                                            </td>
                                            <td class="p-2 text-center">
                                                <input
                                                    type="number"
                                                    min="1"
                                                    bind:value={row["Jumlah"]}
                                                    oninput={recalculateFromEditedData}
                                                    class="w-16 px-2 py-1 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg font-bold text-center text-xs"
                                                />
                                            </td>
                                            <td class="p-2 text-right">
                                                <input
                                                    type="number"
                                                    min="0"
                                                    bind:value={row["Total Pembayaran"]}
                                                    oninput={recalculateFromEditedData}
                                                    class="w-32 px-2.5 py-1 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg font-mono font-extrabold text-right text-xs text-emerald-600 dark:text-emerald-400"
                                                />
                                            </td>
                                            <td class="p-2">
                                                <input
                                                    type="text"
                                                    bind:value={row["Nama Produk"]}
                                                    class="w-full px-2.5 py-1 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs text-slate-700 dark:text-slate-300"
                                                />
                                            </td>
                                            <td class="p-2 text-center">
                                                <button
                                                    onclick={() => deleteRow(idx)}
                                                    class="p-1.5 text-rose-500 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition-all"
                                                    title="Hapus Baris"
                                                >
                                                    <Trash2 class="w-4 h-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    {/each}
                                {/if}
                            </tbody>
                        </table>
                    </div>
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
                                            Semua Order ID TikTok berhasil terhubung 100%! Tidak ada Order ID missing.
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
