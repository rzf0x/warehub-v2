<script lang="ts">
    import AdminLayout from "@/layouts/AdminLayout.svelte";
    import ConfirmDialog from "@/components/ConfirmDialog.svelte";
    import { router, Link } from "@inertiajs/svelte";
    import {
        ArrowDownLeft,
        Search,
        Plus,
        Trash2,
        Edit2,
        UserCheck,
        PackageCheck,
        DollarSign,
        RotateCcw,
        Eye,
        FileText,
        Printer,
        Calendar,
        Factory,
    } from "@lucide/svelte";

    interface StockInRecord {
        id: number;
        detail_key?: string;
        invoice_number: string;
        date: string;
        note: string | null;
        warehouse?: { id: number; name: string } | null;
        period?: { id: number; name: string } | null;
        user?: { id?: number; name: string } | null;
        sellers: string[];
        konveksis: string[];
        total_qty: number;
        total_nominal: number;
        item_count: number;
    }

    interface Option {
        id: number;
        name?: string;
        seller_name?: string;
        is_active?: boolean;
        is_public?: boolean;
    }

    interface SummaryStats {
        total_transactions: number;
        total_qty: number;
        total_nominal: number;
        total_sellers: number;
    }

    let {
        stockIns = [],
        warehouses = [],
        periods = [],
        sellers = [],
        konveksis = [],
        summary = {
            total_transactions: 0,
            total_qty: 0,
            total_nominal: 0,
            total_sellers: 0,
        },
        filters = {
            search: "",
            warehouse_id: "",
            period_id: "",
            seller_id: "",
            konveksi_id: "",
            start_date: "",
            end_date: "",
        },
    }: {
        stockIns: StockInRecord[];
        warehouses?: Option[];
        periods?: Option[];
        sellers?: Option[];
        konveksis?: Option[];
        summary?: SummaryStats;
        filters?: {
            search?: string;
            warehouse_id?: string;
            period_id?: string;
            seller_id?: string;
            konveksi_id?: string;
            start_date?: string;
            end_date?: string;
        };
    } = $props();

    let search = $state(filters.search ?? "");
    let warehouseId = $state(filters.warehouse_id ?? "");
    let periodId = $state(String(filters.period_id ?? ""));
    let sellerId = $state(filters.seller_id ?? "");
    let konveksiId = $state(filters.konveksi_id ?? "");
    let startDate = $state(filters.start_date ?? "");
    let endDate = $state(filters.end_date ?? "");

    let isConfirmDeleteOpen = $state(false);
    let selectedRecord = $state<StockInRecord | null>(null);

    let exportPeriodParam = $derived(periodId === "all" ? "" : periodId);
    let selectedPeriodName = $derived(
        periodId === "all" || !periodId
            ? "Semua Periode"
            : (periods.find((p) => String(p.id) === String(periodId))?.name ??
                  "Periode Terpilih"),
    );

    let hasActiveFilter = $derived(
        Boolean(
            search ||
                warehouseId ||
                sellerId ||
                konveksiId ||
                startDate ||
                endDate ||
                periodId === "all",
        ),
    );

    let pdfReportUrl = $derived.by(() => {
        const params = new URLSearchParams();
        if (search) params.set("search", search);
        if (warehouseId) params.set("warehouse_id", String(warehouseId));
        if (exportPeriodParam)
            params.set("period_id", String(exportPeriodParam));
        if (sellerId) params.set("seller_id", String(sellerId));
        if (konveksiId) params.set("konveksi_id", String(konveksiId));
        if (startDate) params.set("start_date", startDate);
        if (endDate) params.set("end_date", endDate);
        const qs = params.toString();
        return `/superadmin/warehouse/stock-in/report/pdf${qs ? `?${qs}` : ""}`;
    });

    const KONVEKSI_BADGE_STYLES = [
        {
            pill: "bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-300 border-amber-200/80 dark:border-amber-800/70",
            icon: "text-amber-500",
        },
        {
            pill: "bg-sky-50 dark:bg-sky-950/50 text-sky-700 dark:text-sky-300 border-sky-200/80 dark:border-sky-800/70",
            icon: "text-sky-500",
        },
        {
            pill: "bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200/80 dark:border-emerald-800/70",
            icon: "text-emerald-500",
        },
        {
            pill: "bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-300 border-rose-200/80 dark:border-rose-800/70",
            icon: "text-rose-500",
        },
        {
            pill: "bg-violet-50 dark:bg-violet-950/50 text-violet-700 dark:text-violet-300 border-violet-200/80 dark:border-violet-800/70",
            icon: "text-violet-500",
        },
        {
            pill: "bg-teal-50 dark:bg-teal-950/50 text-teal-700 dark:text-teal-300 border-teal-200/80 dark:border-teal-800/70",
            icon: "text-teal-500",
        },
        {
            pill: "bg-orange-50 dark:bg-orange-950/50 text-orange-700 dark:text-orange-300 border-orange-200/80 dark:border-orange-800/70",
            icon: "text-orange-500",
        },
        {
            pill: "bg-fuchsia-50 dark:bg-fuchsia-950/50 text-fuchsia-700 dark:text-fuchsia-300 border-fuchsia-200/80 dark:border-fuchsia-800/70",
            icon: "text-fuchsia-500",
        },
        {
            pill: "bg-cyan-50 dark:bg-cyan-950/50 text-cyan-700 dark:text-cyan-300 border-cyan-200/80 dark:border-cyan-800/70",
            icon: "text-cyan-500",
        },
        {
            pill: "bg-lime-50 dark:bg-lime-950/50 text-lime-700 dark:text-lime-300 border-lime-200/80 dark:border-lime-800/70",
            icon: "text-lime-500",
        },
        {
            pill: "bg-pink-50 dark:bg-pink-950/50 text-pink-700 dark:text-pink-300 border-pink-200/80 dark:border-pink-800/70",
            icon: "text-pink-500",
        },
        {
            pill: "bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border-indigo-200/80 dark:border-indigo-800/70",
            icon: "text-indigo-500",
        },
    ];

    function getKonveksiBadgeStyle(name: string) {
        const key = (name || "").trim().toLowerCase();
        // Use index in master konveksis list first so every konveksi gets a unique slot before wrapping
        const idx = konveksis.findIndex(
            (k) => (k.name || "").trim().toLowerCase() === key,
        );
        if (idx >= 0) {
            return KONVEKSI_BADGE_STYLES[idx % KONVEKSI_BADGE_STYLES.length];
        }
        let hash = 0;
        for (let i = 0; i < key.length; i++) {
            hash = (hash * 31 + key.charCodeAt(i)) >>> 0;
        }
        return KONVEKSI_BADGE_STYLES[hash % KONVEKSI_BADGE_STYLES.length];
    }

    function formatDate(dateStr: string): string {
        if (!dateStr) return "-";
        const cleanStr = dateStr.split("T")[0];
        const [year, month, day] = cleanStr.split("-");
        if (!year || !month || !day) return dateStr;
        const d = new Date(Number(year), Number(month) - 1, Number(day));
        return d.toLocaleDateString("id-ID", {
            day: "numeric",
            month: "short",
            year: "numeric",
        });
    }

    function formatRupiah(num: number | string | null | undefined): string {
        if (num === null || num === undefined || num === "") return "-";
        const val = typeof num === "string" ? parseFloat(num) : num;
        if (isNaN(val)) return "-";
        return new Intl.NumberFormat("id-ID", {
            style: "currency",
            currency: "IDR",
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(val);
    }

    function applyFilter() {
        router.get(
            "/superadmin/warehouse/stock-in",
            {
                search,
                warehouse_id: warehouseId,
                period_id: periodId,
                seller_id: sellerId,
                konveksi_id: konveksiId,
                start_date: startDate,
                end_date: endDate,
            },
            { preserveState: true, replace: true },
        );
    }

    function resetFilter() {
        search = "";
        warehouseId = "";
        periodId = "";
        sellerId = "";
        konveksiId = "";
        startDate = "";
        endDate = "";
        router.get(
            "/superadmin/warehouse/stock-in",
            {},
            { preserveState: true, replace: true },
        );
    }

    function openDeleteModal(rec: StockInRecord) {
        selectedRecord = rec;
        isConfirmDeleteOpen = true;
    }

    function submitDelete() {
        if (!selectedRecord) return;
        router.delete(`/superadmin/warehouse/stock-in/${selectedRecord.id}`, {
            onSuccess: () => {
                isConfirmDeleteOpen = false;
            },
        });
    }
</script>

<AdminLayout
    title="Penerimaan Barang (Stock In)"
    breadcrumbs={[{ name: "Mutasi Stok" }, { name: "Penerimaan (Stock In)" }]}
>
    <div
        class="polkadot-surface rounded-3xl p-4 sm:p-6 -m-2 sm:-m-3 border border-[#E6007A]/15 dark:border-[#E6007A]/25"
    >
        <!-- Header -->
        <div
            class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2.5"
                >
                    <span
                        class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-[#E6007A]/10 text-[#E6007A] border border-[#E6007A]/25"
                    >
                        <ArrowDownLeft class="h-5 w-5" />
                    </span>
                    Penerimaan Barang (Stock In)
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Pencatatan masuknya barang dari vendor/seller ke gudang
                    persediaan
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a
                    href={pdfReportUrl}
                    target="_blank"
                    class="inline-flex items-center gap-2 rounded-xl border border-[#E6007A]/25 dark:border-[#E6007A]/35 bg-white/90 dark:bg-slate-900/90 px-3.5 py-2.5 text-xs font-semibold text-[#E6007A] dark:text-pink-300 hover:bg-[#E6007A]/5 dark:hover:bg-[#E6007A]/15 transition-colors shadow-xs"
                >
                    <Printer class="h-4 w-4 text-[#E6007A] dark:text-pink-400" />
                    Export PDF Laporan
                </a>

                <Link
                    href="/superadmin/warehouse/stock-in/create"
                    class="inline-flex items-center gap-2 rounded-xl bg-[#E6007A] px-4 py-2.5 text-xs font-semibold text-white shadow-lg shadow-[#E6007A]/30 hover:bg-[#c9006b] transition-colors"
                >
                    <Plus class="h-4 w-4" />
                    Catat Penerimaan Barang
                </Link>
            </div>
        </div>

        <!-- Summary Cards Grid -->
        <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div
                class="rounded-2xl bg-white/95 dark:bg-slate-900/95 backdrop-blur-xs p-5 shadow-xs border border-[#E6007A]/15 dark:border-[#E6007A]/25 flex items-center justify-between transition-all hover:shadow-md hover:border-[#E6007A]/40"
            >
                <div>
                    <span
                        class="block text-xs font-semibold uppercase tracking-wider text-slate-400"
                        >Total Transaksi</span
                    >
                    <span
                        class="text-2xl font-bold text-slate-800 dark:text-slate-100 mt-1 block"
                    >
                        {summary.total_transactions.toLocaleString("id-ID")}
                        <span class="text-sm font-normal text-slate-500"
                            >Penerimaan</span
                        >
                    </span>
                    <span
                        class="text-xs text-[#E6007A] dark:text-pink-400 font-semibold mt-1 inline-block"
                    >
                        {selectedPeriodName}
                    </span>
                </div>
                <div
                    class="h-12 w-12 rounded-2xl bg-[#E6007A]/10 text-[#E6007A] dark:text-pink-400 border border-[#E6007A]/25 flex items-center justify-center shrink-0"
                >
                    <ArrowDownLeft class="h-6 w-6" />
                </div>
            </div>

            <div
                class="rounded-2xl bg-white/95 dark:bg-slate-900/95 backdrop-blur-xs p-5 shadow-xs border border-[#E6007A]/15 dark:border-[#E6007A]/25 flex items-center justify-between transition-all hover:shadow-md hover:border-[#E6007A]/40"
            >
                <div>
                    <span
                        class="block text-xs font-semibold uppercase tracking-wider text-slate-400"
                        >Total Barang Masuk</span
                    >
                    <span
                        class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1 block"
                    >
                        +{summary.total_qty.toLocaleString("id-ID")}
                        <span class="text-sm font-normal text-slate-500">Pcs</span>
                    </span>
                    <span
                        class="text-xs text-emerald-600 dark:text-emerald-400 font-medium mt-1 inline-block"
                    >
                        Total fisik stok diterima
                    </span>
                </div>
                <div
                    class="h-12 w-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-800 flex items-center justify-center shrink-0"
                >
                    <PackageCheck class="h-6 w-6" />
                </div>
            </div>

            <div
                class="rounded-2xl bg-white/95 dark:bg-slate-900/95 backdrop-blur-xs p-5 shadow-xs border border-[#E6007A]/15 dark:border-[#E6007A]/25 flex items-center justify-between transition-all hover:shadow-md hover:border-[#E6007A]/40"
            >
                <div>
                    <span
                        class="block text-xs font-semibold uppercase tracking-wider text-slate-400"
                        >Total Nilai Pembelian</span
                    >
                    <span
                        class="text-xl font-bold text-[#E6007A] dark:text-pink-400 mt-1 block"
                    >
                        {formatRupiah(summary.total_nominal)}
                    </span>
                    <span
                        class="text-xs text-[#E6007A]/80 dark:text-pink-300 font-medium mt-1 inline-block"
                    >
                        Total HPP Modal Masuk
                    </span>
                </div>
                <div
                    class="h-12 w-12 rounded-2xl bg-[#E6007A]/10 text-[#E6007A] dark:text-pink-400 border border-[#E6007A]/25 flex items-center justify-center shrink-0"
                >
                    <DollarSign class="h-6 w-6" />
                </div>
            </div>

            <div
                class="rounded-2xl bg-white/95 dark:bg-slate-900/95 backdrop-blur-xs p-5 shadow-xs border border-[#E6007A]/15 dark:border-[#E6007A]/25 flex items-center justify-between transition-all hover:shadow-md hover:border-[#E6007A]/40"
            >
                <div>
                    <span
                        class="block text-xs font-semibold uppercase tracking-wider text-slate-400"
                        >Mitra / Seller</span
                    >
                    <span
                        class="text-2xl font-bold text-fuchsia-600 dark:text-fuchsia-400 mt-1 block"
                    >
                        {summary.total_sellers}
                        <span class="text-sm font-normal text-slate-500">Mitra</span>
                    </span>
                    <span
                        class="text-xs text-fuchsia-600 dark:text-fuchsia-400 font-medium mt-1 inline-block"
                    >
                        Terdaftar di sistem
                    </span>
                </div>
                <div
                    class="h-12 w-12 rounded-2xl bg-fuchsia-50 dark:bg-fuchsia-950/60 text-fuchsia-600 dark:text-fuchsia-400 border border-fuchsia-100 dark:border-fuchsia-800 flex items-center justify-center shrink-0"
                >
                    <UserCheck class="h-6 w-6" />
                </div>
            </div>
        </div>

        <!-- Filters Bar -->
        <div
            class="mb-6 rounded-2xl bg-white/95 dark:bg-slate-900/95 backdrop-blur-xs p-4 shadow-xs border border-[#E6007A]/15 dark:border-[#E6007A]/25 space-y-3"
        >
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <!-- Filter Periode (Utama — menggantikan pagination) -->
                <div class="relative">
                    <Calendar
                        class="absolute left-3 top-3 h-4 w-4 text-[#E6007A] pointer-events-none"
                    />
                    <select
                        bind:value={periodId}
                        onchange={applyFilter}
                        class="w-full rounded-xl border border-[#E6007A]/35 dark:border-[#E6007A]/45 bg-[#E6007A]/5 dark:bg-[#E6007A]/15 pl-9 pr-3.5 py-2.5 text-sm font-semibold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#E6007A]"
                    >
                        {#each periods as p}
                            <option value={String(p.id)}>
                                {p.name}{p.is_active || p.is_public
                                    ? " (Aktif)"
                                    : ""}
                            </option>
                        {/each}
                        <option value="all">Semua Periode</option>
                    </select>
                </div>

                <!-- Filter Gudang -->
                <select
                    bind:value={warehouseId}
                    onchange={applyFilter}
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#E6007A]"
                >
                    <option value="">Semua Gudang</option>
                    {#each warehouses as w}
                        <option value={w.id}>{w.name}</option>
                    {/each}
                </select>

                <!-- Filter Seller -->
                <select
                    bind:value={sellerId}
                    onchange={applyFilter}
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#E6007A]"
                >
                    <option value="">Semua Mitra / Seller</option>
                    {#each sellers as s}
                        <option value={s.id}>{s.seller_name || s.name}</option>
                    {/each}
                </select>

                <!-- Filter Konveksi -->
                <select
                    bind:value={konveksiId}
                    onchange={applyFilter}
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#E6007A]"
                >
                    <option value="">Semua Vendor Konveksi</option>
                    {#each konveksis as k}
                        <option value={k.id}>{k.name}</option>
                    {/each}
                </select>
            </div>

            <div
                class="flex flex-col lg:flex-row items-center justify-between gap-3 pt-1"
            >
                <!-- Filter Rentang Tanggal -->
                <div
                    class="grid grid-cols-1 sm:grid-cols-2 gap-3 w-full lg:w-auto"
                >
                    <div class="flex items-center gap-2">
                        <span
                            class="text-xs font-semibold text-slate-500 whitespace-nowrap w-14 sm:w-auto"
                            >Dari:</span
                        >
                        <input
                            type="date"
                            bind:value={startDate}
                            onchange={applyFilter}
                            class="w-full sm:w-44 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#E6007A]"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            class="text-xs font-semibold text-slate-500 whitespace-nowrap w-14 sm:w-auto"
                            >Sampai:</span
                        >
                        <input
                            type="date"
                            bind:value={endDate}
                            onchange={applyFilter}
                            class="w-full sm:w-44 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-[#E6007A]"
                        />
                    </div>
                </div>

                <!-- Search & Reset Filter -->
                <div
                    class="flex items-center gap-2 w-full lg:w-auto flex-1 lg:max-w-md justify-end"
                >
                    <div class="relative flex-1">
                        <Search
                            class="absolute left-3.5 top-2.5 h-4 w-4 text-[#E6007A]/70"
                        />
                        <input
                            type="text"
                            bind:value={search}
                            onkeyup={(e) => e.key === "Enter" && applyFilter()}
                            placeholder="Cari invoice, gudang, seller, konveksi, SKU..."
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 pl-10 pr-4 py-2 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-[#E6007A]"
                        />
                    </div>

                    {#if hasActiveFilter}
                        <button
                            type="button"
                            onclick={resetFilter}
                            class="inline-flex items-center gap-1.5 rounded-xl border border-[#E6007A]/30 bg-[#E6007A]/10 px-3.5 py-2 text-xs font-semibold text-[#E6007A] dark:text-pink-300 hover:bg-[#E6007A]/20 transition-colors shrink-0"
                            title="Reset Semua Filter"
                        >
                            <RotateCcw class="h-3.5 w-3.5" />
                            Reset
                        </button>
                    {/if}
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div
            class="rounded-2xl bg-white/95 dark:bg-slate-900/95 backdrop-blur-xs shadow-xs border border-[#E6007A]/15 dark:border-[#E6007A]/25 overflow-hidden"
        >
            <div class="overflow-x-auto">
                <table
                    class="w-full text-left text-xs text-slate-600 dark:text-slate-400"
                >
                    <thead
                        class="bg-[#E6007A]/[0.04] dark:bg-[#E6007A]/[0.10] uppercase font-semibold text-slate-600 dark:text-pink-200/80 border-b border-[#E6007A]/15 dark:border-[#E6007A]/25"
                    >
                        <tr>
                            <th class="px-4 py-3.5 w-10">No</th>
                            <th class="px-4 py-3.5 whitespace-nowrap"
                                >No Invoice / SJ</th
                            >
                            <th class="px-4 py-3.5 whitespace-nowrap">Tanggal</th
                            >
                            <th class="px-4 py-3.5">Pemilik / Seller</th>
                            <th class="px-4 py-3.5">Konveksi</th>
                            <th class="px-4 py-3.5 whitespace-nowrap">Gudang</th
                            >
                            <th
                                class="px-4 py-3.5 text-center whitespace-nowrap"
                                >Total Qty</th
                            >
                            <th class="px-4 py-3.5 text-right whitespace-nowrap"
                                >Total HPP</th
                            >
                            <th class="px-4 py-3.5 whitespace-nowrap"
                                >Petugas</th
                            >
                            <th class="px-4 py-3.5 text-right whitespace-nowrap"
                                >Aksi</th
                            >
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-100 dark:divide-slate-800"
                    >
                        {#each stockIns as item, index}
                            <tr
                                class="hover:bg-[#E6007A]/[0.03] dark:hover:bg-[#E6007A]/[0.07] transition-colors"
                            >
                                <td
                                    class="px-4 py-3.5 text-slate-400 font-mono whitespace-nowrap"
                                    >{index + 1}</td
                                >
                                <td
                                    class="px-4 py-3.5 font-mono font-bold text-[#E6007A] dark:text-pink-400 whitespace-nowrap"
                                >
                                    {item.invoice_number}
                                    <div
                                        class="text-[10px] font-sans font-normal text-slate-400"
                                    >
                                        {item.item_count} varian SKU
                                    </div>
                                </td>
                            <td
                                class="px-4 py-3.5 text-slate-700 dark:text-slate-200 font-medium whitespace-nowrap"
                            >
                                {formatDate(item.date)}
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="flex flex-wrap gap-1">
                                    {#each item.sellers as sellerName}
                                        <span
                                            class="inline-flex items-center gap-1 rounded-lg bg-purple-50 dark:bg-purple-950/60 px-2 py-0.5 text-[11px] font-bold text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800 whitespace-nowrap"
                                        >
                                            <UserCheck
                                                class="h-3 w-3 text-purple-500 shrink-0"
                                            />
                                            <span>{sellerName}</span>
                                        </span>
                                    {:else}
                                        <span class="text-slate-400 italic"
                                            >Umum</span
                                        >
                                    {/each}
                                </div>
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="flex flex-wrap gap-1">
                                    {#each item.konveksis as konvName}
                                        {@const badge =
                                            getKonveksiBadgeStyle(konvName)}
                                        <span
                                            class={`inline-flex items-center gap-1 rounded-lg px-2 py-0.5 text-[11px] font-semibold border whitespace-nowrap ${badge.pill}`}
                                        >
                                            <Factory
                                                class={`h-3 w-3 shrink-0 ${badge.icon}`}
                                            />
                                            <span>{konvName}</span>
                                        </span>
                                    {:else}
                                        <span class="text-slate-400">-</span>
                                    {/each}
                                </div>
                            </td>
                            <td
                                class="px-4 py-3.5 font-semibold text-slate-800 dark:text-slate-100 whitespace-nowrap"
                                >{item.warehouse?.name ?? "-"}</td
                            >
                            <td
                                class="px-4 py-3.5 text-center font-bold text-emerald-600 dark:text-emerald-400 whitespace-nowrap"
                            >
                                <span
                                    class="inline-flex items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-950/60 px-2.5 py-1 border border-emerald-200 dark:border-emerald-800"
                                >
                                    +{item.total_qty.toLocaleString("id-ID")} pcs
                                </span>
                            </td>
                            <td
                                class="px-4 py-3.5 text-right font-mono font-bold text-slate-700 dark:text-slate-200 whitespace-nowrap"
                            >
                                {formatRupiah(item.total_nominal)}
                            </td>
                            <td
                                class="px-4 py-3.5 text-slate-500 whitespace-nowrap"
                                >{item.user?.name ?? "-"}</td
                            >
                            <td
                                class="px-4 py-3.5 text-right whitespace-nowrap"
                            >
                                <div
                                    class="inline-flex items-center justify-end gap-1"
                                >
                                    <Link
                                        href={`/superadmin/warehouse/stock-in/${item.detail_key ?? item.id}`}
                                        class="inline-flex items-center rounded-lg p-2 text-cyan-600 hover:bg-cyan-50 dark:hover:bg-cyan-950/50 transition-colors"
                                        title="Lihat Detail Barang Masuk"
                                    >
                                        <Eye class="h-4 w-4" />
                                    </Link>
                                    <a
                                        href={`/superadmin/warehouse/stock-in/${item.id}/pdf`}
                                        target="_blank"
                                        class="inline-flex items-center rounded-lg p-2 text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 transition-colors"
                                        title="Cetak Surat Jalan PDF"
                                    >
                                        <FileText class="h-4 w-4" />
                                    </a>
                                    <Link
                                        href={`/superadmin/warehouse/stock-in/${item.id}/edit`}
                                        class="inline-flex items-center rounded-lg p-2 text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/50 transition-colors"
                                        title="Edit Penerimaan Barang"
                                    >
                                        <Edit2 class="h-4 w-4" />
                                    </Link>
                                    <button
                                        type="button"
                                        onclick={() => openDeleteModal(item)}
                                        class="inline-flex items-center rounded-lg p-2 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition-colors"
                                        title="Batalkan Penerimaan"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    {:else}
                        <tr>
                            <td
                                colspan="10"
                                class="px-6 py-12 text-center text-slate-400"
                                >Belum ada transaksi penerimaan barang pada
                                periode ini.</td
                            >
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
        <div
            class="px-4 py-3 bg-[#E6007A]/[0.04] dark:bg-[#E6007A]/[0.08] border-t border-[#E6007A]/15 dark:border-[#E6007A]/25 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500 dark:text-slate-400"
        >
            <span>
                Menampilkan <strong class="text-slate-700 dark:text-slate-200"
                    >{stockIns.length.toLocaleString("id-ID")}</strong
                >
                transaksi pada
                <strong class="text-[#E6007A] dark:text-pink-400"
                    >{selectedPeriodName}</strong
                >
            </span>
            <span class="text-[11px] text-slate-400"
                >Ganti dropdown periode di atas untuk melihat periode lainnya</span
            >
        </div>
    </div>
    </div>

    <!-- Delete Confirm -->
    <ConfirmDialog
        show={isConfirmDeleteOpen}
        title="Batalkan Penerimaan Barang"
        message={`Apakah Anda yakin ingin membatalkan transaksi Surat Jalan #${selectedRecord?.invoice_number ?? ""}? Stok fisik di gudang akan dikurangi kembali.`}
        confirmText="Batalkan Transaksi"
        onconfirm={submitDelete}
        oncancel={() => (isConfirmDeleteOpen = false)}
    />
</AdminLayout>

<style>
    .polkadot-surface {
        background-color: rgba(230, 0, 122, 0.025);
        background-image:
            radial-gradient(rgba(230, 0, 122, 0.14) 1.5px, transparent 1.5px),
            radial-gradient(rgba(230, 0, 122, 0.08) 1.5px, transparent 1.5px);
        background-size: 24px 24px;
        background-position: 0 0, 12px 12px;
    }

    :global(.dark) .polkadot-surface {
        background-color: rgba(230, 0, 122, 0.04);
        background-image:
            radial-gradient(rgba(230, 0, 122, 0.22) 1.5px, transparent 1.5px),
            radial-gradient(rgba(230, 0, 122, 0.12) 1.5px, transparent 1.5px);
    }
</style>
