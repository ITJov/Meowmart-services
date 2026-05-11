<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory;

    /**
     */
    protected $fillable = [
        'name',
        'item_code',
        'description',
        'unit_id',
        'category_id',
        'brand_id',
        'photo', 
        'is_active',
    ];

    /**
     * Mendefinisikan relasi ke tabel detail produk.
     * Satu produk bisa memiliki banyak detail (satu untuk setiap cabang/warehouse).
     */
   protected $guarded = ['id'];

    /**
     * Secara otomatis membuat atribut 'image_url'.
     * Frontend bisa langsung memanggil product.image_url
     */
    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        if ($this->photo) {
            // Menggunakan Storage::url untuk mendapatkan URL publik yang benar
            return asset('storage/' . $this->photo);
        }
        return null; // atau return URL placeholder default
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function details()
    {
        return $this->hasMany(ProductDetail::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function batches() {
        return $this->hasMany(ProductBatches::class);
    }
}