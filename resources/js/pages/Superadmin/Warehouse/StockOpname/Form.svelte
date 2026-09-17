<script lang="ts">
    import AdminLayout from '@/Layouts/AdminLayout.svelte';
    import { router, Link } from '@inertiajs/svelte';
    import { ClipboardCheck, Plus, Trash2, ArrowLeft, Save } from '@lucide/svelte';

    interface OptionItem {
        id: number;
        name: string;
    }

    interface VariantOption {
        id: number;
        sku: string;
        size: string;
        color: string;
        product?: { product_name: string };
        stocks?: Array<{ warehouse_id: number; qty: number }>;
    }

    let {
        warehouses = [],
        variants = [],
    }: {
        warehouses?: OptionItem[];
        variants?: VariantOption[];
    } = $props();

    let form = $state({
        warehouse_id: warehouses[0]?.id ?? '',
        date: new Date().toISOString().split('T')[0],
        note: '',
        items: [
            {
                product_variant_id: variants[0]?.id ?? '',
                system_qty: 0,
                physical_qty: 0,
            },
        ],
    });

    function getSystemStockForVariant(variantId: number, whId: number): number {
        const v = variants.find((opt) => opt.id === Number(variantId));
        if (!v || !v.stocks) return 0;
        const st = v.stocks.find((s) => s.warehouse_id === Number(whId));
        return st ? st.qty : 0;
    }

    function onWarehouseOrVariantChange(idx: number) {
        const item = form.items[idx];
        item.system_qty = getSystemStockForVariant(item.product_variant_id, form.warehouse_id);
    }

    function addItem() {
        const firstVarId = variants[0]?.id ?? '';
        const sysQty = getSystemStockForVariant(Number(firstVarId), Number(form.warehouse_id));
        form.items.push({
            product_variant_id: firstVarId,
            system_qty: sysQty,
            physical_qty: sysQty,
        });
    }

    function removeItem(idx: number) {
        if (form.items.length > 1) {
            form.items.splice(idx, 1);
        }
    }

    function handleSubmit() {
        router.post('/superadmin/warehouse/stock-opname', form);
    }
</script>

<AdminLayout
    title="Form Audit Stock Opname"
    breadcrumbs={[{ name: 'Mutasi Stok' }, { name: 'Audit Stock Opname', url: '/superadmin/warehouse/stock-opname' }, { name: 'Mulai Audit' }]}
