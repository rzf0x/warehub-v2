<script lang="ts">
    import { usePage, Link } from '@inertiajs/svelte';
    import {
        Home,
        Boxes,
        ClipboardList,
        TrendingUp,
        Wallet
    } from '@lucide/svelte';

    const pageState = usePage();
    let currentUrl = $derived(pageState.url);

    function isActive(path: string): boolean {
        if (path === '/seller/dashboard') {
            return currentUrl === '/seller/dashboard' || currentUrl === '/seller';
        }
        return currentUrl.startsWith(path);
    }

    const navItems = [
        { name: 'Home', href: '/seller/dashboard', icon: Home },
        { name: 'Stok', href: '/seller/stocks', icon: Boxes },
        { name: 'PO Restock', href: '/seller/purchase-orders', icon: ClipboardList },
        { name: 'Penjualan', href: '/seller/sales', icon: TrendingUp },
        { name: 'Hutang', href: '/seller/debt', icon: Wallet },
    ];
</script>

<nav class="fixed bottom-0 left-0 right-0 z-40 flex h-16 w-full max-w-md mx-auto items-center justify-around border-t border-slate-200/80 dark:border-slate-800 bg-white/95 dark:bg-slate-900/95 px-2 backdrop-blur-lg transition-all shadow-lg">
    {#each navItems as item}
        {@const Icon = item.icon}
        {@const active = isActive(item.href)}
        <Link
            href={item.href}
            class="flex flex-1 flex-col items-center justify-center py-1 text-center transition-all duration-200 group relative"
        >
            <div
                class="flex h-8 w-12 items-center justify-center rounded-2xl transition-all duration-200
                {active
                    ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30 scale-105'
                    : 'text-slate-500 dark:text-slate-400 group-hover:bg-slate-100 dark:group-hover:bg-slate-800/60'}"
            >
                <Icon class="h-5 w-5 stroke-[2.2]" />
            </div>

            <span
                class="text-[10px] font-semibold mt-0.5 tracking-tight transition-colors
                {active
                    ? 'text-indigo-600 dark:text-indigo-400 font-bold'
                    : 'text-slate-500 dark:text-slate-400'}"
            >
                {item.name}
            </span>

            {#if active}
                <span class="absolute -top-0.5 h-1 w-6 rounded-full bg-indigo-600 dark:bg-indigo-400"></span>
            {/if}
        </Link>
    {/each}
</nav>
