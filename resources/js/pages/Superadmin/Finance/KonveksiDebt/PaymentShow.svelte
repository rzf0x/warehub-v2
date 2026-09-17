<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import { router, Link } from '@inertiajs/svelte';
    import { ArrowLeft, Factory, Plus, Trash2, Calendar, FileText, CheckCircle2, Receipt, Image } from '@lucide/svelte';

    interface KonveksiItem {
        id: number;
        name: string;
        contact: string | null;
    }

    interface StockInItemRow {
        id: number;
        qty: number;
        price: number;
        product_variant?: {
            sku: string;
            size: string;
            color: string;
            product?: { product_name: string };
        };
        stock_in?: { invoice_number: string; date: string; warehouse?: { name: string } };
    }

    interface PaymentRow {
        id: number;
        amount: number;
        payment_date: string;
        payment_method: string | null;
        reference_number: string | null;
        note: string | null;
        proof_image: string | null;
    }

    let {
        konveksi,
        stockInItems,
        payments = [],
        stats = { total_tagihan: 0, total_paid: 0, sisa_tagihan: 0 },
    }: {
        konveksi: KonveksiItem;
        stockInItems: { data: StockInItemRow[]; links: any[] };
        payments?: PaymentRow[];
        stats?: { total_tagihan: number; total_paid: number; sisa_tagihan: number };
    } = $props();

    let isPaymentModalOpen = $state(false);
    let paymentForm = $state({
        amount: 0,
        payment_date: new Date().toISOString().split('T')[0],
        payment_method: 'Transfer Bank',
        reference_number: '',
        note: '',
        proof_image: null as File | null,
    });

    function formatRupiah(num: number): string {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num || 0);
    }

    function formatDate(dateStr: string): string {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    function openPaymentModal() {
        paymentForm.amount = stats.sisa_tagihan > 0 ? stats.sisa_tagihan : 0;
        paymentForm.payment_method = 'Transfer Bank';
        paymentForm.reference_number = '';
        paymentForm.note = '';
        paymentForm.proof_image = null;
        isPaymentModalOpen = true;
    }

    function submitPayment() {
        const formData = new FormData();
        formData.append('amount', String(paymentForm.amount));
        formData.append('payment_date', paymentForm.payment_date);
        formData.append('payment_method', paymentForm.payment_method);
        if (paymentForm.reference_number) formData.append('reference_number', paymentForm.reference_number);
        if (paymentForm.note) formData.append('note', paymentForm.note);
        if (paymentForm.proof_image) formData.append('proof_image', paymentForm.proof_image);

        router.post(`/superadmin/finance/konveksi-debt/${konveksi.id}/payment`, formData, {
            onSuccess: () => {
                isPaymentModalOpen = false;
            },
        });
    }

    function deletePayment(paymentId: number) {
        if (confirm('Yakin ingin menghapus catatan pembayaran ini?')) {
            router.delete(`/superadmin/finance/konveksi-debt/payment/${paymentId}`);
        }
    }
</script>

<AdminLayout title="Detail Tagihan Konveksi {konveksi.name}" breadcrumbs={[{ name: 'Keuangan' }, { name: 'Tagihan Konveksi', href: '/superadmin/finance/konveksi-debt' }, { name: konveksi.name }]}>
    <!-- Header -->
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <Link
                href="/superadmin/finance/konveksi-debt"
                class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-2.5 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors shadow-xs"
            >
                <ArrowLeft class="h-5 w-5" />
            </Link>
            <div>
                <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                    <Factory class="h-7 w-7 text-amber-500" />
                    Detail Tagihan Vendor: {konveksi.name}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Pencatatan pembayaran DP/Lunas & riwayat penerimaan barang</p>
            </div>
        </div>

        <button
            type="button"
            onclick={openPaymentModal}
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-amber-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg shadow-amber-600/30 hover:bg-amber-700 transition-colors"
        >
            <Plus class="h-4 w-4" />
            Catat Pembayaran DP / Lunas
        </button>
    </div>

    <!-- Stats Cards -->
    <div class="mb-8 grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Total Tagihan Produksi</span>
            <span class="text-xl font-bold text-sky-600 dark:text-sky-400 mt-1 block">{formatRupiah(stats.total_tagihan)}</span>
        </div>

        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Total Sudah Dibayar</span>
            <span class="text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-1 block">{formatRupiah(stats.total_paid)}</span>
        </div>

        <div class="rounded-2xl bg-gradient-to-br from-amber-900 to-amber-950 p-5 text-white shadow-md">
            <span class="block text-xs font-semibold uppercase tracking-wider text-amber-200">Sisa Tagihan Belum Dibayar</span>
            <span class="text-xl font-black text-amber-300 mt-1 block">{formatRupiah(stats.sisa_tagihan)}</span>
        </div>
    </div>

    <!-- Payment History Table -->
    <div class="mb-8 rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-100 dark:border-slate-800 space-y-4">
        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
            <Receipt class="h-5 w-5 text-amber-500" />
            Riwayat Pembayaran DP / Pelunasan
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 font-semibold uppercase">
                    <tr>
                        <th class="p-3">Tanggal</th>
                        <th class="p-3">Metode & No Ref</th>
                        <th class="p-3">Catatan</th>
                        <th class="p-3 text-center">Bukti Transfer</th>
                        <th class="p-3 text-right">Nominal Dibayar</th>
                        <th class="p-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    {#each payments as pay}
                        <tr>
                            <td class="p-3 font-medium">{formatDate(pay.payment_date)}</td>
                            <td class="p-3">
                                <span class="font-bold text-slate-800 dark:text-slate-100 block">{pay.payment_method || 'Transfer'}</span>
                                <span class="text-[10px] font-mono text-slate-400">Ref: {pay.reference_number || '-'}</span>
                            </td>
                            <td class="p-3 text-slate-600 dark:text-slate-300">{pay.note || '-'}</td>
                            <td class="p-3 text-center">
                                {#if pay.proof_image}
                                    <a
                                        href="/storage/{pay.proof_image}"
                                        target="_blank"
                                        class="inline-flex items-center gap-1 text-sky-600 hover:underline font-semibold"
                                    >
                                        <Image class="h-3.5 w-3.5" />
                                        Lihat Foto
                                    </a>
                                {:else}
                                    <span class="text-slate-400 italic">Tanpa Foto</span>
                                {/if}
                            </td>
                            <td class="p-3 text-right font-bold text-emerald-600 dark:text-emerald-400">{formatRupiah(pay.amount)}</td>
                            <td class="p-3 text-center">
                                <button
                                    onclick={() => deletePayment(pay.id)}
                                    class="text-rose-500 hover:text-rose-700 p-1"
                                    title="Hapus Pembayaran"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                    {:else}
                        <tr>
                            <td colspan="6" class="p-6 text-center text-slate-400">Belum ada catatan pembayaran.</td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    </div>

    <!-- Stock In Items Breakdown -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-100 dark:border-slate-800 space-y-4">
        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
            <Receipt class="h-5 w-5 text-sky-500" />
            Daftar Barang Masuk dari Konveksi Ini
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 font-semibold uppercase">
                    <tr>
                        <th class="p-3">Tanggal & No Invoice</th>
                        <th class="p-3">Gudang</th>
                        <th class="p-3">Produk & Varian</th>
                        <th class="p-3 text-center">Qty Masuk</th>
                        <th class="p-3 text-right">Harga HPP</th>
                        <th class="p-3 text-right">Subtotal Tagihan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    {#each stockInItems.data as item}
                        <tr>
                            <td class="p-3">
                                <span class="font-mono font-bold text-sky-600 block">{item.stock_in?.invoice_number}</span>
                                <span class="text-[10px] text-slate-400">{formatDate(item.stock_in?.date || '')}</span>
                            </td>
                            <td class="p-3 font-medium">{item.stock_in?.warehouse?.name ?? '-'}</td>
                            <td class="p-3">
                                <span class="font-bold text-slate-800 dark:text-slate-100 block">{item.product_variant?.product?.product_name}</span>
                                <span class="text-[10px] font-mono text-slate-400">SKU: {item.product_variant?.sku}</span>
                            </td>
                            <td class="p-3 text-center font-bold">{item.qty} Pcs</td>
                            <td class="p-3 text-right font-medium">{formatRupiah(item.price)}</td>
                            <td class="p-3 text-right font-bold text-amber-600 dark:text-amber-400">{formatRupiah(item.qty * item.price)}</td>
                        </tr>
                    {:else}
                        <tr>
                            <td colspan="6" class="p-6 text-center text-slate-400">Belum ada barang masuk dari konveksi ini.</td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
        <Pagination links={stockInItems.links} />
    </div>

    <!-- Modal Catat Pembayaran -->
    {#if isPaymentModalOpen}
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4">
            <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-2xl border border-slate-100 dark:border-slate-800 space-y-5">
                <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 border-b border-slate-100 dark:border-slate-800 pb-3">
                    Catat Pembayaran Tagihan Konveksi
                </h3>

                <form onsubmit={(e) => { e.preventDefault(); submitPayment(); }} class="space-y-4">
                    <div>
                        <label for="payment_amount" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Nominal Pembayaran (Rp)</label>
                        <input
                            id="payment_amount"
                            type="number"
                            step="1000"
                            bind:value={paymentForm.amount}
                            required
                            min="1"
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2 text-sm font-bold text-emerald-600 dark:text-emerald-400 focus:ring-2 focus:ring-amber-500"
                        />
                    </div>

                    <div>
                        <label for="payment_date" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Tanggal Pembayaran</label>
                        <input
                            id="payment_date"
                            type="date"
                            bind:value={paymentForm.payment_date}
                            required
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-amber-500"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="payment_method" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Metode Bayar</label>
                            <input
                                id="payment_method"
                                type="text"
                                bind:value={paymentForm.payment_method}
                                placeholder="Contoh: Transfer BCA"
                                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-amber-500"
                            />
                        </div>
                        <div>
                            <label for="reference_number" class="block text-xs font-semibold uppercase text-slate-500 mb-1">No Referensi / Resi</label>
                            <input
                                id="reference_number"
                                type="text"
                                bind:value={paymentForm.reference_number}
                                placeholder="No Ref Bank..."
                                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-amber-500"
                            />
                        </div>
                    </div>

                    <div>
                        <label for="proof_image" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Upload Foto Bukti Transfer</label>
                        <input
                            id="proof_image"
                            type="file"
                            accept="image/*"
                            onchange={(e) => {
                                const target = e.target as HTMLInputElement;
                                if (target.files && target.files[0]) {
                                    paymentForm.proof_image = target.files[0];
                                }
                            }}
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2 text-xs text-slate-800 dark:text-slate-100"
                        />
                    </div>

                    <div>
                        <label for="payment_note" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Catatan (Opsional)</label>
                        <input
                            id="payment_note"
                            type="text"
                            bind:value={paymentForm.note}
                            placeholder="Contoh: Pelunasan PO #102..."
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-amber-500"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button
                            type="button"
                            onclick={() => (isPaymentModalOpen = false)}
                            class="rounded-xl border border-slate-200 dark:border-slate-800 px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="rounded-xl bg-amber-600 px-5 py-2 text-xs font-semibold text-white hover:bg-amber-700 shadow-md shadow-amber-600/30"
                        >
                            Simpan Pembayaran
                        </button>
                    </div>
                </form>
            </div>
        </div>
    {/if}
</AdminLayout>
