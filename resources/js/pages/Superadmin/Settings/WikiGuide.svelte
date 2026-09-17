<script>
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import { 
        BookOpen, Search, FileText, DollarSign, ShieldCheck, 
        Layers, RefreshCw, Database, HelpCircle, ChevronRight, CheckCircle2
    } from '@lucide/svelte';

    let activeCategory = $state('finance');
    let searchQuery = $state('');

    const guides = [
        {
            id: 'finance',
            category: 'Keuangan & Hutang Seller',
            icon: DollarSign,
            color: 'from-emerald-500 to-teal-600',
            topics: [
                {
                    title: 'Perhitungan Automatic Seller Debt',
                    summary: 'Bagaimana rumus modal seller dihitung secara otomatis berdasarkan transaksi fisik gudang.',
                    content: `Hutang Seller / Tagihan Modal Seller dihitung dengan formula:
                    
[Total Stock In HPP] - [Total Stock Out HPP] + [Adjustments]

• Stock In HPP: Total nilai barang yang masuk ke gudang (Qty In × HPP).
• Stock Out HPP: Total nilai barang yang keluar dari gudang (Qty Out × HPP).
• Adjustment: Penyesuaian manual (misalnya return, kerusakan, atau kompensasi).`
                },
                {
                    title: 'Pencatatan Penyesuaian Saldo (Adjustments)',
                    summary: 'Langkah menambahkan penyesuaian penambahan atau pengurangan saldo seller.',
                    content: `Admin dapat menambahkan adjustment di menu Superadmin > Keuangan > Hutang Seller > Detail Seller:
1. Klik tombol "+ Penyesuaian Saldo".
2. Pilih Tipe: Credit (Mengurangi Hutang) atau Debit (Menambah Hutang).
3. Masukkan Nominal dan Catatan penjelasan.
4. Simpan untuk memperbarui total saldo secara real-time.`
                }
            ]
        },
        {
            id: 'konveksi',
            category: 'Tagihan Vendor Konveksi',
            icon: Layers,
            color: 'from-amber-500 to-orange-600',
            topics: [
                {
                    title: 'Alur Pembayaran Vendor Konveksi (DP & Pelunasan)',
                    summary: 'Tata cara pencatatan tagihan konveksi beserta bukti transfer.',
                    content: `Pencatatan pembayaran tagihan vendor konveksi dilakukan di menu Superadmin > Keuangan > Tagihan Konveksi:
1. Pilih batch/nota konveksi.
2. Klik tombol "Catat Pembayaran".
3. Masukkan Jumlah Bayar, Metode (DP / Pelunasan), dan Unggah Foto Bukti Bayar.
4. Status akan otomatis berubah menjadi "Partially Paid" atau "Paid Off".`
                }
            ]
        },
        {
            id: 'datacleaning',
            category: 'Data Cleaning Tools (TikTok & Shopee)',
            icon: RefreshCw,
            color: 'from-indigo-500 to-purple-600',
            topics: [
                {
                    title: 'Workflow Urutan Data Cleaning 8 Tahap',
                    summary: 'Panduan lengkap pengolahan file pesanan TikTok/Shopee dari Excel ke JSON.',
                    content: `Urutan workflow yang disarankan:
1. Excel to JSON Converter
2. Short JSON TikTok (Standardization)
3. Split Rows / Merge Parts
4. Komparasi Qty & Matching SKU
5. Generator Rangking Clean Flow & PDF Report`
                }
            ]
        },
        {
            id: 'system',
            category: 'Backup & Pengaturan Sistem',
            icon: Database,
            color: 'from-blue-500 to-cyan-600',
            topics: [
                {
                    title: 'Backup & Restore Database JSON',
                    summary: 'Cara mengunduh snapshot seluruh tabel penting secara aman.',
                    content: `Superadmin dapat membuat cadangan data kapan saja di menu Settings > Database Backup:
1. Klik "Download Backup JSON".
2. Simpan file .json berstempel waktu di penyimpanan lokal aman.
3. File berisi snapshot seller, produk, varian, stok, dan histori barang masuk/keluar.`
                },
                {
                    title: 'Pembersihan Deep Clean Cache',
                    summary: 'Mengatasi sistem yang lambat atau perubahan tampilan yang belum ter-render.',
                    content: `Gunakan menu Settings > Deep Clean jika mengalami kelambatan atau perbaikan bug yang butuh clearing cache Artisan (view:clear, config:clear, route:clear).`
                }
            ]
        }
    ];

    let currentGuide = $derived(guides.find(g => g.id === activeCategory) || guides[0]);
    let filteredTopics = $derived(
        currentGuide.topics.filter(t => 
            t.title.toLowerCase().includes(searchQuery.toLowerCase()) || 
            t.summary.toLowerCase().includes(searchQuery.toLowerCase())
        )
    );
