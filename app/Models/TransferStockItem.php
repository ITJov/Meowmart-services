<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferStockItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_stocks_id',
        'product_id',
        'quantity',
    ];

    /**
     * Relasi kembali ke header transfer.
     */
    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(TransferStocks::class, 'transfer_stocks_id');
    }

    /**
     * Relasi ke produk yang ditransfer.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
