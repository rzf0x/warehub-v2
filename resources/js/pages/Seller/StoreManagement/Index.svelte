<script lang="ts">
    import SellerMobileLayout from '@/layouts/SellerMobileLayout.svelte';
    import BottomSheet from '@/components/seller/BottomSheet.svelte';
    import { router, useForm } from '@inertiajs/svelte';
    import {
        Store,
        Plus,
        Search,
        Trash2,
        Edit2,
        ShoppingBag,
        Boxes,
        ClipboardList,
        CheckCircle2,
        Video,
        Sparkles
    } from '@lucide/svelte';

    interface StoreItem {
        id: number;
        store_name: string;
        marketplace: string;
        stock_outs_count: number;
        purchase_orders_count: number;
        created_at: string;
    }

    let {
        stores = [],
        seller = null,
        filters = { search: '' },
    }: {
        stores?: StoreItem[];
        seller?: { id: number; seller_name: string } | null;
        filters?: { search?: string };
    } = $props();

    let search = $state(filters.search ?? '');

    // Bottom Sheet Form State
    let showModal = $state(false);
    let isEditing = $state(false);
    let editingStoreId = $state<number | null>(null);

    const form = useForm({
        store_name: '',
        marketplace: 'Shopee',
    });

    const marketplaces = [
        { id: 'Shopee', name: 'Shopee', color: 'bg-orange-500 text-white' },
        { id: 'TikTok Shop', name: 'TikTok Shop', color: 'bg-slate-900 text-white' },
        { id: 'Tokopedia', name: 'Tokopedia', color: 'bg-emerald-600 text-white' },
        { id: 'Lazada', name: 'Lazada', color: 'bg-blue-600 text-white' },
        { id: 'Manual', name: 'Penjualan Offline / Custom', color: 'bg-indigo-600 text-white' },
    ];

    function openCreateModal() {
        isEditing = false;
        editingStoreId = null;
        form.store_name = '';
        form.marketplace = 'Shopee';
        showModal = true;
    }

    function openEditModal(item: StoreItem) {
        isEditing = true;
        editingStoreId = item.id;
        form.store_name = item.store_name;
        form.marketplace = item.marketplace;
        showModal = true;
    }

    function handleSubmit() {
        if (isEditing && editingStoreId) {
            form.put(`/seller/stores/${editingStoreId}`, {
                onSuccess: () => {
                    showModal = false;
                    form.reset();
                },
            });
        } else {
            form.post('/seller/stores', {
                onSuccess: () => {
                    showModal = false;
                    form.reset();
                },
            });
        }
    }

    function handleDelete(id: number) {
        if (confirm('Apakah Anda yakin ingin menghapus toko marketplace ini?')) {
            router.delete(`/seller/stores/${id}`);
        }
    }

    function applyFilter() {
        router.get(
            '/seller/stores',
            { search },
            { preserveState: true, replace: true }
        );
    }
</script>

