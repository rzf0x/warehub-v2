<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import Modal from '@/components/Modal.svelte';
    import ConfirmDialog from '@/components/ConfirmDialog.svelte';
    import { router } from '@inertiajs/svelte';
    import { Grid, Plus, Trash2, Layers } from '@lucide/svelte';

    interface TemplateItem {
        id: number;
        size: string;
        color: string | null;
        price?: number | string;
    }

    interface Template {
        id: number;
        name?: string;
        template_name?: string;
        items: TemplateItem[];
        created_at: string;
    }

    let { templates = [] }: { templates?: Template[] } = $props();

    let isCreateModalOpen = $state(false);
    let isConfirmDeleteOpen = $state(false);

    let selectedTemplate = $state<Template | null>(null);

    let form = $state({
        name: '',
        items: [
            { size: 'S', color: 'Standard' },
            { size: 'M', color: 'Standard' },
            { size: 'L', color: 'Standard' },
            { size: 'XL', color: 'Standard' },
            { size: 'XXL', color: 'Standard' },
        ],
    });

    function addItem() {
        form.items.push({ size: '3XL', color: 'Standard' });
    }

    function removeItem(idx: number) {
        if (form.items.length > 1) {
            form.items.splice(idx, 1);
        }
    }

    function openCreateModal() {
        form = {
            name: '',
            items: [
                { size: 'S', color: 'Standard' },
                { size: 'M', color: 'Standard' },
                { size: 'L', color: 'Standard' },
                { size: 'XL', color: 'Standard' },
                { size: 'XXL', color: 'Standard' },
            ],
        };
        isCreateModalOpen = true;
    }

    function openDeleteModal(t: Template) {
        selectedTemplate = t;
        isConfirmDeleteOpen = true;
    }

    function submitCreate() {
        router.post('/superadmin/warehouse/bulk-variant', form, {
            onSuccess: () => {
                isCreateModalOpen = false;
            },
        });
    }

    function submitDelete() {
        if (!selectedTemplate) return;
        router.delete(`/superadmin/warehouse/bulk-variant/${selectedTemplate.id}`, {
            onSuccess: () => {
                isConfirmDeleteOpen = false;
            },
        });
    }
</script>

<AdminLayout title="Template Varian & Ukuran Massal" breadcrumbs={[{ name: 'Gudang & Katalog' }, { name: 'Template Varian' }]}>
    <!-- Page Header -->
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                <Grid class="h-7 w-7 text-indigo-600" />
                Template Ukuran & Varian Massal
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Buat preset template opsi ukuran & harga HPP untuk mempercepat pembuatan varian produk baru</p>
        </div>

        <button
            onclick={openCreateModal}
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/30 hover:bg-indigo-700 transition-colors"
        >
            <Plus class="h-4 w-4" />
            Buat Template Ukuran
        </button>
    </div>

    <!-- Templates Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {#each templates as item}
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-100 dark:border-slate-800 flex flex-col justify-between space-y-4">
                <div>
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">{item.template_name || item.name || `Template #${item.id}`}</h3>
                        <button
                            onclick={() => openDeleteModal(item)}
                            class="rounded-lg p-1.5 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition-colors"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        {#each item.items as sub}
                            <span class="rounded-xl bg-indigo-50 dark:bg-indigo-950/50 px-3 py-1 text-xs font-semibold text-indigo-700 dark:text-indigo-300 border border-indigo-100 dark:border-indigo-900">
                                {sub.size} {sub.price ? `(Rp ${Number(sub.price).toLocaleString('id-ID')})` : ''}
                            </span>
                        {/each}
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 dark:border-slate-800 text-xs text-slate-400">
                    Total {item.items.length} opsi ukuran terdaftar
                </div>
            </div>
        {:else}
            <div class="col-span-full rounded-2xl bg-white dark:bg-slate-900 p-12 text-center text-slate-400 border border-slate-100 dark:border-slate-800">
                Belum ada template ukuran varian.
            </div>
        {/each}
    </div>

    <!-- Create Modal -->
    <Modal show={isCreateModalOpen} title="Buat Template Ukuran Baru" onclose={() => isCreateModalOpen = false}>
        <form onsubmit={(e) => { e.preventDefault(); submitCreate(); }} class="space-y-4">
            <div>
                <label for="template_name" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Nama Template</label>
                <input
                    id="template_name"
                    type="text"
                    bind:value={form.name}
                    required
                    placeholder="Contoh: Ukuran Pakaian Standar (S-XXL)"
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                />
            </div>

            <div class="space-y-2">
                <label for="size_items_list" class="block text-xs font-semibold uppercase text-slate-500">Daftar Ukuran (Sizes)</label>
                {#each form.items as it, idx}
                    <div class="flex items-center gap-3">
                        <input
                            type="text"
                            bind:value={it.size}
                            required
                            placeholder="Ukuran (S, M, L...)"
                            class="flex-1 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                        />
                        <button
                            type="button"
                            onclick={() => removeItem(idx)}
                            disabled={form.items.length <= 1}
                            class="rounded-lg p-2 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/50 disabled:opacity-30 transition-colors"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                {/each}
                <button
                    type="button"
                    onclick={addItem}
                    class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:underline"
                >
                    <Plus class="h-3.5 w-3.5" />
                    Tambah Ukuran Lagi
                </button>
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
                >Simpan Template</button>
            </div>
        </form>
    </Modal>

    <!-- Delete Confirm -->
    <ConfirmDialog
        show={isConfirmDeleteOpen}
        title="Hapus Template"
        message="Apakah Anda yakin ingin menghapus template ukuran '{selectedTemplate?.name}'?"
        confirmText="Hapus"
        onconfirm={submitDelete}
        oncancel={() => isConfirmDeleteOpen = false}
    />
</AdminLayout>
