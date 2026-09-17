<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import Modal from '@/components/Modal.svelte';
    import ConfirmDialog from '@/components/ConfirmDialog.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import { router } from '@inertiajs/svelte';
    import { FileSpreadsheet, Search, Plus, Edit2, Trash2, Warehouse as WarehouseIcon, Package, Calendar, UserCheck } from '@lucide/svelte';

    interface ProductRecord {
        id: number;
        product_name: string;
    }

    interface ProductVariantRecord {
        id: number;
        sku: string;
        size?: string;
        color?: string;
        product?: ProductRecord;
    }

    interface MappingItem {
        id: number;
        seller_id: number;
        warehouse_id: number;
        product_variant_id: number;
        user_id?: number;
        qty: number;
        date: string;
        note?: string;
        seller?: { id: number; name?: string; seller_name?: string };
        warehouse?: { id: number; name: string };
        product_variant?: ProductVariantRecord;
        user?: { id: number; name: string };
        created_at: string;
    }

    interface SellerOption {
        id: number;
        name: string;
    }

    interface WarehouseOption {
        id: number;
        name: string;
    }

    let {
        mappings,
        sellers = [],
        warehouses = [],
        warehouseVariants = [],
        filters = { search: '', seller_id: '' },
    }: {
        mappings: {
            data: MappingItem[];
            links: any[];
            current_page: number;
            last_page: number;
            total: number;
        };
        sellers?: SellerOption[];
        warehouses?: WarehouseOption[];
        warehouseVariants?: ProductVariantRecord[];
        filters?: { search?: string; seller_id?: string };
    } = $props();

    let search = $state(filters.search ?? '');
    let sellerId = $state(filters.seller_id ?? '');

    let isCreateModalOpen = $state(false);
    let isEditModalOpen = $state(false);
    let isConfirmDeleteOpen = $state(false);

    let selectedMapping = $state<MappingItem | null>(null);

    let form = $state({
        seller_id: sellers[0]?.id ?? '',
        warehouse_id: warehouses[0]?.id ?? '',
        product_variant_id: warehouseVariants[0]?.id ?? '',
        qty: 1,
        date: new Date().toISOString().split('T')[0],
        note: '',
    });

    function formatDate(dateStr: string): string {
        if (!dateStr) return '-';
        const cleanStr = dateStr.split('T')[0];
        const [year, month, day] = cleanStr.split('-');
        if (!year || !month || !day) return dateStr;
        const d = new Date(Number(year), Number(month) - 1, Number(day));
        return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
    }

    function handleFilter() {
        router.get(
            '/superadmin/warehouse/mapping/manual-global',
            { search, seller_id: sellerId },
            { preserveState: true, replace: true }
        );
    }

    function openCreateModal() {
        form = {
            seller_id: sellers[0]?.id ?? '',
            warehouse_id: warehouses[0]?.id ?? '',
            product_variant_id: warehouseVariants[0]?.id ?? '',
            qty: 1,
            date: new Date().toISOString().split('T')[0],
            note: '',
        };
        isCreateModalOpen = true;
    }

    function openEditModal(m: MappingItem) {
        selectedMapping = m;
        form = {
            seller_id: m.seller_id,
            warehouse_id: m.warehouse_id,
            product_variant_id: m.product_variant_id,
            qty: m.qty,
            date: m.date ? m.date.split('T')[0] : '',
            note: m.note ?? '',
        };
        isEditModalOpen = true;
    }

    function openDeleteModal(m: MappingItem) {
        selectedMapping = m;
        isConfirmDeleteOpen = true;
    }

    function submitCreate() {
        router.post('/superadmin/warehouse/mapping/manual-global', form, {
            onSuccess: () => {
                isCreateModalOpen = false;
            },
        });
    }

    function submitUpdate() {
        if (!selectedMapping) return;
        router.put(`/superadmin/warehouse/mapping/manual-global/${selectedMapping.id}`, form, {
            onSuccess: () => {
                isEditModalOpen = false;
            },
        });
    }

    function submitDelete() {
        if (!selectedMapping) return;
        router.delete(`/superadmin/warehouse/mapping/manual-global/${selectedMapping.id}`, {
            onSuccess: () => {
                isConfirmDeleteOpen = false;
            },
        });
    }
