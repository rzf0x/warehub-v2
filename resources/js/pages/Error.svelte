<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { Compass, ArrowLeft, Home, Package, Calendar, SearchX, RefreshCw } from '@lucide/svelte';

    let { status = 404 }: { status?: number } = $props();

    const titleMap: Record<number, string> = {
        404: 'Halaman Tidak Ditemukan',
        403: 'Akses Ditolak',
        500: 'Terjadi Kesalahan Server',
        503: 'Layanan Dalam Pemeliharaan',
    };

    const descriptionMap: Record<number, string> = {
        404: 'Waduh! Halaman yang Anda cari tidak ditemukan, telah dihapus, atau alamat URL yang Anda masukkan salah.',
        403: 'Maaf, akun Anda tidak memiliki hak akses yang cukup untuk membuka halaman ini.',
        500: 'Maaf, sistem mengalami kendala internal di server. Tim kami akan segera menanganinya.',
        503: 'Sistem sedang dalam proses pemeliharaan rutin. Silakan kembali dalam beberapa saat lagi.',
    };

    const title = $derived(titleMap[status] || 'Terjadi Kesalahan');
    const description = $derived(descriptionMap[status] || 'Terjadi kesalahan tidak terduga saat memuat halaman.');

    function goBack() {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = '/dashboard';
        }
    }
</script>

<svelte:head>
    <title>{status} - {title}</title>
</svelte:head>

<div class="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-4 relative overflow-hidden font-sans">
    <!-- Ambient Background Glows -->
    <div class="absolute -top-40 -left-40 w-96 h-96 bg-indigo-600/20 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-purple-600/20 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-sky-500/10 rounded-full blur-3xl pointer-events-none"></div>

    <!-- Grid Accent Lines Pattern -->
    <div class="absolute inset-0 bg-[linear-gradient(to_right,#1e293b15_1px,transparent_1px),linear-gradient(to_bottom,#1e293b15_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_50%,#000_70%,transparent_100%)] pointer-events-none"></div>

    <div class="max-w-2xl w-full text-center relative z-10 space-y-8 py-12 px-6 rounded-3xl bg-slate-900/60 border border-slate-800/80 backdrop-blur-2xl shadow-2xl">
        <!-- Big Animated Status Code Header -->
        <div class="relative inline-block">
            <h1 class="text-8xl sm:text-9xl font-extrabold tracking-widest text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 via-purple-400 to-pink-400 select-none drop-shadow-lg animate-pulse">
                {status}
            </h1>
            <div class="absolute inset-0 flex items-center justify-center opacity-10 blur-xl text-9xl font-black text-indigo-400 select-none">
                {status}
            </div>
        </div>

        <!-- Floating Icon Accent -->
        <div class="flex justify-center">
            <div class="inline-flex items-center justify-center p-4 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 shadow-inner">
                {#if status === 404}
                    <SearchX class="h-10 w-10 animate-bounce" />
                {:else if status === 403}
                    <Compass class="h-10 w-10" />
                {:else}
                    <RefreshCw class="h-10 w-10 animate-spin" />
                {/if}
            </div>
        </div>

        <!-- Text Title & Description -->
        <div class="space-y-3 max-w-lg mx-auto">
            <h2 class="text-2xl sm:text-3xl font-bold text-white tracking-tight">
                {title}
            </h2>
            <p class="text-sm sm:text-base text-slate-400 leading-relaxed">
                {description}
            </p>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2">
            <button
                type="button"
                onclick={goBack}
                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700/80 px-6 py-3 text-sm font-semibold transition-all shadow-md active:scale-95"
            >
                <ArrowLeft class="h-4 w-4" />
                <span>Kembali ke Halaman Sebelumnya</span>
            </button>

            <Link
                href="/dashboard"
                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white px-6 py-3 text-sm font-semibold shadow-lg shadow-indigo-600/30 transition-all active:scale-95"
            >
                <Home class="h-4 w-4" />
                <span>Kembali ke Dashboard Utama</span>
            </Link>
        </div>

        <!-- Quick Links Suggestion Section -->
        <div class="pt-8 border-t border-slate-800/80">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-4">Akses Cepat Halaman Populer</span>
            <div class="flex flex-wrap items-center justify-center gap-2">
                <Link
                    href="/superadmin/warehouse/products"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-slate-800/50 hover:bg-slate-800 px-3 py-1.5 text-xs font-medium text-slate-300 border border-slate-700/50 transition-colors"
                >
                    <Package class="h-3.5 w-3.5 text-indigo-400" />
                    <span>Katalog Produk</span>
                </Link>
                <Link
                    href="/superadmin/warehouse/periods"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-slate-800/50 hover:bg-slate-800 px-3 py-1.5 text-xs font-medium text-slate-300 border border-slate-700/50 transition-colors"
                >
                    <Calendar class="h-3.5 w-3.5 text-purple-400" />
                    <span>Periode Pembukuan</span>
                </Link>
                <Link
                    href="/superadmin/warehouse/mapping/manual-global"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-slate-800/50 hover:bg-slate-800 px-3 py-1.5 text-xs font-medium text-slate-300 border border-slate-700/50 transition-colors"
                >
                    <Compass class="h-3.5 w-3.5 text-orange-400" />
                    <span>Report Global Product</span>
                </Link>
            </div>
        </div>
    </div>
</div>
