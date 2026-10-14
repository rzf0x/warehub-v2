<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stock_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_logs', 'product_variant_id')) {
                $table->unsignedBigInteger('product_variant_id')->nullable()->after('stock_id');
                $table->index('product_variant_id');
            }
            if (!Schema::hasColumn('stock_logs', 'warehouse_id')) {
                $table->unsignedBigInteger('warehouse_id')->nullable()->after('product_variant_id');
                $table->index('warehouse_id');
            }
            if (!Schema::hasColumn('stock_logs', 'qty_before')) {
                $table->integer('qty_before')->nullable()->after('quantity_after');
            }
            if (!Schema::hasColumn('stock_logs', 'qty_change')) {
                $table->integer('qty_change')->nullable()->after('qty_before');
            }
            if (!Schema::hasColumn('stock_logs', 'qty_after')) {
                $table->integer('qty_after')->nullable()->after('qty_change');
            }

            // Relaksasi kolom lama agar nullable jika insert baru hanya mengisi kolom baru
            if (Schema::hasColumn('stock_logs', 'stock_id')) {
                $table->unsignedBigInteger('stock_id')->nullable()->change();
            }
            if (Schema::hasColumn('stock_logs', 'type')) {
                $table->string('type')->nullable()->change();
            }
            if (Schema::hasColumn('stock_logs', 'quantity_before')) {
                $table->integer('quantity_before')->nullable()->change();
            }
            if (Schema::hasColumn('stock_logs', 'quantity_after')) {
                $table->integer('quantity_after')->nullable()->change();
            }
            if (Schema::hasColumn('stock_logs', 'change_amount')) {
                $table->integer('change_amount')->nullable()->change();
            }
        });

        // Backfill data lama 65k+ baris dari tabel stocks
        try {
            DB::statement("
                UPDATE stock_logs
                INNER JOIN stocks ON stock_logs.stock_id = stocks.id
                SET 
                    stock_logs.product_variant_id = stocks.product_variant_id,
                    stock_logs.warehouse_id = stocks.warehouse_id,
                    stock_logs.qty_before = stock_logs.quantity_before,
                    stock_logs.qty_change = stock_logs.change_amount,
                    stock_logs.qty_after = stock_logs.quantity_after
                WHERE stock_logs.product_variant_id IS NULL
            ");
        } catch (\Throwable $e) {
            // Abaikan jika tabel kosong atau sintaks tidak didukung
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_logs', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('stock_logs', 'product_variant_id')) $cols[] = 'product_variant_id';
            if (Schema::hasColumn('stock_logs', 'warehouse_id')) $cols[] = 'warehouse_id';
            if (Schema::hasColumn('stock_logs', 'qty_before')) $cols[] = 'qty_before';
            if (Schema::hasColumn('stock_logs', 'qty_change')) $cols[] = 'qty_change';
            if (Schema::hasColumn('stock_logs', 'qty_after')) $cols[] = 'qty_after';

            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
