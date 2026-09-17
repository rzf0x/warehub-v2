<script lang="ts">
    import SellerMobileLayout from '@/layouts/SellerMobileLayout.svelte';
    import { Link, useForm } from '@inertiajs/svelte';
    import {
        ArrowLeft,
        ClipboardList,
        Plus,
        Minus,
        Search,
        CheckCircle2,
        Store,
        Coins,
        Boxes,
        ChevronRight,
        ChevronLeft,
        Send,
        Sparkles,
        Trash2
    } from '@lucide/svelte';

    interface VariantOption {
        id: number;
        product_name: string;
        category_name: string;
        sku: string;
        size: string;
        color: string;
        price: number;
        selling_price: number;
    }

    interface SelectedItem {
        product_variant_id: number;
        product_name: string;
        sku: string;
        variant_info: string;
        qty: number;
        price: number;
    }

    let {
        stores = [],
        poTypes = [],
        variants = [],
        seller = null,
    }: {
        stores?: { id: number; store_name: string; marketplace: string }[];
        poTypes?: { id: number; name: string }[];
        variants?: VariantOption[];
        seller?: { id: number; seller_name: string } | null;
    } = $props();

    // 3-Step Wizard State
    let currentStep = $state(1);
    let searchVariant = $state('');

    const form = useForm({
        store_id: stores[0]?.id?.toString() ?? '',
        po_type_id: poTypes[0]?.id?.toString() ?? '',
        priority: 'normal',
        notes: '',
        items: [] as SelectedItem[],
    });

    function formatRupiah(num: number): string {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num || 0);
    }

    let filteredVariants = $derived(
        variants.filter((v) =>
            !searchVariant.trim() ||
            v.product_name.toLowerCase().includes(searchVariant.toLowerCase()) ||
            v.sku.toLowerCase().includes(searchVariant.toLowerCase()) ||
            v.color.toLowerCase().includes(searchVariant.toLowerCase()) ||
            v.size.toLowerCase().includes(searchVariant.toLowerCase())
        )
    );

    function getItemQty(variantId: number): number {
        const item = $form.items.find((i) => i.product_variant_id === variantId);
        return item ? item.qty : 0;
    }

    function updateItemQty(variant: VariantOption, delta: number) {
        const index = $form.items.findIndex((i) => i.product_variant_id === variant.id);
        if (index > -1) {
            const newQty = $form.items[index].qty + delta;
            if (newQty <= 0) {
                $form.items.splice(index, 1);
            } else {
                $form.items[index].qty = newQty;
            }
        } else if (delta > 0) {
            $form.items.push({
                product_variant_id: variant.id,
                product_name: variant.product_name,
                sku: variant.sku,
                variant_info: trimStr(`${variant.color} ${variant.size}`),
                qty: 1,
                price: variant.price,
            });
        }
    }

    function trimStr(str: string): string {
        return str.trim();
    }

    function removeItem(variantId: number) {
        $form.items = $form.items.filter((i) => i.product_variant_id !== variantId);
    }

    let totalSelectedItems = $derived(
        $form.items.reduce((sum, item) => sum + item.qty, 0)
    );

    let totalEstimatedSubtotal = $derived(
        $form.items.reduce((sum, item) => sum + (item.qty * item.price), 0)
    );

    function handleSubmit() {
        if ($form.items.length === 0) {
            alert('Silakan pilih minimal 1 varian produk untuk di-order.');
            return;
        }

        $form.post('/seller/purchase-orders');
    }
</script>

