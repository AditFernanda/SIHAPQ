<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Jenis NG (kategori defect) yang dapat dipilih saat hasil cek NG.
 */
class QcNgType extends Model
{
    protected $fillable = [
        'name',
        'status',
    ];

    public function qcInspections()
    {
        return $this->belongsToMany(QcInspection::class, 'qc_inspection_ng_type')
            ->withTimestamps();
    }
}
