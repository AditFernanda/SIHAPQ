<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qc_inspection_id')->constrained('qc_inspections')->onDelete('cascade');
            $table->foreignId('defect_id')->nullable()->constrained('qc_defects')->onDelete('set null');
            $table->integer('item_number');
            $table->enum('status', ['ok', 'reject'])->default('ok');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_details');
    }
};
