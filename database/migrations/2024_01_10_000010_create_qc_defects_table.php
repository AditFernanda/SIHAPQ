<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qc_defects', function (Blueprint $table) {
            $table->id();
            $table->string('defect_code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('severity', ['minor', 'major', 'critical'])->default('major');
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_defects');
    }
};
