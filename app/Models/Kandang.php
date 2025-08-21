<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kandang extends Model
{
    use HasFactory;

    /**
     */
    protected $fillable = [
        'kode_room',
        'quota',
        'status',
        'warehouse_id',
        'company_id',
        'flag_deleted',
    ];

    /**
     * Relasi: Kandang ini milik satu Warehouse (cabang).
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Relasi: Kandang ini milik satu Company (perusahaan).
     * Ini berguna untuk query tingkat tinggi.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}