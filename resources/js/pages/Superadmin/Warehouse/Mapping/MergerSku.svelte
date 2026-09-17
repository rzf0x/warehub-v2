<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import { router } from '@inertiajs/svelte';
    import { GitMerge } from '@lucide/svelte';

    interface VariantItem {
        id: number;
        product_id: number;
        sku: string;
        size: string;
        color: string;
        price: number;
        selling_price?: number;
        product?: {
            product_name: string;
            seller?: { name?: string; seller_name?: string };
        };
    }

    let {
        variants,
    }: {
        variants: {
            data: VariantItem[];
            links: any[];
            current_page: number;
            last_page: number;
            total: number;
        };
    } = $props();

    let targetSku = $state('');
    let selectedVariantIds = $state<number[]>([]);

    function toggleVariant(id: number) {
        if (selectedVariantIds.includes(id)) {
            selectedVariantIds = selectedVariantIds.filter((vId) => vId !== id);
        } else {
            selectedVariantIds.push(id);
        }
    }

    function submitMerge() {
        if (!targetSku || selectedVariantIds.length === 0) return;
        router.post('/superadmin/warehouse/mapping/merger-sku', {
            target_sku: targetSku,
            source_variant_ids: selectedVariantIds,
        }, {
            onSuccess: () => {
                targetSku = '';
                selectedVariantIds = [];
            },
        });
    }

    function formatRupiah(num: number | string | null | undefined): string {
        if (num === null || num === undefined || num === '') return '-';
        const val = typeof num === 'string' ? parseFloat(num) : num;
        if (isNaN(val)) return '-';
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(val);
    }
</script>

<AdminLayout title="Tools Merger SKU Varian" breadcrumbs={[{ name: 'Gudang & Katalog' }, { name: 'Merger SKU' }]}>
    <!-- Page Header -->
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                <GitMerge class="h-7 w-7 text-teal-600" />
                Merger & Penggabungan SKU Varian
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Pilih beberapa SKU varian ganda dan gabungkan secara otomatis menjadi 1 SKU standar pilihan</p>
        </div>
    </div>

    <!-- Merge Form Bar -->
    <div class="mb-8 rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-100 dark:border-slate-800 space-y-4">
        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
            Target SKU Penggabungan
        </h3>

        <div class="flex flex-col sm:flex-row items-center gap-4">
            <div class="flex-1 w-full">
                <input
                    type="text"
                    bind:value={targetSku}
                    placeholder="Masukkan SKU Standar Baru (misal: KKA27-XXL)..."
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm font-mono text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-teal-500"
                />
            </div>

            <button
                onclick={submitMerge}
                disabled={!targetSku || selectedVariantIds.length === 0}
                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-teal-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg shadow-teal-600/30 hover:bg-teal-700 disabled:opacity-40 transition-colors"
            >
                <GitMerge class="h-4 w-4" />
                Gabungkan {selectedVariantIds.length} Varian
            </button>
        </div>
    </div>

    <!-- Table -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-xs border border-slate-100 dark:border-slate-800 overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600 dark:text-slate-400">
                <thead class="bg-slate-50 dark:bg-slate-800/50 text-xs uppercase font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800">
                    <tr>
                        <th class="px-4 py-4 text-center">Pilih</th>
                        <th class="px-6 py-4">SKU Saat Ini</th>
                        <th class="px-6 py-4">Nama Produk & Seller</th>
                        <th class="px-6 py-4">Size / Color</th>
                        <th class="px-6 py-4">HPP Modal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    {#each variants.data as item}
                        <tr
                            onclick={() => toggleVariant(item.id)}
                            class="cursor-pointer transition-colors {selectedVariantIds.includes(item.id) ? 'bg-teal-50/70 dark:bg-teal-950/30' : 'hover:bg-slate-50/50 dark:hover:bg-slate-800/30'}"
                        >
                            <td class="px-4 py-4 text-center">
                                <input
                                    type="checkbox"
                                    checked={selectedVariantIds.includes(item.id)}
                                    onchange={() => {}}
                                    class="h-4 w-4 rounded text-teal-600 focus:ring-teal-500"
                                />
                            </td>
                            <td class="px-6 py-4 font-mono font-bold text-slate-800 dark:text-slate-100">{item.sku}</td>
                            <td class="px-6 py-4">
                                <p class="font-semibold text-slate-800 dark:text-slate-100">{item.product?.product_name ?? '-'}</p>
                                <span class="text-xs text-slate-400">{item.product?.seller?.seller_name || item.product?.seller?.name || 'Seller'}</span>
                            </td>
                            <td class="px-6 py-4">{item.size || '-'} / {item.color || '-'}</td>
                            <td class="px-6 py-4 font-semibold text-slate-800 dark:text-slate-100">{formatRupiah(item.price)}</td>
                        </tr>
                    {:else}
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-slate-400">Belum ada varian produk.</td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    </div>

    <Pagination links={variants.links} />
</AdminLayout>
