<script lang="ts">
    import { onMount } from 'svelte';
    import { usePage, router } from '@inertiajs/svelte';
    import AdminSidebar from '@/components/AdminSidebar.svelte';
    import Toast from '@/components/Toast.svelte';
    import { updateAppearance, initializeTheme } from '@/lib/theme.svelte';
    import {
        Menu,
        User as UserIcon,
        LogOut,
        ChevronRight,
        Home,
        Bell,
        Sun,
        Moon
    } from '@lucide/svelte';

    let {
        title = 'Warehub v2',
        breadcrumbs = [],
        children,
    }: {
        title?: string;
        breadcrumbs?: Array<{ name: string; url?: string }>;
        children?: Snippet;
    } = $props();

    let isSidebarCollapsed = $state(false);
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

    function logout() {
        router.post('/logout');
    }
</script>

<svelte:head>
    <title>{title} - Warehub v2</title>
</svelte:head>

<div class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 font-sans">
    <!-- Sidebar -->
    <AdminSidebar isCollapsed={isSidebarCollapsed} />

    <!-- Main Wrapper -->
    <div class="flex flex-col transition-all duration-300 {isSidebarCollapsed ? 'pl-20' : 'pl-64'} min-h-screen">
        <!-- Topbar Navbar -->
        <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200/80 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 px-6 backdrop-blur-md">
            <div class="flex items-center gap-4">
                <!-- Sidebar Toggle -->
                <button
                    onclick={() => isSidebarCollapsed = !isSidebarCollapsed}
                    class="rounded-xl p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 dark:text-slate-400 transition-colors"
                >
                    <Menu class="h-5 w-5" />
                </button>

                <!-- Breadcrumbs -->
                <nav class="hidden md:flex items-center gap-2 text-sm font-medium text-slate-500 dark:text-slate-400">
                    <a href="/superadmin/dashboard" class="flex items-center gap-1 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                        <Home class="h-4 w-4" />
                        <span>Home</span>
                    </a>
                    {#each breadcrumbs as item}
                        <ChevronRight class="h-4 w-4 text-slate-300 dark:text-slate-600 shrink-0" />
                        {#if item.url}
                            <a href={item.url} class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">{item.name}</a>
                        {:else}
                            <span class="text-slate-800 dark:text-slate-100 font-semibold">{item.name}</span>
                        {/if}
                    {/each}
                </nav>
            </div>

            <!-- Right Header Actions -->
            <div class="flex items-center gap-2 sm:gap-3">
                <!-- Theme Mode Toggle Button -->
                <button
                    onclick={toggleTheme}
                    type="button"
                    title={isDark ? "Beralih ke Mode Terang" : "Beralih ke Mode Gelap"}
                    aria-label="Toggle theme mode"
                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors border border-slate-200/60 dark:border-slate-700/60 shadow-xs cursor-pointer"
                >
                    {#if isDark}
                        <Sun class="h-4 w-4 text-amber-400 fill-amber-400/20" />
                    {:else}
                        <Moon class="h-4 w-4 text-slate-700 dark:text-slate-200 fill-slate-700/20" />
                    {/if}
                </button>

                <button class="relative flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors border border-slate-200/60 dark:border-slate-700/60 shadow-xs">
                    <Bell class="h-4 w-4" />
                    <span class="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-indigo-600"></span>
                </button>

                <!-- User Dropdown -->
                <div class="relative">
                    <button
                        onclick={() => userDropdownOpen = !userDropdownOpen}
                        class="flex items-center gap-3 rounded-xl p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    >
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700 font-bold dark:bg-indigo-950 dark:text-indigo-300">
                            {authUser?.name?.charAt(0).toUpperCase() ?? 'A'}
                        </div>
                        <div class="hidden sm:block text-left">
                            <p class="text-xs font-semibold text-slate-800 dark:text-slate-100 leading-tight">{authUser?.name ?? 'Super Admin'}</p>
                            <p class="text-[10px] text-slate-500 dark:text-slate-400">{authUser?.email ?? 'admin@warehub.test'}</p>
                        </div>
                    </button>

                    {#if userDropdownOpen}
                        <div class="absolute right-0 mt-2 w-56 rounded-2xl bg-white dark:bg-slate-900 p-2 shadow-xl ring-1 ring-slate-900/5 border border-slate-100 dark:border-slate-800 z-50 animate-in fade-in slide-in-from-top-2 duration-200">
                            <div class="px-3 py-2 border-b border-slate-100 dark:border-slate-800 mb-1">
                                <p class="text-xs font-semibold text-slate-800 dark:text-slate-100">{authUser?.name}</p>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">{authUser?.email}</p>
                            </div>
                            <button
                                onclick={logout}
                                class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-xs font-medium text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition-colors"
                            >
                                <LogOut class="h-4 w-4" />
                                <span>Keluar Aplikasi</span>
                            </button>
                        </div>
                    {/if}
                </div>
            </div>
        </header>

        <!-- Main Page Content -->
        <main class="flex-1 w-full p-6 sm:p-8">
            {@render children?.()}
        </main>
    </div>

    <!-- Global Toast Notifier -->
    <Toast />
</div>
