<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'branches_id',
        'order_id',
        'payment_mode_id',
        'amount',
        'payment_date',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'payment_date' => 'datetime',
        'amount' => 'decimal:2',
    ];

    /**
     * Mendapatkan data order yang terkait dengan pembayaran ini.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Mendapatkan data mode pembayaran yang digunakan.
     */
    public function paymentMode()
    {
        return $this->belongsTo(PaymentMode::class);
    }

    /**
     * Mendapatkan data cabang tempat pembayaran ini terjadi.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branches_id');
    }
}
