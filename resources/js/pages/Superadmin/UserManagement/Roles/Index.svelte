<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import Modal from '@/components/Modal.svelte';
    import Badge from '@/components/Badge.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import ConfirmDialog from '@/components/ConfirmDialog.svelte';
    import { useForm, router } from '@inertiajs/svelte';
    import { Search, Plus, Edit2, KeyRound, Trash2 } from '@lucide/svelte';

    interface Permission {
        id: number;
        name: string;
    }

    interface RoleItem {
        id: number;
        name: string;
        permissions: Permission[];
    }

    interface PaginationData {
        data: RoleItem[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    }

    let {
        roles,
        permissions = [],
        filters = { search: '' },
    }: {
        roles: PaginationData;
        permissions?: Permission[];
        filters?: { search?: string };
    } = $props();

    let search = $state(filters.search ?? '');

    let isCreateModalOpen = $state(false);
    let isEditModalOpen = $state(false);
    let isPermissionsModalOpen = $state(false);
    let isConfirmDeleteOpen = $state(false);

    let selectedRole = $state<RoleItem | null>(null);

    const createForm = useForm({
        name: '',
        permissions: [] as string[],
    });

    const editForm = useForm({
        name: '',
    });

    const permissionsForm = useForm({
        permissions: [] as string[],
    });

    function handleSearch() {
        router.get(
            '/superadmin/user-management/roles',
            { search },
            { preserveState: true, replace: true }
        );
    }

    function openCreateModal() {
        createForm.reset();
        isCreateModalOpen = true;
    }

    function submitCreate() {
        createForm.post('/superadmin/user-management/roles', {
            onSuccess: () => {
                isCreateModalOpen = false;
                createForm.reset();
            },
        });
    }

    function openEditModal(role: RoleItem) {
        selectedRole = role;
        editForm.name = role.name;
        isEditModalOpen = true;
    }

    function submitEdit() {
        if (!selectedRole) return;
        editForm.put(`/superadmin/user-management/roles/${selectedRole.id}`, {
            onSuccess: () => {
                isEditModalOpen = false;
            },
        });
    }

    function openPermissionsModal(role: RoleItem) {
        selectedRole = role;
        permissionsForm.permissions = role.permissions.map(p => p.name);
        isPermissionsModalOpen = true;
    }

    function togglePermission(permName: string) {
        if (permissionsForm.permissions.includes(permName)) {
            permissionsForm.permissions = permissionsForm.permissions.filter(p => p !== permName);
        } else {
            permissionsForm.permissions = [...permissionsForm.permissions, permName];
        }
    }

    function submitSyncPermissions() {
        if (!selectedRole) return;
        permissionsForm.put(`/superadmin/user-management/roles/${selectedRole.id}/permissions`, {
            onSuccess: () => {
                isPermissionsModalOpen = false;
            },
        });
    }

    function openDeleteConfirm(role: RoleItem) {
        selectedRole = role;
        isConfirmDeleteOpen = true;
    }

    function submitDelete() {
        if (!selectedRole) return;
        router.delete(`/superadmin/user-management/roles/${selectedRole.id}`, {
            onSuccess: () => {
                isConfirmDeleteOpen = false;
            },
        });
    }
</script>

<AdminLayout title="Manajemen Role" breadcrumbs={[{ name: 'User Management', url: '/superadmin/user-management/users' }, { name: 'Roles' }]}>
    <!-- Page Header -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight">Role & Hak Akses System</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Atur grup role dan assign kewenangan izin (permissions) untuk modul sistem</p>
        </div>
        <button
            onclick={openCreateModal}
            class="flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 transition-colors shadow-md shadow-indigo-600/20"
        >
            <Plus class="h-4 w-4" />
            <span>Buat Role Baru</span>
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
                placeholder="Cari nama role..."
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
                    <th scope="col" class="px-6 py-4">Nama Role</th>
                    <th scope="col" class="px-6 py-4">Daftar Permissions Assigned</th>
                    <th scope="col" class="px-6 py-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                {#each roles.data as role, index}
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                        <td class="px-6 py-4 font-medium text-slate-400">{index + 1}</td>
                        <td class="px-6 py-4 font-bold text-slate-800 dark:text-slate-100 uppercase">{role.name}</td>
                        <td class="px-6 py-4">
                            <div class="flex flex-wrap gap-1.5 max-w-xl">
                                {#each role.permissions as p}
                                    <Badge variant="info">{p.name}</Badge>
                                {:else}
                                    <span class="text-xs text-slate-400 italic">Belum ada permission terhubung</span>
                                {/each}
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button
                                    onclick={() => openPermissionsModal(role)}
                                    title="Kelola Permissions Role"
                                    class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-amber-600 dark:hover:bg-slate-800 transition-colors"
                                >
                                    <KeyRound class="h-4 w-4" />
                                </button>
                                <button
                                    onclick={() => openEditModal(role)}
                                    title="Edit Nama Role"
                                    class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-indigo-600 dark:hover:bg-slate-800 transition-colors"
                                >
                                    <Edit2 class="h-4 w-4" />
                                </button>
                                <button
                                    onclick={() => openDeleteConfirm(role)}
                                    title="Hapus Role"
                                    class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800 transition-colors"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                {:else}
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-slate-400">
                            Tidak ada data role ditemukan.
                        </td>
                    </tr>
                {/each}
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <Pagination links={roles.links} />

    <!-- Create Role Modal -->
    <Modal show={isCreateModalOpen} title="Buat Role Baru" maxWidth="md" onclose={() => isCreateModalOpen = false}>
        <form onsubmit={(e) => { e.preventDefault(); submitCreate(); }} class="space-y-4">
            <div>
                <label for="create-role-name" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Nama Role (lowercase)</label>
                <input
                    id="create-role-name"
                    type="text"
                    bind:value={createForm.name}
                    required
                    placeholder="misal: finance-admin"
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
                    Simpan Role
                </button>
            </div>
        </form>
    </Modal>

    <!-- Edit Role Modal -->
    <Modal show={isEditModalOpen} title="Edit Nama Role" maxWidth="md" onclose={() => isEditModalOpen = false}>
        <form onsubmit={(e) => { e.preventDefault(); submitEdit(); }} class="space-y-4">
            <div>
                <label for="edit-role-name" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Nama Role</label>
                <input
                    id="edit-role-name"
                    type="text"
                    bind:value={editForm.name}
                    required
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                />
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
                    Update Role
                </button>
            </div>
        </form>
    </Modal>

    <!-- Sync Permissions Modal -->
    <Modal show={isPermissionsModalOpen} title="Assign Permissions ke Role: {selectedRole?.name}" maxWidth="2xl" onclose={() => isPermissionsModalOpen = false}>
        <form onsubmit={(e) => { e.preventDefault(); submitSyncPermissions(); }} class="space-y-4">
            <p class="text-xs text-slate-500 mb-4">Centang izin modul yang diperbolehkan untuk role ini:</p>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 max-h-96 overflow-y-auto p-2 border border-slate-100 dark:border-slate-800 rounded-xl">
                {#each permissions as perm}
                    <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 cursor-pointer text-xs font-medium text-slate-700 dark:text-slate-300">
                        <input
                            type="checkbox"
                            checked={permissionsForm.permissions.includes(perm.name)}
                            onchange={() => togglePermission(perm.name)}
                            class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <span>{perm.name}</span>
                    </label>
                {:else}
                    <p class="text-xs text-slate-400 col-span-3 text-center py-4">Belum ada data permission di database.</p>
                {/each}
            </div>

            <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                <button
                    type="button"
                    onclick={() => isPermissionsModalOpen = false}
                    class="rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    disabled={permissionsForm.processing}
                    class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 transition-colors shadow-xs disabled:opacity-50"
                >
                    Simpan Hak Akses
                </button>
            </div>
        </form>
    </Modal>

    <!-- Confirm Delete Modal -->
    <ConfirmDialog
        show={isConfirmDeleteOpen}
        title="Hapus Role System"
        message="Apakah Anda yakin ingin menghapus role {selectedRole?.name}?"
        onconfirm={submitDelete}
        oncancel={() => isConfirmDeleteOpen = false}
    />
</AdminLayout>
