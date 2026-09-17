<script lang="ts">
    import SellerMobileLayout from '@/layouts/SellerMobileLayout.svelte';
    import { useForm, Link } from '@inertiajs/svelte';
    import {
        Upload,
        Building2,
        Copy,
        Check,
        ArrowLeft,
        Camera,
        Image as ImageIcon,
        X,
        AlertCircle,
        Coins,
        Calendar,
        Store as StoreIcon,
        FileText,
        Loader2
    } from '@lucide/svelte';

    interface StoreItem {
        id: number;
        store_name: string;
        marketplace: string;
    }

    let {
        stores = [],
        netDebt = 0,
        bankInfo = {
            bank_name: 'BCA (Bank Central Asia)',
            account_number: '8410928192',
            account_holder: 'AA Gym Konveksi Gudang',
        },
    }: {
        stores?: StoreItem[];
        netDebt?: number;
        bankInfo?: { bank_name: string; account_number: string; account_holder: string };
    } = $props();

    // Today's date string format YYYY-MM-DD
    const today = new Date().toISOString().split('T')[0];

    const form = useForm({
        store_id: stores.length > 0 ? (stores[0].id as number | null) : null,
        amount: netDebt > 0 ? netDebt : 0,
        transfer_date: today,
        proof_file: null as File | null,
        notes: '',
    });

    let copied = $state(false);
    let previewUrl = $state<string | null>(null);
    let fileInputRef = $state<HTMLInputElement | null>(null);

    function formatRupiah(num: number): string {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num || 0);
    }

    function copyAccount() {
        navigator.clipboard.writeText(bankInfo.account_number);
        copied = true;
        setTimeout(() => (copied = false), 2000);
    }

    function setFullAmount() {
        form.amount = netDebt;
    }

    function addAmount(delta: number) {
        form.amount = (Number(form.amount) || 0) + delta;
    }

    function handleFileChange(event: Event) {
        const input = event.target as HTMLInputElement;
        if (input.files && input.files[0]) {
            const file = input.files[0];
            form.proof_file = file;

            if (file.type.startsWith('image/')) {
                previewUrl = URL.createObjectURL(file);
            } else {
                previewUrl = null;
            }
        }
    }

    function clearFile() {
        form.proof_file = null;
        previewUrl = null;
        if (fileInputRef) {
            fileInputRef.value = '';
        }
    }

    function handleSubmit(e: Event) {
        e.preventDefault();
        form.post('/seller/debt/settlement', {
            forceFormData: true,
        });
    }
</script>

