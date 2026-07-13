<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;

/**
 * PIC QC (petugas pemeriksa); PIN-nya dipakai memverifikasi input dan disimpan ter-hash.
 */
class QcInspector extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'employee_id',
        'name',
        'pin',
        'status',
        'notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function inspections()
    {
        return $this->hasMany(QcInspection::class);
    }

    public function setPinAttribute(?string $value): void
    {
        // PIN kosong saat edit berarti PIN lama tetap dipakai.
        if ($value === null || $value === '') {
            return;
        }

        $this->attributes['pin'] = Hash::needsRehash($value) ? Hash::make($value) : $value;
    }

    public function verifyPin(string $pin): bool
    {
        // Semua PIN dijamin ter-hash (setter di atas + migrasi 2026_05_23).
        // Nilai yang bukan hash (data lama/rusak) ditolak dengan aman, bukan
        // dipaksa dibandingkan — mencegah error sekaligus menutup jalur teks biasa.
        if (! $this->pin || ! Hash::isHashed($this->pin)) {
            return false;
        }

        return Hash::check($pin, $this->pin);
    }
}
