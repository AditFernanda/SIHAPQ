<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Akun login aplikasi: admin, supervisor, QC, atau akun mesin.
 */
class User extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'username',
        'name',
        'email',
        'password',
        'role',
        'status',
    ];

    protected $hidden = [
        'password',
    ];

    public function machines()
    {
        return $this->belongsToMany(Machine::class, 'user_machines');
    }

    /**
     * Bentuk email internal dari username. Email hanya identitas internal
     * (login memakai username), domainnya diatur lewat config qc.mail_domain.
     */
    public static function internalEmail(string $username): string
    {
        return strtolower(preg_replace('/[^a-z0-9._-]+/i', '', $username)).'@'.config('qc.mail_domain');
    }

    public function qcInspector()
    {
        return $this->hasOne(QcInspector::class);
    }
}
