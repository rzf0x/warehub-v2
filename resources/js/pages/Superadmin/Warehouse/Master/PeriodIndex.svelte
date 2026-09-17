<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import Modal from '@/components/Modal.svelte';
    import ConfirmDialog from '@/components/ConfirmDialog.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import Badge from '@/components/Badge.svelte';
    import { Link, router } from '@inertiajs/svelte';
    import { Calendar, Search, Plus, Edit2, Trash2, ArrowDownLeft, ArrowUpRight } from '@lucide/svelte';

    interface PeriodItem {
        id: number;
        name: string;
        start_date: string;
        end_date: string;
        is_public: boolean;
        stock_ins_count?: number;
        stock_outs_count?: number;
        created_at: string;
    }

    let {
        periods,
        filters = { search: '' },
    }: {
        periods: {
            data: PeriodItem[];
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
    let selectedPeriod = $state<PeriodItem | null>(null);

    let form = $state({
        name: '',
        start_date: '',
        end_date: '',
        is_public: false,
    });

    function formatDate(dateStr: string): string {
        if (!dateStr) return '-';
        const cleanStr = dateStr.split('T')[0];
        const [year, month, day] = cleanStr.split('-');
        if (!year || !month || !day) return dateStr;
        const d = new Date(Number(year), Number(month) - 1, Number(day));
        return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
    }

    function handleSearch() {
        router.get('/superadmin/warehouse/periods', { search }, { preserveState: true, replace: true });
    }

    function openCreateModal() {
        form = { name: '', start_date: '', end_date: '', is_public: false };
        isCreateModalOpen = true;
    }

    function openEditModal(period: PeriodItem) {
        selectedPeriod = period;
        form = {
            name: period.name,
            start_date: period.start_date ? period.start_date.split('T')[0] : '',
            end_date: period.end_date ? period.end_date.split('T')[0] : '',
            is_public: period.is_public,
        };
        isEditModalOpen = true;
    }

    function openDeleteModal(period: PeriodItem) {
        selectedPeriod = period;
        isConfirmDeleteOpen = true;
    }

    function submitCreate() {
        router.post('/superadmin/warehouse/periods', form, {
            onSuccess: () => {
                isCreateModalOpen = false;
            },
        });
    }

    function submitUpdate() {
        if (!selectedPeriod) return;
        router.put(`/superadmin/warehouse/periods/${selectedPeriod.id}`, form, {
            onSuccess: () => {
                isEditModalOpen = false;
            },
        });
    }

    function submitDelete() {
        if (!selectedPeriod) return;
        router.delete(`/superadmin/warehouse/periods/${selectedPeriod.id}`, {
            onSuccess: () => {
                isConfirmDeleteOpen = false;
            },
        });
    }
</script>

<AdminLayout title="Periode Pembukuan" breadcrumbs={[{ name: 'Gudang & Katalog' }, { name: 'Periode Pembukuan' }]}>
    <!-- Page Header -->
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                <Calendar class="h-7 w-7 text-purple-500" />
                Periode Pembukuan
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Pengaturan rentang waktu periode pembukuan laporan transaksi & persediaan</p>
        </div>

        <button
            onclick={openCreateModal}
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/30 hover:bg-indigo-700 transition-colors"
        >
            <Plus class="h-4 w-4" />
            Tambah Periode
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
                placeholder="Cari nama periode..."
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
                        <th class="px-6 py-4">Nama Periode</th>
                        <th class="px-6 py-4">Tanggal Mulai</th>
                        <th class="px-6 py-4">Tanggal Selesai</th>
                        <th class="px-6 py-4 text-center">Stock In</th>
                        <th class="px-6 py-4 text-center">Stock Out</th>
                        <th class="px-6 py-4">Status Publik</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    {#each periods.data as item, index}
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="px-6 py-4 text-xs text-slate-400">{index + 1 + (periods.current_page - 1) * 10}</td>
                            <td class="px-6 py-4 font-semibold text-slate-800 dark:text-slate-100">{item.name}</td>
                            <td class="px-6 py-4 text-slate-700 dark:text-slate-200 font-medium">{formatDate(item.start_date)}</td>
                            <td class="px-6 py-4 text-slate-700 dark:text-slate-200 font-medium">{formatDate(item.end_date)}</td>
                            <td class="px-6 py-4 text-center">
                                <Link
                                    href={`/superadmin/warehouse/periods/${item.id}/stock-in`}
                                    class="inline-flex items-center gap-1.5 rounded-xl bg-sky-50 hover:bg-sky-100 dark:bg-sky-950/50 dark:hover:bg-sky-900/50 px-3 py-1.5 text-xs font-bold text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800 shadow-2xs transition-all group"
                                    title="Klik untuk membuka halaman detail transaksi Stock In"
                                >
                                    <ArrowDownLeft class="h-3.5 w-3.5 text-sky-500 group-hover:scale-110 transition-transform" />
                                    <span>{item.stock_ins_count ?? 0} Transaksi</span>
                                </Link>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <Link
                                    href={`/superadmin/warehouse/periods/${item.id}/stock-out`}
                                    class="inline-flex items-center gap-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/50 dark:hover:bg-rose-900/50 px-3 py-1.5 text-xs font-bold text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800 shadow-2xs transition-all group"
                                    title="Klik untuk membuka halaman detail transaksi Stock Out"
                                >
                                    <ArrowUpRight class="h-3.5 w-3.5 text-rose-500 group-hover:scale-110 transition-transform" />
                                    <span>{item.stock_outs_count ?? 0} Transaksi</span>
                                </Link>
                            </td>
                            <td class="px-6 py-4">
                                <Badge variant={item.is_public ? 'success' : 'neutral'}>
                                    {item.is_public ? 'Publik' : 'Internal / Draft'}
                                </Badge>
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
                            <td colspan="8" class="px-6 py-12 text-center text-slate-400">Belum ada periode pembukuan.</td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    </div>

    <Pagination links={periods.links} />

    <!-- Create Modal -->
    <Modal show={isCreateModalOpen} title="Tambah Periode Pembukuan" onclose={() => isCreateModalOpen = false}>
        <form onsubmit={(e) => { e.preventDefault(); submitCreate(); }} class="space-y-4">
            <div>
                <label for="name" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Nama Periode</label>
                <input
                    type="text"
                    bind:value={form.name}
                    required
                    placeholder="Contoh: Periode Q1 2026"
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="start_date" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Tgl Mulai</label>
                    <input
                        type="date"
                        bind:value={form.start_date}
                        required
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
                <div>
                    <label for="end_date" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Tgl Selesai</label>
                    <input
                        type="date"
                        bind:value={form.end_date}
                        required
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
            </div>
            <div class="flex items-center gap-2 pt-2">
                <input
                    type="checkbox"
                    id="is_public"
                    bind:checked={form.is_public}
                    class="rounded text-indigo-600 focus:ring-indigo-500 h-4 w-4"
                />
                <label for="is_public" class="text-sm font-medium text-slate-700 dark:text-slate-300">Publikasikan ke semua user</label>
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
    <Modal show={isEditModalOpen} title="Edit Periode" onclose={() => isEditModalOpen = false}>
        <form onsubmit={(e) => { e.preventDefault(); submitUpdate(); }} class="space-y-4">
            <div>
                <label for="name" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Nama Periode</label>
                <input
                    type="text"
                    bind:value={form.name}
                    required
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="start_date" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Tgl Mulai</label>
                    <input
                        type="date"
                        bind:value={form.start_date}
                        required
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
                <div>
                    <label for="end_date" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Tgl Selesai</label>
                    <input
                        type="date"
                        bind:value={form.end_date}
                        required
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
            </div>
            <div class="flex items-center gap-2 pt-2">
                <input
                    type="checkbox"
                    id="is_public_edit"
                    bind:checked={form.is_public}
                    class="rounded text-indigo-600 focus:ring-indigo-500 h-4 w-4"
                />
                <label for="is_public_edit" class="text-sm font-medium text-slate-700 dark:text-slate-300">Publikasikan ke semua user</label>
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
        title="Hapus Periode"
        message="Apakah Anda yakin ingin menghapus periode '{selectedPeriod?.name}'?"
        confirmText="Hapus"
        onconfirm={submitDelete}
        oncancel={() => isConfirmDeleteOpen = false}
    />
</AdminLayout>
