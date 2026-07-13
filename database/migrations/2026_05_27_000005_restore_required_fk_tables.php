<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique()->nullable();
                $table->string('contact_person')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->text('address')->nullable();
                $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('production_orders')) {
            Schema::create('production_orders', function (Blueprint $table) {
                $table->id();
                $table->string('po_number')->unique();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->foreignId('machine_id')->constrained('machines')->cascadeOnDelete();
                $table->integer('quantity')->default(0);
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->string('status')->default('pending');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        // These tables are retained only because qc_inspections still has a
        // nullable legacy foreign key to production_orders.
    }
};
