<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'branches_id',
        'supplier', // Nama supplier sebagai teks biasa
        'invoice_number',
        'purchase_date',
        'purchase_status',
        'total_amount',
        'payment_status',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'purchase_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Mendefinisikan relasi bahwa sebuah pembelian (purchase)
     * memiliki banyak item (purchase_items).
     */
    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * Mendefinisikan relasi bahwa sebuah pembelian
     * dimiliki oleh satu cabang (branch).
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branches_id');
    }
}