</script>

<AdminLayout title="Report Global Product" breadcrumbs={[{ name: 'Gudang & Katalog' }, { name: 'Report Global Product' }]}>
    <!-- Page Header -->
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                <FileSpreadsheet class="h-7 w-7 text-orange-500" />
                Report Global Product
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Pencatatan mutasi pengambilan barang manual produk global oleh seller dari gudang</p>
        </div>

        <button
            onclick={openCreateModal}
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/30 hover:bg-indigo-700 transition-colors"
        >
            <Plus class="h-4 w-4" />
            Tambah Pengambilan Manual
        </button>
    </div>

    <!-- Filters -->
    <div class="mb-6 rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="w-full sm:w-64">
            <select
                bind:value={sellerId}
                onchange={handleFilter}
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
            >
                <option value="">Semua Seller</option>
                {#each sellers as s}
                    <option value={s.id}>{s.name}</option>
                {/each}
            </select>
        </div>

        <div class="relative flex-1 w-full">
            <Search class="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
            <input
                type="text"
                bind:value={search}
                onkeyup={(e) => e.key === 'Enter' && handleFilter()}
                placeholder="Cari berdasarkan produk, SKU, gudang, seller, atau catatan..."
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 pl-10 pr-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
        </div>
    </div>

    <!-- Table -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-xs border border-slate-100 dark:border-slate-800 overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600 dark:text-slate-400">
                <thead class="bg-slate-50 dark:bg-slate-800/50 text-xs uppercase font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800">
                    <tr>
                        <th class="px-6 py-4">No</th>
                        <th class="px-6 py-4">Tanggal</th>
                        <th class="px-6 py-4">Pemilik / Seller</th>
                        <th class="px-6 py-4">Gudang Asal</th>
                        <th class="px-6 py-4">Produk & SKU Gudang</th>
                        <th class="px-6 py-4 text-center">Jumlah Qty</th>
                        <th class="px-6 py-4">Catatan / Alasan</th>
                        <th class="px-6 py-4">Operator</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    {#each mappings.data as item, index}
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="px-6 py-4 text-xs text-slate-400">{index + 1 + (mappings.current_page - 1) * 15}</td>
                            <td class="px-6 py-4 text-slate-700 dark:text-slate-200 font-medium">{formatDate(item.date)}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center gap-1.5 rounded-lg bg-purple-50 dark:bg-purple-950/60 px-2.5 py-1 text-xs font-bold text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                                    <UserCheck class="h-3.5 w-3.5 text-purple-500" />
                                    <span>{item.seller?.seller_name || item.seller?.name || '-'}</span>
                                </span>
                            </td>
                            <td class="px-6 py-4 font-semibold text-slate-800 dark:text-slate-100 flex items-center gap-1.5">
                                <WarehouseIcon class="h-4 w-4 text-slate-400" />
                                <span>{item.warehouse?.name ?? '-'}</span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <Package class="h-4 w-4 text-indigo-500" />
                                    <div>
                                        <span class="font-bold text-slate-800 dark:text-slate-100 block">
                                            {item.product_variant?.product?.product_name ?? 'Produk'}
                                        </span>
                                        <span class="font-mono text-xs text-slate-500">
                                            SKU: <strong class="text-indigo-600 dark:text-indigo-400">{item.product_variant?.sku ?? '-'}</strong>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center font-bold text-rose-600">
                                <span class="rounded-lg bg-rose-50 dark:bg-rose-950/60 px-2.5 py-1 text-xs border border-rose-200 dark:border-rose-800">
                                    {item.qty} Pcs
                                </span>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-600 dark:text-slate-300">
                                {item.note || '-'}
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-500 dark:text-slate-400">
                                {item.user?.name ?? '-'}
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
                            <td colspan="9" class="px-6 py-12 text-center text-slate-400">Belum ada data pengambilan produk manual.</td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    </div>

    <Pagination links={mappings.links} />

    <!-- Create Modal -->
    <Modal show={isCreateModalOpen} title="Tambah Pengambilan Produk Manual" onclose={() => isCreateModalOpen = false}>
        <form onsubmit={(e) => { e.preventDefault(); submitCreate(); }} class="space-y-4">
            <div>
                <label for="seller_id" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Seller / Pemilik</label>
                <select
                    bind:value={form.seller_id}
                    required
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                >
                    {#each sellers as s}
                        <option value={s.id}>{s.name}</option>
                    {/each}
                </select>
            </div>

            <div>
                <label for="warehouse_id" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Gudang Asal</label>
                <select
                    bind:value={form.warehouse_id}
                    required
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                >
                    {#each warehouses as w}
                        <option value={w.id}>{w.name}</option>
                    {/each}
                </select>
            </div>

            <div>
                <label for="product_variant_id" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Produk Varian / SKU Gudang</label>
                <select
                    bind:value={form.product_variant_id}
                    required
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                >
                    {#each warehouseVariants as pv}
                        <option value={pv.id}>
                            {pv.product?.product_name ?? 'Produk'} ({pv.sku})
                        </option>
                    {/each}
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="qty" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Jumlah Qty (Pcs)</label>
                    <input
                        type="number"
                        min="1"
                        bind:value={form.qty}
                        required
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
                <div>
                    <label for="date" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Tanggal</label>
                    <input
                        type="date"
                        bind:value={form.date}
                        required
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
            </div>

            <div>
                <label for="note" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Catatan / Alasan</label>
                <input
                    type="text"
                    bind:value={form.note}
                    placeholder="Contoh: Ambil untuk orderan offline / marketplace"
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                />
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
                >Simpan Data</button>
            </div>
        </form>
    </Modal>

    <!-- Edit Modal -->
    <Modal show={isEditModalOpen} title="Edit Pengambilan Produk Manual" onclose={() => isEditModalOpen = false}>
        <form onsubmit={(e) => { e.preventDefault(); submitUpdate(); }} class="space-y-4">
            <div>
                <label for="seller_id_edit" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Seller / Pemilik</label>
                <select
                    bind:value={form.seller_id}
                    required
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                >
                    {#each sellers as s}
                        <option value={s.id}>{s.name}</option>
                    {/each}
                </select>
            </div>

            <div>
                <label for="warehouse_id_edit" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Gudang Asal</label>
                <select
                    bind:value={form.warehouse_id}
                    required
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                >
                    {#each warehouses as w}
                        <option value={w.id}>{w.name}</option>
                    {/each}
                </select>
            </div>

            <div>
                <label for="product_variant_id_edit" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Produk Varian / SKU Gudang</label>
                <select
                    bind:value={form.product_variant_id}
                    required
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                >
                    {#each warehouseVariants as pv}
                        <option value={pv.id}>
                            {pv.product?.product_name ?? 'Produk'} ({pv.sku})
                        </option>
                    {/each}
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="qty_edit" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Jumlah Qty (Pcs)</label>
                    <input
                        type="number"
                        min="1"
                        bind:value={form.qty}
                        required
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
                <div>
                    <label for="date_edit" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Tanggal</label>
                    <input
                        type="date"
                        bind:value={form.date}
                        required
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
            </div>

            <div>
                <label for="note_edit" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Catatan / Alasan</label>
                <input
                    type="text"
                    bind:value={form.note}
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                />
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
        title="Hapus Data Pengambilan"
        message="Apakah Anda yakin ingin menghapus catatan pengambilan barang ini?"
        confirmText="Hapus"
        onconfirm={submitDelete}
        oncancel={() => isConfirmDeleteOpen = false}
    />
</AdminLayout>
