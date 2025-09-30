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
        'branches_id',
        'flag_deleted',
    ];

    /**
     * Relasi: Kandang ini milik satu Warehouse (cabang).
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branches_id');
    }
    
}