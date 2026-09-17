<script lang="ts">
    import AdminLayout from '@/Layouts/AdminLayout.svelte';
    import Modal from '@/Components/Modal.svelte';
    import ConfirmDialog from '@/Components/ConfirmDialog.svelte';
    import Pagination from '@/Components/Pagination.svelte';
    import { router } from '@inertiajs/svelte';
    import { Factory, Search, Plus, Edit2, Trash2, Phone, MapPin } from '@lucide/svelte';

    interface KonveksiItem {
        id: number;
        name: string;
        phone: string | null;
        address: string | null;
        created_at: string;
    }

    let {
        konveksis,
        filters = { search: '' },
    }: {
        konveksis: {
            data: KonveksiItem[];
            links: any[];
            current_page: number;
            last_page: number;
            total: number;
        };
        filters?: { search?: string };
    } = $props();

    let search = $state(filters.search ?? '');
    let isCreateModalOpen = $state(false);
    let isEditModalOpen = $state(false);
    let isConfirmDeleteOpen = $state(false);

    let selectedKonveksi = $state<KonveksiItem | null>(null);

    let form = $state({
        name: '',
        phone: '',
        address: '',
    });

    function handleSearch() {
        router.get('/superadmin/warehouse/konveksis', { search }, { preserveState: true, replace: true });
    }

    function openCreateModal() {
        form = { name: '', phone: '', address: '' };
        isCreateModalOpen = true;
    }

    function openEditModal(item: KonveksiItem) {
        selectedKonveksi = item;
        form = {
            name: item.name,
            phone: item.phone ?? '',
            address: item.address ?? '',
        };
        isEditModalOpen = true;
    }

    function openDeleteModal(item: KonveksiItem) {
        selectedKonveksi = item;
        isConfirmDeleteOpen = true;
    }

    function submitCreate() {
        router.post('/superadmin/warehouse/konveksis', form, {
            onSuccess: () => {
                isCreateModalOpen = false;
            },
        });
    }

    function submitUpdate() {
        if (!selectedKonveksi) return;
        router.put(`/superadmin/warehouse/konveksis/${selectedKonveksi.id}`, form, {
            onSuccess: () => {
                isEditModalOpen = false;
            },
        });
    }

    function submitDelete() {
        if (!selectedKonveksi) return;
        router.delete(`/superadmin/warehouse/konveksis/${selectedKonveksi.id}`, {
            onSuccess: () => {
                isConfirmDeleteOpen = false;
            },
        });
    }
</script>

