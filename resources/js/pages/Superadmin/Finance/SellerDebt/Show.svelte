<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import { router, Link } from '@inertiajs/svelte';
    import {
        ArrowLeft,
        UserCheck,
        ArrowDownLeft,
        ArrowUpRight,
        Plus,
        Trash2,
        Calendar,
        FileText,
        Wallet,
        Printer,
        MessageCircle,
        Search,
        Layers,
        Store,
        TrendingUp,
        CheckCircle2,
        AlertCircle,
        X,
        Columns,
        Clock,
        DollarSign
    } from '@lucide/svelte';

    interface SellerDetail {
        id: number;
        seller_name: string;
        phone: string | null;
        address: string | null;
        brand?: { id: number; name: string } | null;
    }

    interface ItemRow {
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
        stock_out?: { date: string; store?: { store_name: string } };
    }

    interface AdjustmentItem {
        id: number;
        amount: number;
        adjustment_date: string;
        note: string | null;
        period?: { name: string };
    }

    interface StatsData {
        stock_in_hpp: number;
        stock_out_hpp: number;
        total_adjustment: number;
        net_debt: number;
        stock_in_qty?: number;
        stock_out_qty?: number;
        liquidation_rate?: number;
        active_products_count?: number;
        stores_count?: number;
    }

    let {
        seller,
        stockInItems,
        stockOutItems,
        adjustments = [],
        periods = [],
        stats = {
            stock_in_hpp: 0,
            stock_out_hpp: 0,
            total_adjustment: 0,
            net_debt: 0,
            stock_in_qty: 0,
            stock_out_qty: 0,
            liquidation_rate: 0,
            active_products_count: 0,
            stores_count: 0,
        },
        filters = { period_id: '', search: '' },
    }: {
        seller: SellerDetail;
        stockInItems: { data: ItemRow[]; links: any[] };
        stockOutItems: { data: ItemRow[]; links: any[] };
        adjustments?: AdjustmentItem[];
        periods?: { id: number; name: string }[];
        stats?: StatsData;
        filters?: { period_id?: string; search?: string };
    } = $props();

    let periodId = $state(filters.period_id ?? '');
    let searchQuery = $state(filters.search ?? '');
    let activeTab = $state<'split' | 'stock_in' | 'stock_out' | 'adjustments'>('split');

    // Quick Adjustment Modal State
    let isAdjustmentModalOpen = $state(false);
    let adjustmentType = $state<'credit' | 'debit'>('credit'); // credit = - (pelunasan), debit = + (nambah modal)
    let adjustmentAmount = $state<number | string>('');
    let adjustmentPeriodId = $state(filters.period_id ?? '');
    let adjustmentDate = $state(new Date().toISOString().split('T')[0]);
    let adjustmentNote = $state('');

    function formatRupiah(num: number): string {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num || 0);
    }

    function formatDate(dateStr: string): string {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    let searchTimer: any;
    function handleSearchInput() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            applyFilter();
        }, 400);
    }

    function applyFilter() {
        router.get(
            `/superadmin/finance/seller-debt/${seller.id}`,
            { period_id: periodId, search: searchQuery },
            { preserveState: true, replace: true }
        );
    }

    function deleteAdjustment(id: number) {
        if (confirm('Yakin ingin menghapus penyesuaian ini?')) {
            router.delete(`/superadmin/finance/seller-debt/adjustment/${id}`);
        }
    }

    function openAdjustmentModal() {
        adjustmentPeriodId = periodId || (periods[0]?.id ? String(periods[0].id) : '');
        adjustmentAmount = '';
        adjustmentNote = '';
        adjustmentType = 'credit';
        adjustmentDate = new Date().toISOString().split('T')[0];
        isAdjustmentModalOpen = true;
    }

    function submitAdjustment() {
        const rawNum = Math.abs(Number(adjustmentAmount) || 0);
        if (rawNum <= 0) {
            alert('Masukkan nominal penyesuaian yang valid.');
            return;
        }

        const finalAmount = adjustmentType === 'credit' ? -rawNum : rawNum;

        router.post(
            '/superadmin/finance/seller-debt/adjustment',
            {
                seller_id: seller.id,
                period_id: adjustmentPeriodId || null,
                amount: finalAmount,
                adjustment_date: adjustmentDate,
                note: adjustmentNote || (adjustmentType === 'credit' ? 'Pelunasan / Pemotongan Saldo Modal' : 'Penambahan Modal / Retur'),
            },
            {
                onSuccess: () => {
                    isAdjustmentModalOpen = false;
                },
            }
        );
    }

    function sendWhatsAppNotification() {
        if (!seller.phone) {
            alert('Nomor telepon seller belum terisi di profil.');
            return;
        }

        let cleanPhone = seller.phone.replace(/[^0-9]/g, '');
        if (cleanPhone.startsWith('0')) {
            cleanPhone = '62' + cleanPhone.slice(1);
        }

        const periodName = periods.find((p) => String(p.id) === String(periodId))?.name ?? 'Semua Periode';
        const msg = [
            `Halo Kak *${seller.seller_name}*,`,
            `Berikut update mutasi modal & sisa tagihan berjalan Anda di *Warehub v2*:`,
            ``,
            `📅 *Periode:* ${periodName}`,
            `📥 *Total Barang Masuk (HPP):* ${formatRupiah(stats.stock_in_hpp)} (${stats.stock_in_qty || 0} Pcs)`,
            `📤 *Total Barang Terjual (HPP):* ${formatRupiah(stats.stock_out_hpp)} (${stats.stock_out_qty || 0} Pcs)`,
            `🔄 *Penyesuaian Saldo:* ${formatRupiah(stats.total_adjustment)}`,
            `----------------------------------------`,
            `💰 *Sisa Tagihan Modal (Net):* *${formatRupiah(stats.net_debt)}*`,
            `📊 *Tingkat Likuidasi Modal:* ${stats.liquidation_rate || 0}%`,
            ``,
            `Laporan lengkap dapat diunduh melalui portal atau hubungi admin. Terima kasih! 🙏`,
        ].join('\n');

        window.open(`https://wa.me/${cleanPhone}?text=${encodeURIComponent(msg)}`, '_blank');
    }

    const pdfExportUrl = $derived(
        `/superadmin/finance/seller-debt/${seller.id}/pdf${periodId ? `?period_id=${periodId}` : ''}`
    );

    // Liquidation status evaluation
    let liquidationStatus = $derived.by(() => {
        const net = stats.net_debt;
        const rate = stats.liquidation_rate || 0;

        if (net <= 0) {
            return {
                title: 'Lunas / Saldo Surplus',
                desc: 'Tidak ada tagihan modal tertunda. Semua barang masuk telah terkompensasi oleh penjualan/pembayaran.',
                color: 'emerald',
                badge: 'Lunas',
            };
        } else if (rate >= 60) {
            return {
                title: 'Perputaran Modal Sehat (Fast Moving)',
                desc: `${rate}% modal barang masuk telah sukses terjual keluar melalui marketplace.`,
                color: 'sky',
                badge: 'Perputaran Sehat',
            };
        } else if (rate >= 30) {
            return {
                title: 'Perputaran Normal (Moderate)',
                desc: `${rate}% modal barang terlikuidasi. Masih terdapat sisa modal mengendap di gudang.`,
                color: 'amber',
                badge: 'Normal',
            };
        } else {
            return {
                title: 'Modal Tertahan Tinggi (Slow Moving)',
                desc: `Barang yang keluar baru ${rate}%. Mayoritas stok masih mengendap sebagai modal di gudang.`,
                color: 'rose',
                badge: 'Slow Moving',
            };
        }
    });
