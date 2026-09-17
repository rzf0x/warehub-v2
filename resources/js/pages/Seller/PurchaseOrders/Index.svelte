<script lang="ts">
    import SellerMobileLayout from '@/layouts/SellerMobileLayout.svelte';
    import BottomSheet from '@/components/seller/BottomSheet.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import { Link, router } from '@inertiajs/svelte';
    import {
        ClipboardList,
        Plus,
        Search,
        Clock,
        CheckCircle2,
        Printer,
        ChevronRight,
        Store,
        FileText,
        Trash2,
        AlertCircle,
        Coins,
        Boxes,
        Sparkles
    } from '@lucide/svelte';

    interface POItemDetail {
        id: number;
        product_name: string;
        variant_info: string;
        sku: string;
        qty: number;
        price: number;
        subtotal: number;
        konveksi_name: string;
    }

    interface POItem {
        id: number;
        po_number: string;
        created_at: string;
        store_name: string;
        po_type_name: string;
        priority: string;
        production_status: 'pending' | 'approved' | 'in_production' | 'completed';
        status_progress: number;
        status_label: string;
        notes: string | null;
        konveksi_notes: string | null;
        total_qty: number;
        total_estimated_cost: number;
        items: POItemDetail[];
    }

    interface PaginatedPOs {
        data: POItem[];
        links: any[];
    }

    let {
        purchaseOrders = { data: [], links: [] },
        summary = { total_po: 0, pending_count: 0, in_production_count: 0, completed_count: 0 },
        filters = { search: '', status: 'all' },
    }: {
        purchaseOrders?: PaginatedPOs;
        summary?: { total_po: number; pending_count: number; in_production_count: number; completed_count: number };
        filters?: { search?: string; status?: string };
    } = $props();

    let search = $state(filters.search ?? '');
    let statusFilter = $state(filters.status ?? 'all');
    let selectedPO = $state<POItem | null>(null);
    let showDetailSheet = $state(false);

    function formatRupiah(num: number): string {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num || 0);
    }

    function applyFilter() {
        router.get(
            '/seller/purchase-orders',
            { search, status: statusFilter },
            { preserveState: true, replace: true }
        );
    }

    function openDetailSheet(po: POItem) {
        selectedPO = po;
        showDetailSheet = true;
    }

    function handleCancelPO(id: number) {
        if (confirm('Apakah Anda yakin ingin membatalkan pengajuan PO Restock ini?')) {
            router.delete(`/seller/purchase-orders/${id}`);
        }
    }
</script>

