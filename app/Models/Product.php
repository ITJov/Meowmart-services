<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'photo', // Dulu 'image', kita standarkan menjadi 'photo'
        // 'company_id', // Sebaiknya di-handle otomatis
        // 'user_id',    // Sebaiknya di-handle otomatis
    ];

    /**
     * Mendefinisikan relasi ke tabel detail produk.
     * Satu produk bisa memiliki banyak detail (satu untuk setiap cabang/warehouse).
     */
    public function details()
    {
        return $this->hasMany(ProductDetail::class);
    }
    
    /**
     * Relasi: Produk ini milik satu Kategori.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    
    /**
     * Relasi: Produk ini milik satu Merek (Brand).
     */
    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
    
    /**
     * Relasi: Produk ini memiliki satu Satuan (Unit).
     */
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}