<SellerMobileLayout title="Form Wizard PO Restock">
    <div class="space-y-4 pb-12">
        <!-- Back Header -->
        <div class="flex items-center gap-3">
            <Link
                href="/seller/purchase-orders"
                class="flex h-9 w-9 items-center justify-center rounded-xl bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800 shadow-2xs hover:bg-slate-100 transition-colors"
            >
                <ArrowLeft class="h-5 w-5" />
            </Link>

            <div>
                <h1 class="text-lg font-black text-slate-800 dark:text-slate-100 leading-tight">Pengajuan PO Restock</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Step {currentStep} dari 3 • Form Wizard Mobile</p>
            </div>
        </div>

        <!-- Wizard Step Progress Bar -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-3 shadow-xs border border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
            <button
                type="button"
                onclick={() => (currentStep = 1)}
                class="flex items-center gap-1.5 font-bold transition-colors {currentStep === 1 ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400'}"
            >
                <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs {currentStep === 1 ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800'}">1</span>
                <span>Toko</span>
            </button>

            <span class="h-0.5 w-6 bg-slate-200 dark:bg-slate-800"></span>

            <button
                type="button"
                onclick={() => { if ($form.items.length > 0 || currentStep > 1) currentStep = 2; }}
                class="flex items-center gap-1.5 font-bold transition-colors {currentStep === 2 ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400'}"
            >
                <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs {currentStep === 2 ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800'}">2</span>
                <span>Varian ({totalSelectedItems})</span>
            </button>

            <span class="h-0.5 w-6 bg-slate-200 dark:bg-slate-800"></span>

            <button
                type="button"
                onclick={() => { if ($form.items.length > 0) currentStep = 3; }}
                class="flex items-center gap-1.5 font-bold transition-colors {currentStep === 3 ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400'}"
            >
                <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs {currentStep === 3 ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800'}">3</span>
                <span>Review</span>
            </button>
        </div>

        <!-- STEP 1: Toko Tujuan & Prioritas -->
        {#if currentStep === 1}
            <div class="rounded-3xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800 space-y-4">
                <div>
                    <h3 class="text-sm font-extrabold text-slate-800 dark:text-slate-100">Step 1: Pilih Toko & Prioritas</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Toko tujuan alokasi barang restock konveksi</p>
                </div>

                <div>
                    <label for="f_store" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">
                        Toko Marketplace Tujuan
                    </label>
                    <select
                        id="f_store"
                        bind:value={$form.store_id}
                        class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-3 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                    >
                        <option value="">-- Tanpa Toko / Stok Umum Gudang --</option>
                        {#each stores as st}
                            <option value={st.id}>{st.store_name} ({st.marketplace})</option>
                        {/each}
                    </select>
                </div>

                <div>
                    <label for="f_po_type" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">
                        Tipe Pengajuan PO
                    </label>
                    <select
                        id="f_po_type"
                        bind:value={$form.po_type_id}
                        class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-3 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                    >
                        {#each poTypes as pt}
                            <option value={pt.id}>{pt.name}</option>
                        {/each}
                    </select>
                </div>

                <div>
                    <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">
                        Tingkat Prioritas Produksi
                    </span>
                    <div class="grid grid-cols-3 gap-2">
                        <label class="flex flex-col items-center justify-center p-3 rounded-2xl border text-center cursor-pointer transition-all
                            {$form.priority === 'normal' ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 font-bold' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400'}"
                        >
                            <input type="radio" value="normal" bind:group={$form.priority} class="hidden" />
                            <span class="text-xs">Normal</span>
                        </label>

                        <label class="flex flex-col items-center justify-center p-3 rounded-2xl border text-center cursor-pointer transition-all
                            {$form.priority === 'high' ? 'border-amber-500 bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 font-bold' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400'}"
                        >
                            <input type="radio" value="high" bind:group={$form.priority} class="hidden" />
                            <span class="text-xs">High</span>
                        </label>

                        <label class="flex flex-col items-center justify-center p-3 rounded-2xl border text-center cursor-pointer transition-all
                            {$form.priority === 'urgent' ? 'border-rose-600 bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 font-bold' : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400'}"
                        >
                            <input type="radio" value="urgent" bind:group={$form.priority} class="hidden" />
                            <span class="text-xs">Urgent</span>
                        </label>
                    </div>
                </div>

                <div class="pt-3 flex justify-end">
                    <button
                        type="button"
                        onclick={() => (currentStep = 2)}
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-6 py-3 text-xs font-bold text-white shadow-lg shadow-indigo-600/30 hover:bg-indigo-700 transition-colors"
                    >
                        Lanjut Step 2: Pilih Varian
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>
            </div>
        {/if}

        <!-- STEP 2: Pilih Varian & Qty Stepper (48px Touch Targets) -->
        {#if currentStep === 2}
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-800 dark:text-slate-100">Step 2: Pilih Varian Produk</h3>
                        <p class="text-xs text-slate-400">Gunakan tombol + / - untuk atur Qty order</p>
                    </div>
                </div>

                <!-- Sticky Search -->
                <div class="relative">
                    <Search class="absolute left-3 top-3 h-4 w-4 text-slate-400" />
                    <input
                        type="text"
                        placeholder="Cari SKU / produk..."
                        bind:value={searchVariant}
                        class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 pl-9 pr-4 py-2.5 text-xs text-slate-800 dark:text-slate-100 shadow-xs focus:ring-2 focus:ring-indigo-500"
                    />
                </div>

                <!-- Variant Selection List -->
                <div class="space-y-2 max-h-[55vh] overflow-y-auto pr-1">
                    {#each filteredVariants as v}
                        {@const currentQty = getItemQty(v.id)}
                        <div class="rounded-2xl bg-white dark:bg-slate-900 p-3.5 shadow-xs border border-slate-100 dark:border-slate-800 flex items-center justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <h4 class="text-xs font-extrabold text-slate-800 dark:text-slate-100 truncate">{v.product_name}</h4>
                                <p class="text-[11px] font-mono text-indigo-600 dark:text-indigo-400 font-bold">{v.sku}</p>
                                <p class="text-[10px] text-slate-400">
                                    {v.color} • {v.size} | HPP: {formatRupiah(v.price)}
                                </p>
                            </div>

                            <!-- 48px Touch Target Qty Stepper -->
                            <div class="flex items-center gap-1 shrink-0 rounded-2xl bg-slate-100 dark:bg-slate-800 p-1">
                                <button
                                    type="button"
                                    onclick={() => updateItemQty(v, -1)}
                                    disabled={currentQty === 0}
                                    class="flex h-11 w-11 items-center justify-center rounded-xl bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 shadow-2xs hover:bg-slate-200 dark:hover:bg-slate-700 disabled:opacity-30 active:scale-95 transition-all cursor-pointer"
                                    aria-label="Kurangi Qty"
                                >
                                    <Minus class="h-4 w-4" />
                                </button>

                                <span class="w-8 text-center text-xs font-black text-slate-800 dark:text-slate-100">
                                    {currentQty}
                                </span>

                                <button
                                    type="button"
                                    onclick={() => updateItemQty(v, 1)}
                                    class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-md shadow-indigo-600/30 hover:bg-indigo-700 active:scale-95 transition-all cursor-pointer"
                                    aria-label="Tambah Qty"
                                >
                                    <Plus class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    {:else}
                        <div class="rounded-2xl bg-white dark:bg-slate-900 p-8 text-center text-slate-400 border border-slate-100 dark:border-slate-800 text-xs">
                            Varian produk tidak ditemukan.
                        </div>
                    {/each}
                </div>
            </div>
        {/if}

        <!-- STEP 3: Review & Catatan Konveksi -->
        {#if currentStep === 3}
            <div class="rounded-3xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800 space-y-4">
                <div>
                    <h3 class="text-sm font-extrabold text-slate-800 dark:text-slate-100">Step 3: Review & Catatan</h3>
                    <p class="text-xs text-slate-400">Periksa ringkasan item & masukkan instruksi konveksi</p>
                </div>

                <!-- Selected Items List -->
                <div class="space-y-2">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">Ringkasan Items ({$form.items.length})</h4>

                    {#each $form.items as item}
                        <div class="rounded-2xl bg-slate-50 dark:bg-slate-950 p-3 border border-slate-200/80 dark:border-slate-800 flex items-center justify-between text-xs">
                            <div>
                                <p class="font-bold text-slate-800 dark:text-slate-100">{item.product_name}</p>
                                <p class="text-[10px] font-mono text-slate-400">{item.sku} ({item.variant_info})</p>
                                <p class="text-[10px] text-indigo-600 dark:text-indigo-400 font-semibold">{item.qty} Pcs × {formatRupiah(item.price)}</p>
                            </div>

                            <div class="flex items-center gap-2">
                                <span class="font-black text-slate-800 dark:text-slate-100">{formatRupiah(item.qty * item.price)}</span>
                                <button
                                    type="button"
                                    onclick={() => removeItem(item.product_variant_id)}
                                    class="p-1 text-rose-500 hover:bg-rose-100 dark:hover:bg-rose-950 rounded-lg transition-colors"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                    {/each}
                </div>

                <!-- Catatan Konveksi Field -->
                <div>
                    <label for="f_notes" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">
                        Catatan Khusus Konveksi / Instruksi
                    </label>
                    <textarea
                        id="f_notes"
                        rows="3"
                        placeholder="Contoh: Tolong utamakan varian warna hitam untuk dikirim minggu ini."
                        bind:value={$form.notes}
                        class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-3 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                    ></textarea>
                </div>
            </div>
        {/if}
    </div>

    <!-- Sticky Bottom Subtotal Action Panel -->
    <div class="fixed bottom-16 left-0 right-0 z-30 max-w-md mx-auto bg-white/95 dark:bg-slate-900/95 border-t border-slate-200/80 dark:border-slate-800 p-3.5 backdrop-blur-md shadow-2xl flex items-center justify-between gap-3">
        <div>
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Subtotal Estimasi PO</span>
            <span class="text-sm font-black text-indigo-600 dark:text-indigo-400">{formatRupiah(totalEstimatedSubtotal)}</span>
        </div>

        <div>
            {#if currentStep === 1}
                <button
                    type="button"
                    onclick={() => (currentStep = 2)}
                    class="inline-flex items-center gap-1.5 rounded-2xl bg-indigo-600 px-5 py-2.5 text-xs font-bold text-white shadow-lg shadow-indigo-600/30 hover:bg-indigo-700 transition-colors"
                >
                    Pilih Varian
                    <ChevronRight class="h-4 w-4" />
                </button>
            {:else if currentStep === 2}
                <button
                    type="button"
                    onclick={() => {
                        if ($form.items.length === 0) {
                            alert('Pilih minimal 1 varian produk.');
                            return;
                        }
                        currentStep = 3;
                    }}
                    class="inline-flex items-center gap-1.5 rounded-2xl bg-indigo-600 px-5 py-2.5 text-xs font-bold text-white shadow-lg shadow-indigo-600/30 hover:bg-indigo-700 transition-colors"
                >
                    Review PO ({totalSelectedItems})
                    <ChevronRight class="h-4 w-4" />
                </button>
            {:else if currentStep === 3}
                <button
                    type="button"
                    onclick={handleSubmit}
                    disabled={$form.processing || $form.items.length === 0}
                    class="inline-flex items-center gap-1.5 rounded-2xl bg-emerald-600 px-6 py-2.5 text-xs font-bold text-white shadow-lg shadow-emerald-600/30 hover:bg-emerald-700 disabled:opacity-50 transition-colors"
                >
                    <Send class="h-4 w-4" />
                    Kirim PO Restock
                </button>
            {/if}
        </div>
    </div>
</SellerMobileLayout>
