<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory;

    /**
     * Menonaktifkan timestamps (created_at, updated_at) karena tidak ada di migrasi Anda.
     * Jika Anda ingin menggunakannya, tambahkan `$table->timestamps();` di file migrasi.
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
