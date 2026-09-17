<script lang="ts">
    import { usePage, router, Link } from '@inertiajs/svelte';
    import { updateAppearance, initializeTheme } from '@/lib/theme.svelte';
    import { onMount } from 'svelte';
    import {
        Bell,
        Sun,
        Moon,
        LogOut,
        Store,
        ChevronDown,
        User,
        Boxes,
        Sparkles
    } from '@lucide/svelte';

    let {
        activeStoreName = '',
    }: {
        activeStoreName?: string;
    } = $props();

    let userDropdownOpen = $state(false);
    let isDark = $state(false);

    onMount(() => {
        initializeTheme();
        checkTheme();
    });

    function checkTheme() {
        if (typeof document !== 'undefined') {
            isDark = document.documentElement.classList.contains('dark');
        }
    }

    function toggleTheme() {
        const nextMode = isDark ? 'light' : 'dark';
        updateAppearance(nextMode);
        checkTheme();
    }

    const pageState = usePage();
    let authUser = $derived(pageState.props.auth?.user as { name: string; email: string } | undefined);
    let sellerName = $derived(pageState.props.seller?.seller_name as string | undefined ?? authUser?.name ?? 'Seller');

    function logout() {
        router.post('/logout');
    }
</script>

<header class="sticky top-0 z-40 flex h-16 w-full items-center justify-between border-b border-slate-200/80 dark:border-slate-800 bg-white/90 dark:bg-slate-900/90 px-4 backdrop-blur-md transition-all">
    <!-- Left: Brand / Seller Profile Header -->
    <div class="flex items-center gap-3">
        <Link href="/seller/dashboard" class="flex items-center gap-2.5">
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-tr from-indigo-600 to-indigo-500 text-white font-bold shadow-md shadow-indigo-600/30">
                <Boxes class="h-5 w-5" />
            </div>
            <div class="flex flex-col">
                <span class="text-xs font-bold leading-tight text-slate-800 dark:text-slate-100 flex items-center gap-1">
                    {sellerName}
                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                </span>
                <span class="text-[10px] font-medium text-slate-400 dark:text-slate-500 flex items-center gap-1">
                    <Store class="h-3 w-3 text-indigo-500" />
                    {activeStoreName || 'Portal Seller Warehub'}
                </span>
            </div>
        </Link>
    </div>

    <!-- Right: Actions & Profile -->
    <div class="flex items-center gap-2">
        <!-- Theme Mode Toggle -->
        <button
            onclick={toggleTheme}
            type="button"
            title={isDark ? "Modus Terang" : "Modus Gelap"}
            class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors border border-slate-200/60 dark:border-slate-700/60 shadow-2xs"
        >
            {#if isDark}
                <Sun class="h-4 w-4 text-amber-400 fill-amber-400/20" />
            {:else}
                <Moon class="h-4 w-4 text-slate-700 dark:text-slate-200 fill-slate-700/20" />
            {/if}
        </button>

        <!-- Notification Bell -->
        <button
            type="button"
            class="relative flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors border border-slate-200/60 dark:border-slate-700/60 shadow-2xs"
        >
            <Bell class="h-4 w-4 text-slate-600 dark:text-slate-300" />
            <span class="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-rose-500 ring-2 ring-white dark:ring-slate-900"></span>
        </button>

        <!-- Profile Menu Dropdown -->
        <div class="relative">
            <button
                type="button"
                onclick={() => userDropdownOpen = !userDropdownOpen}
                class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700 font-bold dark:bg-indigo-950 dark:text-indigo-300 border border-indigo-200/60 dark:border-indigo-900/60 shadow-2xs hover:opacity-90 transition-opacity"
            >
                {authUser?.name?.charAt(0).toUpperCase() ?? 'S'}
            </button>

            {#if userDropdownOpen}
                <!-- Backdrop for dropdown -->
                <div 
                    class="fixed inset-0 z-40" 
                    onclick={() => userDropdownOpen = false} 
                    role="button" 
                    tabindex="-1"
                ></div>

                <div class="absolute right-0 mt-2 w-56 rounded-2xl bg-white dark:bg-slate-900 p-2 shadow-2xl ring-1 ring-slate-900/5 border border-slate-100 dark:border-slate-800 z-50 animate-in fade-in slide-in-from-top-2 duration-150">
                    <div class="px-3 py-2 border-b border-slate-100 dark:border-slate-800 mb-1">
                        <p class="text-xs font-bold text-slate-800 dark:text-slate-100">{sellerName}</p>
                        <p class="text-[10px] text-slate-500 dark:text-slate-400 truncate">{authUser?.email}</p>
                    </div>

                    <Link
                        href="/seller/stores"
                        class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        onclick={() => userDropdownOpen = false}
                    >
                        <Store class="h-4 w-4 text-indigo-500" />
                        <span>Manajemen Toko</span>
                    </Link>

                    <button
                        onclick={logout}
                        type="button"
                        class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-xs font-medium text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition-colors"
                    >
                        <LogOut class="h-4 w-4" />
                        <span>Keluar Akun</span>
                    </button>
                </div>
            {/if}
        </div>
    </div>
</header>
