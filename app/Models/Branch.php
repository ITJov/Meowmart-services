<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $table = 'branches'; // nama tabel

    protected $fillable = [
        'name',
        'address',
        'phone',
    ];

    /**
     * Relasi ke User
     * Satu branch bisa punya banyak user
     */
    public function users()
    {
        return $this->hasMany(User::class, 'branches_id');
    }
}
