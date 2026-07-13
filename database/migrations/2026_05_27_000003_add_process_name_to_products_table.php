<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['product_code']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('process_name')->default('Proses Utama')->after('product_code');
        });

        DB::table('products')->whereNull('process_name')->update(['process_name' => 'Proses Utama']);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('process_name');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unique('product_code');
        });
    }
};
