<script lang="ts">
    import { usePage, Link } from "@inertiajs/svelte";
    import {
        LayoutDashboard,
        Users,
        ShieldCheck,
        KeyRound,
        ShoppingBag,
        UserCheck,
        Boxes,
        ChevronDown,
        Package,
        Building2,
        Tag,
        Layers,
        Calendar,
        Factory,
        FileSpreadsheet,
        ArrowDownLeft,
        ArrowUpRight,
        ClipboardCheck,
        Activity,
        Grid,
        Store,
        DollarSign,
        Wallet,
        FileText,
        Receipt,
        Settings,
        Database,
        Sparkles,
        BookOpen,
        PieChart,
        FileCheck,
        Video,
        TrendingUp,
    } from "@lucide/svelte";

    let { isCollapsed = false }: { isCollapsed?: boolean } = $props();

    let userManagementOpen = $state(true);
    let warehouseMenuOpen = $state(true);
    let mutationsMenuOpen = $state(true);
    let financeMenuOpen = $state(true);
    let dataCleaningMenuOpen = $state(true);
    let settingsMenuOpen = $state(true);

    const pageState = usePage();
    let currentUrl = $derived(pageState.url);

    function isActive(path: string): boolean {
        return currentUrl.startsWith(path);
    }
</script>

<aside
    class="fixed inset-y-0 left-0 z-40 flex flex-col bg-slate-900 text-slate-300 transition-all duration-300 border-r border-slate-800
    {isCollapsed ? 'w-20' : 'w-64'}"
