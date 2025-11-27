<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Customer;
use App\Models\PetType;
use Carbon\Carbon; // <-- Import Carbon


class Pet extends Model
{
    use HasFactory;
    protected $appends = ['age_string']; 

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
        'date_of_birth',
        'photo',
        'branches_id',
    ];

    public function getAgeStringAttribute()
    {
        if (is_null($this->date_of_birth)) {
            return 'N/A';
        }
        
        $birthDate = Carbon::parse($this->date_of_birth);
        $diff = $birthDate->diff(Carbon::now());

        if ($diff->y > 0) {
            return $diff->y . ' Tahun';
        } elseif ($diff->m > 0) {
            return $diff->m . ' Bulan';
        } else {
            return $diff->d . ' Hari';
        }
    }
    /**
     
     */
    public function customer()
    {
        
        return $this->belongsTo(Customer::class, 'customer_id');
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

    public function branch()
    {
        // Nama foreign key 'branches_id' didefinisikan secara eksplisit
        return $this->belongsTo(Branch::class, 'branches_id');
    }
}