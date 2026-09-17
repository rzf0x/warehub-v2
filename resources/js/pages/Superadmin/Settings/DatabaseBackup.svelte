<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import { Database, Download, CheckCircle2, ShieldCheck, RefreshCw } from '@lucide/svelte';

    interface TableStat {
        name: string;
        count: number;
    }

    let {
        tableStats = [],
        lastBackupAt = '',
    }: {
        tableStats?: TableStat[];
        lastBackupAt?: string;
    } = $props();

    function triggerDownload() {
        window.open('/superadmin/settings/database-backup/download', '_blank');
    }
</script>

<AdminLayout title="Database Backup" breadcrumbs={[{ name: 'Pengaturan' }, { name: 'Database Backup' }]}>
    <!-- Header -->
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                <Database class="h-7 w-7 text-cyan-500" />
                Penyimpanan & Cadangan Database (Database Backup)
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Unduh cadangan data sistem (Sellers, Products, Stocks, Stock In/Out) dalam bentuk berkas JSON/SQL</p>
        </div>

        <button
            onclick={triggerDownload}
            class="inline-flex items-center gap-2 rounded-xl bg-cyan-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-cyan-600/30 hover:bg-cyan-700 transition-colors"
        >
            <Download class="h-4 w-4" />
            Unduh Database Backup Sekarang
        </button>
    </div>

    <!-- Overview Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-6 rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-100 dark:border-slate-800 space-y-6">
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 border-b border-slate-100 dark:border-slate-800 pb-3 flex items-center gap-2">
                <ShieldCheck class="h-5 w-5 text-cyan-500" />
                Ringkasan Jumlah Data Tabel
            </h3>

            <div class="space-y-3">
                {#each tableStats as t}
                    <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 flex justify-between items-center">
                        <span class="font-bold text-slate-800 dark:text-slate-100 font-mono text-xs uppercase">{t.name}</span>
                        <span class="font-black text-cyan-600 dark:text-cyan-400 text-sm">{t.count} Baris</span>
                    </div>
                {/each}
            </div>
        </div>

        <div class="lg:col-span-6 rounded-2xl bg-gradient-to-br from-slate-900 via-cyan-950 to-indigo-950 p-6 text-white shadow-xl space-y-6 flex flex-col justify-between">
            <div>
                <h3 class="text-base font-bold border-b border-white/10 pb-3 flex items-center gap-2 text-cyan-200">
                    <CheckCircle2 class="h-5 w-5 text-emerald-400" />
                    Status Keamanan Cadangan
                </h3>

                <div class="space-y-4 mt-6">
                    <div class="p-4 rounded-xl bg-white/5 border border-white/10">
                        <span class="block text-xs uppercase tracking-wider text-slate-400 font-semibold">Waktu Pengambilan Terakhir</span>
                        <span class="text-lg font-bold text-cyan-300 mt-1 block">{lastBackupAt || 'Hari Ini'}</span>
                    </div>

                    <div class="p-4 rounded-xl bg-white/5 border border-white/10">
                        <span class="block text-xs uppercase tracking-wider text-slate-400 font-semibold">Tipe Cadangan</span>
                        <span class="text-sm font-semibold text-emerald-300 mt-1 block">JSON Structured Snapshot Dump</span>
                    </div>
                </div>
            </div>

            <div class="p-4 rounded-xl bg-cyan-500/10 border border-cyan-500/20 text-xs text-cyan-200 leading-relaxed">
                🔒 <strong>Rekomendasi Keamanan:</strong> Disarankan mengunduh cadangan database secara berkala sebelum melakukan perubahan massal pada stok atau data master.
            </div>
        </div>
    </div>
</AdminLayout>
