<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import { router } from '@inertiajs/svelte';
    import { FileText, Plus, Search, CheckCircle2, XCircle, Clock, Trash2, Image, UserCheck, Store } from '@lucide/svelte';

    interface ProofTransferRecord {
        id: number;
        seller?: { seller_name: string };
        store?: { store_name: string };
        amount: number;
        transfer_date: string;
        proof_file: string | null;
        status: string;
        notes: string | null;
    }

    interface SellerOption {
        id: number;
        seller_name: string;
    }

    interface StoreOption {
        id: number;
        seller_id: number;
        store_name: string;
    }

    let {
        proofTransfers,
        sellers = [],
        stores = [],
        summary = { total: 0, pending: 0, approved: 0, rejected: 0, total_amount_approved: 0 },
        filters = { seller_id: '', status: '', search: '' },
    }: {
        proofTransfers: { data: ProofTransferRecord[]; links: any[] };
        sellers?: SellerOption[];
        stores?: StoreOption[];
        summary?: { total: number; pending: number; approved: number; rejected: number; total_amount_approved: number };
        filters?: { seller_id?: string; status?: string; search?: string };
    } = $props();

    let sellerId = $state(filters.seller_id ?? '');
    let status = $state(filters.status ?? '');
    let search = $state(filters.search ?? '');

    let isCreateModalOpen = $state(false);
    let createForm = $state({
        seller_id: '',
        store_id: '',
        amount: 0,
        transfer_date: new Date().toISOString().split('T')[0],
        proof_file: null as File | null,
        notes: '',
    });

    function formatRupiah(num: number): string {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num || 0);
    }

    function formatDate(dateStr: string): string {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    function applyFilter() {
        router.get('/superadmin/finance/proof-transfers', { seller_id: sellerId, status, search }, { preserveState: true, replace: true });
    }

    function updateStatus(id: number, newStatus: string) {
        router.put(`/superadmin/finance/proof-transfers/${id}/status`, { status: newStatus });
    }

    function deleteTransfer(id: number) {
        if (confirm('Yakin ingin menghapus bukti transfer ini?')) {
            router.delete(`/superadmin/finance/proof-transfers/${id}`);
        }
    }

    function submitCreate() {
        const formData = new FormData();
        formData.append('seller_id', createForm.seller_id);
        if (createForm.store_id) formData.append('store_id', createForm.store_id);
        formData.append('amount', String(createForm.amount));
        formData.append('transfer_date', createForm.transfer_date);
        if (createForm.notes) formData.append('notes', createForm.notes);
        if (createForm.proof_file) formData.append('proof_file', createForm.proof_file);

        router.post('/superadmin/finance/proof-transfers', formData, {
            onSuccess: () => {
                isCreateModalOpen = false;
            },
        });
    }

    let availableStores = $derived(
        createForm.seller_id ? stores.filter((s) => s.seller_id === Number(createForm.seller_id)) : []
    );
</script>

<AdminLayout title="Bukti Transfer Pembayaran Toko" breadcrumbs={[{ name: 'Keuangan' }, { name: 'Bukti Transfer' }]}>
    <!-- Header -->
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                <FileText class="h-7 w-7 text-sky-500" />
                Verifikasi Bukti Transfer Pembayaran Toko
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Verifikasi kelayakan dan status bukti transfer dari seller/mitra (Approve/Reject)</p>
        </div>

        <button
            type="button"
            onclick={() => {
                createForm.seller_id = sellers[0]?.id ? String(sellers[0].id) : '';
                createForm.store_id = '';
                createForm.amount = 0;
                createForm.notes = '';
                createForm.proof_file = null;
                isCreateModalOpen = true;
            }}
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-sky-600/30 hover:bg-sky-700 transition-colors"
        >
            <Plus class="h-4 w-4" />
            Unggah Bukti Transfer
        </button>
    </div>

    <!-- Stats Cards -->
    <div class="mb-8 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Total Pengajuan</span>
            <span class="text-2xl font-bold text-slate-800 dark:text-slate-100 mt-1 block">{summary.total} Berkas</span>
        </div>

        <div class="rounded-2xl bg-amber-50 dark:bg-amber-950/40 p-5 border border-amber-200 dark:border-amber-900">
            <span class="block text-xs font-semibold uppercase tracking-wider text-amber-700 dark:text-amber-300">Menunggu Verifikasi</span>
            <span class="text-2xl font-bold text-amber-700 dark:text-amber-300 mt-1 block">{summary.pending} Pending</span>
        </div>

        <div class="rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 p-5 border border-emerald-200 dark:border-emerald-900">
            <span class="block text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">Disetujui (Approved)</span>
            <span class="text-2xl font-bold text-emerald-700 dark:text-emerald-300 mt-1 block">{summary.approved} Approved</span>
        </div>

        <div class="rounded-2xl bg-gradient-to-br from-sky-900 to-indigo-950 p-5 text-white shadow-md">
            <span class="block text-xs font-semibold uppercase tracking-wider text-sky-200">Total Nominal Approved</span>
            <span class="text-xl font-black text-emerald-300 mt-1 block">{formatRupiah(summary.total_amount_approved)}</span>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="mb-6 rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 w-full sm:w-auto flex-1">
            <select
                bind:value={sellerId}
                onchange={applyFilter}
                class="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-sky-500"
            >
                <option value="">Semua Seller</option>
                {#each sellers as s}
                    <option value={s.id}>{s.seller_name}</option>
                {/each}
            </select>

            <select
                bind:value={status}
                onchange={applyFilter}
                class="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-sky-500"
            >
                <option value="">Semua Status</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>

        <div class="relative w-full sm:w-72">
            <Search class="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
            <input
                type="text"
                bind:value={search}
                onkeyup={(e) => e.key === 'Enter' && applyFilter()}
                placeholder="Cari catatan / seller / toko..."
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 pl-10 pr-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-sky-500"
            />
        </div>
    </div>

    <!-- Table Card -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-xs border border-slate-100 dark:border-slate-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 dark:bg-slate-950/50 text-slate-500 font-semibold border-b border-slate-100 dark:border-slate-800 uppercase text-[11px] tracking-wider">
                    <tr>
                        <th class="px-6 py-4 w-12">#</th>
                        <th class="px-6 py-4">Seller & Toko</th>
                        <th class="px-6 py-4">Tanggal Transfer</th>
                        <th class="px-6 py-4 text-right">Nominal Transfer</th>
                        <th class="px-6 py-4 text-center">Berkas Bukti</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-center">Aksi Verifikasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    {#each proofTransfers.data as item, index}
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="px-6 py-4 text-xs text-slate-400">{index + 1}</td>
                            <td class="px-6 py-4">
                                <span class="font-bold text-slate-800 dark:text-slate-100 block">{item.seller?.seller_name}</span>
                                <span class="text-xs text-slate-400 flex items-center gap-1 mt-0.5">
                                    <Store class="h-3 w-3" />
                                    {item.store?.store_name ?? 'Toko Umum'}
                                </span>
                            </td>
                            <td class="px-6 py-4 font-medium text-slate-700 dark:text-slate-300">{formatDate(item.transfer_date)}</td>
                            <td class="px-6 py-4 text-right font-black text-emerald-600 dark:text-emerald-400 text-base">{formatRupiah(item.amount)}</td>
                            <td class="px-6 py-4 text-center">
                                {#if item.proof_file}
                                    <a
                                        href="/storage/{item.proof_file}"
                                        target="_blank"
                                        class="inline-flex items-center gap-1 rounded-lg bg-slate-100 dark:bg-slate-800 px-3 py-1.5 text-xs font-semibold text-sky-600 hover:bg-sky-50 transition-colors"
                                    >
                                        <Image class="h-3.5 w-3.5" />
                                        Lihat File
                                    </a>
                                {:else}
                                    <span class="text-slate-400 italic text-xs">Tanpa File</span>
                                {/if}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold
                                    {item.status === 'approved' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
                                     item.status === 'rejected' ? 'bg-rose-50 text-rose-700 border border-rose-200' :
                                     'bg-amber-50 text-amber-700 border border-amber-200'}">
                                    {#if item.status === 'approved'}
                                        <CheckCircle2 class="h-3.5 w-3.5" />
                                    {:else if item.status === 'rejected'}
                                        <XCircle class="h-3.5 w-3.5" />
                                    {:else}
                                        <Clock class="h-3.5 w-3.5" />
                                    {/if}
                                    {item.status.toUpperCase()}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    {#if item.status !== 'approved'}
                                        <button
                                            onclick={() => updateStatus(item.id, 'approved')}
                                            class="rounded-lg bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-emerald-700 transition-colors"
                                            title="Setujui Bukti Transfer"
                                        >
                                            Approve
                                        </button>
                                    {/if}

                                    {#if item.status !== 'rejected'}
                                        <button
                                            onclick={() => updateStatus(item.id, 'rejected')}
                                            class="rounded-lg bg-rose-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-rose-700 transition-colors"
                                            title="Tolak Bukti Transfer"
                                        >
                                            Reject
                                        </button>
                                    {/if}

                                    <button
                                        onclick={() => deleteTransfer(item.id)}
                                        class="text-slate-400 hover:text-rose-600 p-1"
                                        title="Hapus Record"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    {:else}
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-400">Belum ada bukti transfer diajukan.</td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
        <Pagination links={proofTransfers.links} />
    </div>

    <!-- Modal Upload Bukti Transfer -->
    {#if isCreateModalOpen}
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4">
            <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-2xl border border-slate-100 dark:border-slate-800 space-y-5">
                <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 border-b border-slate-100 dark:border-slate-800 pb-3">
                    Unggah Bukti Transfer Baru
                </h3>

                <form onsubmit={(e) => { e.preventDefault(); submitCreate(); }} class="space-y-4">
                    <div>
                        <label for="c_seller" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Mitra / Seller</label>
                        <select
                            id="c_seller"
                            bind:value={createForm.seller_id}
                            required
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-sky-500"
                        >
                            <option value="">-- Pilih Seller --</option>
                            {#each sellers as s}
                                <option value={s.id}>{s.seller_name}</option>
                            {/each}
                        </select>
                    </div>

                    <div>
                        <label for="c_store" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Toko (Opsional)</label>
                        <select
                            id="c_store"
                            bind:value={createForm.store_id}
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-sky-500"
                        >
                            <option value="">-- Semua Toko --</option>
                            {#each availableStores as st}
                                <option value={st.id}>{st.store_name}</option>
                            {/each}
                        </select>
                    </div>

                    <div>
                        <label for="c_amount" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Nominal Transfer (Rp)</label>
                        <input
                            id="c_amount"
                            type="number"
                            step="1000"
                            bind:value={createForm.amount}
                            required
                            min="1"
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2 text-sm font-bold text-emerald-600 dark:text-emerald-400 focus:ring-2 focus:ring-sky-500"
                        />
                    </div>

                    <div>
                        <label for="c_date" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Tanggal Transfer</label>
                        <input
                            id="c_date"
                            type="date"
                            bind:value={createForm.transfer_date}
                            required
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-sky-500"
                        />
                    </div>

                    <div>
                        <label for="c_file" class="block text-xs font-semibold uppercase text-slate-500 mb-1">File Bukti Transfer (JPG/PNG/PDF)</label>
                        <input
                            id="c_file"
                            type="file"
                            accept="image/*,.pdf"
                            onchange={(e) => {
                                const target = e.target as HTMLInputElement;
                                if (target.files && target.files[0]) {
                                    createForm.proof_file = target.files[0];
                                }
                            }}
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2 text-xs text-slate-800 dark:text-slate-100"
                        />
                    </div>

                    <div>
                        <label for="c_notes" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Catatan</label>
                        <input
                            id="c_notes"
                            type="text"
                            bind:value={createForm.notes}
                            placeholder="Contoh: Transfer setoran toko Shopee kloter 1..."
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-sky-500"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button
                            type="button"
                            onclick={() => (isCreateModalOpen = false)}
                            class="rounded-xl border border-slate-200 dark:border-slate-800 px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="rounded-xl bg-sky-600 px-5 py-2 text-xs font-semibold text-white hover:bg-sky-700 shadow-md shadow-sky-600/30"
                        >
                            Unggah & Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    {/if}
</AdminLayout>
