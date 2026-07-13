<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qc_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_code')->unique();
            $table->foreignId('production_order_id')->constrained('production_orders')->onDelete('cascade');
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->onDelete('set null');
            $table->integer('total_quantity');
            $table->integer('total_passed');
            $table->integer('total_failed');
            $table->decimal('overall_pass_percentage', 5, 2);
            $table->enum('final_status', ['pass', 'fail'])->default('pass');
            $table->text('supervisor_notes')->nullable();
            $table->dateTime('report_date');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_reports');
    }
};
