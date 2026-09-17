<script lang="ts">
    import SellerMobileLayout from '@/layouts/SellerMobileLayout.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import { Link } from '@inertiajs/svelte';
    import {
        Wallet,
        Upload,
        CheckCircle2,
        Clock,
        AlertCircle,
        Copy,
        Check,
        Building2,
        HelpCircle,
        Coins,
        ArrowUpRight,
        ArrowDownLeft,
        FileText,
        ChevronRight
    } from '@lucide/svelte';

    interface ProofTransferItem {
        id: number;
        amount: number;
        transfer_date: string;
        proof_file: string;
        status: 'pending' | 'approved' | 'rejected';
        notes: string | null;
        store_name: string;
        created_at: string;
    }

    interface AdjustmentItem {
        id: number;
        amount: number;
        reason: string;
        period_name: string;
        date: string;
    }

    interface PaginatedTransfers {
        data: ProofTransferItem[];
        links: any[];
    }

    let {
        metrics = {
            stock_in_hpp: 0,
            total_hpp_sold: 0,
            total_adjustments: 0,
            approved_transfers: 0,
            net_debt: 0,
        },
        proofTransfers = { data: [], links: [] },
        adjustments = [],
        bankInfo = {
            bank_name: 'BCA (Bank Central Asia)',
            account_number: '8410928192',
            account_holder: 'AA Gym Konveksi Gudang',
        },
    }: {
        metrics?: {
            stock_in_hpp: number;
            total_hpp_sold: number;
            total_adjustments: number;
            approved_transfers: number;
            net_debt: number;
        };
        proofTransfers?: PaginatedTransfers;
        adjustments?: AdjustmentItem[];
        bankInfo?: { bank_name: string; account_number: string; account_holder: string };
    } = $props();

    let copied = $state(false);
    let showFormulaModal = $state(false);

    function formatRupiah(num: number): string {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num || 0);
    }

    function copyAccount() {
        navigator.clipboard.writeText(bankInfo.account_number);
        copied = true;
        setTimeout(() => (copied = false), 2000);
    }
</script>

