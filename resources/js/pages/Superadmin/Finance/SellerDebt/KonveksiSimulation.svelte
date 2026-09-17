<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import { Link } from '@inertiajs/svelte';
    import { Calculator, ArrowLeft, DollarSign, PieChart, Sparkles, UserCheck } from '@lucide/svelte';

    interface SellerOption {
        id: number;
        seller_name: string;
    }

    let { sellers = [] }: { sellers?: SellerOption[] } = $props();

    let selectedSellerId = $state<number | string>(sellers[0]?.id ?? '');
    let itemPriceHpp = $state(45000);
    let itemPriceSelling = $state(75000);
    let totalQtyProduced = $state(1000);
    let konveksiMarginSharePercent = $state(30);

    function formatRupiah(num: number): string {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num || 0);
    }

    let totalProductionModal = $derived(itemPriceHpp * totalQtyProduced);
    let totalPotentialOmset = $derived(itemPriceSelling * totalQtyProduced);
    let totalGrossMargin = $derived(totalPotentialOmset - totalProductionModal);
    let konveksiMarginShare = $derived((totalGrossMargin * konveksiMarginSharePercent) / 100);
    let sellerNetProfit = $derived(totalGrossMargin - konveksiMarginShare);
</script>

<AdminLayout title="Simulasi Pembagian Margin Konveksi" breadcrumbs={[{ name: 'Keuangan' }, { name: 'Hutang Seller', href: '/superadmin/finance/seller-debt' }, { name: 'Simulasi Margin Konveksi' }]}>
    <!-- Header -->
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <Link
                href="/superadmin/finance/seller-debt"
                class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-2.5 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors shadow-xs"
            >
                <ArrowLeft class="h-5 w-5" />
            </Link>
            <div>
                <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                    <Calculator class="h-7 w-7 text-purple-500" />
                    Simulasi Pembagian Margin & Profit Konveksi
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Kalkulator kalkulasi pembagian hasil produksi vendor konveksi & mitra seller</p>
            </div>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Simulation Controls Form -->
        <div class="lg:col-span-6 rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-100 dark:border-slate-800 space-y-6">
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 border-b border-slate-100 dark:border-slate-800 pb-3 flex items-center gap-2">
                <Sparkles class="h-5 w-5 text-purple-500" />
                Parameter Input Produksi & Harga
            </h3>

            <div class="space-y-4">
                <div>
                    <label for="sim_seller" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Mitra / Seller</label>
                    <select
                        id="sim_seller"
                        bind:value={selectedSellerId}
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-purple-500"
                    >
                        {#each sellers as s}
                            <option value={s.id}>{s.seller_name}</option>
                        {/each}
                    </select>
                </div>

                <div>
                    <label for="total_qty_produced" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Total Qty Produksi (Pcs)</label>
                    <input
                        id="total_qty_produced"
                        type="number"
                        bind:value={totalQtyProduced}
                        min="1"
                        step="50"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm font-bold text-purple-600 dark:text-purple-400 focus:ring-2 focus:ring-purple-500"
                    />
                </div>

                <div>
                    <label for="item_price_hpp" class="block text-xs font-semibold uppercase text-slate-500 mb-1">HPP Modal Konveksi Per Pcs (Rp)</label>
                    <input
                        id="item_price_hpp"
                        type="number"
                        bind:value={itemPriceHpp}
                        min="0"
                        step="1000"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm font-semibold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-purple-500"
                    />
                </div>

                <div>
                    <label for="item_price_selling" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Estimasi Harga Jual Per Pcs (Rp)</label>
                    <input
                        id="item_price_selling"
                        type="number"
                        bind:value={itemPriceSelling}
                        min="0"
                        step="1000"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm font-semibold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-purple-500"
                    />
                </div>

                <div>
                    <div class="flex justify-between items-center mb-1">
                        <label for="konveksi_share" class="block text-xs font-semibold uppercase text-slate-500">Persentase Bagi Hasil Konveksi (%)</label>
                        <span class="text-sm font-bold text-purple-600 dark:text-purple-400">{konveksiMarginSharePercent}%</span>
                    </div>
                    <input
                        id="konveksi_share"
                        type="range"
                        bind:value={konveksiMarginSharePercent}
                        min="0"
                        max="100"
                        step="5"
                        class="w-full accent-purple-600"
                    />
                </div>
            </div>
        </div>

        <!-- Simulation Results Display Card -->
        <div class="lg:col-span-6 rounded-2xl bg-gradient-to-br from-slate-900 via-purple-950 to-indigo-950 p-6 text-white shadow-xl space-y-6 flex flex-col justify-between">
            <div>
                <h3 class="text-base font-bold border-b border-white/10 pb-3 flex items-center gap-2 text-purple-200">
                    <PieChart class="h-5 w-5 text-amber-400" />
                    Hasil Estimasi Profit & Pembagian Margin
                </h3>

                <div class="grid grid-cols-2 gap-4 mt-6">
                    <div class="p-4 rounded-xl bg-white/5 border border-white/10">
                        <span class="block text-xs text-slate-400 uppercase font-semibold">Total Modal Produksi</span>
                        <span class="text-lg font-bold text-sky-300 mt-1 block">{formatRupiah(totalProductionModal)}</span>
                    </div>

                    <div class="p-4 rounded-xl bg-white/5 border border-white/10">
                        <span class="block text-xs text-slate-400 uppercase font-semibold">Total Potensi Omset</span>
                        <span class="text-lg font-bold text-emerald-300 mt-1 block">{formatRupiah(totalPotentialOmset)}</span>
                    </div>
                </div>

                <div class="mt-6 p-5 rounded-2xl bg-white/10 border border-white/15 space-y-4">
                    <div class="flex justify-between items-center border-b border-white/10 pb-2">
                        <span class="text-sm text-slate-300 font-medium">Total Margin Kotor (Profit)</span>
                        <span class="text-lg font-bold text-amber-300">{formatRupiah(totalGrossMargin)}</span>
                    </div>

                    <div class="flex justify-between items-center">
                        <span class="text-xs text-purple-200">Bagian Profit Konveksi ({konveksiMarginSharePercent}%)</span>
                        <span class="text-base font-bold text-purple-300">{formatRupiah(konveksiMarginShare)}</span>
                    </div>

                    <div class="flex justify-between items-center pt-2 border-t border-white/10">
                        <span class="text-sm font-bold text-emerald-300">Bagian Profit Bersih Seller</span>
                        <span class="text-xl font-black text-emerald-400">{formatRupiah(sellerNetProfit)}</span>
                    </div>
                </div>
            </div>

            <div class="p-4 rounded-xl bg-purple-500/10 border border-purple-500/20 text-xs text-purple-200 leading-relaxed">
                💡 <strong>Info:</strong> Perhitungan simulasi di atas berbasis pada harga pokok produksi (HPP) aktual dan estimasi harga jual per item. Hasil dapat disesuaikan dengan kesepakatan akad bagi hasil konveksi.
            </div>
        </div>
    </div>
</AdminLayout>