</script>

<AdminLayout
    title="Detail Hutang {seller.seller_name}"
    breadcrumbs={[
        { name: 'Keuangan' },
        { name: 'Hutang Seller', href: '/superadmin/finance/seller-debt' },
        { name: seller.seller_name },
    ]}
>
    <!-- Top Action & Info Banner -->
    <div class="mb-6 rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-200/80 dark:border-slate-800">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
            <!-- Seller Identity -->
            <div class="flex items-start gap-4">
                <Link
                    href="/superadmin/finance/seller-debt"
                    class="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 p-2.5 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors shadow-xs shrink-0"
                    title="Kembali ke Daftar Hutang"
                >
                    <ArrowLeft class="h-5 w-5" />
                </Link>

                <div class="space-y-1.5">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1 class="text-2xl font-extrabold text-slate-900 dark:text-slate-50 tracking-tight flex items-center gap-2">
                            {seller.seller_name}
                        </h1>
                        {#if seller.brand?.name}
                            <span class="inline-flex items-center gap-1 rounded-full bg-purple-50 dark:bg-purple-950/50 border border-purple-200 dark:border-purple-800 px-2.5 py-0.5 text-xs font-semibold text-purple-700 dark:text-purple-300">
                                <Layers class="h-3 w-3" />
                                {seller.brand.name}
                            </span>
                        {/if}
                    </div>

                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                        {#if seller.phone}
                            <span class="flex items-center gap-1 text-slate-600 dark:text-slate-300">
                                📞 {seller.phone}
                            </span>
                        {/if}
                        <span class="flex items-center gap-1">
                            📦 <strong>{stats.active_products_count || 0}</strong> Produk Aktif
                        </span>
                        <span class="flex items-center gap-1">
                            🏪 <strong>{stats.stores_count || 0}</strong> Toko Marketplace
                        </span>
                    </div>
                </div>
            </div>

            <!-- Action Buttons: + Adjustment, WhatsApp, Cetak PDF -->
            <div class="flex flex-wrap items-center gap-2.5 pt-2 lg:pt-0">
                <button
                    onclick={openAdjustmentModal}
                    class="inline-flex items-center gap-2 rounded-xl bg-purple-600 px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-purple-600/30 hover:bg-purple-700 active:scale-95 transition-all"
                >
                    <Plus class="h-4 w-4" />
                    + Penyesuaian Saldo
                </button>

                {#if seller.phone}
                    <button
                        onclick={sendWhatsAppNotification}
                        class="inline-flex items-center gap-2 rounded-xl border border-emerald-300 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/60 px-3.5 py-2.5 text-xs font-bold text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 active:scale-95 transition-all shadow-xs"
                        title="Kirim pesan ringkasan saldo ke nomor WhatsApp seller"
                    >
                        <MessageCircle class="h-4 w-4 text-emerald-600" />
                        Kirim WA Saldo
                    </button>
                {/if}

                <a
                    href={pdfExportUrl}
                    target="_blank"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3.5 py-2.5 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 active:scale-95 transition-all shadow-xs"
                    title="Cetak Rekening Koran Modal Seller dalam format PDF"
                >
                    <Printer class="h-4 w-4 text-indigo-500" />
                    Cetak PDF
                </a>
            </div>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="mb-6 flex flex-col sm:flex-row items-center justify-between gap-3 bg-white dark:bg-slate-900 p-3 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
        <!-- Search SKU / Produk -->
        <div class="relative w-full sm:w-96">
            <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
            <input
                type="text"
                placeholder="Cari SKU, Nama Produk, Invoice, atau Toko..."
                bind:value={searchQuery}
                oninput={handleSearchInput}
                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 pl-10 pr-4 py-2 text-xs text-slate-800 dark:text-slate-100 placeholder:text-slate-400 focus:ring-2 focus:ring-purple-500"
            />
            {#if searchQuery}
                <button
                    onclick={() => { searchQuery = ''; applyFilter(); }}
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                >
                    <X class="h-3.5 w-3.5" />
                </button>
            {/if}
        </div>

        <!-- Period Selector -->
        <div class="flex items-center gap-2 w-full sm:w-auto">
            <Calendar class="h-4 w-4 text-slate-400 shrink-0" />
            <select
                bind:value={periodId}
                onchange={applyFilter}
                class="w-full sm:w-60 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs font-medium text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-purple-500"
            >
                <option value="">Semua Periode Akuntansi</option>
                {#each periods as p}
                    <option value={p.id}>{p.name}</option>
                {/each}
            </select>
        </div>
    </div>

    <!-- Health & Capital Liquidation Flow Bar -->
    <div class="mb-6 rounded-2xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 p-5 text-white shadow-lg border border-indigo-900/40 relative overflow-hidden">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 relative z-10">
            <div class="space-y-1 max-w-xl">
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-white/10 text-indigo-200 border border-white/10">
                        {liquidationStatus.badge}
                    </span>
                    <h3 class="text-base font-bold text-white flex items-center gap-1.5">
                        <TrendingUp class="h-4 w-4 text-indigo-400" />
                        {liquidationStatus.title}
                    </h3>
                </div>
                <p class="text-xs text-slate-300 leading-relaxed">
                    {liquidationStatus.desc}
                </p>
            </div>

            <!-- Percentage Pill -->
            <div class="flex items-center gap-3">
                <div class="text-right">
                    <span class="block text-[10px] font-semibold uppercase tracking-wider text-indigo-300">Likuidasi Modal</span>
                    <span class="text-2xl font-black text-amber-300">{stats.liquidation_rate || 0}%</span>
                </div>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="mt-4 relative z-10">
            <div class="h-3 w-full bg-slate-800/80 rounded-full overflow-hidden flex border border-white/10">
                <div
                    class="bg-gradient-to-r from-emerald-500 to-teal-400 h-full transition-all duration-500"
                    style="width: {Math.min(stats.liquidation_rate || 0, 100)}%"
                    title="Terjual: {stats.liquidation_rate || 0}%"
                ></div>
                <div
                    class="bg-gradient-to-r from-amber-500/80 to-indigo-500/80 h-full transition-all duration-500 flex-1"
                    title="Sisa Modal Tertahan"
                ></div>
            </div>
            <div class="flex justify-between items-center text-[10px] text-slate-400 mt-1.5 font-medium">
                <span class="flex items-center gap-1">
                    <span class="inline-block w-2 h-2 rounded-full bg-emerald-400"></span>
                    Modal Terjual: {formatRupiah(stats.stock_out_hpp)} ({stats.stock_out_qty || 0} Pcs)
                </span>
                <span class="flex items-center gap-1">
                    <span class="inline-block w-2 h-2 rounded-full bg-amber-400"></span>
                    Sisa Modal Bersih: {formatRupiah(stats.net_debt)}
                </span>
            </div>
        </div>
    </div>

    <!-- Stats Cards (4 Grid) -->
    <div class="mb-8 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800 flex flex-col justify-between">
            <div>
                <span class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400">Total Stock In HPP</span>
                <span class="text-xl font-bold text-sky-600 dark:text-sky-400 mt-1 block">{formatRupiah(stats.stock_in_hpp)}</span>
            </div>
            <div class="mt-3 flex items-center justify-between text-xs text-slate-400 pt-2 border-t border-slate-100 dark:border-slate-800">
                <span>Barang Masuk:</span>
                <span class="font-bold text-slate-700 dark:text-slate-200">{stats.stock_in_qty || 0} Pcs</span>
            </div>
        </div>

        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800 flex flex-col justify-between">
            <div>
                <span class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400">Total Stock Out HPP</span>
                <span class="text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-1 block">{formatRupiah(stats.stock_out_hpp)}</span>
            </div>
            <div class="mt-3 flex items-center justify-between text-xs text-slate-400 pt-2 border-t border-slate-100 dark:border-slate-800">
                <span>Barang Terjual:</span>
                <span class="font-bold text-slate-700 dark:text-slate-200">{stats.stock_out_qty || 0} Pcs</span>
            </div>
        </div>

        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800 flex flex-col justify-between">
            <div>
                <span class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400">Penyesuaian Saldo</span>
                <span class="text-xl font-bold text-indigo-600 dark:text-indigo-400 mt-1 block">{formatRupiah(stats.total_adjustment)}</span>
            </div>
            <div class="mt-3 flex items-center justify-between text-xs text-slate-400 pt-2 border-t border-slate-100 dark:border-slate-800">
                <span>Total Catatan:</span>
                <span class="font-bold text-slate-700 dark:text-slate-200">{adjustments.length} Transaksi</span>
            </div>
        </div>

        <div class="rounded-2xl bg-gradient-to-br from-purple-900 via-indigo-900 to-slate-900 p-5 text-white shadow-md flex flex-col justify-between">
            <div>
                <span class="block text-[11px] font-semibold uppercase tracking-wider text-purple-200">Sisa Net Saldo Hutang</span>
                <span class="text-2xl font-black text-amber-300 mt-1 block">{formatRupiah(stats.net_debt)}</span>
            </div>
            <div class="mt-3 flex items-center justify-between text-xs text-purple-200/80 pt-2 border-t border-purple-800/60">
                <span>Status Modal:</span>
                <span class="font-bold text-amber-200">{stats.net_debt <= 0 ? 'Surplus / Lunas' : 'Tanggungan Aktif'}</span>
            </div>
        </div>
    </div>

    <!-- Tab View Switcher -->
    <div class="mb-4 flex flex-wrap items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-2">
        <button
            onclick={() => (activeTab = 'split')}
            class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition-all {activeTab === 'split'
                ? 'bg-purple-600 text-white shadow-sm'
                : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'}"
        >
            <Columns class="h-3.5 w-3.5" />
            2 Kolom (Split View)
        </button>

        <button
            onclick={() => (activeTab = 'stock_in')}
            class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition-all {activeTab === 'stock_in'
                ? 'bg-sky-600 text-white shadow-sm'
                : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'}"
        >
            <ArrowDownLeft class="h-3.5 w-3.5" />
            Barang Masuk ({stockInItems.data.length})
        </button>

        <button
            onclick={() => (activeTab = 'stock_out')}
            class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition-all {activeTab === 'stock_out'
                ? 'bg-emerald-600 text-white shadow-sm'
                : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'}"
        >
            <ArrowUpRight class="h-3.5 w-3.5" />
            Barang Keluar ({stockOutItems.data.length})
        </button>

        <button
            onclick={() => (activeTab = 'adjustments')}
            class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition-all {activeTab === 'adjustments'
                ? 'bg-indigo-600 text-white shadow-sm'
                : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'}"
        >
            <Wallet class="h-3.5 w-3.5" />
            Penyesuaian Saldo ({adjustments.length})
        </button>
    </div>

    <!-- TAB: Penyesuaian Saldo (Adjustments) -->
    {#if activeTab === 'adjustments' || (activeTab === 'split' && adjustments.length > 0)}
        <div class="mb-8 rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-100 dark:border-slate-800 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    <Wallet class="h-5 w-5 text-purple-500" />
                    Riwayat Penyesuaian Saldo (Adjustments)
                </h3>
                <button
                    onclick={openAdjustmentModal}
                    class="text-xs font-bold text-purple-600 dark:text-purple-400 hover:underline flex items-center gap-1"
                >
                    <Plus class="h-3.5 w-3.5" />
                    Tambah Baru
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 font-semibold uppercase">
                        <tr>
                            <th class="p-3">Tanggal</th>
                            <th class="p-3">Periode</th>
                            <th class="p-3">Catatan / Keterangan</th>
                            <th class="p-3 text-right">Nominal (Rp)</th>
                            <th class="p-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        {#each adjustments as adj}
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                <td class="p-3 font-medium">{formatDate(adj.adjustment_date)}</td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                        {adj.period?.name ?? 'Umum'}
                                    </span>
                                </td>
                                <td class="p-3 text-slate-600 dark:text-slate-300">{adj.note || '-'}</td>
                                <td class="p-3 text-right font-bold {adj.amount >= 0 ? 'text-indigo-600 dark:text-indigo-400' : 'text-rose-500 dark:text-rose-400'}">
                                    {adj.amount >= 0 ? '+' : ''} {formatRupiah(adj.amount)}
                                </td>
                                <td class="p-3 text-center">
                                    <button
                                        onclick={() => deleteAdjustment(adj.id)}
                                        class="text-rose-500 hover:text-rose-700 p-1.5 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors"
                                        title="Hapus Penyesuaian"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                        {:else}
                            <tr>
                                <td colspan="5" class="p-6 text-center text-slate-400">Belum ada catatan penyesuaian saldo.</td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </div>
    {/if}

    <!-- TAB: SPLIT VIEW or INDIVIDUAL TABLES -->
    {#if activeTab === 'split'}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Stock In Table -->
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-100 dark:border-slate-800 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        <ArrowDownLeft class="h-5 w-5 text-sky-500" />
                        Barang Masuk (Stock In HPP)
                    </h3>
                    <span class="text-xs text-sky-600 dark:text-sky-400 font-bold">{formatRupiah(stats.stock_in_hpp)}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 font-semibold uppercase">
                            <tr>
                                <th class="p-2.5">Tgl & Invoice</th>
                                <th class="p-2.5">Produk & Varian</th>
                                <th class="p-2.5 text-center">Qty</th>
                                <th class="p-2.5 text-right">Subtotal HPP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            {#each stockInItems.data as item}
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                    <td class="p-2.5">
                                        <span class="font-mono font-bold text-sky-600 block">{item.stock_in?.invoice_number}</span>
                                        <span class="text-[10px] text-slate-400">{formatDate(item.stock_in?.date || '')}</span>
                                    </td>
                                    <td class="p-2.5">
                                        <span class="font-medium block">{item.product_variant?.product?.product_name}</span>
                                        <span class="text-[10px] text-slate-400 font-mono">SKU: {item.product_variant?.sku}</span>
                                    </td>
                                    <td class="p-2.5 text-center font-bold">{item.qty} Pcs</td>
                                    <td class="p-2.5 text-right font-bold text-sky-600 dark:text-sky-400">{formatRupiah(item.qty * item.price)}</td>
                                </tr>
                            {:else}
                                <tr>
                                    <td colspan="4" class="p-8 text-center text-slate-400">Tidak ada item Stock In ditemukan.</td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
                <Pagination links={stockInItems.links} />
            </div>

            <!-- Stock Out Table -->
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-100 dark:border-slate-800 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        <ArrowUpRight class="h-5 w-5 text-emerald-500" />
                        Barang Keluar (Stock Out HPP)
                    </h3>
                    <span class="text-xs text-emerald-600 dark:text-emerald-400 font-bold">{formatRupiah(stats.stock_out_hpp)}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 font-semibold uppercase">
                            <tr>
                                <th class="p-2.5">Tanggal & Toko</th>
                                <th class="p-2.5">Produk & Varian</th>
                                <th class="p-2.5 text-center">Qty</th>
                                <th class="p-2.5 text-right">Subtotal HPP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            {#each stockOutItems.data as item}
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                    <td class="p-2.5">
                                        <span class="font-medium block">{item.stock_out?.store?.store_name ?? 'Penjualan Umum'}</span>
                                        <span class="text-[10px] text-slate-400">{formatDate(item.stock_out?.date || '')}</span>
                                    </td>
                                    <td class="p-2.5">
                                        <span class="font-medium block">{item.product_variant?.product?.product_name}</span>
                                        <span class="text-[10px] text-slate-400 font-mono">SKU: {item.product_variant?.sku}</span>
                                    </td>
                                    <td class="p-2.5 text-center font-bold">{item.qty} Pcs</td>
                                    <td class="p-2.5 text-right font-bold text-emerald-600 dark:text-emerald-400">{formatRupiah(item.qty * item.price)}</td>
                                </tr>
                            {:else}
                                <tr>
                                    <td colspan="4" class="p-8 text-center text-slate-400">Tidak ada item Stock Out ditemukan.</td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
                <Pagination links={stockOutItems.links} />
            </div>
        </div>
    {:else if activeTab === 'stock_in'}
        <!-- Full-width Stock In Table -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-100 dark:border-slate-800 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    <ArrowDownLeft class="h-5 w-5 text-sky-500" />
                    Barang Masuk Gudang (Stock In HPP)
                </h3>
                <span class="text-sm text-sky-600 dark:text-sky-400 font-bold">Total: {formatRupiah(stats.stock_in_hpp)}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 font-semibold uppercase">
                        <tr>
                            <th class="p-3">Tanggal</th>
                            <th class="p-3">No. Invoice</th>
                            <th class="p-3">Gudang</th>
                            <th class="p-3">Produk & Varian</th>
                            <th class="p-3">SKU</th>
                            <th class="p-3 text-center">Qty</th>
                            <th class="p-3 text-right">HPP Satuan</th>
                            <th class="p-3 text-right">Subtotal HPP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        {#each stockInItems.data as item}
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                <td class="p-3">{formatDate(item.stock_in?.date || '')}</td>
                                <td class="p-3 font-mono font-bold text-sky-600">{item.stock_in?.invoice_number}</td>
                                <td class="p-3">{item.stock_in?.warehouse?.name ?? '-'}</td>
                                <td class="p-3 font-medium">
                                    {item.product_variant?.product?.product_name}
                                    <span class="text-slate-400 text-[11px] block">({item.product_variant?.size ?? '-'} / {item.product_variant?.color ?? '-'})</span>
                                </td>
                                <td class="p-3 font-mono">{item.product_variant?.sku}</td>
                                <td class="p-3 text-center font-bold">{item.qty} Pcs</td>
                                <td class="p-3 text-right">{formatRupiah(item.price)}</td>
                                <td class="p-3 text-right font-bold text-sky-600 dark:text-sky-400">{formatRupiah(item.qty * item.price)}</td>
                            </tr>
                        {:else}
                            <tr>
                                <td colspan="8" class="p-8 text-center text-slate-400">Tidak ada item Stock In ditemukan.</td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
            <Pagination links={stockInItems.links} />
        </div>
    {:else if activeTab === 'stock_out'}
        <!-- Full-width Stock Out Table -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-100 dark:border-slate-800 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    <ArrowUpRight class="h-5 w-5 text-emerald-500" />
                    Barang Keluar / Terjual (Stock Out HPP)
                </h3>
                <span class="text-sm text-emerald-600 dark:text-emerald-400 font-bold">Total: {formatRupiah(stats.stock_out_hpp)}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 font-semibold uppercase">
                        <tr>
                            <th class="p-3">Tanggal</th>
                            <th class="p-3">Toko / Marketplace</th>
                            <th class="p-3">Produk & Varian</th>
                            <th class="p-3">SKU</th>
                            <th class="p-3 text-center">Qty</th>
                            <th class="p-3 text-right">HPP Satuan</th>
                            <th class="p-3 text-right">Subtotal HPP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        {#each stockOutItems.data as item}
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                <td class="p-3">{formatDate(item.stock_out?.date || '')}</td>
                                <td class="p-3 font-semibold text-slate-700 dark:text-slate-200">
                                    {item.stock_out?.store?.store_name ?? 'Penjualan Umum'}
                                </td>
                                <td class="p-3 font-medium">
                                    {item.product_variant?.product?.product_name}
                                    <span class="text-slate-400 text-[11px] block">({item.product_variant?.size ?? '-'} / {item.product_variant?.color ?? '-'})</span>
                                </td>
                                <td class="p-3 font-mono">{item.product_variant?.sku}</td>
                                <td class="p-3 text-center font-bold">{item.qty} Pcs</td>
                                <td class="p-3 text-right">{formatRupiah(item.price)}</td>
                                <td class="p-3 text-right font-bold text-emerald-600 dark:text-emerald-400">{formatRupiah(item.qty * item.price)}</td>
                            </tr>
                        {:else}
                            <tr>
                                <td colspan="7" class="p-8 text-center text-slate-400">Tidak ada item Stock Out ditemukan.</td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
            <Pagination links={stockOutItems.links} />
        </div>
    {/if}

    <!-- Quick Adjustment Modal -->
    {#if isAdjustmentModalOpen}
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
            <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-2xl border border-slate-100 dark:border-slate-800 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <h3 class="text-base font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                        <Wallet class="h-5 w-5 text-purple-600" />
                        Tambah Penyesuaian Saldo
                    </h3>
                    <button
                        onclick={() => (isAdjustmentModalOpen = false)}
                        class="text-slate-400 hover:text-slate-600 p-1"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <form onsubmit={(e) => { e.preventDefault(); submitAdjustment(); }} class="space-y-4">
                    <!-- Adjustment Type Selector -->
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5">Tipe Penyesuaian</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                onclick={() => (adjustmentType = 'credit')}
                                class="flex items-center justify-center gap-1.5 p-2.5 rounded-xl border text-xs font-bold transition-all {adjustmentType === 'credit'
                                    ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 ring-2 ring-emerald-500/20'
                                    : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-50'}"
                            >
                                <span class="text-sm">📉</span>
                                Mengurangi Hutang (Pelunasan)
                            </button>

                            <button
                                type="button"
                                onclick={() => (adjustmentType = 'debit')}
                                class="flex items-center justify-center gap-1.5 p-2.5 rounded-xl border text-xs font-bold transition-all {adjustmentType === 'debit'
                                    ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 ring-2 ring-indigo-500/20'
                                    : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-50'}"
                            >
                                <span class="text-sm">📈</span>
                                Menambah Hutang (Modal/Klaim)
                            </button>
                        </div>
                    </div>

                    <!-- Nominal Input -->
                    <div>
                        <label for="modal_adj_amount" class="block text-xs font-semibold uppercase text-slate-500 mb-1">
                            Nominal (Rp)
                        </label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">Rp</span>
                            <input
                                id="modal_adj_amount"
                                type="number"
                                min="1"
                                step="1000"
                                bind:value={adjustmentAmount}
                                required
                                placeholder="Contoh: 1500000"
                                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 pl-10 pr-4 py-2.5 text-sm font-semibold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-purple-500"
                            />
                        </div>
                        <span class="text-[11px] text-slate-400 block mt-1">
                            {adjustmentType === 'credit'
                                ? 'Nilai ini akan memotong/mengurangi sisa saldo hutang seller.'
                                : 'Nilai ini akan menambah sisa saldo hutang modal seller.'}
                        </span>
                    </div>

                    <!-- Tanggal -->
                    <div>
                        <label for="modal_adj_date" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Tanggal</label>
                        <input
                            id="modal_adj_date"
                            type="date"
                            bind:value={adjustmentDate}
                            required
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-purple-500"
                        />
                    </div>

                    <!-- Periode -->
                    <div>
                        <label for="modal_adj_period" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Periode Buku</label>
                        <select
                            id="modal_adj_period"
                            bind:value={adjustmentPeriodId}
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-purple-500"
                        >
                            <option value="">Semua / Tanpa Periode Khusus</option>
                            {#each periods as p}
                                <option value={p.id}>{p.name}</option>
                            {/each}
                        </select>
                    </div>

                    <!-- Catatan -->
                    <div>
                        <label for="modal_adj_note" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Catatan / Keterangan</label>
                        <input
                            id="modal_adj_note"
                            type="text"
                            bind:value={adjustmentNote}
                            placeholder="Contoh: Pembayaran setoran modal via transfer BCA..."
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-purple-500"
                        />
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button
                            type="button"
                            onclick={() => (isAdjustmentModalOpen = false)}
                            class="rounded-xl border border-slate-200 dark:border-slate-800 px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="rounded-xl bg-purple-600 px-5 py-2 text-xs font-bold text-white hover:bg-purple-700 shadow-md shadow-purple-600/30"
                        >
                            Simpan Penyesuaian
                        </button>
                    </div>
                </form>
            </div>
        </div>
    {/if}
</AdminLayout>
