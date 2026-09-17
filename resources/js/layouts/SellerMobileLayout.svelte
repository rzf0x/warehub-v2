<script lang="ts">
    import type { Snippet } from 'svelte';
    import MobileHeader from '@/components/seller/MobileHeader.svelte';
    import MobileBottomNav from '@/components/seller/MobileBottomNav.svelte';
    import Toast from '@/components/Toast.svelte';

    let {
        title = 'Portal Seller',
        activeStoreName = '',
        children,
    }: {
        title?: string;
        activeStoreName?: string;
        children?: Snippet;
    } = $props();
</script>

<svelte:head>
    <title>{title} - Warehub Seller</title>
</svelte:head>

<div class="min-h-screen bg-slate-100 dark:bg-slate-950 text-slate-900 dark:text-slate-100 font-sans antialiased selection:bg-indigo-500 selection:text-white">
    <!-- Centered Mobile Viewport Container -->
    <div class="relative max-w-md mx-auto min-h-screen bg-slate-50 dark:bg-slate-900 shadow-2xl border-x border-slate-200/60 dark:border-slate-800 flex flex-col">
        <!-- Top App Bar Header -->
        <MobileHeader {activeStoreName} />

        <!-- Main Scrollable Mobile Content Area -->
        <main class="flex-1 w-full px-4 py-5 pb-24 overflow-x-hidden">
            {@render children?.()}
        </main>

        <!-- Fixed Bottom Navigation Bar -->
        <MobileBottomNav />

        <!-- Toast Notifications -->
        <Toast />
    </div>
</div>
