<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import Modal from '@/components/Modal.svelte';
    import Badge from '@/components/Badge.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import ConfirmDialog from '@/components/ConfirmDialog.svelte';
    import { useForm, router } from '@inertiajs/svelte';
    import { Search, Plus, FileText, Edit2, Key, Trash2, Filter } from '@lucide/svelte';

    interface Role {
        id: number;
        name: string;
    }

    interface UserItem {
        id: number;
        name: string;
        email: string;
        roles: Role[];
        created_at: string;
    }

    interface PaginationData {
        data: UserItem[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    }

    let {
        users,
        roles = [],
        filters = { search: '', role: '' },
    }: {
        users: PaginationData;
        roles?: Role[];
        filters?: { search?: string; role?: string };
    } = $props();

    // Local Search & Filter State
    let search = $state(filters.search ?? '');
    let selectedRole = $state(filters.role ?? '');

    // Modals visibility
    let isCreateModalOpen = $state(false);
    let isEditModalOpen = $state(false);
    let isResetPasswordModalOpen = $state(false);
    let isConfirmDeleteOpen = $state(false);

    let selectedUser = $state<UserItem | null>(null);

    // Form setup
    const createForm = useForm({
        name: '',
        email: '',
        password: '',
        role: 'staff',
    });

    const editForm = useForm({
        name: '',
        email: '',
        role: '',
    });

    const resetPasswordForm = useForm({
        password: '',
    });

    function handleFilterChange() {
        router.get(
            '/superadmin/user-management/users',
            { search, role: selectedRole },
            { preserveState: true, replace: true }
        );
    }

    function openCreateModal() {
        createForm.reset();
        isCreateModalOpen = true;
    }

    function submitCreate() {
        createForm.post('/superadmin/user-management/users', {
            onSuccess: () => {
                isCreateModalOpen = false;
                createForm.reset();
            },
        });
    }

    function openEditModal(user: UserItem) {
        selectedUser = user;
        editForm.name = user.name;
        editForm.email = user.email;
        editForm.role = user.roles[0]?.name ?? 'staff';
        isEditModalOpen = true;
    }

    function submitEdit() {
        if (!selectedUser) return;
        editForm.put(`/superadmin/user-management/users/${selectedUser.id}`, {
            onSuccess: () => {
                isEditModalOpen = false;
            },
        });
    }

    function openResetPasswordModal(user: UserItem) {
        selectedUser = user;
        resetPasswordForm.reset();
        isResetPasswordModalOpen = true;
    }

    function submitResetPassword() {
        if (!selectedUser) return;
        resetPasswordForm.put(`/superadmin/user-management/users/${selectedUser.id}/reset-password`, {
            onSuccess: () => {
                isResetPasswordModalOpen = false;
                resetPasswordForm.reset();
            },
        });
    }

    function openDeleteConfirm(user: UserItem) {
        selectedUser = user;
        isConfirmDeleteOpen = true;
    }

    function submitDelete() {
        if (!selectedUser) return;
        router.delete(`/superadmin/user-management/users/${selectedUser.id}`, {
            onSuccess: () => {
                isConfirmDeleteOpen = false;
            },
        });
    }

    function exportPdf() {
        const queryParams = new URLSearchParams({ search, role: selectedRole }).toString();
        window.open(`/superadmin/user-management/users/pdf?${queryParams}`, '_blank');
    }
</script>

<AdminLayout title="Manajemen User" breadcrumbs={[{ name: 'User Management', url: '/superadmin/user-management/users' }, { name: 'Users' }]}>
    <!-- Page Title & Actions -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight">Data Users</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Kelola akun dan kewenangan hak akses pengguna Warehub v2</p>
        </div>
        <div class="flex items-center gap-3">
            <button
                onclick={exportPdf}
                class="flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors shadow-xs"
            >
                <FileText class="h-4 w-4 text-rose-500" />
                <span>Cetak PDF</span>
            </button>
            <button
                onclick={openCreateModal}
                class="flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 transition-colors shadow-md shadow-indigo-600/20"
            >
                <Plus class="h-4 w-4" />
                <span>Tambah User Baru</span>
            </button>
        </div>
    </div>

    <!-- Search & Filter Controls -->
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <Search class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
                type="text"
                bind:value={search}
                oninput={handleFilterChange}
                placeholder="Cari berdasarkan nama atau email..."
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 pl-10 pr-4 py-2.5 text-sm text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none"
            />
        </div>
        <div class="sm:w-64">
            <div class="relative">
                <Filter class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <select
                    bind:value={selectedRole}
                    onchange={handleFilterChange}
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 pl-10 pr-4 py-2.5 text-sm text-slate-800 dark:text-slate-200 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none"
                >
                    <option value="">Semua Role Access</option>
                    {#each roles as role}
                        <option value={role.name}>{role.name}</option>
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
                    <th scope="col" class="px-6 py-4">Nama Lengkap</th>
                    <th scope="col" class="px-6 py-4">Email</th>
                    <th scope="col" class="px-6 py-4">Role System</th>
                    <th scope="col" class="px-6 py-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                {#each users.data as user, index}
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                        <td class="px-6 py-4 font-medium text-slate-400">{index + 1}</td>
                        <td class="px-6 py-4 font-semibold text-slate-800 dark:text-slate-100">{user.name}</td>
                        <td class="px-6 py-4 text-slate-500 dark:text-slate-400">{user.email}</td>
                        <td class="px-6 py-4">
                            {#each user.roles as r}
                                <Badge variant="primary">{r.name}</Badge>
                            {:else}
                                <Badge variant="secondary">No Role</Badge>
                            {/each}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button
                                    onclick={() => openResetPasswordModal(user)}
                                    title="Reset Password"
                                    class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-amber-600 dark:hover:bg-slate-800 transition-colors"
                                >
                                    <Key class="h-4 w-4" />
                                </button>
                                <button
                                    onclick={() => openEditModal(user)}
                                    title="Edit User"
                                    class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-indigo-600 dark:hover:bg-slate-800 transition-colors"
                                >
                                    <Edit2 class="h-4 w-4" />
                                </button>
                                <button
                                    onclick={() => openDeleteConfirm(user)}
                                    title="Hapus User"
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
                            Tidak ada data user ditemukan.
                        </td>
                    </tr>
                {/each}
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <Pagination links={users.links} />

    <!-- Create User Modal -->
    <Modal show={isCreateModalOpen} title="Tambah User Baru" maxWidth="lg" onclose={() => isCreateModalOpen = false}>
        <form onsubmit={(e) => { e.preventDefault(); submitCreate(); }} class="space-y-4">
            <div>
                <label for="create-name" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Nama Lengkap</label>
                <input
                    id="create-name"
                    type="text"
                    bind:value={createForm.name}
                    required
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                />
                {#if createForm.errors.name}
                    <p class="text-xs text-rose-500 mt-1">{createForm.errors.name}</p>
                {/if}
            </div>

            <div>
                <label for="create-email" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Email</label>
                <input
                    id="create-email"
                    type="email"
                    bind:value={createForm.email}
                    required
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                />
                {#if createForm.errors.email}
                    <p class="text-xs text-rose-500 mt-1">{createForm.errors.email}</p>
                {/if}
            </div>

            <div>
                <label for="create-password" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Password</label>
                <input
                    id="create-password"
                    type="password"
                    bind:value={createForm.password}
                    required
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                />
                {#if createForm.errors.password}
                    <p class="text-xs text-rose-500 mt-1">{createForm.errors.password}</p>
                {/if}
            </div>

            <div>
                <label for="create-role" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Role Akses</label>
                <select
                    id="create-role"
                    bind:value={createForm.role}
                    required
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                >
                    {#each roles as role}
                        <option value={role.name}>{role.name}</option>
                    {/each}
                </select>
                {#if createForm.errors.role}
                    <p class="text-xs text-rose-500 mt-1">{createForm.errors.role}</p>
                {/if}
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
                    Simpan User
                </button>
            </div>
        </form>
    </Modal>

    <!-- Edit User Modal -->
    <Modal show={isEditModalOpen} title="Edit Data User" maxWidth="lg" onclose={() => isEditModalOpen = false}>
        <form onsubmit={(e) => { e.preventDefault(); submitEdit(); }} class="space-y-4">
            <div>
                <label for="edit-name" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Nama Lengkap</label>
                <input
                    id="edit-name"
                    type="text"
                    bind:value={editForm.name}
                    required
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                />
            </div>

            <div>
                <label for="edit-email" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Email</label>
                <input
                    id="edit-email"
                    type="email"
                    bind:value={editForm.email}
                    required
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                />
            </div>

            <div>
                <label for="edit-role" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Role Akses</label>
                <select
                    id="edit-role"
                    bind:value={editForm.role}
                    required
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                >
                    {#each roles as role}
                        <option value={role.name}>{role.name}</option>
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
                    Update User
                </button>
            </div>
        </form>
    </Modal>

    <!-- Reset Password Modal -->
    <Modal show={isResetPasswordModalOpen} title="Reset Password User" maxWidth="md" onclose={() => isResetPasswordModalOpen = false}>
        <form onsubmit={(e) => { e.preventDefault(); submitResetPassword(); }} class="space-y-4">
            <p class="text-xs text-slate-500">
                Masukkan password baru untuk user <strong class="text-slate-800 dark:text-slate-200">{selectedUser?.name}</strong>.
            </p>
            <div>
                <label for="reset-password-input" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Password Baru</label>
                <input
                    id="reset-password-input"
                    type="password"
                    bind:value={resetPasswordForm.password}
                    required
                    placeholder="Minimal 8 karakter..."
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                />
            </div>

            <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                <button
                    type="button"
                    onclick={() => isResetPasswordModalOpen = false}
                    class="rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    disabled={resetPasswordForm.processing}
                    class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 transition-colors shadow-xs disabled:opacity-50"
                >
                    Update Password
                </button>
            </div>
        </form>
    </Modal>

    <!-- Confirm Delete Modal -->
    <ConfirmDialog
        show={isConfirmDeleteOpen}
        title="Hapus Account User"
        message="Apakah Anda yakin ingin menghapus user {selectedUser?.name}? Seluruh kewenangan akses user ini akan dicabut."
        onconfirm={submitDelete}
        oncancel={() => isConfirmDeleteOpen = false}
    />
</AdminLayout>
