<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qc_ab_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('status', 20)->default('aktif');
            $table->timestamps();
        });

        Schema::create('qc_inspection_ab_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qc_inspection_id')->constrained('qc_inspections')->cascadeOnDelete();
            $table->foreignId('qc_ab_type_id')->constrained('qc_ab_types')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['qc_inspection_id', 'qc_ab_type_id'], 'qc_inspection_ab_type_unique');
            $table->index('qc_ab_type_id', 'qc_inspection_ab_type_type_idx');
        });

        $now = now();
        $defaultTypes = ['Burry/Burr', 'Dented', 'Scratch', 'Crack', 'Dimensi NG', 'Deform', 'Karat', 'Dirty/Oil', 'Lainnya'];
        foreach ($defaultTypes as $type) {
            DB::table('qc_ab_types')->updateOrInsert(
                ['name' => $type],
                ['status' => 'aktif', 'created_at' => $now, 'updated_at' => $now]
            );
        }

        if (Schema::hasColumn('qc_inspections', 'ab_type')) {
            $typeIds = DB::table('qc_ab_types')->pluck('id', 'name');
            DB::table('qc_inspections')
                ->whereNotNull('ab_type')
                ->where('ab_type', '<>', '')
                ->orderBy('id')
                ->select(['id', 'ab_type'])
                ->chunkById(500, function ($inspections) use ($typeIds, $now) {
                    $rows = [];
                    foreach ($inspections as $inspection) {
                        $typeName = trim((string) $inspection->ab_type);
                        $typeId = $typeIds[$typeName] ?? null;
                        if (! $typeId) {
                            continue;
                        }

                        $rows[] = [
                            'qc_inspection_id' => $inspection->id,
                            'qc_ab_type_id' => $typeId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if ($rows !== []) {
                        DB::table('qc_inspection_ab_type')->insertOrIgnore($rows);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_inspection_ab_type');
        Schema::dropIfExists('qc_ab_types');
    }
};
