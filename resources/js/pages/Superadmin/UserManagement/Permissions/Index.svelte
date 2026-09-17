<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import Modal from '@/components/Modal.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import ConfirmDialog from '@/components/ConfirmDialog.svelte';
    import { useForm, router } from '@inertiajs/svelte';
    import { Search, Plus, Trash2 } from '@lucide/svelte';

    interface PermissionItem {
        id: number;
        name: string;
        guard_name: string;
        created_at: string;
    }

    interface PaginationData {
        data: PermissionItem[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    }

    let {
        permissions,
        filters = { search: '' },
    }: {
        permissions: PaginationData;
        filters?: { search?: string };
    } = $props();

    let search = $state(filters.search ?? '');

    let isCreateModalOpen = $state(false);
    let isConfirmDeleteOpen = $state(false);

    let selectedPermission = $state<PermissionItem | null>(null);

    const createForm = useForm({
        name: '',
    });

    function handleSearch() {
        router.get(
            '/superadmin/user-management/permissions',
            { search },
            { preserveState: true, replace: true }
        );
    }

    function openCreateModal() {
        createForm.reset();
        isCreateModalOpen = true;
    }

    function submitCreate() {
        createForm.post('/superadmin/user-management/permissions', {
            onSuccess: () => {
                isCreateModalOpen = false;
                createForm.reset();
            },
        });
    }

    function openDeleteConfirm(perm: PermissionItem) {
        selectedPermission = perm;
        isConfirmDeleteOpen = true;
    }

    function submitDelete() {
        if (!selectedPermission) return;
        router.delete(`/superadmin/user-management/permissions/${selectedPermission.id}`, {
            onSuccess: () => {
                isConfirmDeleteOpen = false;
            },
        });
    }
</script>

<AdminLayout title="Manajemen Permission" breadcrumbs={[{ name: 'User Management', url: '/superadmin/user-management/users' }, { name: 'Permissions' }]}>
    <!-- Page Header -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight">Katalog Permissions</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Daftar kunci izin fitur spesifik yang dapat di-assign ke Role pengguna</p>
        </div>
        <button
            onclick={openCreateModal}
            class="flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 transition-colors shadow-md shadow-indigo-600/20"
        >
            <Plus class="h-4 w-4" />
            <span>Tambah Permission Baru</span>
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
                placeholder="Cari nama permission (e.g. view-users)..."
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
                    <th scope="col" class="px-6 py-4">Nama Permission</th>
                    <th scope="col" class="px-6 py-4">Guard</th>
                    <th scope="col" class="px-6 py-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                {#each permissions.data as perm, index}
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                        <td class="px-6 py-4 font-medium text-slate-400">{index + 1}</td>
                        <td class="px-6 py-4 font-semibold text-indigo-600 dark:text-indigo-400">{perm.name}</td>
                        <td class="px-6 py-4 text-slate-500 dark:text-slate-400">{perm.guard_name}</td>
                        <td class="px-6 py-4 text-right">
                            <button
                                onclick={() => openDeleteConfirm(perm)}
                                title="Hapus Permission"
                                class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800 transition-colors"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </td>
                    </tr>
                {:else}
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-slate-400">
                            Tidak ada data permission ditemukan.
                        </td>
                    </tr>
                {/each}
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <Pagination links={permissions.links} />

    <!-- Create Permission Modal -->
    <Modal show={isCreateModalOpen} title="Tambah Permission Baru" maxWidth="md" onclose={() => isCreateModalOpen = false}>
        <form onsubmit={(e) => { e.preventDefault(); submitCreate(); }} class="space-y-4">
            <div>
                <label for="create-perm-name" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Nama Permission (kebab-case)</label>
                <input
                    id="create-perm-name"
                    type="text"
                    bind:value={createForm.name}
                    required
                    placeholder="misal: manage-inventory"
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                />
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
                    Simpan Permission
                </button>
            </div>
        </form>
    </Modal>

    <!-- Confirm Delete Modal -->
    <ConfirmDialog
        show={isConfirmDeleteOpen}
        title="Hapus Permission"
        message="Apakah Anda yakin ingin menghapus permission {selectedPermission?.name}?"
        onconfirm={submitDelete}
        oncancel={() => isConfirmDeleteOpen = false}
    />
</AdminLayout>
