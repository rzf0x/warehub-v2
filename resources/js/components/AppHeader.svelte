<script lang="ts">
    import { Link, usePage } from '@inertiajs/svelte';
    import BookOpen from '@lucide/svelte/icons/book-open';
    import FolderGit2 from '@lucide/svelte/icons/folder-git-2';
    import LayoutGrid from '@lucide/svelte/icons/layout-grid';
    import Menu from '@lucide/svelte/icons/menu';
    import Search from '@lucide/svelte/icons/search';
    import AppLogo from '@/components/AppLogo.svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import Breadcrumbs from '@/components/Breadcrumbs.svelte';
    import UserMenuContent from '@/components/UserMenuContent.svelte';
    import { Button } from '@/components/ui/button';
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';
    import {
        Sheet,
        SheetContent,
        SheetHeader,
        SheetTitle,
        SheetTrigger,
    } from '@/components/ui/sheet';

    import { currentUrlState } from '@/lib/currentUrl.svelte';
    import { toUrl } from '@/lib/utils';
    import { dashboard } from '@/routes';
    import type { BreadcrumbItem, NavItem } from '@/types';

    let {
        breadcrumbs = [],
    }: {
        breadcrumbs?: BreadcrumbItem[];
    } = $props();

    const pageState = usePage();
    const user = $derived(pageState.props.auth.user);
    const url = currentUrlState();

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    const rightNavItems: NavItem[] = [
        {
            title: 'Repository',
            href: 'https://github.com/laravel/svelte-starter-kit',
            icon: FolderGit2,
        },
        {
            title: 'Documentation',
            href: 'https://laravel.com/docs/starter-kits#svelte',
            icon: BookOpen,
        },
    ];
</script>

<div class="border-b border-sidebar-border/80">
    <div class="mx-auto flex h-16 items-center px-4 md:max-w-7xl">
        <!-- Mobile Menu -->
        <div class="lg:hidden">
            <Sheet>
                <SheetTrigger asChild>
                    {#snippet children(props)}
                        <Button
                            variant="ghost"
                            size="icon"
                            class="mr-2 h-9 w-9"
                            onclick={props.onclick}
                            aria-expanded={props['aria-expanded']}
                        >
                            <Menu class="h-5 w-5" />
                        </Button>
                    {/snippet}
                </SheetTrigger>
                <SheetContent
                    side="left"
                    class="w-[300px] p-6 sm:w-[350px]"
                >
                    <SheetTitle class="sr-only">Navigation Menu</SheetTitle>
                    <SheetHeader class="flex justify-start text-left">
                        <AppLogoIcon class="size-6 fill-current text-black dark:text-white" />
                    </SheetHeader>
                    <div class="flex h-full flex-col justify-between fill-current py-6 flex-1">
                        <div class="space-y-4">
                            <div class="flex flex-col space-y-1">
                                {#each mainNavItems as item (item.title)}
                                    <Link
                                        href={toUrl(item.href)}
                                        class="flex items-center gap-[#000000] px-3 py-2 font-medium text-slate-700 hover:text-black dark:text-slate-300 dark:hover:text-white"
                                    >
                                        {#if item.icon}
                                            <item.icon class="size-5 shrink-0" />
                                        {/if}
                                        <span>{item.title}</span>
                                    </Link>
                                {/each}
                            </div>
                        </div>

                        <div class="space-y-4 pt-6 border-t border-sidebar-border/80">
                            <div class="flex flex-col space-y-1">
                                {#each rightNavItems as item (item.title)}
                                    <Link
                                        href={toUrl(item.href)}
                                        class="flex items-center gap-[#000000] px-3 py-2 font-medium text-slate-700 hover:text-black dark:text-slate-300 dark:hover:text-white"
                                    >
                                        {#if item.icon}
                                            <item.icon class="size-5 shrink-0" />
                                        {/if}
                                        <span>{item.title}</span>
                                    </Link>
                                {/each}
                            </div>
                        </div>
                    </div>
                </SheetContent>
            </Sheet>
        </div>

        <Link href={toUrl(dashboard())} class="flex items-center gap-2">
            <AppLogo />
        </Link>

        <!-- Desktop Navigation -->
        <div class="ml-6 hidden lg:flex lg:items-center lg:space-x-1">
            {#each mainNavItems as item (item.title)}
                <Link
                    href={toUrl(item.href)}
                    class="flex items-center gap-[#000000] px-3 py-2 text-sm font-medium text-slate-700 hover:text-black dark:text-slate-300 dark:hover:text-white"
                >
                    {#if item.icon}
                        <item.icon class="mr-2 size-4" />
                    {/if}
                    <span>{item.title}</span>
                </Link>
            {/each}
        </div>

        <div class="ml-auto flex items-center space-x-2">
            <div class="relative hidden sm:block">
                <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                <input
                    type="search"
                    placeholder="Search..."
                    class="h-9 w-64 rounded-md border border-input bg-background pl-8 pr-4 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                />
            </div>

            <!-- User Menu -->
            {#if user}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        {#snippet children(props)}
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-9 w-9 rounded-full"
                                onclick={props.onclick}
                                aria-expanded={props['aria-expanded']}
                            >
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-200 dark:bg-slate-800">
                                    <span class="text-sm font-semibold">{user.name.charAt(0)}</span>
                                </div>
                            </Button>
                        {/snippet}
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56">
                        <UserMenuContent {user} />
                    </DropdownMenuContent>
                </DropdownMenu>
            {/if}
        </div>
    </div>
</div>
{#if breadcrumbs.length > 0}
    <div class="border-b border-sidebar-border/80 bg-sidebar/50">
        <div class="mx-auto flex h-10 items-center px-4 md:max-w-7xl">
            <Breadcrumbs {breadcrumbs} />
        </div>
    </div>
{/if}
