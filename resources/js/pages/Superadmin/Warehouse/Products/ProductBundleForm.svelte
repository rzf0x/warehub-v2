<script lang="ts">
    import AdminLayout from '@/Layouts/AdminLayout.svelte';
    import { router, Link } from '@inertiajs/svelte';
    import { PackagePlus, Plus, Trash2, ArrowLeft, Save, Boxes } from '@lucide/svelte';

    interface OptionItem {
        id: number;
        name: string;
    }

    interface AvailableVariant {
        id: number;
        product_id: number;
        sku: string;
        size: string;
        color: string;
        price: number;
        selling_price: number;
        product?: { product_name: string };
    }

    let {
        sellers = [],
        availableVariants = [],
    }: {
        sellers?: OptionItem[];
        availableVariants?: AvailableVariant[];
    } = $props();

    let form = $state({
        seller_id: sellers[0]?.id ?? '',
        bundle_name: '',
        parent_sku: '',
        price: 0,
        selling_price: 0,
        items: [
            { component_variant_id: availableVariants[0]?.id ?? '', quantity: 1 }
        ],
    });

    function addItem() {
        form.items.push({
            component_variant_id: availableVariants[0]?.id ?? '',
            quantity: 1,
        });
    }

    function removeItem(index: number) {
        if (form.items.length > 1) {
            form.items.splice(index, 1);
        }
    }

    function handleSubmit() {
        router.post('/superadmin/warehouse/products/bundle', form);
    }
</script>

<AdminLayout
    title="Rakit Produk Bundle Multi-SKU"
    breadcrumbs={[{ name: 'Gudang & Katalog' }, { name: 'Katalog Produk', url: '/superadmin/warehouse/products' }, { name: 'Rakit Bundle' }]}
>
    <!-- Page Header -->
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <Link
                href="/superadmin/warehouse/products"
                class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-2.5 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors shadow-xs"
            >
                <ArrowLeft class="h-5 w-5" />
            </Link>
            <div>
                <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                    <PackagePlus class="h-7 w-7 text-purple-600" />
                    Rakit Paket Produk Bundle (Multi-SKU)
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Gabungkan beberapa SKU produk tunggal menjadi 1 paket bundle penawaran</p>
            </div>
        </div>

        <button
            onclick={handleSubmit}
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-purple-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg shadow-purple-600/30 hover:bg-purple-700 transition-colors"
        >
            <Save class="h-4 w-4" />
            Simpan Paket Bundle
        </button>
    </div>

    <form onsubmit={(e) => { e.preventDefault(); handleSubmit(); }} class="space-y-8">
        <!-- Parent Bundle Card -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-100 dark:border-slate-800 space-y-6">
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 border-b border-slate-100 dark:border-slate-800 pb-3">Informasi Utama Paket Bundle</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Seller -->
                <div>
                    <label for="seller_id" class="block text-xs font-semibold uppercase text-slate-500 mb-1.5">Seller (Pemilik)</label>
                    <select
                        bind:value={form.seller_id}
                        required
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-purple-500"
                    >
                        <option value="">Pilih Seller</option>
                        {#each sellers as s}
                            <option value={s.id}>{s.name}</option>
                        {/each}
                    </select>
                </div>

                <!-- Bundle Name -->
                <div>
                    <label for="bundle_name" class="block text-xs font-semibold uppercase text-slate-500 mb-1.5">Nama Paket Bundle</label>
                    <input
                        type="text"
                        bind:value={form.bundle_name}
                        required
                        placeholder="Contoh: Paket Hemat Kaos KKA27 + KKA28"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-purple-500"
                    />
                </div>

                <!-- Parent SKU -->
                <div>
                    <label for="parent_sku" class="block text-xs font-semibold uppercase text-slate-500 mb-1.5">SKU Parent Bundle</label>
                    <input
                        type="text"
                        bind:value={form.parent_sku}
                        required
                        placeholder="Contoh: BUNDLE-KKA27-KKA28"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-purple-500 font-mono"
                    />
                </div>

                <!-- HPP Bundle -->
                <div>
                    <label for="price" class="block text-xs font-semibold uppercase text-slate-500 mb-1.5">HPP Total Bundle (Rp)</label>
                    <input
                        type="number"
                        bind:value={form.price}
                        required
                        min="0"
                        step="500"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-purple-500"
                    />
                </div>

                <!-- Selling Price Bundle -->
                <div>
                    <label for="selling_price" class="block text-xs font-semibold uppercase text-slate-500 mb-1.5">Harga Jual Paket (Rp)</label>
                    <input
                        type="number"
                        bind:value={form.selling_price}
                        required
                        min="0"
                        step="500"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-purple-500"
                    />
                </div>
            </div>
        </div>

        <!-- Bundle Items Composition -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-100 dark:border-slate-800 space-y-6">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
                <div>
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        <Boxes class="h-5 w-5 text-purple-600" />
                        Komponen SKU Anak (Child SKUs)
                    </h3>
                    <p class="text-xs text-slate-500">Pilih varian SKU yang akan dikurangi stoknya saat bundle ini terjual</p>
                </div>

                <button
                    type="button"
                    onclick={addItem}
                    class="inline-flex items-center gap-1.5 rounded-xl bg-purple-100 dark:bg-purple-950 text-purple-700 dark:text-purple-300 px-3.5 py-2 text-xs font-semibold hover:bg-purple-200 transition-colors"
                >
                    <Plus class="h-3.5 w-3.5" />
                    Tambah Komponen
                </button>
            </div>

            <div class="space-y-3">
                {#each form.items as item, idx}
                    <div class="flex items-center gap-4 p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                        <span class="text-xs font-bold text-slate-400">#{idx + 1}</span>

                        <div class="flex-1">
                            <label for="component_variant_{idx}" class="block text-[11px] font-semibold text-slate-500 mb-1">Varian Produk Anak</label>
                            <select
                                id="component_variant_{idx}"
                                bind:value={item.component_variant_id}
                                required
                                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-purple-500"
                            >
                                {#each availableVariants as v}
                                    <option value={v.id}>
                                        {v.sku} ({v.product?.product_name ?? 'Produk'} - {v.size}/{v.color})
                                    </option>
                                {/each}
                            </select>
                        </div>

                        <div class="w-32">
                            <label for="quantity_{idx}" class="block text-[11px] font-semibold text-slate-500 mb-1">Jumlah (Qty)</label>
                            <input
                                id="quantity_{idx}"
                                type="number"
                                bind:value={item.quantity}
                                min="1"
                                required
                                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-purple-500"
                            />
                        </div>

                        <div class="pt-5">
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
