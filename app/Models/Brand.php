<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Brand extends Model
{
    use HasFactory;

    /**
     * Kolom yang boleh diisi secara massal.
     */
    protected $fillable = [
        'name',
        'slug',
        'image',
    ];

    /**
     * Relasi ke model Product.
     * Sebuah brand bisa memiliki banyak produk.
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Accessor untuk mendapatkan URL lengkap dari gambar.
     * Ini membuat frontend lebih mudah, hanya perlu memanggil `brand.image_url`.
     */
    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return asset('storage/' . $this->image);
        }

        // URL fallback jika tidak ada gambar
        return 'https://placehold.co/400x400/eeeeee/cccccc?text=No+Image';
    }
}
