<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import LayoutGrid from '@lucide/svelte/icons/layout-grid';
    import Users from '@lucide/svelte/icons/users';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import KeyRound from '@lucide/svelte/icons/key-round';
    import ShoppingBag from '@lucide/svelte/icons/shopping-bag';
    import UserCheck from '@lucide/svelte/icons/user-check';
    import type { Snippet } from 'svelte';
    import AppLogo from '@/components/AppLogo.svelte';
    import NavMain from '@/components/NavMain.svelte';
    import NavUser from '@/components/NavUser.svelte';
    import {
        Sidebar,
        SidebarContent,
        SidebarFooter,
        SidebarHeader,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar';
    import { toUrl } from '@/lib/utils';
    import type { NavItem } from '@/types';

    let {
        children,
    }: {
        children?: Snippet;
    } = $props();

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard Superadmin',
            href: '/superadmin/dashboard',
            icon: LayoutGrid,
        },
        {
            title: 'Data Users',
            href: '/superadmin/user-management/users',
            icon: Users,
        },
        {
            title: 'Roles System',
            href: '/superadmin/user-management/roles',
            icon: ShieldCheck,
        },
        {
            title: 'Permissions',
            href: '/superadmin/user-management/permissions',
            icon: KeyRound,
        },
        {
            title: 'Toko Marketplace',
            href: '/superadmin/user-management/stores',
            icon: ShoppingBag,
        },
        {
            title: 'Tim Pengadaan',
            href: '/superadmin/user-management/pengadaan',
            icon: UserCheck,
        },
    ];
</script>

<Sidebar collapsible="icon" variant="inset">
    <SidebarHeader>
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton size="lg" asChild>
                    {#snippet children(props)}
                        <Link
                            {...props}
                            href="/superadmin/dashboard"
                            class={props.class}
                        >
                            <AppLogo />
                        </Link>
                    {/snippet}
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
        <NavMain items={mainNavItems} />
    </SidebarContent>

    <SidebarFooter>
        <NavUser />
    </SidebarFooter>
</Sidebar>
{@render children?.()}
