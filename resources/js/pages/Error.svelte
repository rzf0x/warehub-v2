<script lang="ts">
    import { Link } from "@inertiajs/svelte";
    import {
        Compass,
        ArrowLeft,
        Home,
        Package,
        Calendar,
        SearchX,
        RefreshCw,
    } from "@lucide/svelte";

    let { status = 404 }: { status?: number } = $props();

    const titleMap: Record<number, string> = {
        404: "Halaman Tidak Ditemukan",
        403: "Akses Ditolak",
        500: "Terjadi Kesalahan Server",
        503: "Layanan Dalam Pemeliharaan",
    };

    const descriptionMap: Record<number, string> = {
        404: "Waduh! Halaman yang Anda cari tidak ditemukan, telah dihapus, atau alamat URL yang Anda masukkan salah.",
        403: "Maaf, akun Anda tidak memiliki hak akses yang cukup untuk membuka halaman ini.",
        500: "Maaf, sistem mengalami kendala internal di server. Tim kami akan segera menanganinya.",
        503: "Sistem sedang dalam proses pemeliharaan rutin. Silakan kembali dalam beberapa saat lagi.",
    };

    const title = $derived(titleMap[status] || "Terjadi Kesalahan");
    const description = $derived(
        descriptionMap[status] ||
            "Terjadi kesalahan tidak terduga saat memuat halaman.",
    );

    function goBack() {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = "/dashboard";
        }
    }
</script>

<svelte:head>
    <title>{status} - {title}</title>
</svelte:head>

<div
    class="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-4 relative overflow-hidden font-sans"
>
    <!-- Ambient Background Glows -->
    <div
        class="absolute -top-40 -left-40 w-96 h-96 bg-indigo-600/20 rounded-full blur-3xl pointer-events-none"
    ></div>
    <div
        class="absolute -bottom-40 -right-40 w-96 h-96 bg-purple-600/20 rounded-full blur-3xl pointer-events-none"
    ></div>
    <div
        class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-sky-500/10 rounded-full blur-3xl pointer-events-none"
    ></div>

    <!-- Grid Accent Lines Pattern -->
    <div
        class="absolute inset-0 bg-[linear-gradient(to_right,#1e293b15_1px,transparent_1px),linear-gradient(to_bottom,#1e293b15_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_50%,#000_70%,transparent_100%)] pointer-events-none"
    ></div>

    <div
        class="max-w-2xl w-full text-center relative z-10 space-y-8 py-12 px-6 rounded-3xl bg-slate-900/60 border border-slate-800/80 backdrop-blur-2xl shadow-2xl"
    >
        <!-- Big Animated Status Code Header -->
        <div class="relative inline-block">
            <h1
                class="text-8xl sm:text-9xl font-extrabold tracking-widest text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 via-purple-400 to-pink-400 select-none drop-shadow-lg animate-pulse"
            >
                {status}
            </h1>
            <div
                class="absolute inset-0 flex items-center justify-center opacity-10 blur-xl text-9xl font-black text-indigo-400 select-none"
            >
                {status}
            </div>
        </div>

        <!-- Floating Icon Accent -->
        <div class="flex justify-center">
            <div
                class="inline-flex items-center justify-center p-4 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 shadow-inner"
            >
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
            <h2
                class="text-2xl sm:text-3xl font-bold text-white tracking-tight"
            >
                {title}
            </h2>
            <p class="text-sm sm:text-base text-slate-400 leading-relaxed">
                {description}
            </p>
        </div>
    </div>
</div>
