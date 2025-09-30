<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'branches_id',
        'name',
        'address',
        'phone',
        'email',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    // protected $hidden = [];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    // protected $casts = [];

    /**
     * Mendefinisikan relasi "belongsTo" ke model Branch.
     * Sebuah Warehouse dimiliki oleh satu Branch.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branches_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

}
