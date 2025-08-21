<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// Ganti nama class menjadi ProductDetail (singular) agar sesuai konvensi Laravel
class ProductDetail extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Nama tabel yang digunakan oleh model ini.
     * Perlu didefinisikan jika nama class (ProductDetail) tidak sama dengan nama tabel (product_details).
     */
    protected $table = 'product_details';
    
    /**
     * Kolom yang boleh diisi secara massal (mass assignable).
     * Ini adalah kebalikan dari $guarded. Lebih disarankan untuk keamanan.
     */
    protected $fillable = [
        'product_id',
        'warehouse_id',
        'purchase_price',
        'sales_price',
        'current_stock',
        'stock_quantity_alert', 
        'status',
    ];

    /**
     * Kolom yang harus disembunyikan saat model diubah menjadi array atau JSON.
     */
    protected $hidden = [
        'deleted_at'
    ];

    /**
     * Relasi: Detail ini milik satu Produk (Product).
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relasi: Detail ini berada di satu Gudang/Cabang (Warehouse).
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}