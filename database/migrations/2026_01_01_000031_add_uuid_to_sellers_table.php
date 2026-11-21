<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('sellers', 'uuid')) {
            Schema::table('sellers', function (Blueprint $table) {
                $table->uuid('uuid')->nullable()->unique()->after('id');
            });

            // Populate UUID for existing records
            $sellers = DB::table('sellers')->whereNull('uuid')->get();
            foreach ($sellers as $seller) {
                DB::table('sellers')
                    ->where('id', $seller->id)
                    ->update(['uuid' => (string) Str::uuid()]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sellers', 'uuid')) {
            Schema::table('sellers', function (Blueprint $table) {
                $table->dropColumn('uuid');
            });
        }
    }
};