<SellerMobileLayout title="Toko Marketplace" activeStoreName={stores[0]?.store_name ?? ''}>
    <div class="space-y-4">
        <!-- Page Header Bar -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-black text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                    <Store class="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                    Toko Marketplace
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Daftar kanal toko & marketplace mitra seller</p>
            </div>
        </div>

        <!-- Sticky Search Bar -->
        <div class="relative">
            <Search class="absolute left-3 top-3 h-4 w-4 text-slate-400" />
            <input
                type="text"
                placeholder="Cari nama toko / platform..."
                bind:value={search}
                oninput={applyFilter}
                class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 pl-9 pr-4 py-2.5 text-xs text-slate-800 dark:text-slate-100 shadow-xs focus:ring-2 focus:ring-indigo-500"
            />
        </div>

        <!-- Mobile Store Cards List -->
        <div class="space-y-3">
            {#each stores as item}
                <div class="rounded-3xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 space-y-3">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                                <ShoppingBag class="h-5 w-5" />
                            </div>
                            <div>
                                <h3 class="text-sm font-extrabold text-slate-800 dark:text-slate-100">{item.store_name}</h3>
                                <span class="inline-block rounded-md bg-slate-100 dark:bg-slate-800 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:text-slate-300 mt-0.5">
                                    {item.marketplace}
                                </span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                onclick={() => openEditModal(item)}
                                class="p-2 text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                title="Edit Toko"
                            >
                                <Edit2 class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                onclick={() => handleDelete(item.id)}
                                class="p-2 text-slate-400 hover:text-rose-600 transition-colors"
                                title="Hapus Toko"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Store Stats -->
                    <div class="pt-2 border-t border-slate-100 dark:border-slate-800/80 grid grid-cols-2 gap-2 text-xs">
                        <div class="flex items-center gap-1.5 text-slate-500">
                            <Boxes class="h-3.5 w-3.5 text-sky-500" />
                            <span><strong>{item.stock_outs_count}</strong> Transaksi Keluar</span>
                        </div>
                        <div class="flex items-center gap-1.5 text-slate-500">
                            <ClipboardList class="h-3.5 w-3.5 text-indigo-500" />
                            <span><strong>{item.purchase_orders_count}</strong> Order PO</span>
                        </div>
                    </div>
                </div>
            {:else}
                <div class="rounded-3xl bg-white dark:bg-slate-900 p-8 text-center text-slate-400 border border-slate-100 dark:border-slate-800 text-xs space-y-2">
                    <Store class="h-10 w-10 mx-auto text-slate-300 dark:text-slate-700" />
                    <p class="font-bold">Belum Ada Toko</p>
                    <p class="text-[11px] text-slate-400">Klik tombol (+) di kanan bawah untuk menambahkan toko marketplace Anda.</p>
                </div>
            {/each}
        </div>
    </div>

    <!-- Floating Action Button (FAB) -->
    <button
        type="button"
        onclick={openCreateModal}
        class="fixed bottom-20 right-5 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-indigo-600 text-white shadow-2xl shadow-indigo-600/50 hover:bg-indigo-700 hover:scale-105 active:scale-95 transition-all"
        title="Tambah Toko Baru"
    >
        <Plus class="h-7 w-7" />
    </button>

    <!-- Bottom Sheet Modal Form -->
    <BottomSheet
        show={showModal}
        title={isEditing ? "Edit Toko Marketplace" : "Tambah Toko Baru"}
        onclose={() => (showModal = false)}
    >
        <form onsubmit={(e) => { e.preventDefault(); handleSubmit(); }} class="space-y-4">
            <div>
                <label for="f_store_name" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">
                    Nama Toko Marketplace *
                </label>
                <input
                    id="f_store_name"
                    type="text"
                    placeholder="Contoh: Toko Official Shopee / Baban Store"
                    bind:value={$form.store_name}
                    required
                    class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-3 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                />
            </div>

            <div>
                <label for="f_marketplace" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">
                    Platform Marketplace *
                </label>
                <select
                    id="f_marketplace"
                    bind:value={$form.marketplace}
                    class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-3 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                >
                    {#each marketplaces as m}
                        <option value={m.id}>{m.name}</option>
                    {/each}
                </select>
            </div>

            <div class="pt-4 flex items-center justify-end gap-3">
                <button
                    type="button"
                    onclick={() => (showModal = false)}
                    class="rounded-xl px-4 py-3 text-xs font-semibold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                >
                    Batal
                </button>

                <button
                    type="submit"
                    disabled={$form.processing}
                    class="rounded-2xl bg-indigo-600 px-6 py-3 text-xs font-bold text-white shadow-lg shadow-indigo-600/30 hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                >
                    {isEditing ? "Simpan Perubahan" : "Tambah Toko"}
                </button>
            </div>
        </form>
    </BottomSheet>
</SellerMobileLayout>
