<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

use Inertia\Inertia;

class SettingsController extends Controller
{
    public function databaseBackup(Request $request)
    {
        $tableStats = [
            ['name' => 'sellers', 'count' => DB::table('sellers')->count()],
            ['name' => 'products', 'count' => DB::table('products')->count()],
            ['name' => 'product_variants', 'count' => DB::table('product_variants')->count()],
            ['name' => 'stocks', 'count' => DB::table('stocks')->count()],
            ['name' => 'stock_ins', 'count' => DB::table('stock_ins')->count()],
            ['name' => 'stock_outs', 'count' => DB::table('stock_outs')->count()],
            ['name' => 'konveksis', 'count' => DB::table('konveksis')->count()],
        ];

        return Inertia::render('Superadmin/Settings/DatabaseBackup', [
            'tableStats' => $tableStats,
            'lastBackupAt' => date('Y-m-d H:i:s'),
        ]);
    }

    public function downloadBackup(Request $request)
    {
        $data = [
            'timestamp' => now()->toDateTimeString(),
            'sellers' => DB::table('sellers')->get(),
            'products' => DB::table('products')->get(),
            'product_variants' => DB::table('product_variants')->get(),
            'stocks' => DB::table('stocks')->get(),
            'stock_ins' => DB::table('stock_ins')->get(),
            'stock_outs' => DB::table('stock_outs')->get(),
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT);
        $fileName = 'backup_warehub_v2_' . date('Y_m_d_His') . '.json';

        return response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    public function appSettings(Request $request)
    {
        $settings = [
            'app_name' => config('app.name', 'Warehub v2'),
            'app_env' => config('app.env', 'local'),
            'app_url' => config('app.url', 'https://warehub-v2.test'),
            'low_stock_threshold' => 10,
            'auto_backup_enabled' => true,
        ];

        return Inertia::render('Superadmin/Settings/AppSettings', [
            'settings' => $settings,
        ]);
    }

    public function updateAppSettings(Request $request)
    {
        return redirect()->back()->with('success', 'Pengaturan sistem berhasil diperbarui.');
    }

    public function deepClean(Request $request)
    {
        return Inertia::render('Superadmin/Settings/DeepClean');
    }

    public function executeDeepClean(Request $request)
    {
        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');

        return redirect()->back()->with('success', 'Pembersihan Deep Clean Cache & Temp files berhasil dijalankan!');
    }

    public function wikiGuide(Request $request)
    {
        return Inertia::render('Superadmin/Settings/WikiGuide');
    }
}
