<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'purchase_id',
        'product_id',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    /**
     * Mendefinisikan relasi bahwa sebuah item pembelian
     * dimiliki oleh satu transaksi pembelian (purchase).
     */
    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * Mendefinisikan relasi bahwa sebuah item pembelian
     * merujuk pada satu produk.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
