<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import Modal from '@/components/Modal.svelte';
    import { router, Link } from '@inertiajs/svelte';
    import { Package, Plus, Trash2, ArrowLeft, Save, Sparkles, AlertTriangle } from '@lucide/svelte';

    interface VariantInput {
        id?: number;
        sku: string;
        size: string;
        color: string;
        price: number;
        selling_price: number;
        barcode?: string;
    }

    interface OptionItem {
        id: number;
        name?: string;
        seller_name?: string;
    }

    interface VariantTemplateItem {
        id: number;
        variant_template_id: number;
        size: string;
        color: string;
        price?: number | string;
    }

    interface VariantTemplate {
        id: number;
        name?: string;
        template_name?: string;
        items: VariantTemplateItem[];
    }

    let {
        product = null,
        sellers = [],
        brands = [],
        categories = [],
        konveksis = [],
        variantTemplates = [],
    }: {
        product?: {
            id: number;
            seller_id: number;
            brand_id: number | null;
            category_id: number | null;
            konveksi_id: number | null;
            product_name: string;
            sku: string;
            product_type: string;
            variants: VariantInput[];
        } | null;
        sellers?: OptionItem[];
        brands?: OptionItem[];
        categories?: OptionItem[];
        konveksis?: OptionItem[];
        variantTemplates?: VariantTemplate[];
    } = $props();

    const isEdit = $derived(!!product);

    let selectedTemplateId = $state<string>('');
    let isSkuAlertModalOpen = $state(false);

    let form = $state({
        seller_id: product?.seller_id ?? (sellers[0]?.id ?? ''),
        brand_id: product?.brand_id ?? '',
        category_id: product?.category_id ?? '',
        konveksi_id: product?.konveksi_id ?? '',
        product_name: product?.product_name ?? '',
        sku: product?.sku ?? '',
        product_type: product?.product_type ?? 'single',
        variants: product?.variants?.length
            ? product.variants.map((v) => ({ ...v }))
            : [
                  {
                      sku: '',
                      size: 'ALL SIZE',
                      color: 'STANDARD',
                      price: 0,
                      selling_price: 0,
                      barcode: '',
                  },
              ],
    });

    function addVariant() {
        const baseSku = form.sku ? `${form.sku}-` : '';
        const count = form.variants.length + 1;
        form.variants.push({
            sku: `${baseSku}V${count}`,
            size: 'S',
            color: 'HITAM',
            price: 0,
            selling_price: 0,
            barcode: '',
        });
    }

    function removeVariant(index: number) {
        if (form.variants.length > 1) {
            form.variants.splice(index, 1);
        }
    }

    function generateVariantsFromTemplate() {
        if (!form.sku || !form.sku.trim()) {
            isSkuAlertModalOpen = true;
            return;
        }

        const template = variantTemplates.find((t) => String(t.id) === String(selectedTemplateId));
        if (template && template.items && template.items.length > 0) {
            form.variants = template.items.map((item) => {
                const colorTag = item.color && item.color.toUpperCase() !== 'STANDARD' ? `-${item.color}` : '';
                const itemPrice = item.price ? Number(item.price) : 0;

                return {
                    sku: `${form.sku.trim()}-${item.size}${colorTag}`,
                    size: item.size,
                    color: item.color || 'STANDARD',
                    price: itemPrice,
                    selling_price: 0,
                    barcode: '',
                };
            });
        } else {
            const defaultSizes = ['S', 'M', 'L', 'XL', 'XXL'];
            form.variants = defaultSizes.map((sz) => ({
                sku: `${form.sku.trim()}-${sz}`,
                size: sz,
                color: 'STANDARD',
                price: 0,
                selling_price: 0,
                barcode: '',
            }));
        }
    }

    function handleSubmit() {
        if (isEdit && product) {
            router.put(`/superadmin/warehouse/products/${product.id}`, form);
        } else {
            router.post('/superadmin/warehouse/products', form);
        }
    }
</script>

