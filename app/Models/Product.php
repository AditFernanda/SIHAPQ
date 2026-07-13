<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Part/produk yang menjadi objek pengecekan QC.
 */
class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_code',
        'process_name',
        'name',
        'customer_name',
        'status',
    ];

    public function qcInspections()
    {
        return $this->hasMany(QcInspection::class);
    }

    public function processName(): string
    {
        return $this->process_name ?: 'Proses Utama';
    }

    public function displayName(): string
    {
        return "{$this->name} - {$this->product_code} - {$this->processName()}";
    }
}
