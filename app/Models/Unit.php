<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory;

    /**

     */
    public $timestamps = true;

    /**
     * Kolom yang boleh diisi secara massal.
     */
    protected $fillable = [
        'name',
        'short_name',
    ];

    /**
     * Relasi ke model Product.
     * Sebuah unit bisa digunakan oleh banyak produk.
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