<AdminLayout
    title={isEdit ? 'Edit Produk' : 'Tambah Produk Baru'}
    breadcrumbs={[{ name: 'Gudang & Katalog' }, { name: 'Katalog Produk', url: '/superadmin/warehouse/products' }, { name: isEdit ? 'Edit' : 'Tambah Produk' }]}
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
                    <Package class="h-7 w-7 text-indigo-600" />
                    {isEdit ? 'Edit Master Produk & Varian' : 'Tambah Master Produk & Varian Baru'}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Lengkapi identitas produk dan generator varian dinamis</p>
            </div>
        </div>

        <button
            onclick={handleSubmit}
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/30 hover:bg-indigo-700 transition-colors"
        >
            <Save class="h-4 w-4" />
            {isEdit ? 'Simpan Perubahan' : 'Simpan Produk Baru'}
        </button>
    </div>

    <form onsubmit={(e) => { e.preventDefault(); handleSubmit(); }} class="space-y-8">
        <!-- Master Product Card -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-100 dark:border-slate-800 space-y-6">
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 border-b border-slate-100 dark:border-slate-800 pb-3">Informasi Utama Produk</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Seller -->
                <div>
                    <label for="seller_id" class="block text-xs font-semibold uppercase text-slate-500 mb-1.5">Seller (Pemilik)</label>
                    <select
                        bind:value={form.seller_id}
                        required
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                    >
                        <option value="">Pilih Seller</option>
                        {#each sellers as s}
                            <option value={s.id}>{s.seller_name || s.name || `Seller #${s.id}`}</option>
                        {/each}
                    </select>
                </div>

                <!-- Product Name -->
                <div>
                    <label for="product_name" class="block text-xs font-semibold uppercase text-slate-500 mb-1.5">Nama Produk</label>
                    <input
                        type="text"
                        bind:value={form.product_name}
                        required
                        placeholder="Contoh: Kaos Polos Cotton Combed 30s"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                    />
                </div>

                <!-- Master SKU -->
                <div>
                    <label for="sku" class="block text-xs font-semibold uppercase text-slate-500 mb-1.5">SKU Master Parent</label>
                    <input
                        type="text"
                        bind:value={form.sku}
                        required
                        placeholder="Contoh: KKA27"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500 font-mono"
                    />
                </div>

                <!-- Brand -->
                <div>
                    <label for="brand_id" class="block text-xs font-semibold uppercase text-slate-500 mb-1.5">Brand Master</label>
                    <select
                        bind:value={form.brand_id}
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                    >
                        <option value="">Tidak ada brand</option>
                        {#each brands as b}
                            <option value={b.id}>{b.name}</option>
                        {/each}
                    </select>
                </div>

                <!-- Category -->
                <div>
                    <label for="category_id" class="block text-xs font-semibold uppercase text-slate-500 mb-1.5">Kategori</label>
                    <select
                        bind:value={form.category_id}
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                    >
                        <option value="">Tidak ada kategori</option>
                        {#each categories as c}
                            <option value={c.id}>{c.name}</option>
                        {/each}
                    </select>
                </div>

                <!-- Konveksi -->
                <div>
                    <label for="konveksi_id" class="block text-xs font-semibold uppercase text-slate-500 mb-1.5">Vendor Konveksi (Mitra Produksi)</label>
                    <select
                        bind:value={form.konveksi_id}
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                    >
                        <option value="">Tidak ada vendor konveksi</option>
                        {#each konveksis as k}
                            <option value={k.id}>{k.name}</option>
                        {/each}
                    </select>
                </div>
            </div>
        </div>

        <!-- Dynamic Variants Section -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-100 dark:border-slate-800 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-4">
                <div>
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Generator Varian Dinamis</h3>
                    <p class="text-xs text-slate-500">Atur SKU varian, ukuran, warna, HPP modal, harga jual, dan barcode berdasarkan Template Varian</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <select
                        bind:value={selectedTemplateId}
                        onchange={generateVariantsFromTemplate}
                        class="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                    >
                        <option value="">-- Pilih Template Varian --</option>
                        {#each variantTemplates as t}
                            <option value={t.id}>{t.template_name || t.name || `Template #${t.id}`} ({t.items?.length ?? 0} ukuran)</option>
                        {/each}
                    </select>

                    <button
                        type="button"
                        onclick={generateVariantsFromTemplate}
                        class="inline-flex items-center gap-1.5 rounded-xl border border-indigo-200 dark:border-indigo-900 bg-indigo-50 dark:bg-indigo-950/50 px-3.5 py-2 text-xs font-semibold text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 transition-colors"
                    >
                        <Sparkles class="h-3.5 w-3.5" />
                        Auto Generate Varian
                    </button>
                    <button
                        type="button"
                        onclick={addVariant}
                        class="inline-flex items-center gap-1.5 rounded-xl bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900 px-3.5 py-2 text-xs font-semibold hover:bg-slate-700 transition-colors"
                    >
                        <Plus class="h-3.5 w-3.5" />
                        Tambah Row Varian
                    </button>
                </div>
            </div>

            <!-- Table Varian -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600 dark:text-slate-400">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 text-xs uppercase font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800">
                        <tr>
                            <th class="px-4 py-3">SKU Varian</th>
                            <th class="px-4 py-3">Ukuran (Size)</th>
                            <th class="px-4 py-3">Warna (Color)</th>
                            <th class="px-4 py-3">HPP Modal (Rp)</th>
                            <th class="px-4 py-3">Harga Jual (Rp)</th>
                            <th class="px-4 py-3">Barcode</th>
                            <th class="px-4 py-3 text-center">Hapus</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        {#each form.variants as v, idx}
                            <tr>
                                <td class="px-3 py-2">
                                    <input
                                        type="text"
                                        bind:value={v.sku}
                                        required
                                        placeholder="KKA27-M"
                                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs font-mono text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                                    />
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        type="text"
                                        bind:value={v.size}
                                        required
                                        placeholder="M"
                                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                                    />
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        type="text"
                                        bind:value={v.color}
                                        required
                                        placeholder="HITAM"
                                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                                    />
                                </td>
                                <td class="px-3 py-2 min-w-[130px]">
                                    <div class="relative rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 focus-within:ring-2 focus-within:ring-indigo-500 overflow-hidden">
                                        <div class="flex items-center px-2.5">
                                            <span class="text-xs font-semibold text-slate-400 shrink-0 select-none">Rp</span>
                                            <input
                                                type="number"
                                                bind:value={v.price}
                                                required
                                                min="0"
                                                step="500"
                                                class="w-full bg-transparent px-1.5 py-2 text-xs font-medium text-slate-800 dark:text-slate-100 focus:outline-none"
                                            />
                                        </div>
                                    </div>
                                    <span class="text-[10px] text-indigo-600 dark:text-indigo-400 font-semibold px-1 block mt-0.5">
                                        Rp {(Number(v.price) || 0).toLocaleString('id-ID')}
                                    </span>
                                </td>
                                <td class="px-3 py-2 min-w-[130px]">
                                    <div class="relative rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 focus-within:ring-2 focus-within:ring-indigo-500 overflow-hidden">
                                        <div class="flex items-center px-2.5">
                                            <span class="text-xs font-semibold text-slate-400 shrink-0 select-none">Rp</span>
                                            <input
                                                type="number"
                                                bind:value={v.selling_price}
                                                required
                                                min="0"
                                                step="500"
                                                class="w-full bg-transparent px-1.5 py-2 text-xs font-medium text-slate-800 dark:text-slate-100 focus:outline-none"
                                            />
                                        </div>
                                    </div>
                                    <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold px-1 block mt-0.5">
                                        Rp {(Number(v.selling_price) || 0).toLocaleString('id-ID')}
                                    </span>
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        type="text"
                                        bind:value={v.barcode}
                                        placeholder="Opsional"
                                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs font-mono text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                                    />
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <button
                                        type="button"
                                        onclick={() => removeVariant(idx)}
                                        disabled={form.variants.length <= 1}
                                        class="rounded-lg p-2 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/50 disabled:opacity-30 transition-colors"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </div>
    </form>

    <!-- SKU Warning Modal -->
    <Modal show={isSkuAlertModalOpen} title="Informasi Peringatan" maxWidth="md" onclose={() => isSkuAlertModalOpen = false}>
        <div class="space-y-4 text-center py-2">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-50 dark:bg-amber-950/50 text-amber-500 border border-amber-200 dark:border-amber-900 shadow-md">
                <AlertTriangle class="h-7 w-7" />
            </div>

            <div class="space-y-1.5">
                <h4 class="text-base font-bold text-slate-800 dark:text-slate-100">SKU Master Parent Belum Diisi</h4>
                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed px-4">
                    Harap isi <span class="font-semibold text-slate-700 dark:text-slate-200">SKU Master Parent</span> terlebih dahulu agar sistem dapat membuat SKU varian secara otomatis (contoh: <code class="font-mono text-indigo-600 bg-indigo-50 dark:bg-indigo-950 px-1.5 py-0.5 rounded text-[11px]">KKA27-M</code>).
                </p>
            </div>

            <div class="pt-4 flex justify-center">
                <button
                    type="button"
                    onclick={() => isSkuAlertModalOpen = false}
                    class="w-full sm:w-auto min-w-[140px] rounded-xl bg-indigo-600 px-5 py-2.5 text-xs font-semibold text-white shadow-lg shadow-indigo-600/30 hover:bg-indigo-700 transition-colors"
                >
                    Mengerti & Isi SKU
                </button>
            </div>
        </div>
    </Modal>
</AdminLayout>