<SellerMobileLayout title="Ringkasan Hutang & Keuangan">
    <div class="space-y-4">
        <!-- Header Bar -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-black text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                    <Wallet class="h-6 w-6 text-amber-500" />
                    Hutang & Settlement
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Transparansi tagihan modal HPP & bukti transfer</p>
            </div>

            <Link
                href="/seller/debt/settlement"
                class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-bold text-white shadow-md shadow-emerald-600/30 hover:bg-emerald-700 transition-colors"
            >
                <Upload class="h-4 w-4" />
                Upload
            </Link>
        </div>

        <!-- Financial Health Summary Card -->
        <div class="rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 p-5 text-white shadow-xl space-y-4 relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="rounded-full bg-amber-400/20 px-3 py-1 text-[11px] font-bold text-amber-300 border border-amber-400/30 backdrop-blur-md">
                    Saldo Tagihan Modal Net
                </span>
                <button
                    type="button"
                    onclick={() => (showFormulaModal = !showFormulaModal)}
                    class="text-xs font-bold text-indigo-200 hover:text-white flex items-center gap-1"
                >
                    <HelpCircle class="h-3.5 w-3.5 text-indigo-300" />
                    Rumus HPP
                </button>
            </div>

            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-indigo-200 block">Total Tagihan Bersih (HPP Modal)</span>
                <h2 class="text-2xl font-black text-amber-300 mt-0.5">{formatRupiah(metrics.net_debt)}</h2>
            </div>

            <!-- Detailed HPP Breakdown Grid -->
            <div class="pt-3 border-t border-white/10 grid grid-cols-2 gap-3 text-xs">
                <div>
                    <span class="text-[10px] text-indigo-200/80 uppercase font-semibold block">HPP Masuk (Stock In)</span>
                    <span class="font-extrabold text-white">{formatRupiah(metrics.stock_in_hpp)}</span>
                </div>

                <div>
                    <span class="text-[10px] text-indigo-200/80 uppercase font-semibold block">HPP Terjual (Stock Out)</span>
                    <span class="font-extrabold text-white">{formatRupiah(metrics.total_hpp_sold)}</span>
                </div>

                <div>
                    <span class="text-[10px] text-indigo-200/80 uppercase font-semibold block">Penyesuaian (Adjust)</span>
                    <span class="font-extrabold text-amber-300">{formatRupiah(metrics.total_adjustments)}</span>
                </div>

                <div>
                    <span class="text-[10px] text-indigo-200/80 uppercase font-semibold block">Disetujui (Terbayar)</span>
                    <span class="font-extrabold text-emerald-400">{formatRupiah(metrics.approved_transfers)}</span>
                </div>
            </div>
        </div>

        <!-- Formula Transparency Explanation Banner -->
        {#if showFormulaModal}
            <div class="rounded-3xl bg-indigo-50 dark:bg-indigo-950/60 p-4 border border-indigo-200 dark:border-indigo-900 text-xs space-y-2 animate-in fade-in duration-200">
                <div class="flex items-center gap-2 font-extrabold text-indigo-900 dark:text-indigo-200">
                    <HelpCircle class="h-4 w-4 text-indigo-600 dark:text-indigo-400 shrink-0" />
                    <span>Rumus Transparansi Tagihan Modal HPP:</span>
                </div>
                <p class="text-indigo-800 dark:text-indigo-300 leading-relaxed text-[11px]">
                    <strong>Net Tagihan</strong> = (HPP Barang Masuk / Stock In) - (HPP Barang Terjual / Stock Out) + (Penyesuaian Manual) - (Bukti Transfer Disetujui Admin).
                </p>
            </div>
        {/if}

        <!-- Bank Account Copy Card -->
        <div class="rounded-3xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 space-y-2">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Building2 class="h-4 w-4 text-sky-500" />
                    <span class="text-xs font-bold text-slate-800 dark:text-slate-100">{bankInfo.bank_name}</span>
                </div>
                <span class="text-[10px] text-slate-400 font-medium">{bankInfo.account_holder}</span>
            </div>

            <div class="rounded-2xl bg-slate-50 dark:bg-slate-950 p-3 flex items-center justify-between border border-slate-100 dark:border-slate-800">
                <span class="font-mono text-sm font-extrabold text-indigo-600 dark:text-indigo-400">{bankInfo.account_number}</span>

                <button
                    type="button"
                    onclick={copyAccount}
                    class="inline-flex items-center gap-1 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 px-3 py-1.5 text-xs font-bold text-indigo-600 dark:text-indigo-400 transition-colors"
                >
                    {#if copied}
                        <Check class="h-3.5 w-3.5 text-emerald-500" />
                        <span class="text-emerald-500">Tersalin!</span>
                    {:else}
                        <Copy class="h-3.5 w-3.5" />
                        <span>Salin Rekening</span>
                    {/if}
                </button>
            </div>
        </div>

        <!-- Mobile Proof Transfer Submissions List -->
        <div class="space-y-3">
            <div class="flex items-center justify-between px-1">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Riwayat Upload Bukti Transfer</h3>
                <Link href="/seller/debt/settlement" class="text-xs font-bold text-emerald-600 dark:text-emerald-400">+ Upload Bukti</Link>
            </div>

            <div class="space-y-3">
                {#each proofTransfers.data as pf}
                    <div class="rounded-3xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-100 dark:border-slate-800 space-y-3">
                        <div class="flex items-start justify-between">
                            <div>
                                <span class="text-xs font-extrabold text-slate-800 dark:text-slate-100">{pf.store_name}</span>
                                <p class="text-[11px] text-slate-400 flex items-center gap-1 mt-0.5">
                                    <Clock class="h-3 w-3" />
                                    Tgl Transfer: {pf.transfer_date}
                                </p>
                            </div>

                            <!-- Status Badge Verification -->
                            <div class="text-right">
                                {#if pf.status === 'approved'}
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-950/60 px-2.5 py-1 text-[10px] font-extrabold text-emerald-700 dark:text-emerald-300 border border-emerald-200">
                                        <CheckCircle2 class="h-3 w-3" />
                                        Disetujui Admin
                                    </span>
                                {:else if pf.status === 'rejected'}
                                    <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 dark:bg-rose-950/60 px-2.5 py-1 text-[10px] font-extrabold text-rose-700 dark:text-rose-300 border border-rose-200">
                                        <AlertCircle class="h-3 w-3" />
                                        Ditolak
                                    </span>
                                {:else}
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 dark:bg-amber-950/60 px-2.5 py-1 text-[10px] font-extrabold text-amber-700 dark:text-amber-300 border border-amber-200">
                                        <Clock class="h-3 w-3" />
                                        Menunggu Verifikasi
                                    </span>
                                {/if}
                            </div>
                        </div>

                        <div class="rounded-2xl bg-slate-50 dark:bg-slate-950 p-3 flex items-center justify-between border border-slate-100 dark:border-slate-800 text-xs">
                            <span class="text-slate-400 font-medium">Nominal Transfer:</span>
                            <span class="font-black text-emerald-600 dark:text-emerald-400 text-sm">{formatRupiah(pf.amount)}</span>
                        </div>

                        {#if pf.notes}
                            <p class="text-[11px] text-slate-400 italic bg-slate-50 dark:bg-slate-950 p-2 rounded-xl">
                                "{pf.notes}"
                            </p>
                        {/if}

                        {#if pf.proof_file}
                            <div class="pt-1 flex justify-end">
                                <a
                                    href="/{pf.proof_file}"
                                    target="_blank"
                                    class="inline-flex items-center gap-1 text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline"
                                >
                                    <FileText class="h-3.5 w-3.5" />
                                    Lihat File Struk Transfer
                                </a>
                            </div>
                        {/if}
                    </div>
                {:else}
                    <div class="rounded-3xl bg-white dark:bg-slate-900 p-8 text-center text-slate-400 border border-slate-100 dark:border-slate-800 text-xs space-y-2">
                        <Wallet class="h-10 w-10 mx-auto text-slate-300 dark:text-slate-700" />
                        <p class="font-bold">Belum Ada Bukti Transfer</p>
                        <p class="text-[11px] text-slate-400">Klik tombol "Upload" untuk memasukkan bukti transfer pembayaran modal ke gudang.</p>
                    </div>
                {/each}
            </div>
        </div>

        <!-- Pagination -->
        {#if proofTransfers.links && proofTransfers.links.length > 3}
            <div class="pt-2 flex justify-center">
                <Pagination links={proofTransfers.links} />
            </div>
        {/if}
    </div>
</SellerMobileLayout>
