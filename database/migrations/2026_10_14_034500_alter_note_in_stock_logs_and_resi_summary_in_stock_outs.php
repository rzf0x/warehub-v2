<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_logs', function (Blueprint $table) {
            $table->text('note')->nullable()->change();
        });

        Schema::table('stock_outs', function (Blueprint $table) {
            $table->longText('resi_summary')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('stock_logs', function (Blueprint $table) {
            $table->string('note', 255)->nullable()->change();
        });

        Schema::table('stock_outs', function (Blueprint $table) {
            $table->string('resi_summary', 255)->nullable()->change();
        });
    }
};
