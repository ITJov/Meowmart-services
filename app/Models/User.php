<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles; 

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * Kolom yang boleh diisi.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',       
        'address',     
        'role_id',
        'warehouse_id',
        'branches_id',    
    ];


    /**
     * Kolom yang disembunyikan.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Tipe data kolom.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Relasi: Satu user memiliki satu role.
     * Ini akan mengambil data role berdasarkan 'role_id'.
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Relasi: Satu user ditugaskan di satu warehouse.
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function branches()
    {
        return $this->belongsTo(Branch::class);
    }
}