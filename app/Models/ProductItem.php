<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'branches_id',
        'product_batch_id',
        'unique_qr_code',
        'status', // Tersedia, Terjual, Rusak, Retur
    ];

    // Relasi ke Produk Induk
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Relasi ke Batch (untuk tahu expired date unit ini)
    public function batch()
    {
        return $this->belongsTo(ProductBatches::class, 'product_batch_id');
    }
}