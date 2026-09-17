<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import { Link } from '@inertiajs/svelte';
    import { 
        Video, Upload, Trash2, Download, Search, RefreshCw, 
        CheckCircle2, AlertTriangle, Layers, DollarSign, Package, Filter, FileText, ArrowRight, Sparkles, ShoppingBag, Check
    } from '@lucide/svelte';

    let masterFileName = $state<string>('');
    let masterJsonData = $state<any[]>([]);
    let rawFileNames = $state<string[]>([]);
    let rawJsonsData = $state<any[]>([]);

    let isProcessing = $state<boolean>(false);
    let processProgress = $state<number>(0);
    let showToast = $state<boolean>(false);
    let toastMessage = $state<string>('');
    let activeTab = $state<'matched_data' | 'sku_breakdown' | 'missing'>('matched_data');
    let searchFilter = $state<string>('');
    let exportFileName = $state<string>('CD_Tiktok_BABAN_cleaned');

    // Schema Metrics Matching User TikTok Target V1 Export
    let exportPayload = $state({
        bruto: 0,
        netto: 0,
        total_sama_gmv: 0,
        pemakaian_gmv: 0,
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
        missing_count: 0,
        data: [] as Array<{
            'No. Pesanan': string;
            'No. Resi': string;
            'Nomor Referensi SKU': string;
            'Nama Variasi': string;
            'Jumlah': number;
            'Total Pembayaran': number;
            'Nama Produk': string;
        }>
    });

    let skuBreakdown = $state<Array<{ sku: string; size: string; total_qty: number; order_count: number }>>([]);
    let missingOrderIds = $state<Array<{ order_id: string; amount: number }>>([]);

    // Helper to unwrap JSON payload for TikTok master & raw files
    function unwrapData(parsed: any): any[] {
        if (!parsed) return [];
        if (Array.isArray(parsed)) return parsed;

        if (typeof parsed === 'object') {
            const keys = ['Order details', 'OrderSKUList', 'OrderList', 'Income', 'orders', 'data', 'items', 'rows', 'Sheet1'];
            for (const k of keys) {
                if (Array.isArray(parsed[k])) return parsed[k];
            }
        }

        return [parsed];
    }

    let masterRawObject = $state<any>(null);

    // Handlers for File Upload
    function handleMasterFileUpload(event: Event) {
        const input = event.target as HTMLInputElement;
        if (!input.files || input.files.length === 0) return;

        const file = input.files[0];
        masterFileName = file.name;

        const reader = new FileReader();
        reader.onload = (e) => {
            try {
                const parsed = JSON.parse(e.target?.result as string);
                masterRawObject = parsed;
                masterJsonData = unwrapData(parsed);
            } catch (err) {
                alert('Format Master Settlement JSON TikTok tidak valid!');
                masterFileName = '';
                masterJsonData = [];
                masterRawObject = null;
            }
        };
        reader.readAsText(file);
    }

    function handleRawFilesUpload(event: Event) {
        const input = event.target as HTMLInputElement;
        if (!input.files || input.files.length === 0) return;

        const files = Array.from(input.files);
        rawFileNames = files.map(f => f.name);
        rawJsonsData = [];

        files.forEach(file => {
            const reader = new FileReader();
            reader.onload = (e) => {
                try {
                    const parsed = JSON.parse(e.target?.result as string);
                    const unwrapped = unwrapData(parsed);
                    rawJsonsData.push(...unwrapped);
                } catch (err) {
                    console.error('Err parsing raw file:', file.name);
                }
            };
            reader.readAsText(file);
        });
    }

    // TikTok Data Cleaning Engine matching V1 Target Schema
    function runDataCleaning() {
        if (masterJsonData.length === 0) {
            alert('Silakan upload file Master Settlement JSON TikTok terlebih dahulu.');
            return;
        }

        isProcessing = true;
        processProgress = 20;

        setTimeout(() => {
            // 1. Extract Official Reports Summary & Process Master Orders
            let brutoSum = 0;
            let nettoSum = 0;
            let pemakaianGmvSum = 0;
            let totalSamaGmvSum = 0;

            if (masterRawObject && Array.isArray(masterRawObject.Reports)) {
                masterRawObject.Reports.forEach((item: any) => {
                    if (!item || typeof item !== 'object') return;
                    const vals = Object.values(item).map(v => String(v));
                    const valStr = vals[vals.length - 1] || '0';
                    const numVal = parseFloat(valStr.replace(/[Rp.\s]/g, '').replace(',', '.')) || 0;

                    if (vals.includes('Subtotal after seller discounts')) {
                        brutoSum = Math.abs(numVal);
                    }
                    if (vals.includes('Total settlement amount')) {
                        nettoSum = Math.abs(numVal);
                    }
                    if (vals.includes('GMV payment for TikTok Ads') || vals.includes('Adjustments')) {
                        if (vals.includes('GMV payment for TikTok Ads')) {
                            pemakaianGmvSum = Math.abs(numVal);
                        } else if (!pemakaianGmvSum) {
                            pemakaianGmvSum = Math.abs(numVal);
                        }
                    }
                });
                totalSamaGmvSum = nettoSum + pemakaianGmvSum;
            }

            const masterOrdersMap: Record<string, number> = {};
            let fallbackBruto = 0;
            let fallbackTotalSamaGmv = 0;
            let fallbackPemakaianGmv = 0;

            masterJsonData.forEach(row => {
                if (!row || typeof row !== 'object') return;
                const orderId = String(row['Order/Adjustment ID'] || row['Order ID'] || row['Order No'] || row['order_id'] || '').trim();
                const transType = String(row['Transaction type'] || 'Order').trim();

                const rawSettlement = row['Total settlement amount'] ?? row['Settlement Amount'] ?? row['Pencairan'] ?? row['Total Revenue'] ?? 0;
                const settlement = parseFloat(String(rawSettlement).replace(/[Rp.\s]/g, '').replace(',', '.')) || 0;

                const rawSubtotal = row['Subtotal after seller discounts'] ?? row['Subtotal before discounts'] ?? row['Subtotal'] ?? 0;
                const subtotal = parseFloat(String(rawSubtotal).replace(/[Rp.\s]/g, '').replace(',', '.')) || 0;

                fallbackBruto += subtotal;

                const isGmvAd = transType.toLowerCase().includes('gmv') || transType.toLowerCase().includes('ads');

                if (isGmvAd) {
                    fallbackPemakaianGmv += Math.abs(settlement);
                } else {
                    if (orderId && (transType.toLowerCase() === 'order' || transType.toLowerCase() === 'pesanan' || transType === '' || /^\d{15,20}$/.test(orderId))) {
                        masterOrdersMap[orderId] = (masterOrdersMap[orderId] || 0) + settlement;
                        fallbackTotalSamaGmv += settlement;
                    }
                }
            });

            // Exclude orders with non-positive settlement (<= 0, e.g. cancelled/retur 0 or minus)
            Object.keys(masterOrdersMap).forEach(id => {
                if (masterOrdersMap[id] <= 0) {
                    delete masterOrdersMap[id];
                }
            });

            if (brutoSum === 0) brutoSum = fallbackBruto;
            if (totalSamaGmvSum === 0) totalSamaGmvSum = fallbackTotalSamaGmv;
            if (pemakaianGmvSum === 0) pemakaianGmvSum = fallbackPemakaianGmv;
            if (nettoSum === 0) nettoSum = totalSamaGmvSum - pemakaianGmvSum;

            const masterAmountSum = Object.values(masterOrdersMap).reduce((a, b) => a + b, 0);

            processProgress = 50;

            // 2. Process Raw Files & Deduplication (Order ID + Nama Produk + Nama Variasi)
            const dedupMap: Record<string, boolean> = {};
            const matchedOrdersMap: Record<string, number> = {};
            const matchedDataItems: Array<any> = [];
            const skuMap: Record<string, { sku: string; size: string; total_qty: number; order_count: number }> = {};
            let totalQtySatuanSum = 0;

            rawJsonsData.forEach(item => {
                if (!item || typeof item !== 'object') return;
                const orderId = String(item['Order ID'] || item['No. Pesanan'] || item['order_id'] || '').trim();
                const resi = String(item['Tracking ID'] || item['No. Resi'] || item['No Resi'] || item['resi'] || '-').trim();
                const productName = String(item['Product Name'] || item['Nama Produk'] || item['product_name'] || '').trim();
                const variantName = String(item['Variation'] || item['Nama Variasi'] || item['Variasi'] || item['variant_name'] || '').trim();
                const rawSku = String(item['Seller SKU'] || item['Nomor Referensi SKU'] || item['SKU Induk'] || item['sku'] || 'NO-SKU').trim();
                const rawQty = parseInt(item['Quantity'] || item['Jumlah'] || item['Qty'] || '1', 10) || 1;

                if (!orderId) return;

                const dedupKey = `${orderId}_${productName}_${variantName}`;
                if (dedupMap[dedupKey]) return;
                dedupMap[dedupKey] = true;

                if (masterOrdersMap[orderId] !== undefined) {
                    const masterAmt = masterOrdersMap[orderId];
                    const itemPayment = Math.max(0, masterAmt);
                    matchedOrdersMap[orderId] = itemPayment;

                    matchedDataItems.push({
                        'No. Pesanan': orderId,
                        'No. Resi': resi,
                        'Nomor Referensi SKU': rawSku,
                        'Nama Variasi': variantName,
                        'Jumlah': rawQty,
                        'Total Pembayaran': itemPayment,
                        'Nama Produk': productName
                    });

                    // Parse TikTok Bundle SKU
                    const parsedItems = parseBundleSku(rawSku, variantName, rawQty);
                    parsedItems.forEach(p => {
                        totalQtySatuanSum += p.qty;
                        const bKey = `${p.sku}_${p.size}`;
                        if (!skuMap[bKey]) {
                            skuMap[bKey] = { sku: p.sku, size: p.size, total_qty: 0, order_count: 0 };
                        }
                        skuMap[bKey].total_qty += p.qty;
                        skuMap[bKey].order_count += 1;
                    });
                }
            });

            processProgress = 85;

            // 3. Compute Missing Orders
            const missingList: Array<{ order_id: string; amount: number }> = [];
            Object.keys(masterOrdersMap).forEach(orderId => {
                if (matchedOrdersMap[orderId] === undefined) {
                    missingList.push({
                        order_id: orderId,
                        amount: masterOrdersMap[orderId]
                    });
                }
            });

            const totalMasterCount = Object.keys(masterOrdersMap).length;
            const matchedMasterAmountSum = Object.values(matchedOrdersMap).reduce((a, b) => a + b, 0);

            // Update Target Export Schema (100% Matching V1 Target JSON)
            exportPayload = {
                bruto: Math.round(brutoSum),
                netto: Math.round(nettoSum),
                total_sama_gmv: Math.round(totalSamaGmvSum),
                pemakaian_gmv: Math.round(pemakaianGmvSum),
                total_master: totalMasterCount,
                total_matched: matchedDataItems.length,
                total_payment: Math.round(masterAmountSum),
                total_quantity: totalQtySatuanSum,
                total_produk_ditemukan: totalQtySatuanSum,
                total_qty_product_ditemukan: totalQtySatuanSum,
                total_qty_baris_tiktok: matchedDataItems.length,
                total_pesanan_ditemukan: Object.keys(matchedOrdersMap).length,
                total_master_amount: Math.round(masterAmountSum),
                matched_master_amount: Math.round(matchedMasterAmountSum),
                missing_count: missingList.length,
                data: matchedDataItems
            };

            skuBreakdown = Object.values(skuMap);
            missingOrderIds = missingList;

            // Save to localStorage for MatchedRows sub-page
            localStorage.setItem('tiktok_matched_rows', JSON.stringify(matchedDataItems));

            processProgress = 100;
            setTimeout(() => {
                isProcessing = false;
                toastMessage = `Data Cleaning TikTok Shop Berhasil! ${exportPayload.total_matched} Baris Matched.`;
                showToast = true;
                setTimeout(() => { showToast = false; }, 6000);
            }, 400);
        }, 100);
    }

    function parseBundleSku(skuStr: string, variationStr: string, qty: number) {
        const results: Array<{ sku: string; size: string; qty: number }> = [];
        const cleanSku = skuStr.trim();
        const cleanVar = variationStr.trim();

        let size = 'ALL SIZE';
        const sizeMatch = (cleanVar + ' ' + cleanSku).match(/\b(3XL|2XL|XL|L|M|S|XS|XXL|XXXL)\b/i);
        if (sizeMatch) {
            size = sizeMatch[1].toUpperCase();
        }

        let delimiter: string | null = null;
        if (cleanSku.includes('+')) delimiter = '+';
        else if (cleanSku.includes(',')) delimiter = ',';
        else if (/^[A-Z0-9]+-[A-Z0-9]+/i.test(cleanSku)) delimiter = '-';

        if (delimiter) {
            const parts = cleanSku.split(delimiter);
            let prefix = '';
            parts.forEach((part, idx) => {
                let itemSku = part.trim();
                if (idx === 0) {
                    const pm = itemSku.match(/^([A-Z0-9\s]+)\s+([A-Z0-9]+)$/i);
                    if (pm) prefix = pm[1].trim();
                }
                if (idx > 0 && prefix && !itemSku.includes(' ') && !/^[A-Z]{2,}/i.test(itemSku)) {
                    itemSku = `${prefix} ${itemSku}`;
                }

                let pQty = qty;
                const multiplierMatch = itemSku.match(/x(\d+)/i);
                if (multiplierMatch) {
                    pQty *= parseInt(multiplierMatch[1], 10);
                    itemSku = itemSku.replace(/x\d+/i, '').trim();
                }
                results.push({ sku: itemSku, size, qty: pQty });
            });
        } else {
            results.push({ sku: cleanSku, size, qty });
        }

        return results;
    }

    function formatRupiah(num: number) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(num || 0);
    }

    function downloadCleanedExport() {
        if (exportPayload.data.length === 0) {
            alert('Belum ada data hasil cleaning untuk diexport!');
            return;
        }

        const content = JSON.stringify(exportPayload, null, 4);
        const blob = new Blob([content], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${exportFileName.endsWith('.json') ? exportFileName : exportFileName + '.json'}`;
        a.click();
        URL.revokeObjectURL(url);
    }

    let filteredMatchedData = $derived(
        exportPayload.data.filter(item => 
            !searchFilter.trim() || 
            item['No. Pesanan'].toLowerCase().includes(searchFilter.toLowerCase()) || 
            item['No. Resi'].toLowerCase().includes(searchFilter.toLowerCase()) ||
            item['Nomor Referensi SKU'].toLowerCase().includes(searchFilter.toLowerCase()) ||
            item['Nama Produk'].toLowerCase().includes(searchFilter.toLowerCase())
        )
    );

    let filteredSkuBreakdown = $derived(
        skuBreakdown.filter(item => 
            !searchFilter.trim() || 
            item.sku.toLowerCase().includes(searchFilter.toLowerCase()) || 
            item.size.toLowerCase().includes(searchFilter.toLowerCase())
        )
    );
</script>

<AdminLayout title="Data Cleaning TikTok Shop - Warehub v2" breadcrumbs={[{ name: 'Tools' }, { name: 'Data Cleaning' }, { name: 'TikTok Shop' }]}>
    <div class="max-w-8xl mx-auto space-y-6">
        <!-- Toast Notification -->
        {#if showToast}
            <div class="fixed bottom-6 right-6 z-50 p-4 bg-slate-900 text-white rounded-2xl shadow-2xl border border-slate-700 flex items-center gap-3 animate-bounce">
                <CheckCircle2 class="w-6 h-6 text-emerald-400 shrink-0" />
                <span class="text-sm font-semibold">{toastMessage}</span>
            </div>
        {/if}

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-r from-slate-900 via-zinc-900 to-black dark:from-slate-950 dark:via-black dark:to-slate-950 p-6 rounded-2xl text-white shadow-xl relative overflow-hidden">
            <div class="absolute right-0 top-0 translate-x-4 -translate-y-4 opacity-10 pointer-events-none">
                <Video class="w-64 h-64" />
            </div>
            <div class="relative z-10 space-y-1">
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 bg-white/20 text-white text-xs font-semibold rounded-full backdrop-blur-md">TikTok Shop Engine</span>
                    <span class="text-xs text-slate-300">V1 Target Schema Match</span>
                </div>
                <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight flex items-center gap-3">
                    <Video class="w-7 h-7 text-pink-400" />
                    Data Cleaning TikTok Shop
                </h1>
                <p class="text-slate-300 text-sm max-w-xl">
                    Parsing Master Settlement (`Order details`), Deduplikasi Pesanan Raw, Matching Order ID, dan Ekstraksi Bundle SKU Satuan.
                </p>
            </div>

            <Link
                href="/superadmin/settings/cleaning-data-tiktok/matched-rows"
                class="relative z-10 inline-flex items-center gap-2 px-5 py-2.5 bg-white/10 hover:bg-white/20 border border-white/20 text-white text-xs font-bold rounded-xl backdrop-blur-md transition-all">
                <span>View Matched Rows Page</span>
                <ArrowRight class="w-4 h-4 text-pink-400" />
            </Link>
        </div>

        <!-- File Upload Area -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- 1. Master Settlement File -->
            <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                        <FileText class="w-4 h-4 text-pink-500" />
                        1. Master Settlement JSON (TikTok)
                    </h3>
                    {#if masterFileName}
                        <span class="px-2.5 py-0.5 text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 rounded-full flex items-center gap-1">
                            <Check class="w-3 h-3" /> Ready
                        </span>
                    {/if}
                </div>
                <div class="relative border-2 border-dashed border-slate-300 dark:border-slate-700 hover:border-pink-500 dark:hover:border-pink-500 rounded-xl p-6 text-center transition-all bg-slate-50 dark:bg-slate-950/40">
                    <input 
                        type="file" 
                        accept=".json" 
                        onchange={handleMasterFileUpload}
                        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" 
                    />
                    <div class="space-y-2 pointer-events-none">
                        <Upload class="w-8 h-8 text-slate-400 mx-auto" />
                        <div class="text-xs font-medium text-slate-600 dark:text-slate-400">
                            {#if masterFileName}
                                <span class="font-bold text-pink-600 dark:text-pink-400">{masterFileName}</span> ({masterJsonData.length} records)
                            {:else}
                                Drop File Master JSON TikTok atau <span class="text-pink-600 dark:text-pink-400 underline">Browse</span>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Raw JSON Files -->
            <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                        <Layers class="w-4 h-4 text-cyan-500" />
                        2. Raw JSON Files (Orders Mentah)
                    </h3>
                    {#if rawFileNames.length > 0}
                        <span class="px-2.5 py-0.5 text-xs font-semibold bg-cyan-100 text-cyan-800 dark:bg-cyan-950/60 dark:text-cyan-300 rounded-full flex items-center gap-1">
                            <Check class="w-3 h-3" /> {rawFileNames.length} Files
                        </span>
                    {/if}
                </div>
                <div class="relative border-2 border-dashed border-slate-300 dark:border-slate-700 hover:border-cyan-500 dark:hover:border-cyan-500 rounded-xl p-6 text-center transition-all bg-slate-50 dark:bg-slate-950/40">
                    <input 
                        type="file" 
                        accept=".json" 
                        multiple 
                        onchange={handleRawFilesUpload}
                        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" 
                    />
                    <div class="space-y-2 pointer-events-none">
                        <Upload class="w-8 h-8 text-slate-400 mx-auto" />
                        <div class="text-xs font-medium text-slate-600 dark:text-slate-400">
                            {#if rawFileNames.length > 0}
                                <span class="font-bold text-cyan-600 dark:text-cyan-400">{rawFileNames.length} File Dipilih</span> ({rawJsonsData.length} items)
                            {:else}
                                Drop File Raw JSON TikTok (Bisa multiple) atau <span class="text-cyan-600 dark:text-cyan-400 underline">Browse</span>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action & Progress Bar -->
        <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-3 w-full md:w-auto">
                <button
                    onclick={runDataCleaning}
                    disabled={isProcessing || masterJsonData.length === 0}
                    class="w-full md:w-auto px-6 py-3 bg-gradient-to-r from-pink-600 to-rose-600 hover:from-pink-500 hover:to-rose-500 disabled:opacity-50 text-white font-bold text-sm rounded-xl shadow-lg shadow-pink-500/25 flex items-center justify-center gap-2 transition-all">
                    {#if isProcessing}
                        <RefreshCw class="w-4 h-4 animate-spin" />
                        <span>Memproses Data...</span>
                    {:else}
                        <Sparkles class="w-4 h-4" />
                        <span>Jalankan Data Cleaning TikTok</span>
                    {/if}
                </button>
            </div>

            {#if isProcessing || processProgress > 0}
                <div class="w-full md:w-1/2 space-y-1.5">
                    <div class="flex justify-between text-xs font-semibold text-slate-600 dark:text-slate-400">
                        <span>Progress Engine</span>
                        <span>{processProgress}%</span>
                    </div>
                    <div class="w-full bg-slate-100 dark:bg-slate-800 h-2.5 rounded-full overflow-hidden">
                        <div 
                            class="bg-gradient-to-r from-pink-500 to-rose-500 h-full transition-all duration-300 rounded-full"
                            style="width: {processProgress}%">
                        </div>
                    </div>
                </div>
            {/if}
        </div>

        <!-- Financial Overview Cards (BRUTO, NETTO, TOTAL SAMA GMV, PEMAKAIAN GMV) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-gradient-to-br from-purple-900/40 via-slate-900 to-slate-900 p-5 rounded-2xl border border-purple-500/30 shadow-lg relative overflow-hidden">
                <div class="flex items-center justify-between">
                    <p class="text-xs text-purple-300 font-bold uppercase tracking-wider">BRUTO (Subtotal Penjualan)</p>
                    <span class="px-2 py-0.5 text-[10px] font-extrabold bg-purple-500/20 text-purple-300 rounded-full border border-purple-500/30">KOTOR</span>
                </div>
                <h3 class="text-2xl font-black text-white mt-2 tracking-tight">{formatRupiah(exportPayload.bruto)}</h3>
                <p class="text-[11px] text-purple-400 mt-1">Total Subtotal Sebelum Diskon Marketplace</p>
            </div>

            <div class="bg-gradient-to-br from-emerald-900/40 via-slate-900 to-slate-900 p-5 rounded-2xl border border-emerald-500/30 shadow-lg relative overflow-hidden">
                <div class="flex items-center justify-between">
                    <p class="text-xs text-emerald-300 font-bold uppercase tracking-wider">NETTO (Pencairan Bersih)</p>
                    <span class="px-2 py-0.5 text-[10px] font-extrabold bg-emerald-500/20 text-emerald-300 rounded-full border border-emerald-500/30">BERSIH</span>
                </div>
                <h3 class="text-2xl font-black text-emerald-400 mt-2 tracking-tight">{formatRupiah(exportPayload.netto)}</h3>
                <p class="text-[11px] text-emerald-400/80 mt-1">Total GMV Dikurangi Pemakaian GMV Ads</p>
            </div>

            <div class="bg-gradient-to-br from-cyan-900/40 via-slate-900 to-slate-900 p-5 rounded-2xl border border-cyan-500/30 shadow-lg relative overflow-hidden">
                <div class="flex items-center justify-between">
                    <p class="text-xs text-cyan-300 font-bold uppercase tracking-wider">TOTAL SAMA GMV</p>
                    <span class="px-2 py-0.5 text-[10px] font-extrabold bg-cyan-500/20 text-cyan-300 rounded-full border border-cyan-500/30">OMSET GMV</span>
                </div>
                <h3 class="text-2xl font-black text-cyan-400 mt-2 tracking-tight">{formatRupiah(exportPayload.total_sama_gmv)}</h3>
                <p class="text-[11px] text-cyan-400/80 mt-1">Total Settlement Dari Transaksi Sales</p>
            </div>

            <div class="bg-gradient-to-br from-rose-900/40 via-slate-900 to-slate-900 p-5 rounded-2xl border border-rose-500/30 shadow-lg relative overflow-hidden">
                <div class="flex items-center justify-between">
                    <p class="text-xs text-rose-300 font-bold uppercase tracking-wider">PEMAKAIAN GMV (Iklan)</p>
                    <span class="px-2 py-0.5 text-[10px] font-extrabold bg-rose-500/20 text-rose-300 rounded-full border border-rose-500/30">ADS FEE</span>
                </div>
                <h3 class="text-2xl font-black text-rose-400 mt-2 tracking-tight">{formatRupiah(exportPayload.pemakaian_gmv)}</h3>
                <p class="text-[11px] text-rose-400/80 mt-1">Total Potongan Top Up GMV Ads TikTok</p>
            </div>
        </div>

        <!-- Top Level Metrics Cards (Matching Target Export V1) -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                <p class="text-xs text-slate-500 dark:text-slate-400 font-semibold">Total Master IDs</p>
                <h4 class="text-xl font-extrabold text-slate-800 dark:text-slate-100 mt-1">{exportPayload.total_master}</h4>
            </div>
            <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                <p class="text-xs text-slate-500 dark:text-slate-400 font-semibold font-bold text-pink-600 dark:text-pink-400">Total Matched</p>
                <h4 class="text-xl font-extrabold text-pink-600 dark:text-pink-400 mt-1">{exportPayload.total_matched}</h4>
            </div>
            <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                <p class="text-xs text-slate-500 dark:text-slate-400 font-semibold">Total Payment Master</p>
                <h4 class="text-base font-extrabold text-emerald-600 dark:text-emerald-400 mt-1 truncate">{formatRupiah(exportPayload.total_payment)}</h4>
            </div>
            <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                <p class="text-xs text-slate-500 dark:text-slate-400 font-semibold">Matched Master Amt</p>
                <h4 class="text-base font-extrabold text-cyan-600 dark:text-cyan-400 mt-1 truncate">{formatRupiah(exportPayload.matched_master_amount)}</h4>
            </div>
            <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                <p class="text-xs text-slate-500 dark:text-slate-400 font-semibold">Total Qty Satuan</p>
                <h4 class="text-xl font-extrabold text-slate-800 dark:text-slate-100 mt-1">{exportPayload.total_quantity} Pcs</h4>
            </div>
            <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                <p class="text-xs text-slate-500 dark:text-slate-400 font-semibold">Missing Order IDs</p>
                <h4 class="text-xl font-extrabold text-rose-600 dark:text-rose-400 mt-1">{exportPayload.missing_count}</h4>
            </div>
        </div>

        <!-- Main Results Area -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
            <!-- Navigation Tabs & Search & Export -->
            <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex flex-col md:flex-row items-center justify-between gap-4 bg-slate-50/50 dark:bg-slate-950/30">
                <div class="flex items-center gap-2 bg-slate-200/60 dark:bg-slate-800/60 p-1 rounded-xl w-full md:w-auto">
                    <button
                        onclick={() => activeTab = 'matched_data'}
                        class="flex-1 md:flex-initial px-4 py-2 text-xs font-bold rounded-lg transition-all flex items-center justify-center gap-1.5 {activeTab === 'matched_data' ? 'bg-white dark:bg-slate-900 text-pink-600 dark:text-pink-400 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'}">
                        <ShoppingBag class="w-3.5 h-3.5" />
                        <span>Matched Data ({exportPayload.total_matched})</span>
                    </button>
                    <button
                        onclick={() => activeTab = 'sku_breakdown'}
                        class="flex-1 md:flex-initial px-4 py-2 text-xs font-bold rounded-lg transition-all flex items-center justify-center gap-1.5 {activeTab === 'sku_breakdown' ? 'bg-white dark:bg-slate-900 text-pink-600 dark:text-pink-400 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'}">
                        <Package class="w-3.5 h-3.5" />
                        <span>Breakdown SKU ({skuBreakdown.length})</span>
                    </button>
                    <button
                        onclick={() => activeTab = 'missing'}
                        class="flex-1 md:flex-initial px-4 py-2 text-xs font-bold rounded-lg transition-all flex items-center justify-center gap-1.5 {activeTab === 'missing' ? 'bg-white dark:bg-slate-900 text-rose-600 dark:text-rose-400 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'}">
                        <AlertTriangle class="w-3.5 h-3.5" />
                        <span>Missing Order IDs ({missingOrderIds.length})</span>
                    </button>
                </div>

                <div class="flex items-center gap-3 w-full md:w-auto">
                    <!-- Search Input -->
                    <div class="relative w-full md:w-64">
                        <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                        <input
                            type="text"
                            bind:value={searchFilter}
                            placeholder="Cari SKU, Order ID, Produk..."
                            class="w-full pl-9 pr-3 py-1.5 text-xs bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl focus:outline-none focus:border-pink-500 text-slate-800 dark:text-slate-200"
                        />
                    </div>

                    <!-- Download Export -->
                    <div class="flex items-center gap-2">
                        <input 
                            type="text" 
                            bind:value={exportFileName}
                            placeholder="Nama file export"
                            class="w-44 px-3 py-1.5 text-xs bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl focus:outline-none focus:border-pink-500 text-slate-800 dark:text-slate-200 font-medium"
                        />
                        <button
                            onclick={downloadCleanedExport}
                            disabled={exportPayload.data.length === 0}
                            class="px-4 py-1.5 bg-pink-600 hover:bg-pink-700 disabled:opacity-50 text-white font-bold text-xs rounded-xl shadow-sm flex items-center gap-1.5 transition-all shrink-0">
                            <Download class="w-3.5 h-3.5" />
                            <span>Export Result</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tab Contents -->
            <div class="p-6">
                {#if activeTab === 'matched_data'}
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold bg-slate-50 dark:bg-slate-950/50">
                                    <th class="p-3">No. Pesanan</th>
                                    <th class="p-3">No. Resi</th>
                                    <th class="p-3">Nomor Referensi SKU</th>
                                    <th class="p-3">Nama Variasi</th>
                                    <th class="p-3 text-center">Jumlah</th>
                                    <th class="p-3 text-right">Total Pembayaran</th>
                                    <th class="p-3">Nama Produk</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                                {#each filteredMatchedData as item}
                                    <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                                        <td class="p-3 font-mono font-bold text-pink-600 dark:text-pink-400">{item['No. Pesanan']}</td>
                                        <td class="p-3 font-mono text-slate-600 dark:text-slate-400">{item['No. Resi']}</td>
                                        <td class="p-3 font-semibold text-slate-800 dark:text-slate-200">{item['Nomor Referensi SKU']}</td>
                                        <td class="p-3 text-slate-600 dark:text-slate-400">{item['Nama Variasi']}</td>
                                        <td class="p-3 text-center font-bold">{item['Jumlah']}</td>
                                        <td class="p-3 text-right font-bold text-emerald-600 dark:text-emerald-400">{formatRupiah(item['Total Pembayaran'])}</td>
                                        <td class="p-3 text-slate-700 dark:text-slate-300 truncate max-w-xs">{item['Nama Produk']}</td>
                                    </tr>
                                {:else}
                                    <tr>
                                        <td colspan="7" class="text-center py-12 text-slate-400">
                                            Belum ada data matched. Silakan upload Master & Raw JSON lalu klik "Jalankan Data Cleaning TikTok".
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                {:else if activeTab === 'sku_breakdown'}
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold bg-slate-50 dark:bg-slate-950/50">
                                    <th class="p-3">SKU Barang</th>
                                    <th class="p-3">Size / Variasi</th>
                                    <th class="p-3 text-center">Total Qty (Pcs)</th>
                                    <th class="p-3 text-center">Jumlah Transaksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                                {#each filteredSkuBreakdown as row}
                                    <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                                        <td class="p-3 font-bold text-slate-800 dark:text-slate-200">{row.sku}</td>
                                        <td class="p-3 font-semibold text-slate-600 dark:text-slate-400">{row.size}</td>
                                        <td class="p-3 text-center font-extrabold text-pink-600 dark:text-pink-400">{row.total_qty}</td>
                                        <td class="p-3 text-center text-slate-600 dark:text-slate-400">{row.order_count}</td>
                                    </tr>
                                {:else}
                                    <tr>
                                        <td colspan="4" class="text-center py-12 text-slate-400">
                                            Tidak ada data SKU breakdown.
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                {:else if activeTab === 'missing'}
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold bg-slate-50 dark:bg-slate-950/50">
                                    <th class="p-3">Missing Order ID (TikTok Master)</th>
                                    <th class="p-3 text-right">Master Settlement Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                                {#each missingOrderIds as row}
                                    <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                                        <td class="p-3 font-mono font-bold text-rose-600 dark:text-rose-400">{row.order_id}</td>
                                        <td class="p-3 text-right font-bold text-slate-800 dark:text-slate-200">{formatRupiah(row.amount)}</td>
                                    </tr>
                                {:else}
                                    <tr>
                                        <td colspan="2" class="text-center py-12 text-slate-400">
                                            Tidak ada Order ID yang missing! Seluruh Master Order ID terhubung dengan data raw.
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                {/if}
            </div>
        </div>
    </div>
</AdminLayout>
