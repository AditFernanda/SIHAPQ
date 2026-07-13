<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qc_inspections', function (Blueprint $table) {
            $table->id();
            $table->string('inspection_code')->unique();
            $table->foreignId('production_order_id')->constrained('production_orders')->onDelete('cascade');
            $table->foreignId('qc_inspector_id')->constrained('qc_inspectors')->onDelete('cascade');
            $table->integer('quantity_inspected');
            $table->integer('quantity_passed');
            $table->integer('quantity_failed');
            $table->decimal('pass_percentage', 5, 2);
            $table->enum('status', ['pass', 'fail'])->default('pass');
            $table->text('defects')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('inspection_date');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_inspections');
    }
};