<AdminLayout title="Vendor Konveksi" breadcrumbs={[{ name: 'Gudang & Katalog' }, { name: 'Vendor Konveksi' }]}>
    <!-- Page Header -->
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                <Factory class="h-7 w-7 text-rose-500" />
                Vendor Konveksi
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Daftar pabrik & vendor konveksi mitra pengadaan barang Warehub v2</p>
        </div>

        <button
            onclick={openCreateModal}
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/30 hover:bg-indigo-700 transition-colors"
        >
            <Plus class="h-4 w-4" />
            Tambah Vendor Konveksi
        </button>
    </div>

    <!-- Search -->
    <div class="mb-6 rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="relative flex-1">
            <Search class="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
            <input
                type="text"
                bind:value={search}
                onkeyup={(e) => e.key === 'Enter' && handleSearch()}
                placeholder="Cari nama vendor, telepon, atau alamat..."
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 pl-10 pr-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
        </div>
    </div>

    <!-- Data Table -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-xs border border-slate-100 dark:border-slate-800 overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600 dark:text-slate-400">
                <thead class="bg-slate-50 dark:bg-slate-800/50 text-xs uppercase font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800">
                    <tr>
                        <th class="px-6 py-4">No</th>
                        <th class="px-6 py-4">Nama Vendor Konveksi</th>
                        <th class="px-6 py-4">Telepon / HP</th>
                        <th class="px-6 py-4">Alamat</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    {#each konveksis.data as item, index}
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="px-6 py-4 text-xs text-slate-400">{index + 1 + (konveksis.current_page - 1) * 10}</td>
                            <td class="px-6 py-4 font-semibold text-slate-800 dark:text-slate-100">{item.name}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center gap-1 text-slate-600 dark:text-slate-300">
                                    <Phone class="h-3.5 w-3.5 text-slate-400" />
                                    {item.phone ?? '-'}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center gap-1 text-slate-600 dark:text-slate-300">
                                    <MapPin class="h-3.5 w-3.5 text-slate-400" />
                                    {item.address ?? '-'}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <button
                                    onclick={() => openEditModal(item)}
                                    class="inline-flex items-center gap-1 rounded-lg p-2 text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 transition-colors"
                                >
                                    <Edit2 class="h-4 w-4" />
                                </button>
                                <button
                                    onclick={() => openDeleteModal(item)}
                                    class="inline-flex items-center gap-1 rounded-lg p-2 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition-colors"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                    {:else}
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-slate-400">Belum ada vendor konveksi terdaftar.</td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    </div>

    <Pagination links={konveksis.links} />

    <!-- Create Modal -->
    <Modal show={isCreateModalOpen} title="Tambah Vendor Konveksi" onclose={() => isCreateModalOpen = false}>
        <form onsubmit={(e) => { e.preventDefault(); submitCreate(); }} class="space-y-4">
            <div>
                <label for="name" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Nama Vendor Konveksi</label>
                <input
                    type="text"
                    bind:value={form.name}
                    required
                    placeholder="Contoh: Konveksi Sukses Jaya"
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
            </div>
            <div>
                <label for="phone" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Telepon / WhatsApp</label>
                <input
                    type="text"
                    bind:value={form.phone}
                    placeholder="08123456789"
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
            </div>
            <div>
                <label for="address" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Alamat Pabrik / Workshop</label>
                <textarea
                    bind:value={form.address}
                    rows="3"
                    placeholder="Alamat lengkap vendor..."
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                ></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-4">
                <button
                    type="button"
                    onclick={() => isCreateModalOpen = false}
                    class="rounded-xl border border-slate-200 dark:border-slate-800 px-4 py-2 text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"
                >Batal</button>
                <button
                    type="submit"
                    class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-md hover:bg-indigo-700"
                >Simpan</button>
            </div>
        </form>
    </Modal>

    <!-- Edit Modal -->
    <Modal show={isEditModalOpen} title="Edit Vendor Konveksi" onclose={() => isEditModalOpen = false}>
        <form onsubmit={(e) => { e.preventDefault(); submitUpdate(); }} class="space-y-4">
            <div>
                <label for="name" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Nama Vendor Konveksi</label>
                <input
                    type="text"
                    bind:value={form.name}
                    required
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
            </div>
            <div>
                <label for="phone" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Telepon / WhatsApp</label>
                <input
                    type="text"
                    bind:value={form.phone}
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
            </div>
            <div>
                <label for="address" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Alamat</label>
                <textarea
                    bind:value={form.address}
                    rows="3"
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                ></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-4">
                <button
                    type="button"
                    onclick={() => isEditModalOpen = false}
                    class="rounded-xl border border-slate-200 dark:border-slate-800 px-4 py-2 text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"
                >Batal</button>
                <button
                    type="submit"
                    class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-md hover:bg-indigo-700"
                >Simpan Perubahan</button>
            </div>
        </form>
    </Modal>

    <!-- Delete Confirm -->
    <ConfirmDialog
        show={isConfirmDeleteOpen}
        title="Hapus Vendor Konveksi"
        message="Apakah Anda yakin ingin menghapus vendor konveksi '{selectedKonveksi?.name}'?"
        confirmText="Hapus"
        onconfirm={submitDelete}
        oncancel={() => isConfirmDeleteOpen = false}
    />
</AdminLayout>