<SellerMobileLayout title="Form Upload Bukti Setoran">
    <div class="space-y-4 pb-6">
        <!-- Top Navigation Link -->
        <div class="flex items-center gap-3">
            <Link
                href="/seller/debt"
                class="inline-flex h-9 w-9 items-center justify-center rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300 shadow-xs hover:bg-slate-50 transition-colors"
            >
                <ArrowLeft class="h-5 w-5" />
            </Link>
            <div>
                <h1 class="text-xl font-black text-slate-800 dark:text-slate-100 tracking-tight">Upload Bukti Transfer</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Setor tagihan modal HPP ke gudang</p>
            </div>
        </div>

        <!-- Bank Destination Account Card -->
        <div class="rounded-3xl bg-gradient-to-br from-indigo-900 via-slate-900 to-indigo-950 p-5 text-white shadow-xl space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Building2 class="h-5 w-5 text-indigo-300" />
                    <span class="text-xs font-bold text-indigo-200">{bankInfo.bank_name}</span>
                </div>
                <span class="text-[10px] text-indigo-300/80 font-medium">Rekening Resmi</span>
            </div>

            <div>
                <span class="text-[10px] text-indigo-300/80 uppercase font-semibold block">Nomor Rekening Tujuan:</span>
                <div class="flex items-center justify-between mt-1">
                    <span class="font-mono text-xl font-black text-amber-300 tracking-wider">{bankInfo.account_number}</span>
                    <button
                        type="button"
                        onclick={copyAccount}
                        class="inline-flex items-center gap-1 rounded-xl bg-white/10 hover:bg-white/20 px-3 py-1.5 text-xs font-bold text-white transition-colors backdrop-blur-md"
                    >
                        {#if copied}
                            <Check class="h-3.5 w-3.5 text-emerald-400" />
                            <span class="text-emerald-300">Tersalin!</span>
                        {:else}
                            <Copy class="h-3.5 w-3.5" />
                            <span>Salin</span>
                        {/if}
                    </button>
                </div>
            </div>

            <div class="pt-2 border-t border-white/10 flex items-center justify-between text-xs text-indigo-200">
                <span>Atas Nama:</span>
                <span class="font-bold text-white">{bankInfo.account_holder}</span>
            </div>
        </div>

        <!-- Net Debt Quick Preset Card -->
        {#if netDebt > 0}
            <div class="rounded-3xl bg-amber-50 dark:bg-amber-950/40 p-4 border border-amber-200 dark:border-amber-900/60 flex items-center justify-between">
                <div>
                    <span class="text-[10px] uppercase font-bold tracking-wider text-amber-700 dark:text-amber-400 block">Total Tagihan Modal Net</span>
                    <span class="text-base font-black text-amber-900 dark:text-amber-200">{formatRupiah(netDebt)}</span>
                </div>

                <button
                    type="button"
                    onclick={setFullAmount}
                    class="rounded-xl bg-amber-500 hover:bg-amber-600 px-3 py-2 text-xs font-bold text-white shadow-xs transition-colors"
                >
                    Bayar Lunas
                </button>
            </div>
        {/if}

        <!-- Upload Form -->
        <form onSubmit={handleSubmit} class="space-y-4">
            <div class="rounded-3xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-100 dark:border-slate-800 space-y-4">
                <!-- Nominal Input -->
                <div class="space-y-1.5">
                    <label for="amount" class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                        <Coins class="h-4 w-4 text-emerald-500" />
                        Nominal Transfer (Rp) <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 font-bold text-slate-400 text-sm">Rp</span>
                        <input
                            id="amount"
                            type="number"
                            min="1000"
                            step="1000"
                            bind:value={form.amount}
                            placeholder="0"
                            class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 pl-10 pr-4 py-3 text-sm font-black text-slate-800 dark:text-slate-100 focus:border-indigo-500 focus:bg-white focus:outline-hidden"
                            required
                        />
                    </div>
                    {#if form.errors.amount}
                        <p class="text-xs text-rose-500 font-semibold">{form.errors.amount}</p>
                    {/if}

                    <!-- Quick Preset Buttons -->
                    <div class="flex items-center gap-1.5 pt-1 overflow-x-auto no-scrollbar">
                        <button
                            type="button"
                            onclick={() => addAmount(50000)}
                            class="shrink-0 rounded-xl bg-slate-100 dark:bg-slate-800 px-2.5 py-1 text-[11px] font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-200"
                        >
                            +50rb
                        </button>
                        <button
                            type="button"
                            onclick={() => addAmount(100000)}
                            class="shrink-0 rounded-xl bg-slate-100 dark:bg-slate-800 px-2.5 py-1 text-[11px] font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-200"
                        >
                            +100rb
                        </button>
                        <button
                            type="button"
                            onclick={() => addAmount(500000)}
                            class="shrink-0 rounded-xl bg-slate-100 dark:bg-slate-800 px-2.5 py-1 text-[11px] font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-200"
                        >
                            +500rb
                        </button>
                        <button
                            type="button"
                            onclick={() => addAmount(1000000)}
                            class="shrink-0 rounded-xl bg-slate-100 dark:bg-slate-800 px-2.5 py-1 text-[11px] font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-200"
                        >
                            +1jt
                        </button>
                    </div>
                </div>

                <!-- Store Selector -->
                {#if stores.length > 0}
                    <div class="space-y-1.5">
                        <label for="store_id" class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                            <StoreIcon class="h-4 w-4 text-sky-500" />
                            Pilih Toko Terkait (Opsional)
                        </label>
                        <select
                            id="store_id"
                            bind:value={form.store_id}
                            class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-3 text-xs font-bold text-slate-800 dark:text-slate-100 focus:border-indigo-500 focus:outline-hidden"
                        >
                            <option value={null}>-- Semua Toko / Umum --</option>
                            {#each stores as store}
                                <option value={store.id}>{store.store_name} ({store.marketplace})</option>
                            {/each}
                        </select>
                    </div>
                {/if}

                <!-- Date Input -->
                <div class="space-y-1.5">
                    <label for="transfer_date" class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                        <Calendar class="h-4 w-4 text-indigo-500" />
                        Tanggal Transfer <span class="text-rose-500">*</span>
                    </label>
                    <input
                        id="transfer_date"
                        type="date"
                        bind:value={form.transfer_date}
                        class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-3 text-xs font-bold text-slate-800 dark:text-slate-100 focus:border-indigo-500 focus:outline-hidden"
                        required
                    />
                    {#if form.errors.transfer_date}
                        <p class="text-xs text-rose-500 font-semibold">{form.errors.transfer_date}</p>
                    {/if}
                </div>

                <!-- Camera / Proof Transfer Upload Input (Mobile First) -->
                <div class="space-y-1.5">
                    <label for="proof_file" class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center justify-between">
                        <span class="flex items-center gap-1.5">
                            <Camera class="h-4 w-4 text-amber-500" />
                            Bukti Transfer (Foto / PDF) <span class="text-rose-500">*</span>
                        </span>
                        <span class="text-[10px] text-slate-400 font-normal">Maks 5MB</span>
                    </label>

                    <input
                        bind:this={fileInputRef}
                        id="proof_file"
                        type="file"
                        accept="image/*,application/pdf"
                        capture="environment"
                        onchange={handleFileChange}
                        class="hidden"
                    />

                    {#if previewUrl}
                        <!-- Image Preview Box -->
                        <div class="relative rounded-2xl overflow-hidden border border-slate-200 dark:border-slate-800 bg-slate-950 max-h-56 flex items-center justify-center">
                            <img src={previewUrl} alt="Preview Struk Transfer" class="object-contain max-h-56 w-full" />
                            <button
                                type="button"
                                onclick={clearFile}
                                class="absolute top-2 right-2 rounded-full bg-slate-900/80 p-1.5 text-white hover:bg-rose-600 transition-colors"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                    {:else if form.proof_file}
                        <!-- File Uploaded (Non-image e.g. PDF) -->
                        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 p-4 flex items-center justify-between">
                            <div class="flex items-center gap-2 overflow-hidden">
                                <FileText class="h-5 w-5 text-indigo-500 shrink-0" />
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-300 truncate">{form.proof_file.name}</span>
                            </div>
                            <button
                                type="button"
                                onclick={clearFile}
                                class="rounded-full bg-rose-100 p-1.5 text-rose-600 hover:bg-rose-200 transition-colors shrink-0"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                    {:else}
                        <!-- Mobile Camera Trigger Upload Box -->
                        <button
                            type="button"
                            onclick={() => fileInputRef?.click()}
                            class="w-full rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/50 p-6 text-center hover:border-indigo-500 dark:hover:border-indigo-500 transition-colors space-y-2 group"
                        >
                            <div class="mx-auto h-12 w-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400 group-hover:scale-110 transition-transform">
                                <Camera class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-xs font-extrabold text-slate-700 dark:text-slate-200">Ambil Foto Struk / Pilih File</p>
                                <p class="text-[11px] text-slate-400">Klik di sini untuk aktifkan kamera HP / Galeri</p>
                            </div>
                        </button>
                    {/if}

                    {#if form.errors.proof_file}
                        <p class="text-xs text-rose-500 font-semibold">{form.errors.proof_file}</p>
                    {/if}
                </div>

                <!-- Notes Input -->
                <div class="space-y-1.5">
                    <label for="notes" class="text-xs font-bold text-slate-700 dark:text-slate-300">
                        Catatan Tambahan (Opsional)
                    </label>
                    <textarea
                        id="notes"
                        bind:value={form.notes}
                        rows="2"
                        placeholder="Contoh: Transfer pelunasan batch PO minggu ini via m-BCA"
                        class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-3 text-xs text-slate-800 dark:text-slate-100 focus:border-indigo-500 focus:outline-hidden"
                    ></textarea>
                </div>
            </div>

            <!-- Submit Button -->
            <button
                type="submit"
                disabled={form.processing}
                class="w-full rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 py-3.5 px-4 text-sm font-extrabold text-white shadow-lg shadow-emerald-600/30 hover:brightness-110 active:scale-[0.98] transition-all flex items-center justify-center gap-2 disabled:opacity-50"
            >
                {#if form.processing}
                    <Loader2 class="h-5 w-5 animate-spin" />
                    <span>Mengirim Bukti Transfer...</span>
                {:else}
                    <Upload class="h-5 w-5" />
                    <span>Kirim Bukti Transfer Sekarang</span>
                {/if}
            </button>
        </form>
    </div>
</SellerMobileLayout>
