<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import Modal from '@/components/Modal.svelte';
    import Badge from '@/components/Badge.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import ConfirmDialog from '@/components/ConfirmDialog.svelte';
    import { useForm, router } from '@inertiajs/svelte';
    import { Search, Plus, Edit2, Trash2, UserCheck } from '@lucide/svelte';

    interface User {
        id: number;
        name: string;
        email: string;
    }

    interface Seller {
        id: number;
        seller_name: string;
    }

    interface PengadaanItem {
        id: number;
        user_id: number | null;
        name: string;
        phone: string | null;
        user?: User;
        sellers: Seller[];
    }

    interface PaginationData {
        data: PengadaanItem[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    }

    let {
        pengadaans,
        sellers = [],
        users = [],
        filters = { search: '' },
    }: {
        pengadaans: PaginationData;
        sellers?: Seller[];
        users?: User[];
        filters?: { search?: string };
    } = $props();

    let search = $state(filters.search ?? '');

    let isCreateModalOpen = $state(false);
    let isEditModalOpen = $state(false);
    let isConfirmDeleteOpen = $state(false);

    let selectedPengadaan = $state<PengadaanItem | null>(null);

    const createForm = useForm({
        user_id: '',
        name: '',
        phone: '',
        seller_ids: [] as number[],
    });

    const editForm = useForm({
        user_id: '',
        name: '',
        phone: '',
        seller_ids: [] as number[],
    });

    function handleSearch() {
        router.get(
            '/superadmin/user-management/pengadaan',
            { search },
            { preserveState: true, replace: true }
        );
    }

    function openCreateModal() {
        createForm.reset();
        isCreateModalOpen = true;
    }

    function toggleSellerSelection(formType: 'create' | 'edit', sellerId: number) {
        if (formType === 'create') {
            if (createForm.seller_ids.includes(sellerId)) {
                createForm.seller_ids = createForm.seller_ids.filter(id => id !== sellerId);
            } else {
                createForm.seller_ids = [...createForm.seller_ids, sellerId];
            }
        } else {
            if (editForm.seller_ids.includes(sellerId)) {
                editForm.seller_ids = editForm.seller_ids.filter(id => id !== sellerId);
            } else {
                editForm.seller_ids = [...editForm.seller_ids, sellerId];
            }
        }
    }

    function submitCreate() {
        createForm.post('/superadmin/user-management/pengadaan', {
            onSuccess: () => {
                isCreateModalOpen = false;
                createForm.reset();
            },
        });
    }

    function openEditModal(item: PengadaanItem) {
        selectedPengadaan = item;
        editForm.user_id = item.user_id ? String(item.user_id) : '';
        editForm.name = item.name;
        editForm.phone = item.phone ?? '';
        editForm.seller_ids = item.sellers.map(s => s.id);
        isEditModalOpen = true;
    }

    function submitEdit() {
        if (!selectedPengadaan) return;
        editForm.put(`/superadmin/user-management/pengadaan/${selectedPengadaan.id}`, {
            onSuccess: () => {
                isEditModalOpen = false;
            },
        });
    }

    function openDeleteConfirm(item: PengadaanItem) {
        selectedPengadaan = item;
        isConfirmDeleteOpen = true;
    }

    function submitDelete() {
        if (!selectedPengadaan) return;
        router.delete(`/superadmin/user-management/pengadaan/${selectedPengadaan.id}`, {
            onSuccess: () => {
                isConfirmDeleteOpen = false;
            },
        });
    }
</script>

<AdminLayout title="Manajemen Tim Pengadaan" breadcrumbs={[{ name: 'User Management', url: '/superadmin/user-management/users' }, { name: 'Tim Pengadaan' }]}>
    <!-- Page Header -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight">Tim Pengadaan & Binding Seller</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Kelola personil tim pengadaan beserta pemetaan mitra seller yang ditangani</p>
        </div>
        <button
            onclick={openCreateModal}
            class="flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 transition-colors shadow-md shadow-indigo-600/20"
        >
            <Plus class="h-4 w-4" />
            <span>Tambah Tim Pengadaan</span>
        </button>
    </div>

    <!-- Search Bar -->
    <div class="mb-6">
        <div class="relative max-w-md">
            <Search class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
                type="text"
                bind:value={search}
                oninput={handleSearch}
                placeholder="Cari tim pengadaan atau nomor telp..."
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 pl-10 pr-4 py-2.5 text-sm text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none"
            />
        </div>
    </div>

    <!-- Data Table -->
    <div class="overflow-x-auto rounded-2xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs">
        <table class="w-full text-left text-sm text-slate-600 dark:text-slate-400">
            <thead class="bg-slate-50/80 dark:bg-slate-800/50 text-xs uppercase text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800 font-semibold tracking-wider">
                <tr>
                    <th scope="col" class="px-6 py-4">No</th>
                    <th scope="col" class="px-6 py-4">Nama Personil</th>
                    <th scope="col" class="px-6 py-4">No Telepon</th>
                    <th scope="col" class="px-6 py-4">Akun User System</th>
                    <th scope="col" class="px-6 py-4">Mitra Seller Ditangani</th>
                    <th scope="col" class="px-6 py-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                {#each pengadaans.data as item, index}
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                        <td class="px-6 py-4 font-medium text-slate-400">{index + 1}</td>
                        <td class="px-6 py-4 font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                            <UserCheck class="h-4 w-4 text-rose-500" />
                            <span>{item.name}</span>
                        </td>
                        <td class="px-6 py-4 text-slate-500 dark:text-slate-400">{item.phone ?? '-'}</td>
                        <td class="px-6 py-4">
                            {#if item.user}
                                <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">{item.user.name} ({item.user.email})</span>
                            {:else}
                                <span class="text-xs text-slate-400 italic">Unlinked Account</span>
                            {/if}
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-wrap gap-1.5 max-w-sm">
                                {#each item.sellers as s}
                                    <Badge variant="primary">{s.seller_name}</Badge>
                                {:else}
                                    <span class="text-xs text-slate-400 italic">Belum menangani seller</span>
                                {/each}
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button
                                    onclick={() => openEditModal(item)}
                                    title="Edit Tim Pengadaan"
                                    class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-indigo-600 dark:hover:bg-slate-800 transition-colors"
                                >
                                    <Edit2 class="h-4 w-4" />
                                </button>
                                <button
                                    onclick={() => openDeleteConfirm(item)}
                                    title="Hapus Tim Pengadaan"
                                    class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800 transition-colors"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                {:else}
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-slate-400">
                            Tidak ada data tim pengadaan ditemukan.
                        </td>
                    </tr>
                {/each}
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <Pagination links={pengadaans.links} />

    <!-- Create Modal -->
    <Modal show={isCreateModalOpen} title="Tambah Tim Pengadaan" maxWidth="lg" onclose={() => isCreateModalOpen = false}>
        <form onsubmit={(e) => { e.preventDefault(); submitCreate(); }} class="space-y-4">
            <div>
                <label for="create-pengadaan-name" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Nama Tim Pengadaan</label>
                <input
                    id="create-pengadaan-name"
                    type="text"
                    bind:value={createForm.name}
                    required
                    placeholder="misal: Budi Pengadaan"
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                />
            </div>

            <div>
                <label for="create-pengadaan-phone" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Nomor Telepon</label>
                <input
                    id="create-pengadaan-phone"
                    type="text"
                    bind:value={createForm.phone}
                    placeholder="0812xxxx"
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                />
            </div>

            <div>
                <label for="create-pengadaan-user" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Link ke Akun User (Opsional)</label>
                <select
                    id="create-pengadaan-user"
                    bind:value={createForm.user_id}
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                >
                    <option value="">Tanpa akun user</option>
                    {#each users as u}
                        <option value={String(u.id)}>{u.name} ({u.email})</option>
                    {/each}
                </select>
            </div>

            <div>
                <span class="block text-xs font-semibold uppercase text-slate-500 mb-2">Binding Mitra Seller yang Ditangani</span>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-48 overflow-y-auto p-2 border border-slate-100 dark:border-slate-800 rounded-xl">
                    {#each sellers as seller}
                        <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 cursor-pointer text-xs font-medium">
                            <input
                                type="checkbox"
                                checked={createForm.seller_ids.includes(seller.id)}
                                onchange={() => toggleSellerSelection('create', seller.id)}
                                class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span>{seller.seller_name}</span>
                        </label>
                    {:else}
                        <p class="text-xs text-slate-400 col-span-2 text-center py-2">Belum ada seller terdaftar.</p>
                    {/each}
                </div>
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
                    Simpan Data
                </button>
            </div>
        </form>
    </Modal>

    <!-- Edit Modal -->
    <Modal show={isEditModalOpen} title="Edit Tim Pengadaan" maxWidth="lg" onclose={() => isEditModalOpen = false}>
        <form onsubmit={(e) => { e.preventDefault(); submitEdit(); }} class="space-y-4">
            <div>
                <label for="edit-pengadaan-name" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Nama Tim Pengadaan</label>
                <input
                    id="edit-pengadaan-name"
                    type="text"
                    bind:value={editForm.name}
                    required
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                />
            </div>

            <div>
                <label for="edit-pengadaan-phone" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Nomor Telepon</label>
                <input
                    id="edit-pengadaan-phone"
                    type="text"
                    bind:value={editForm.phone}
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                />
            </div>

            <div>
                <label for="edit-pengadaan-user" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Link ke Akun User (Opsional)</label>
                <select
                    id="edit-pengadaan-user"
                    bind:value={editForm.user_id}
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                >
                    <option value="">Tanpa akun user</option>
                    {#each users as u}
                        <option value={String(u.id)}>{u.name} ({u.email})</option>
                    {/each}
                </select>
            </div>

            <div>
                <span class="block text-xs font-semibold uppercase text-slate-500 mb-2">Binding Mitra Seller yang Ditangani</span>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-48 overflow-y-auto p-2 border border-slate-100 dark:border-slate-800 rounded-xl">
                    {#each sellers as seller}
                        <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 cursor-pointer text-xs font-medium">
                            <input
                                type="checkbox"
                                checked={editForm.seller_ids.includes(seller.id)}
                                onchange={() => toggleSellerSelection('edit', seller.id)}
                                class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span>{seller.seller_name}</span>
                        </label>
                    {:else}
                        <p class="text-xs text-slate-400 col-span-2 text-center py-2">Belum ada seller terdaftar.</p>
                    {/each}
                </div>
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
                    Update Tim Pengadaan
                </button>
            </div>
        </form>
    </Modal>

    <!-- Confirm Delete Modal -->
    <ConfirmDialog
        show={isConfirmDeleteOpen}
        title="Hapus Tim Pengadaan"
        message="Apakah Anda yakin ingin menghapus tim pengadaan {selectedPengadaan?.name}?"
        onconfirm={submitDelete}
        oncancel={() => isConfirmDeleteOpen = false}
    />
</AdminLayout>
