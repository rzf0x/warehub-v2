<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import Modal from '@/components/Modal.svelte';
    import Badge from '@/components/Badge.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import ConfirmDialog from '@/components/ConfirmDialog.svelte';
    import { useForm, router } from '@inertiajs/svelte';
    import { Search, Plus, Edit2, Trash2, ShoppingBag, Filter } from '@lucide/svelte';

    interface Seller {
        id: number;
        seller_name: string;
    }

    interface StoreItem {
        id: number;
        seller_id: number;
        store_name: string;
        marketplace: string | null;
        seller?: Seller;
    }

    interface PaginationData {
        data: StoreItem[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    }

    let {
        stores,
        sellers = [],
        filters = { search: '', marketplace: '' },
    }: {
        stores: PaginationData;
        sellers?: Seller[];
        filters?: { search?: string; marketplace?: string };
    } = $props();

    let search = $state(filters.search ?? '');
    let marketplaceFilter = $state(filters.marketplace ?? '');

    let isCreateModalOpen = $state(false);
    let isEditModalOpen = $state(false);
    let isConfirmDeleteOpen = $state(false);

    let selectedStore = $state<StoreItem | null>(null);

    const createForm = useForm({
        seller_id: '',
        store_name: '',
        marketplace: 'Shopee',
    });

    const editForm = useForm({
        seller_id: '',
        store_name: '',
        marketplace: 'Shopee',
    });

    const marketplaces = ['Shopee', 'TikTok Shop', 'Tokopedia', 'Lazada', 'BliBli', 'Lainnya'];

    function handleFilterChange() {
        router.get(
            '/superadmin/user-management/stores',
            { search, marketplace: marketplaceFilter },
            { preserveState: true, replace: true }
        );
    }

    function openCreateModal() {
        createForm.reset();
        if (sellers.length > 0) {
            createForm.seller_id = String(sellers[0].id);
        }
        isCreateModalOpen = true;
    }

    function submitCreate() {
        createForm.post('/superadmin/user-management/stores', {
            onSuccess: () => {
                isCreateModalOpen = false;
                createForm.reset();
            },
        });
    }

    function openEditModal(store: StoreItem) {
        selectedStore = store;
        editForm.seller_id = String(store.seller_id);
        editForm.store_name = store.store_name;
        editForm.marketplace = store.marketplace ?? 'Shopee';
        isEditModalOpen = true;
    }

    function submitEdit() {
        if (!selectedStore) return;
        editForm.put(`/superadmin/user-management/stores/${selectedStore.id}`, {
            onSuccess: () => {
                isEditModalOpen = false;
            },
        });
    }

    function openDeleteConfirm(store: StoreItem) {
        selectedStore = store;
        isConfirmDeleteOpen = true;
    }

    function submitDelete() {
        if (!selectedStore) return;
        router.delete(`/superadmin/user-management/stores/${selectedStore.id}`, {
            onSuccess: () => {
                isConfirmDeleteOpen = false;
            },
        });
    }

    function getMarketplaceBadgeVariant(mp: string | null): 'primary' | 'success' | 'warning' | 'info' | 'secondary' {
        switch (mp?.toLowerCase()) {
            case 'shopee': return 'warning';
            case 'tiktok shop': return 'primary';
            case 'tokopedia': return 'success';
            case 'lazada': return 'info';
            default: return 'secondary';
        }
    }
</script>

<AdminLayout title="Manajemen Toko Marketplace" breadcrumbs={[{ name: 'User Management', url: '/superadmin/user-management/users' }, { name: 'Stores' }]}>
    <!-- Page Header -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight">Toko Marketplace Seller</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Kelola toko online marketplace (Shopee, TikTok Shop, Tokopedia, Lazada) milik mitra Seller</p>
        </div>
        <button
            onclick={openCreateModal}
            class="flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 transition-colors shadow-md shadow-indigo-600/20"
        >
            <Plus class="h-4 w-4" />
            <span>Tambah Toko Baru</span>
        </button>
    </div>

    <!-- Search & Filter Controls -->
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <Search class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
                type="text"
                bind:value={search}
                oninput={handleFilterChange}
                placeholder="Cari nama toko atau nama seller..."
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 pl-10 pr-4 py-2.5 text-sm text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none"
            />
        </div>
        <div class="sm:w-64">
            <div class="relative">
                <Filter class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <select
                    bind:value={marketplaceFilter}
                    onchange={handleFilterChange}
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 pl-10 pr-4 py-2.5 text-sm text-slate-800 dark:text-slate-200 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none"
                >
                    <option value="">Semua Marketplace</option>
                    {#each marketplaces as mp}
                        <option value={mp}>{mp}</option>
                    {/each}
                </select>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="overflow-x-auto rounded-2xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs">
        <table class="w-full text-left text-sm text-slate-600 dark:text-slate-400">
            <thead class="bg-slate-50/80 dark:bg-slate-800/50 text-xs uppercase text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800 font-semibold tracking-wider">
                <tr>
                    <th scope="col" class="px-6 py-4">No</th>
                    <th scope="col" class="px-6 py-4">Nama Toko</th>
                    <th scope="col" class="px-6 py-4">Marketplace</th>
                    <th scope="col" class="px-6 py-4">Pemilik Seller</th>
                    <th scope="col" class="px-6 py-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                {#each stores.data as store, index}
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                        <td class="px-6 py-4 font-medium text-slate-400">{index + 1}</td>
                        <td class="px-6 py-4 font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                            <ShoppingBag class="h-4 w-4 text-indigo-500" />
                            <span>{store.store_name}</span>
                        </td>
                        <td class="px-6 py-4">
                            <Badge variant={getMarketplaceBadgeVariant(store.marketplace)}>
                                {store.marketplace ?? 'Unassigned'}
                            </Badge>
                        </td>
                        <td class="px-6 py-4 font-medium text-slate-700 dark:text-slate-300">
                            {store.seller?.seller_name ?? '-'}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button
                                    onclick={() => openEditModal(store)}
                                    title="Edit Toko"
                                    class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-indigo-600 dark:hover:bg-slate-800 transition-colors"
                                >
                                    <Edit2 class="h-4 w-4" />
                                </button>
                                <button
                                    onclick={() => openDeleteConfirm(store)}
                                    title="Hapus Toko"
                                    class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800 transition-colors"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                {:else}
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-slate-400">
                            Tidak ada data toko marketplace ditemukan.
                        </td>
                    </tr>
                {/each}
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <Pagination links={stores.links} />

    <!-- Create Store Modal -->
    <Modal show={isCreateModalOpen} title="Tambah Toko Marketplace" maxWidth="lg" onclose={() => isCreateModalOpen = false}>
        <form onsubmit={(e) => { e.preventDefault(); submitCreate(); }} class="space-y-4">
            <div>
                <label for="create-store-name" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Nama Toko Online</label>
                <input
                    id="create-store-name"
                    type="text"
                    bind:value={createForm.store_name}
                    required
                    placeholder="misal: Toko Resmi Warehub Official"
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                />
            </div>

            <div>
                <label for="create-store-mp" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Platform Marketplace</label>
                <select
                    id="create-store-mp"
                    bind:value={createForm.marketplace}
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                >
                    {#each marketplaces as mp}
                        <option value={mp}>{mp}</option>
                    {/each}
                </select>
            </div>

            <div>
                <label for="create-store-seller" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Pilih Pemilik Seller</label>
                <select
                    id="create-store-seller"
                    bind:value={createForm.seller_id}
                    required
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                >
                    {#each sellers as seller}
                        <option value={String(seller.id)}>{seller.seller_name}</option>
                    {/each}
                </select>
            </div>

            <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                <button
                    type="button"
                    onclick={() => isCreateModalOpen = false}
                    class="rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    disabled={createForm.processing}
                    class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition-colors shadow-xs disabled:opacity-50"
                >
                    Simpan Toko
                </button>
            </div>
        </form>
    </Modal>

    <!-- Edit Store Modal -->
    <Modal show={isEditModalOpen} title="Edit Toko Marketplace" maxWidth="lg" onclose={() => isEditModalOpen = false}>
        <form onsubmit={(e) => { e.preventDefault(); submitEdit(); }} class="space-y-4">
            <div>
                <label for="edit-store-name" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Nama Toko Online</label>
                <input
                    id="edit-store-name"
                    type="text"
                    bind:value={editForm.store_name}
                    required
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                />
            </div>

            <div>
                <label for="edit-store-mp" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Platform Marketplace</label>
                <select
                    id="edit-store-mp"
                    bind:value={editForm.marketplace}
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                >
                    {#each marketplaces as mp}
                        <option value={mp}>{mp}</option>
                    {/each}
                </select>
            </div>

            <div>
                <label for="edit-store-seller" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Pilih Pemilik Seller</label>
                <select
                    id="edit-store-seller"
                    bind:value={editForm.seller_id}
                    required
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                >
                    {#each sellers as seller}
                        <option value={String(seller.id)}>{seller.seller_name}</option>
                    {/each}
                </select>
            </div>

            <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                <button
                    type="button"
                    onclick={() => isEditModalOpen = false}
                    class="rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    disabled={editForm.processing}
                    class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition-colors shadow-xs disabled:opacity-50"
                >
                    Update Toko
                </button>
            </div>
        </form>
    </Modal>

    <!-- Confirm Delete Modal -->
    <ConfirmDialog
        show={isConfirmDeleteOpen}
        title="Hapus Toko Marketplace"
        message="Apakah Anda yakin ingin menghapus toko {selectedStore?.store_name}?"
        onconfirm={submitDelete}
        oncancel={() => isConfirmDeleteOpen = false}
    />
</AdminLayout>