>
    <!-- Header -->
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <Link
                href="/superadmin/warehouse/stock-opname"
                class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-2.5 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors shadow-xs"
            >
                <ArrowLeft class="h-5 w-5" />
            </Link>
            <div>
                <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                    <ClipboardCheck class="h-7 w-7 text-amber-500" />
                    Input Hasil Audit Stock Opname
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Bandingkan stok sistem dengan hasil perhitungan fisik di lokasi gudang</p>
            </div>
        </div>

        <button
            onclick={handleSubmit}
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-amber-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg shadow-amber-600/30 hover:bg-amber-700 transition-colors"
        >
            <Save class="h-4 w-4" />
            Simpan Hasil Opname
        </button>
    </div>

    <form onsubmit={(e) => { e.preventDefault(); handleSubmit(); }} class="space-y-8">
        <!-- Master Metadata Card -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-100 dark:border-slate-800 space-y-6">
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 border-b border-slate-100 dark:border-slate-800 pb-3">Informasi Audit</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Warehouse -->
                <div>
                    <label for="warehouse_id" class="block text-xs font-semibold uppercase text-slate-500 mb-1.5">Gudang Tempat Audit</label>
                    <select
                        bind:value={form.warehouse_id}
                        onchange={() => form.items.forEach((_, idx) => onWarehouseOrVariantChange(idx))}
                        required
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-amber-500"
                    >
                        {#each warehouses as w}
                            <option value={w.id}>{w.name}</option>
                        {/each}
                    </select>
                </div>

                <!-- Date -->
                <div>
                    <label for="date" class="block text-xs font-semibold uppercase text-slate-500 mb-1.5">Tanggal Audit</label>
                    <input
                        type="date"
                        bind:value={form.date}
                        required
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-amber-500"
                    />
                </div>
            </div>

            <div>
                <label for="note" class="block text-xs font-semibold uppercase text-slate-500 mb-1.5">Catatan Audit (Opsional)</label>
                <input
                    type="text"
                    bind:value={form.note}
                    placeholder="Audit bulanan rak A & B..."
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-amber-500"
                />
            </div>
        </div>

        <!-- Opname Items Table -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-100 dark:border-slate-800 space-y-6">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
                <div>
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Item Audit Varian</h3>
                    <p class="text-xs text-slate-500">Masukkan jumlah stok fisik hasil hitung manual. Sistem akan menghitung selisih secara otomatis.</p>
                </div>

                <button
                    type="button"
                    onclick={addItem}
                    class="inline-flex items-center gap-1.5 rounded-xl bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900 px-3.5 py-2 text-xs font-semibold hover:bg-slate-700 transition-colors"
                >
                    <Plus class="h-3.5 w-3.5" />
                    Tambah Item Row
                </button>
            </div>

            <div class="space-y-4">
                {#each form.items as it, idx}
                    {@const diff = it.physical_qty - it.system_qty}
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                        <!-- Variant Select -->
                        <div class="md:col-span-5">
                            <label for="variant_{idx}" class="block text-[11px] font-semibold text-slate-500 mb-1">Pilih SKU Varian</label>
                            <select
                                id="variant_{idx}"
                                bind:value={it.product_variant_id}
                                onchange={() => onWarehouseOrVariantChange(idx)}
                                required
                                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-amber-500"
                            >
                                {#each variants as v}
                                    <option value={v.id}>
                                        {v.sku} ({v.product?.product_name ?? 'Produk'} - {v.size}/{v.color})
                                    </option>
                                {/each}
                            </select>
                        </div>

                        <!-- System Qty -->
                        <div class="md:col-span-2">
                            <label for="sys_qty_{idx}" class="block text-[11px] font-semibold text-slate-500 mb-1">Stok Sistem</label>
                            <input
                                id="sys_qty_{idx}"
                                type="number"
                                bind:value={it.system_qty}
                                readonly
                                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-100 dark:bg-slate-800 px-3 py-2 text-xs font-bold text-slate-600 dark:text-slate-300"
                            />
                        </div>

                        <!-- Physical Qty Input -->
                        <div class="md:col-span-2">
                            <label for="phy_qty_{idx}" class="block text-[11px] font-semibold text-slate-500 mb-1">Stok Fisik (Hitung)</label>
                            <input
                                id="phy_qty_{idx}"
                                type="number"
                                bind:value={it.physical_qty}
                                min="0"
                                required
                                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-xs font-bold text-amber-600 focus:ring-2 focus:ring-amber-500"
                            />
                        </div>

                        <!-- Diff Calculated -->
                        <div class="md:col-span-2">
                            <label for="diff_{idx}" class="block text-[11px] font-semibold text-slate-500 mb-1">Selisih (Diff)</label>
                            <div class="px-3 py-2 rounded-xl text-xs font-bold font-mono {diff < 0 ? 'bg-rose-50 text-rose-600' : diff > 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-600'}">
                                {diff > 0 ? `+${diff}` : diff} pcs
                            </div>
                        </div>

                        <!-- Remove Button -->
                        <div class="md:col-span-1 text-right pt-4 md:pt-0">
                            <button
                                type="button"
                                onclick={() => removeItem(idx)}
                                disabled={form.items.length <= 1}
                                class="rounded-lg p-2 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/50 disabled:opacity-30 transition-colors"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                {/each}
            </div>
        </div>
    </form>
</AdminLayout>
