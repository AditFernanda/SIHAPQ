<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Mesin produksi; tiap mesin punya satu akun login (role akun_mesin).
 */
class Machine extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'machine_code',
        'name',
        'department_id',
        'status',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_machines');
    }

    public function qcInspections()
    {
        return $this->hasMany(QcInspection::class);
    }

    public function latestQcInspection()
    {
        return $this->hasOne(QcInspection::class)->ofMany([
            'inspection_date' => 'max',
            'start_time' => 'max',
            'id' => 'max',
        ]);
    }

    public function displayName(): string
    {
        return $this->machine_code ?: ($this->name ?: '-');
    }

    public function detailName(): string
    {
        if (! $this->name || $this->name === $this->machine_code) {
            return $this->displayName();
        }

        return "{$this->machine_code} - {$this->name}";
    }
}
