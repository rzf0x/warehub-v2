<?php

use App\Http\Controllers\Superadmin\DashboardController;
use App\Http\Controllers\Superadmin\PengadaanController;
use App\Http\Controllers\Superadmin\PermissionController;
use App\Http\Controllers\Superadmin\RoleController;
use App\Http\Controllers\Superadmin\StoreController;
use App\Http\Controllers\Superadmin\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\Superadmin\BrandController;
use App\Http\Controllers\Superadmin\CategoryController;
use App\Http\Controllers\Superadmin\KonveksiController;
use App\Http\Controllers\Superadmin\ManualGlobalProductController;
use App\Http\Controllers\Superadmin\MergerSkuController;
use App\Http\Controllers\Superadmin\PeriodController;
use App\Http\Controllers\Superadmin\ProductBundleController;
use App\Http\Controllers\Superadmin\ProductBySellerController;
use App\Http\Controllers\Superadmin\ProductController;
use App\Http\Controllers\Superadmin\VariantTemplateController;
use App\Http\Controllers\Superadmin\WarehouseController;

use App\Http\Controllers\Superadmin\StockController;
use App\Http\Controllers\Superadmin\StockInController;
use App\Http\Controllers\Superadmin\StockOpnameController;
use App\Http\Controllers\Superadmin\StockOutController;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('superadmin')->name('superadmin.')->group(function () {
        // Dashboard Superadmin
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // User Management Routes
        Route::prefix('user-management')->name('user-management.')->group(function () {
            // Users
            Route::get('/users/pdf', [UserController::class, 'exportPdf'])->name('users.pdf');
            Route::put('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
            Route::resource('users', UserController::class);

            // Roles & Permissions
            Route::put('/roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->name('roles.sync-permissions');
            Route::resource('roles', RoleController::class);
            Route::resource('permissions', PermissionController::class)->only(['index', 'store', 'destroy']);

            // Stores
            Route::resource('stores', StoreController::class);

            // Tim Pengadaan
            Route::resource('pengadaan', PengadaanController::class);
        });

        // FASE 2 & 3: Warehouse Master, Catalog & Mutations
        Route::prefix('warehouse')->name('warehouse.')->group(function () {
            // Master Data
            Route::resource('warehouses', WarehouseController::class)->except(['create', 'edit', 'show']);
            Route::resource('categories', CategoryController::class)->except(['create', 'edit', 'show']);
            Route::resource('brands', BrandController::class)->except(['create', 'edit', 'show']);
            Route::resource('periods', PeriodController::class)->except(['create', 'edit', 'show']);
            Route::get('periods/{period}/stock-in', [PeriodController::class, 'stockIn'])->name('periods.stock-in');
            Route::get('periods/{period}/stock-out', [PeriodController::class, 'stockOut'])->name('periods.stock-out');
            Route::resource('konveksis', KonveksiController::class)->except(['create', 'edit', 'show']);

            // Katalog Produk & Bundle
            Route::get('products/pdf', [ProductController::class, 'exportPdf'])->name('products.pdf');
            Route::get('products/bundle/create', [ProductBundleController::class, 'create'])->name('products.bundle.create');
            Route::post('products/bundle', [ProductBundleController::class, 'store'])->name('products.bundle.store');
            Route::get('products/by-seller', [ProductBySellerController::class, 'index'])->name('products.by-seller');
            Route::resource('products', ProductController::class);

            // Template Varian Massal
            Route::get('bulk-variant', [VariantTemplateController::class, 'index'])->name('bulk-variant.index');
            Route::post('bulk-variant', [VariantTemplateController::class, 'store'])->name('bulk-variant.store');
            Route::delete('bulk-variant/{template}', [VariantTemplateController::class, 'destroy'])->name('bulk-variant.destroy');

            // Pemetaan SKU
            Route::resource('mapping/manual-global', ManualGlobalProductController::class)->except(['create', 'edit', 'show'])->names('mapping.manual-global');

            // FASE 3: Inventory Mutations
            // Stock In
            Route::get('stock-in/seller-products', [StockInController::class, 'getSellerProducts'])->name('stock-in.seller-products');
            Route::get('stock-in/search-variants', [StockInController::class, 'searchVariants'])->name('stock-in.search-variants');
            Route::get('stock-in/report/pdf', [StockInController::class, 'exportReport'])->name('stock-in.report.pdf');
            Route::get('stock-in/{stockIn}/pdf', [StockInController::class, 'streamSuratJalan'])->name('stock-in.pdf');
            Route::resource('stock-in', StockInController::class)->except(['edit', 'update', 'show']);

            // Stock Out
            Route::get('stock-out/summary/pdf', [StockOutController::class, 'exportSummary'])->name('stock-out.summary.pdf');
            Route::get('stock-out/chart', [StockOutController::class, 'chart'])->name('stock-out.chart');
            Route::get('stock-out/settlement', [StockOutController::class, 'settlement'])->name('stock-out.settlement');
            Route::get('stock-out/seller-detail/{seller}', [StockOutController::class, 'sellerDetail'])->name('stock-out.seller-detail');
            Route::get('stock-out/{stockOut}/pdf', [StockOutController::class, 'streamSuratJalan'])->name('stock-out.pdf');
            Route::post('stock-out/import-chunked', [StockOutController::class, 'importChunked'])->name('stock-out.import-chunked');
            Route::post('stock-out/quick-create-products', [StockOutController::class, 'quickCreateProducts'])->name('stock-out.quick-create-products');
            Route::resource('stock-out', StockOutController::class)->except(['show']);

            // Stock Opname
            Route::get('stock-opname/{stockOpname}/pdf', [StockOpnameController::class, 'streamReport'])->name('stock-opname.pdf');
            Route::resource('stock-opname', StockOpnameController::class)->except(['edit', 'update', 'show']);

            // Realtime Stocks & Logs
            Route::get('stocks/logs', [StockController::class, 'logs'])->name('stocks.logs');
            Route::get('stocks', [StockController::class, 'index'])->name('stocks.index');
        });

        // FASE 5: Finance & Reports
        Route::prefix('finance')->name('finance.')->group(function () {
            // Seller Debt
            Route::get('seller-debt', [\App\Http\Controllers\Superadmin\SellerDebtController::class, 'index'])->name('seller-debt.index');
            Route::get('seller-debt/chart', [\App\Http\Controllers\Superadmin\SellerDebtController::class, 'chart'])->name('seller-debt.chart');
            Route::get('seller-debt/konveksi-simulation', [\App\Http\Controllers\Superadmin\SellerDebtController::class, 'konveksiSimulation'])->name('seller-debt.konveksi-simulation');
            Route::post('seller-debt/adjustment', [\App\Http\Controllers\Superadmin\SellerDebtController::class, 'storeAdjustment'])->name('seller-debt.adjustment.store');
            Route::delete('seller-debt/adjustment/{adjustment}', [\App\Http\Controllers\Superadmin\SellerDebtController::class, 'destroyAdjustment'])->name('seller-debt.adjustment.destroy');
            Route::get('seller-debt/{seller}', [\App\Http\Controllers\Superadmin\SellerDebtController::class, 'show'])->name('seller-debt.show');

            // Tagihan Konveksi
            Route::get('konveksi-debt', [\App\Http\Controllers\Superadmin\KonveksiDebtController::class, 'index'])->name('konveksi-debt.index');
            Route::get('konveksi-debt/{konveksi}', [\App\Http\Controllers\Superadmin\KonveksiDebtController::class, 'show'])->name('konveksi-debt.show');
            Route::post('konveksi-debt/{konveksi}/payment', [\App\Http\Controllers\Superadmin\KonveksiDebtController::class, 'storePayment'])->name('konveksi-debt.payment.store');
            Route::delete('konveksi-debt/payment/{payment}', [\App\Http\Controllers\Superadmin\KonveksiDebtController::class, 'destroyPayment'])->name('konveksi-debt.payment.destroy');

            // Laporan Penghasilan Seller
            Route::get('seller-income-report', [\App\Http\Controllers\Superadmin\SellerIncomeReportController::class, 'index'])->name('seller-income-report.index');
            Route::get('seller-income-report/pdf', [\App\Http\Controllers\Superadmin\SellerIncomeReportController::class, 'exportPdf'])->name('seller-income-report.pdf');

            // Laporan Keuntungan Per-Periode
            Route::get('profit-report', [\App\Http\Controllers\Superadmin\ProfitReportController::class, 'index'])->name('profit-report.index');
            Route::get('profit-report/pdf', [\App\Http\Controllers\Superadmin\ProfitReportController::class, 'exportPdf'])->name('profit-report.pdf');

            // Bukti Transfer
            Route::put('proof-transfers/{proofTransfer}/status', [\App\Http\Controllers\Superadmin\ProofTransferController::class, 'updateStatus'])->name('proof-transfers.update-status');
            Route::resource('proof-transfers', \App\Http\Controllers\Superadmin\ProofTransferController::class)->except(['create', 'edit', 'show']);
        });

        // FASE 5: Settings, Data Cleaning & Tools
        Route::prefix('settings')->name('settings.')->group(function () {
            // Data Cleaning Shopee Engine
            Route::get('cleaning-data', [\App\Http\Controllers\Superadmin\CleaningDataShopeeController::class, 'index'])->name('cleaning-data');
            Route::post('cleaning-data/process', [\App\Http\Controllers\Superadmin\CleaningDataShopeeController::class, 'process'])->name('cleaning-data.process');

            // Data Cleaning TikTok Shop Engine
            Route::get('cleaning-data-tiktok', [\App\Http\Controllers\Superadmin\CleaningDataTiktokController::class, 'index'])->name('cleaning-data-tiktok');
            Route::post('cleaning-data-tiktok/process', [\App\Http\Controllers\Superadmin\CleaningDataTiktokController::class, 'process'])->name('cleaning-data-tiktok.process');

            // Database Backup
            Route::get('database-backup', [\App\Http\Controllers\Superadmin\SettingsController::class, 'databaseBackup'])->name('database-backup.index');
            Route::get('database-backup/download', [\App\Http\Controllers\Superadmin\SettingsController::class, 'downloadBackup'])->name('database-backup.download');

            // App Settings
            Route::get('app-settings', [\App\Http\Controllers\Superadmin\SettingsController::class, 'appSettings'])->name('app-settings.index');
            Route::post('app-settings', [\App\Http\Controllers\Superadmin\SettingsController::class, 'updateAppSettings'])->name('app-settings.update');

            // Deep Clean
            Route::get('deep-clean', [\App\Http\Controllers\Superadmin\SettingsController::class, 'deepClean'])->name('deep-clean.index');
            Route::post('deep-clean/execute', [\App\Http\Controllers\Superadmin\SettingsController::class, 'executeDeepClean'])->name('deep-clean.execute');

            // Wiki Guide
            Route::get('wiki-guide', [\App\Http\Controllers\Superadmin\SettingsController::class, 'wikiGuide'])->name('wiki-guide.index');
        });
    });

    // PORTAL SELLER / MITRA MOBILE WEB VIEW
    Route::prefix('seller')->name('seller.')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Seller\DashboardController::class, 'index'])->name('dashboard');

        // Store Management
        Route::resource('stores', \App\Http\Controllers\Seller\StoreController::class)->except(['create', 'edit', 'show']);

        // FASE 2: Product Catalog & Realtime Stock Monitor
        Route::get('products', [\App\Http\Controllers\Seller\ProductController::class, 'index'])->name('products.index');
        Route::get('stocks', [\App\Http\Controllers\Seller\StockController::class, 'index'])->name('stocks.index');
        Route::get('stocks/product/{product}', [\App\Http\Controllers\Seller\StockDetailController::class, 'showProduct'])->name('stocks.product-detail');

        // FASE 3: Mobile Purchase Orders (PO) Wizard & Invoice PDF
        Route::get('purchase-orders/{purchaseOrder}/pdf', [\App\Http\Controllers\Seller\PurchaseOrderController::class, 'pdf'])->name('purchase-orders.pdf');
        Route::resource('purchase-orders', \App\Http\Controllers\Seller\PurchaseOrderController::class)->except(['show']);

        // FASE 4: Mobile Sales Analytics & Ranking Best Seller
        Route::get('sales', [\App\Http\Controllers\Seller\SalesController::class, 'index'])->name('sales.index');
        Route::get('sales/top-products', [\App\Http\Controllers\Seller\TopProductsController::class, 'index'])->name('sales.top-products');

        // FASE 5: Transparansi Hutang Modal & Mobile Camera Receipt Upload
        Route::get('debt', [\App\Http\Controllers\Seller\DebtController::class, 'index'])->name('debt.index');
        Route::get('debt/settlement', [\App\Http\Controllers\Seller\SettlementController::class, 'create'])->name('debt.settlement.create');
        Route::post('debt/settlement', [\App\Http\Controllers\Seller\SettlementController::class, 'store'])->name('debt.settlement.store');
    });
});

require __DIR__.'/settings.php';

Route::fallback(function () {
    return Inertia::render('Error', ['status' => 404]);
});