<SellerMobileLayout title="Daftar PO Restock Mobile">
    <div class="space-y-4">
        <!-- Header Bar -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-black text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                    <ClipboardList class="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                    PO Restock
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Daftar & status produksi pengajuan PO restock</p>
            </div>

            <Link
                href="/seller/purchase-orders/create"
                class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-3.5 py-2 text-xs font-bold text-white shadow-md shadow-indigo-600/30 hover:bg-indigo-700 transition-colors"
            >
                <Plus class="h-4 w-4" />
                Buat PO
            </Link>
        </div>

        <!-- Sticky Search Bar -->
        <div class="relative">
            <Search class="absolute left-3 top-3 h-4 w-4 text-slate-400" />
            <input
                type="text"
                placeholder="Cari No. PO / Toko..."
                bind:value={search}
                oninput={applyFilter}
                class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 pl-9 pr-4 py-2.5 text-xs text-slate-800 dark:text-slate-100 shadow-xs focus:ring-2 focus:ring-indigo-500"
            />
        </div>

        <!-- Horizontal Filter Chips Bar -->
        <div class="flex items-center gap-2 overflow-x-auto pb-1 no-scrollbar">
            <button
                type="button"
                onclick={() => { statusFilter = 'all'; applyFilter(); }}
                class="shrink-0 rounded-xl px-3.5 py-1.5 text-xs font-bold transition-all
                {statusFilter === 'all'
                    ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30'
                    : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800'}"
            >
                Semua ({summary.total_po})
            </button>

            <button
                type="button"
                onclick={() => { statusFilter = 'pending'; applyFilter(); }}
                class="shrink-0 rounded-xl px-3.5 py-1.5 text-xs font-bold transition-all flex items-center gap-1
                {statusFilter === 'pending'
                    ? 'bg-amber-500 text-white shadow-md shadow-amber-500/30'
                    : 'bg-white dark:bg-slate-900 text-amber-600 dark:text-amber-400 border border-slate-200 dark:border-slate-800'}"
            >
                <Clock class="h-3.5 w-3.5" />
                Pending ({summary.pending_count})
            </button>

            <button
                type="button"
                onclick={() => { statusFilter = 'in_production'; applyFilter(); }}
                class="shrink-0 rounded-xl px-3.5 py-1.5 text-xs font-bold transition-all flex items-center gap-1
                {statusFilter === 'in_production'
                    ? 'bg-sky-600 text-white shadow-md shadow-sky-600/30'
                    : 'bg-white dark:bg-slate-900 text-sky-600 dark:text-sky-400 border border-slate-200 dark:border-slate-800'}"
            >
                <Boxes class="h-3.5 w-3.5" />
                Diproduksi ({summary.in_production_count})
            </button>

            <button
                type="button"
                onclick={() => { statusFilter = 'completed'; applyFilter(); }}
                class="shrink-0 rounded-xl px-3.5 py-1.5 text-xs font-bold transition-all flex items-center gap-1
                {statusFilter === 'completed'
                    ? 'bg-emerald-600 text-white shadow-md shadow-emerald-600/30'
                    : 'bg-white dark:bg-slate-900 text-emerald-600 dark:text-emerald-400 border border-slate-200 dark:border-slate-800'}"
            >
                <CheckCircle2 class="h-3.5 w-3.5" />
                Selesai ({summary.completed_count})
            </button>
        </div>

        <!-- Mobile PO Cards List -->
        <div class="space-y-3">
            {#each purchaseOrders.data as po}
                <div class="rounded-3xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 space-y-3">
                    <div class="flex items-start justify-between">
                        <div>
                            <span class="font-mono text-xs font-black text-indigo-600 dark:text-indigo-400">{po.po_number}</span>
                            <p class="text-[11px] text-slate-400 flex items-center gap-1 mt-0.5">
                                <Store class="h-3 w-3 text-slate-400" />
                                {po.store_name} • {po.created_at}
                            </p>
                        </div>

                        <!-- Priority Badge -->
                        <span class="rounded-full px-2.5 py-0.5 text-[10px] font-extrabold tracking-wider uppercase
                            {po.priority === 'URGENT' || po.priority === 'HIGH'
                                ? 'bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200'
                                : 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200'}"
                        >
                            {po.priority}
                        </span>
                    </div>

                    <!-- Production Status Progress Bar -->
                    <div class="space-y-1">
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="font-bold text-slate-700 dark:text-slate-200">{po.status_label}</span>
                            <span class="font-extrabold text-indigo-600 dark:text-indigo-400">{po.status_progress}%</span>
                        </div>

                        <div class="h-2 w-full rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all duration-500
                                {po.status_progress === 100
                                    ? 'bg-emerald-500'
                                    : po.status_progress >= 75
                                        ? 'bg-sky-500'
                                        : 'bg-indigo-500'}"
                                style="width: {po.status_progress}%;"
                            ></div>
                        </div>
                    </div>

                    <!-- Total Qty & Estimated Cost Bar -->
                    <div class="rounded-2xl bg-slate-50 dark:bg-slate-950 p-3 flex items-center justify-between border border-slate-100 dark:border-slate-800">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Total Qty Order</span>
                            <span class="text-xs font-black text-slate-800 dark:text-slate-100">{po.total_qty} Pcs</span>
                        </div>

                        <div class="text-right">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Estimasi HPP</span>
                            <span class="text-xs font-black text-indigo-600 dark:text-indigo-400">{formatRupiah(po.total_estimated_cost)}</span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="pt-1 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <a
                                href="/seller/purchase-orders/{po.id}/pdf"
                                target="_blank"
                                class="inline-flex items-center gap-1 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 px-3 py-1.5 text-xs font-bold text-slate-700 dark:text-slate-200 transition-colors"
                            >
                                <Printer class="h-3.5 w-3.5" />
                                Invoice
                            </a>

                            {#if po.production_status === 'pending'}
                                <button
                                    type="button"
                                    onclick={() => handleCancelPO(po.id)}
                                    class="p-1.5 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/50 rounded-lg transition-colors"
                                    title="Batalkan PO"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            {/if}
                        </div>

                        <button
                            type="button"
                            onclick={() => openDetailSheet(po)}
                            class="inline-flex items-center gap-1 text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 transition-colors"
                        >
                            Detail Items
                            <ChevronRight class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>
            {:else}
                <div class="rounded-3xl bg-white dark:bg-slate-900 p-8 text-center text-slate-400 border border-slate-100 dark:border-slate-800 text-xs space-y-2">
                    <ClipboardList class="h-10 w-10 mx-auto text-slate-300 dark:text-slate-700" />
                    <p class="font-bold">Belum Ada Pengajuan PO</p>
                    <p class="text-[11px] text-slate-400">Klik tombol "Buat PO" di atas untuk mengajukan restock produk ke konveksi.</p>
                </div>
            {/each}
        </div>

        <!-- Pagination -->
        {#if purchaseOrders.links && purchaseOrders.links.length > 3}
            <div class="pt-2 flex justify-center">
                <Pagination links={purchaseOrders.links} />
            </div>
        {/if}
    </div>

    <!-- Floating Action Button -->
    <Link
        href="/seller/purchase-orders/create"
        class="fixed bottom-20 right-5 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-indigo-600 text-white shadow-2xl shadow-indigo-600/50 hover:bg-indigo-700 hover:scale-105 active:scale-95 transition-all"
        title="Buat PO Baru"
    >
        <Plus class="h-7 w-7" />
    </Link>

    <!-- Bottom Sheet PO Detail Items -->
    <BottomSheet
        show={showDetailSheet}
        title={selectedPO ? `Detail ${selectedPO.po_number}` : 'Detail PO'}
        onclose={() => (showDetailSheet = false)}
    >
        {#if selectedPO}
            <div class="space-y-4">
                <div class="rounded-2xl bg-indigo-50 dark:bg-indigo-950/50 p-3.5 border border-indigo-100 dark:border-indigo-900/60 flex items-center justify-between text-xs">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-400 block">Toko Tujuan</span>
                        <span class="font-bold text-indigo-900 dark:text-indigo-200">{selectedPO.store_name}</span>
                    </div>

                    <div class="text-right">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-400 block">Status Produksi</span>
                        <span class="font-extrabold text-indigo-600 dark:text-indigo-300 uppercase">{selectedPO.status_label}</span>
                    </div>
                </div>

                <div class="space-y-2">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">Daftar Item ({selectedPO.items.length})</h4>

                    {#each selectedPO.items as item}
                        <div class="rounded-2xl bg-slate-50 dark:bg-slate-950 p-3.5 border border-slate-200/80 dark:border-slate-800 space-y-1.5">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h5 class="text-xs font-extrabold text-slate-800 dark:text-slate-100">{item.product_name}</h5>
                                    <p class="text-[11px] font-mono text-slate-500">{item.sku} ({item.variant_info})</p>
                                </div>
                                <span class="rounded-lg bg-emerald-100 dark:bg-emerald-950/80 px-2 py-0.5 text-xs font-black text-emerald-700 dark:text-emerald-300">
                                    {item.qty} Pcs
                                </span>
                            </div>

                            <div class="flex items-center justify-between text-[11px] text-slate-500 pt-1 border-t border-slate-200/60 dark:border-slate-800/60">
                                <span>Harga HPP: {formatRupiah(item.price)}</span>
                                <span class="font-bold text-indigo-600 dark:text-indigo-400">Subtotal: {formatRupiah(item.subtotal)}</span>
                            </div>
                        </div>
                    {/each}
                </div>

                {#if selectedPO.notes}
                    <div class="rounded-2xl bg-amber-50 dark:bg-amber-950/40 p-3 border border-amber-200 dark:border-amber-900/60 text-xs text-amber-900 dark:text-amber-200">
                        <strong>Catatan Seller:</strong> "{selectedPO.notes}"
                    </div>
                {/if}
            </div>
        {/if}
    </BottomSheet>
</SellerMobileLayout>
