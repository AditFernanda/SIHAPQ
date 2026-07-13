<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machine_maintenance', function (Blueprint $table) {
            $table->id();
            $table->string('maintenance_code')->unique();
            $table->foreignId('machine_id')->constrained('machines')->onDelete('cascade');
            $table->enum('type', ['preventive', 'corrective'])->default('preventive');
            $table->text('description');
            $table->dateTime('maintenance_date');
            $table->dateTime('completed_date')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_maintenance');
    }
};
