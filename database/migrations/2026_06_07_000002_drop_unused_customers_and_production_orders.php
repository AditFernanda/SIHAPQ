<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Buang sisa skema yang tidak dipakai aplikasi:
    // - kolom qc_inspections.production_order_id selalu null (tanpa model)
    // - tabel production_orders dan customers tidak punya model/relasi aktif
    public function up(): void
    {
        if (Schema::hasColumn('qc_inspections', 'production_order_id')) {
            // Drop foreign key dan kolomnya sekaligus supaya sqlite membangun
            // ulang tabel tanpa menyisakan referensi FK ke kolom yang dihapus.
            Schema::table('qc_inspections', function (Blueprint $table) {
                $table->dropForeign(['production_order_id']);
                $table->dropColumn('production_order_id');
            });
        }

        Schema::dropIfExists('production_orders');
        Schema::dropIfExists('customers');
    }

    public function down(): void
    {
        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('status')->default('aktif');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('production_orders')) {
            Schema::create('production_orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_code')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasColumn('qc_inspections', 'production_order_id')) {
            Schema::table('qc_inspections', function (Blueprint $table) {
                $table->unsignedBigInteger('production_order_id')->nullable()->after('inspection_code');
            });
        }
    }
};
