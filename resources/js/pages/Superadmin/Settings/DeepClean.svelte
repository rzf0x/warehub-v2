<script>
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import { router } from '@inertiajs/svelte';
    import { 
        Trash2, RefreshCw, Cpu, ShieldCheck, Database, HardDrive, 
        Zap, AlertTriangle, CheckCircle2, Server
    } from '@lucide/svelte';

    let isCleaning = $state(false);
    let cleanSuccess = $state(false);
    let selectedTargets = $state({
        cache: true,
        views: true,
        routes: true,
        config: true,
        logs: false
    });

    function runDeepClean() {
        isCleaning = true;
        cleanSuccess = false;
        
        router.post('/superadmin/settings/deep-clean', selectedTargets, {
            preserveScroll: true,
            onSuccess: () => {
                isCleaning = false;
                cleanSuccess = true;
                setTimeout(() => { cleanSuccess = false; }, 5000);
            },
            onError: () => {
                isCleaning = false;
            }
        });
    }
</script>

<AdminLayout title="Deep Clean System - Warehub v2">
    <div class="max-w-6xl mx-auto space-y-6">
        <!-- Page Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-r from-red-600 via-rose-600 to-pink-600 dark:from-red-900 dark:via-rose-950 dark:to-slate-900 p-6 rounded-2xl text-white shadow-xl relative overflow-hidden">
            <div class="absolute right-0 top-0 translate-x-4 -translate-y-4 opacity-10 pointer-events-none">
                <Trash2 class="w-64 h-64" />
            </div>
            <div class="relative z-10 space-y-1">
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 bg-white/20 text-white text-xs font-semibold rounded-full backdrop-blur-md">System Tools</span>
                    <span class="text-xs text-rose-200">System Optimization</span>
                </div>
                <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight flex items-center gap-3">
                    <Trash2 class="w-7 h-7 text-rose-200" />
                    Deep Clean Cache & Temporary Files
                </h1>
                <p class="text-rose-100 text-sm max-w-xl">
                    Bersihkan seluruh cache aplikasi, cache view, compiled routes, serta file temporer untuk mengoptimalkan performa server.
                </p>
            </div>
        </div>

        {#if cleanSuccess}
            <div class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-300 rounded-xl flex items-center gap-3 shadow-md transition-all">
                <CheckCircle2 class="w-6 h-6 text-emerald-500 shrink-0" />
                <div>
                    <p class="font-bold text-sm">Pembersihan Deep Clean Berhasil!</p>
                    <p class="text-xs">Cache aplikasi, views, routes, dan konfigurasi telah diperbarui dan disegarkan.</p>
                </div>
            </div>
        {/if}

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Left Info Panel -->
            <div class="md:col-span-1 space-y-4">
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm space-y-4">
                    <h3 class="font-bold text-slate-800 dark:text-slate-100 text-base flex items-center gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                        <Server class="w-5 h-5 text-indigo-500" />
                        Status Penyimpanan
                    </h3>

                    <div class="space-y-3">
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                                <Database class="w-4 h-4 text-emerald-500" /> Application Cache
                            </span>
                            <span class="font-semibold text-slate-700 dark:text-slate-300">Active</span>
                        </div>
                        <div class="w-full bg-slate-100 dark:bg-slate-800 h-2 rounded-full overflow-hidden">
                            <div class="bg-emerald-500 h-full w-[45%]"></div>
                        </div>

                        <div class="flex justify-between items-center text-sm pt-2">
                            <span class="text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                                <HardDrive class="w-4 h-4 text-sky-500" /> Compiled Views
                            </span>
                            <span class="font-semibold text-slate-700 dark:text-slate-300">Cached</span>
                        </div>
                        <div class="w-full bg-slate-100 dark:bg-slate-800 h-2 rounded-full overflow-hidden">
                            <div class="bg-sky-500 h-full w-[60%]"></div>
                        </div>

                        <div class="flex justify-between items-center text-sm pt-2">
                            <span class="text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                                <Cpu class="w-4 h-4 text-amber-500" /> Config & Route Cache
                            </span>
                            <span class="font-semibold text-slate-700 dark:text-slate-300">Loaded</span>
                        </div>
                        <div class="w-full bg-slate-100 dark:bg-slate-800 h-2 rounded-full overflow-hidden">
                            <div class="bg-amber-500 h-full w-[80%]"></div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100 dark:border-slate-800 text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        <div class="flex items-start gap-2 text-amber-600 dark:text-amber-400 font-semibold mb-1">
                            <AlertTriangle class="w-4 h-4 shrink-0 mt-0.5" />
                            <span>Catatan Keamanan</span>
                        </div>
                        Pembersihan ini aman dijalankan secara berkala. Pembersihan cache tidak menghapus data transaksi, produk, maupun data master di database.
                    </div>
                </div>
            </div>

            <!-- Right Options Panel -->
            <div class="md:col-span-2 space-y-4">
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-6">
                    <div>
                        <h3 class="font-bold text-slate-800 dark:text-slate-100 text-lg flex items-center gap-2">
                            <Zap class="w-5 h-5 text-red-500" />
                            Target Pembersihan Cache
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Pilih modul cache yang ingin dibersihkan secara instan oleh Artisan Runner.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label class="flex items-start gap-3 p-4 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-red-300 dark:hover:border-red-800/60 bg-slate-50/50 dark:bg-slate-950/40 cursor-pointer transition-all">
                            <input type="checkbox" bind:checked={selectedTargets.cache} class="mt-1 rounded border-slate-300 text-red-600 focus:ring-red-500" />
                            <div>
                                <span class="block font-semibold text-slate-800 dark:text-slate-200 text-sm">Application Cache</span>
                                <span class="text-xs text-slate-500 dark:text-slate-400">Membersihkan key cache aplikasi dan query temporary (cache:clear).</span>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 p-4 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-red-300 dark:hover:border-red-800/60 bg-slate-50/50 dark:bg-slate-950/40 cursor-pointer transition-all">
                            <input type="checkbox" bind:checked={selectedTargets.views} class="mt-1 rounded border-slate-300 text-red-600 focus:ring-red-500" />
                            <div>
                                <span class="block font-semibold text-slate-800 dark:text-slate-200 text-sm">Blade & View Cache</span>
                                <span class="text-xs text-slate-500 dark:text-slate-400">Membersihkan kompilasi tampilan blade & PDF template (view:clear).</span>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 p-4 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-red-300 dark:hover:border-red-800/60 bg-slate-50/50 dark:bg-slate-950/40 cursor-pointer transition-all">
                            <input type="checkbox" bind:checked={selectedTargets.config} class="mt-1 rounded border-slate-300 text-red-600 focus:ring-red-500" />
                            <div>
                                <span class="block font-semibold text-slate-800 dark:text-slate-200 text-sm">Configuration Cache</span>
                                <span class="text-xs text-slate-500 dark:text-slate-400">Membersihkan cache file .env dan config Laravel (config:clear).</span>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 p-4 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-red-300 dark:hover:border-red-800/60 bg-slate-50/50 dark:bg-slate-950/40 cursor-pointer transition-all">
                            <input type="checkbox" bind:checked={selectedTargets.routes} class="mt-1 rounded border-slate-300 text-red-600 focus:ring-red-500" />
                            <div>
                                <span class="block font-semibold text-slate-800 dark:text-slate-200 text-sm">Route Cache</span>
                                <span class="text-xs text-slate-500 dark:text-slate-400">Membersihkan cache pemetaan URL dan API route (route:clear).</span>
                            </div>
                        </label>
                    </div>

                    <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                            <ShieldCheck class="w-4 h-4 text-emerald-500" />
                            Standard Laravel Artisan Execution
                        </div>

                        <button 
                            onclick={runDeepClean}
                            disabled={isCleaning}
                            class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 text-white font-semibold text-sm rounded-xl shadow-md shadow-red-500/20 disabled:opacity-50 transition-all cursor-pointer">
                            {#if isCleaning}
                                <RefreshCw class="w-4 h-4 animate-spin" />
                                <span>Proses Pembersihan...</span>
                            {:else}
                                <Trash2 class="w-4 h-4" />
                                <span>Jalankan Deep Clean Sekarang</span>
                            {/if}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</AdminLayout>
