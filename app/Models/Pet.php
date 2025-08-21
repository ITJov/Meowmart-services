<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\PetType;


class Pet extends Model
{
    use HasFactory;

    /**
     * Kolom yang boleh diisi secara massal.
     * Kita sesuaikan dengan kolom-kolom di migrasi.
     */
    protected $fillable = [
        'name',
        'customer_id',
        'pet_type_id',
        'breed',
        'color',
        'age',
        'photo',
        'warehouse_id',
        'company_id',
    ];

    /**
     
     */
    public function customer()
    {
        
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * Relasi: Hewan ini memiliki satu Jenis Hewan (PetType).
     * Nama method 'petType' lebih deskriptif.
     */
    public function petType()
    {
        // Menghubungkan ke kolom 'pet_type_id' di tabel 'pets'
        return $this->belongsTo(PetType::class, 'pet_type_id');
    }
}