>
    <!-- Brand Logo -->
    <div
        class="flex h-16 items-center justify-between px-6 border-b border-slate-800"
    >
        <Link href="/superadmin/dashboard" class="flex items-center gap-3">
            <div
                class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-600 text-white font-bold shadow-lg shadow-indigo-600/30"
            >
                <Boxes class="h-5 w-5" />
            </div>
            {#if !isCollapsed}
                <span class="text-lg font-bold tracking-tight text-white"
                    >Warehub <span class="text-indigo-400">v2</span></span
                >
            {/if}
        </Link>
    </div>

    <!-- Navigation Menu -->
    <div class="flex-1 overflow-y-auto px-3 py-6 space-y-1">
        <!-- Dashboard -->
        <Link
            href="/superadmin/dashboard"
            class="flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium transition-all
            {isActive('/superadmin/dashboard')
                ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                : 'hover:bg-slate-800/80 hover:text-white'}"
        >
            <LayoutDashboard class="h-5 w-5 shrink-0" />
            {#if !isCollapsed}
                <span>Dashboard</span>
            {/if}
        </Link>

        <!-- User Management Dropdown Header -->
        <div class="pt-4">
            {#if !isCollapsed}
                <button
                    onclick={() => (userManagementOpen = !userManagementOpen)}
                    class="flex w-full items-center justify-between px-3 py-2 text-xs font-semibold uppercase tracking-wider text-slate-500 hover:text-slate-300 transition-colors"
                >
                    <span>Manajemen User</span>
                    <ChevronDown
                        class="h-4 w-4 transition-transform duration-200 {userManagementOpen
                            ? 'rotate-180'
                            : ''}"
                    />
                </button>
            {/if}

            {#if userManagementOpen || isCollapsed}
                <div class="space-y-1 mt-1">
                    <!-- Users -->
                    <Link
                        href="/superadmin/user-management/users"
                        class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all
                        {isActive('/superadmin/user-management/users')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'hover:bg-slate-800/80 hover:text-white'}"
                    >
                        <Users class="h-5 w-5 shrink-0 text-indigo-400" />
                        {#if !isCollapsed}
                            <span>Data Users</span>
                        {/if}
                    </Link>

                    <!-- Roles -->
                    <Link
                        href="/superadmin/user-management/roles"
                        class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all
                        {isActive('/superadmin/user-management/roles')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'hover:bg-slate-800/80 hover:text-white'}"
                    >
                        <ShieldCheck
                            class="h-5 w-5 shrink-0 text-emerald-400"
                        />
                        {#if !isCollapsed}
                            <span>Roles</span>
                        {/if}
                    </Link>

                    <!-- Permissions -->
                    <Link
                        href="/superadmin/user-management/permissions"
                        class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all
                        {isActive('/superadmin/user-management/permissions')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'hover:bg-slate-800/80 hover:text-white'}"
                    >
                        <KeyRound class="h-5 w-5 shrink-0 text-amber-400" />
                        {#if !isCollapsed}
                            <span>Permissions</span>
                        {/if}
                    </Link>

                    <!-- Toko Marketplace -->
                    <Link
                        href="/superadmin/user-management/stores"
                        class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all
                        {isActive('/superadmin/user-management/stores')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'hover:bg-slate-800/80 hover:text-white'}"
                    >
                        <ShoppingBag class="h-5 w-5 shrink-0 text-sky-400" />
                        {#if !isCollapsed}
                            <span>Toko Marketplace</span>
                        {/if}
                    </Link>

                    <!-- Tim Pengadaan -->
                    <Link
                        href="/superadmin/user-management/pengadaan"
                        class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all
                        {isActive('/superadmin/user-management/pengadaan')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'hover:bg-slate-800/80 hover:text-white'}"
                    >
                        <UserCheck class="h-5 w-5 shrink-0 text-rose-400" />
                        {#if !isCollapsed}
                            <span>Tim Pengadaan</span>
                        {/if}
                    </Link>
                </div>
            {/if}
        </div>

        <!-- Master Gudang & Katalog Produk Section -->
        <div class="pt-4">
            {#if !isCollapsed}
                <button
                    onclick={() => (warehouseMenuOpen = !warehouseMenuOpen)}
                    class="flex w-full items-center justify-between px-3 py-2 text-xs font-semibold uppercase tracking-wider text-slate-500 hover:text-slate-300 transition-colors"
                >
                    <span>Gudang & Katalog</span>
                    <ChevronDown
                        class="h-4 w-4 transition-transform duration-200 {warehouseMenuOpen
                            ? 'rotate-180'
                            : ''}"
                    />
                </button>
            {/if}

            {#if warehouseMenuOpen || isCollapsed}
                <div class="space-y-1 mt-1">
                    <!-- Katalog Produk -->
                    <Link
                        href="/superadmin/warehouse/products"
                        class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all
                        {isActive('/superadmin/warehouse/products') &&
                        !isActive('/superadmin/warehouse/products/by-seller')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'hover:bg-slate-800/80 hover:text-white'}"
                    >
                        <Package class="h-5 w-5 shrink-0 text-indigo-400" />
                        {#if !isCollapsed}
                            <span>Katalog Produk</span>
                        {/if}
                    </Link>

                    <!-- Produk Per Seller -->
                    <Link
                        href="/superadmin/warehouse/products/by-seller"
                        class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all
                        {isActive('/superadmin/warehouse/products/by-seller')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'hover:bg-slate-800/80 hover:text-white'}"
                    >
                        <Store class="h-5 w-5 shrink-0 text-sky-400" />
                        {#if !isCollapsed}
                            <span>Produk Per Seller</span>
                        {/if}
                    </Link>

                    <!-- Master Gudang -->
                    <Link
                        href="/superadmin/warehouse/warehouses"
                        class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all
                        {isActive('/superadmin/warehouse/warehouses')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'hover:bg-slate-800/80 hover:text-white'}"
                    >
                        <Building2 class="h-5 w-5 shrink-0 text-emerald-400" />
                        {#if !isCollapsed}
                            <span>Master Gudang</span>
                        {/if}
                    </Link>

                    <!-- Kategori -->
                    <Link
                        href="/superadmin/warehouse/categories"
                        class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all
                        {isActive('/superadmin/warehouse/categories')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'hover:bg-slate-800/80 hover:text-white'}"
                    >
                        <Tag class="h-5 w-5 shrink-0 text-amber-400" />
                        {#if !isCollapsed}
                            <span>Kategori Produk</span>
                        {/if}
                    </Link>

                    <!-- Brand -->
                    <Link
                        href="/superadmin/warehouse/brands"
                        class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all
                        {isActive('/superadmin/warehouse/brands')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'hover:bg-slate-800/80 hover:text-white'}"
                    >
                        <Layers class="h-5 w-5 shrink-0 text-sky-400" />
                        {#if !isCollapsed}
                            <span>Brand Master</span>
                        {/if}
                    </Link>

                    <!-- Periode Pembukuan -->
                    <Link
                        href="/superadmin/warehouse/periods"
                        class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all
                        {isActive('/superadmin/warehouse/periods')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'hover:bg-slate-800/80 hover:text-white'}"
                    >
                        <Calendar class="h-5 w-5 shrink-0 text-purple-400" />
                        {#if !isCollapsed}
                            <span>Periode Pembukuan</span>
                        {/if}
                    </Link>

                    <!-- Vendor Konveksi -->
                    <Link
                        href="/superadmin/warehouse/konveksis"
                        class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all
                        {isActive('/superadmin/warehouse/konveksis')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'hover:bg-slate-800/80 hover:text-white'}"
                    >
                        <Factory class="h-5 w-5 shrink-0 text-rose-400" />
                        {#if !isCollapsed}
                            <span>Vendor Konveksi</span>
                        {/if}
                    </Link>

                    <!-- Pemetaan SKU -->
                    <Link
                        href="/superadmin/warehouse/mapping/manual-global"
                        class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all
                        {isActive('/superadmin/warehouse/mapping/manual-global')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'hover:bg-slate-800/80 hover:text-white'}"
                    >
                        <FileSpreadsheet
                            class="h-5 w-5 shrink-0 text-orange-400"
                        />
                        {#if !isCollapsed}
                            <span>Report Global Product</span>
                        {/if}
                    </Link>

                    <!-- Template Varian -->
                    <Link
                        href="/superadmin/warehouse/bulk-variant"
                        class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all
                        {isActive('/superadmin/warehouse/bulk-variant')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'hover:bg-slate-800/80 hover:text-white'}"
                    >
                        <Grid class="h-5 w-5 shrink-0 text-cyan-400" />
                        {#if !isCollapsed}
                            <span>Template Varian</span>
                        {/if}
                    </Link>
                </div>
            {/if}
        </div>

        <!-- Mutasi Stok & Opname Section -->
        <div class="pt-4 pb-8">
            {#if !isCollapsed}
                <button
                    onclick={() => (mutationsMenuOpen = !mutationsMenuOpen)}
                    class="flex w-full items-center justify-between px-3 py-2 text-xs font-semibold uppercase tracking-wider text-slate-500 hover:text-slate-300 transition-colors"
                >
                    <span>Mutasi Stok & Opname</span>
                    <ChevronDown
                        class="h-4 w-4 transition-transform duration-200 {mutationsMenuOpen
                            ? 'rotate-180'
                            : ''}"
                    />
                </button>
            {/if}

            {#if mutationsMenuOpen || isCollapsed}
                <div class="space-y-1 mt-1">
                    <!-- Realtime Stocks -->
                    <Link
                        href="/superadmin/warehouse/stocks"
                        class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all
                        {isActive('/superadmin/warehouse/stocks')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'hover:bg-slate-800/80 hover:text-white'}"
                    >
                        <Activity class="h-5 w-5 shrink-0 text-emerald-400" />
                        {#if !isCollapsed}
                            <span>Stok Fisik Realtime</span>
                        {/if}
                    </Link>

                    <!-- Stock In -->
                    <Link
                        href="/superadmin/warehouse/stock-in"
                        class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all
                        {isActive('/superadmin/warehouse/stock-in')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'hover:bg-slate-800/80 hover:text-white'}"
                    >
                        <ArrowDownLeft class="h-5 w-5 shrink-0 text-sky-400" />
                        {#if !isCollapsed}
                            <span>Stock In Product</span>
                        {/if}
                    </Link>

                    <!-- Stock Out -->
                    <Link
                        href="/superadmin/warehouse/stock-out"
                        class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all
                        {isActive('/superadmin/warehouse/stock-out')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'hover:bg-slate-800/80 hover:text-white'}"
                    >
                        <ArrowUpRight class="h-5 w-5 shrink-0 text-rose-400" />
                        {#if !isCollapsed}
                            <span>Stock Out Product</span>
                        {/if}
                    </Link>

                    <!-- Stock Opname -->
                    <Link
                        href="/superadmin/warehouse/stock-opname"
                        class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all
                        {isActive('/superadmin/warehouse/stock-opname')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'hover:bg-slate-800/80 hover:text-white'}"
                    >
                        <ClipboardCheck
                            class="h-5 w-5 shrink-0 text-amber-400"
                        />
                        {#if !isCollapsed}
                            <span>Audit Stock Opname</span>
                        {/if}
                    </Link>
                </div>
            {/if}
        </div>

        <!-- FASE 5: Keuangan & Laporan Section -->
        <div class="pt-4">
            {#if !isCollapsed}
                <button
                    onclick={() => (financeMenuOpen = !financeMenuOpen)}
                    class="flex w-full items-center justify-between px-3 py-2 text-xs font-semibold uppercase tracking-wider text-slate-500 hover:text-slate-300 transition-colors"
                >
                    <span>Keuangan & Laporan</span>
                    <ChevronDown
                        class="h-4 w-4 transition-transform duration-200 {financeMenuOpen
                            ? 'rotate-180'
                            : ''}"
                    />
                </button>
            {/if}

            {#if financeMenuOpen || isCollapsed}
                <div class="space-y-1 mt-1">
                    <!-- Hutang Seller -->
                    <Link
                        href="/superadmin/finance/seller-debt"
                        class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all
                        {isActive('/superadmin/finance/seller-debt')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'hover:bg-slate-800/80 hover:text-white'}"
                    >
                        <Wallet class="h-5 w-5 shrink-0 text-purple-400" />
                        {#if !isCollapsed}
                            <span>Hutang Seller</span>
                        {/if}
                    </Link>

                    <!-- Tagihan Konveksi -->
                    <Link
                        href="/superadmin/finance/konveksi-debt"
                        class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all
                        {isActive('/superadmin/finance/konveksi-debt')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'hover:bg-slate-800/80 hover:text-white'}"
                    >
                        <Receipt class="h-5 w-5 shrink-0 text-amber-400" />
                        {#if !isCollapsed}
                            <span>Tagihan Konveksi</span>
                        {/if}
                    </Link>

                    <!-- Laporan Penghasilan -->
                    <Link
                        href="/superadmin/finance/seller-income-report"
                        class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all
                        {isActive('/superadmin/finance/seller-income-report')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'hover:bg-slate-800/80 hover:text-white'}"
                    >
                        <PieChart class="h-5 w-5 shrink-0 text-emerald-400" />
                        {#if !isCollapsed}
                            <span>Laporan HPP Seller</span>
                        {/if}
                    </Link>

                    <!-- Keuntungan -->
                    <Link
                        href="/superadmin/finance/profit-report"
                        class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all
                        {isActive('/superadmin/finance/profit-report')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'hover:bg-slate-800/80 hover:text-white'}"
                    >
                        <TrendingUp class="h-5 w-5 shrink-0 text-emerald-400" />
                        {#if !isCollapsed}
                            <span>Keuntungan</span>
                        {/if}
                    </Link>

                    <!-- Bukti Transfer -->
                    <Link
                        href="/superadmin/finance/proof-transfers"
                        class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all
                        {isActive('/superadmin/finance/proof-transfers')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'hover:bg-slate-800/80 hover:text-white'}"
                    >
                        <FileText class="h-5 w-5 shrink-0 text-sky-400" />
                        {#if !isCollapsed}
                            <span>Bukti Transfer</span>
                        {/if}
                    </Link>
                </div>
            {/if}
        </div>

        <!-- Tools Data Cleaning Section -->
        <div class="pt-4">
            {#if !isCollapsed}
                <button
                    onclick={() => (dataCleaningMenuOpen = !dataCleaningMenuOpen)}
                    class="flex w-full items-center justify-between px-3 py-2 text-xs font-semibold uppercase tracking-wider text-slate-500 hover:text-slate-300 transition-colors"
                >
                    <span>Tools Data Cleaning</span>
                    <ChevronDown
                        class="h-4 w-4 transition-transform duration-200 {dataCleaningMenuOpen
                            ? 'rotate-180'
                            : ''}"
                    />
                </button>
            {/if}

            {#if dataCleaningMenuOpen || isCollapsed}
                <div class="space-y-1 mt-1">
                    <!-- Cleaning Data Shopee -->
                    <Link
                        href="/superadmin/settings/cleaning-data"
                        class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all
                        {isActive('/superadmin/settings/cleaning-data') && !isActive('/superadmin/settings/cleaning-data-tiktok')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'hover:bg-slate-800/80 hover:text-white'}"
                    >
                        <FileCheck class="h-5 w-5 shrink-0 text-orange-400" />
                        {#if !isCollapsed}
                            <span>Cleaning Data Shopee</span>
                        {/if}
                    </Link>

                    <!-- Cleaning Data TikTok -->
                    <Link
                        href="/superadmin/settings/cleaning-data-tiktok"
                        class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all
                        {isActive('/superadmin/settings/cleaning-data-tiktok')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'hover:bg-slate-800/80 hover:text-white'}"
                    >
                        <Video class="h-5 w-5 shrink-0 text-pink-400" />
                        {#if !isCollapsed}
                            <span>Cleaning Data TikTok</span>
                        {/if}
                    </Link>
                </div>
            {/if}
        </div>

        <!-- FASE 5: Settings Section -->
        <div class="pt-4 pb-8">
            {#if !isCollapsed}
                <button
                    onclick={() => (settingsMenuOpen = !settingsMenuOpen)}
                    class="flex w-full items-center justify-between px-3 py-2 text-xs font-semibold uppercase tracking-wider text-slate-500 hover:text-slate-300 transition-colors"
                >
                    <span>Pengaturan Sistem</span>
                    <ChevronDown
                        class="h-4 w-4 transition-transform duration-200 {settingsMenuOpen
                            ? 'rotate-180'
                            : ''}"
                    />
                </button>
            {/if}

            {#if settingsMenuOpen || isCollapsed}
                <div class="space-y-1 mt-1">

                    <!-- Database Backup -->
                    <Link
                        href="/superadmin/settings/database-backup"
                        class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all
                        {isActive('/superadmin/settings/database-backup')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'hover:bg-slate-800/80 hover:text-white'}"
                    >
                        <Database class="h-5 w-5 shrink-0 text-cyan-400" />
                        {#if !isCollapsed}
                            <span>Database Backup</span>
                        {/if}
                    </Link>

                    <!-- App Settings -->
                    <Link
                        href="/superadmin/settings/app-settings"
                        class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all
                        {isActive('/superadmin/settings/app-settings')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'hover:bg-slate-800/80 hover:text-white'}"
                    >
                        <Settings class="h-5 w-5 shrink-0 text-indigo-400" />
                        {#if !isCollapsed}
                            <span>Pengaturan Aplikasi</span>
                        {/if}
                    </Link>

                    <!-- Deep Clean -->
                    <Link
                        href="/superadmin/settings/deep-clean"
                        class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all
                        {isActive('/superadmin/settings/deep-clean')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'hover:bg-slate-800/80 hover:text-white'}"
                    >
                        <Sparkles class="h-5 w-5 shrink-0 text-rose-400" />
                        {#if !isCollapsed}
                            <span>Deep Clean Cache</span>
                        {/if}
                    </Link>

                    <!-- Wiki Guide -->
                    <Link
                        href="/superadmin/settings/wiki-guide"
                        class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all
                        {isActive('/superadmin/settings/wiki-guide')
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'hover:bg-slate-800/80 hover:text-white'}"
                    >
                        <BookOpen class="h-5 w-5 shrink-0 text-yellow-400" />
                        {#if !isCollapsed}
                            <span>Wiki Guide & Manual</span>
                        {/if}
                    </Link>
                </div>
            {/if}
        </div>
    </div>
</aside>
