<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import { router } from '@inertiajs/svelte';
    import { Settings, Save, ShieldCheck, Bell, Server } from '@lucide/svelte';

    interface AppConfig {
        app_name: string;
        app_env: string;
        app_url: string;
        low_stock_threshold: number;
        auto_backup_enabled: boolean;
    }

    let { settings = { app_name: 'Warehub v2', app_env: 'production', app_url: 'https://warehub-v2.test', low_stock_threshold: 10, auto_backup_enabled: true } }: { settings?: AppConfig } = $props();

    let form = $state({ ...settings });

    function submitUpdate() {
        router.post('/superadmin/settings/app-settings', form);
    }
</script>

<AdminLayout title="Pengaturan Aplikasi" breadcrumbs={[{ name: 'Pengaturan' }, { name: 'Pengaturan Aplikasi' }]}>
    <!-- Header -->
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight flex items-center gap-2">
                <Settings class="h-7 w-7 text-indigo-500" />
                Pengaturan Konfigurasi Aplikasi (App Settings)
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Konfigurasi nama sistem, threshold stok minim, dan preferensi aplikasi</p>
        </div>

        <button
            onclick={submitUpdate}
            class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/30 hover:bg-indigo-700 transition-colors"
        >
            <Save class="h-4 w-4" />
            Simpan Konfigurasi
        </button>
    </div>

    <!-- Form Settings -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-100 dark:border-slate-800 max-w-2xl space-y-6">
        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 border-b border-slate-100 dark:border-slate-800 pb-3 flex items-center gap-2">
            <Server class="h-5 w-5 text-indigo-500" />
            Parameter Konfigurasi Utama
        </h3>

        <form onsubmit={(e) => { e.preventDefault(); submitUpdate(); }} class="space-y-4">
            <div>
                <label for="s_name" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Nama Aplikasi</label>
                <input
                    id="s_name"
                    type="text"
                    bind:value={form.app_name}
                    required
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="s_env" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Environment</label>
                    <input
                        id="s_env"
                        type="text"
                        bind:value={form.app_env}
                        readonly
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-100 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-500 font-mono"
                    />
                </div>
                <div>
                    <label for="s_url" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Base App URL</label>
                    <input
                        id="s_url"
                        type="text"
                        bind:value={form.app_url}
                        readonly
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-100 dark:bg-slate-950 px-4 py-2.5 text-sm text-slate-500 font-mono"
                    />
                </div>
            </div>

            <div>
                <label for="s_threshold" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Default Minimum Low Stock Threshold (Pcs)</label>
                <input
                    id="s_threshold"
                    type="number"
                    bind:value={form.low_stock_threshold}
                    min="1"
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 px-4 py-2.5 text-sm font-bold text-amber-600 focus:ring-2 focus:ring-indigo-500"
                />
                <span class="text-[11px] text-slate-400 block mt-1">Sistem akan memberi peringatan stok menipis jika Qty berada di bawah angka ini.</span>
            </div>

            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <div>
                    <span class="text-sm font-bold text-slate-800 dark:text-slate-100 block">Otomatisasi Backup Harian</span>
                    <span class="text-xs text-slate-400">Aktifkan pencadangan snapshot otomatis setiap malam</span>
                </div>
                <input
                    type="checkbox"
                    bind:checked={form.auto_backup_enabled}
                    class="h-5 w-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                />
            </div>
        </form>
    </div>
</AdminLayout>