</script>

<AdminLayout title="Wiki & Panduan Operasional - Warehub v2">
    <div class="max-w-6xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 dark:from-slate-900 dark:via-indigo-950 dark:to-slate-900 p-6 rounded-2xl text-white shadow-xl relative overflow-hidden">
            <div class="absolute right-0 top-0 translate-x-4 -translate-y-4 opacity-10 pointer-events-none">
                <BookOpen class="w-64 h-64" />
            </div>
            <div class="relative z-10 space-y-1">
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 bg-white/20 text-white text-xs font-semibold rounded-full backdrop-blur-md">Documentation</span>
                    <span class="text-xs text-blue-200">Warehub v2 User Manual</span>
                </div>
                <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight flex items-center gap-3">
                    <BookOpen class="w-7 h-7 text-blue-200" />
                    Wiki & Panduan Operasional Sistem
                </h1>
                <p class="text-blue-100 text-sm max-w-xl">
                    Pusat bantuan teknis, workflow keuangan, rumus perhitungan hutang seller, serta alur data cleaning untuk Superadmin.
                </p>
            </div>
            <div class="relative z-10 w-full md:w-72">
                <div class="relative">
                    <Search class="w-4 h-4 text-slate-400 absolute left-3 top-3" />
                    <input 
                        type="text" 
                        bind:value={searchQuery}
                        placeholder="Cari topik atau rumus..." 
                        class="w-full pl-9 pr-4 py-2.5 bg-white/10 dark:bg-slate-800/80 backdrop-blur-md border border-white/20 text-white placeholder-blue-200 text-sm rounded-xl focus:outline-none focus:ring-2 focus:ring-white/40" />
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <!-- Sidebar Navigation -->
            <div class="md:col-span-1 space-y-2">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 px-2 mb-2">Kategori Panduan</h3>
                {#each guides as guide}
                    <button 
                        onclick={() => activeCategory = guide.id}
                        class="w-full flex items-center justify-between p-3.5 rounded-xl border text-left font-semibold text-sm transition-all cursor-pointer {activeCategory === guide.id ? 'bg-white dark:bg-slate-900 border-indigo-500 text-indigo-600 dark:text-indigo-400 shadow-sm ring-1 ring-indigo-500/20' : 'bg-slate-50/50 dark:bg-slate-950/40 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/50'}">
                        <div class="flex items-center gap-3">
                            <div class="p-2 rounded-lg bg-gradient-to-br {guide.color} text-white">
                                <guide.icon class="w-4 h-4" />
                            </div>
                            <span class="text-xs sm:text-sm">{guide.category}</span>
                        </div>
                        <ChevronRight class="w-4 h-4 text-slate-400" />
                    </button>
                {/each}
            </div>

            <!-- Content Area -->
            <div class="md:col-span-3 space-y-4">
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-6">
                    <div class="flex items-center gap-3 border-b border-slate-100 dark:border-slate-800 pb-4">
                        <div class="p-3 rounded-xl bg-gradient-to-br {currentGuide.color} text-white shadow-md">
                            <currentGuide.icon class="w-6 h-6" />
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100">{currentGuide.category}</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Topik dokumentasi dan panduan praktis</p>
                        </div>
                    </div>

                    <div class="space-y-6">
                        {#if filteredTopics.length === 0}
                            <div class="text-center py-10 text-slate-400 dark:text-slate-500 text-sm">
                                Tidak ada topik yang sesuai dengan pencarian "{searchQuery}".
                            </div>
                        {:else}
                            {#each filteredTopics as topic}
                                <div class="bg-slate-50/60 dark:bg-slate-950/50 border border-slate-200/80 dark:border-slate-800/80 rounded-xl p-5 space-y-3">
                                    <h4 class="font-bold text-slate-800 dark:text-slate-100 text-base flex items-center gap-2">
                                        <CheckCircle2 class="w-5 h-5 text-indigo-500 shrink-0" />
                                        {topic.title}
                                    </h4>
                                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                                        {topic.summary}
                                    </p>
                                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-lg p-4 font-mono text-xs text-slate-700 dark:text-slate-300 whitespace-pre-line leading-relaxed">
                                        {topic.content}
                                    </div>
                                </div>
                            {/each}
                        {/if}
                    </div>
                </div>
            </div>
        </div>
    </div>
</AdminLayout>
