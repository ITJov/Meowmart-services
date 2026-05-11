<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'branches_id',
        'customer_id',
        'registration_id',
        'user_id',
        'invoice_number',
        'subtotal',
        'total_discount',
        'total_tax',
        'total',
        'payment_status',
        'order_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'subtotal' => 'decimal:2',
        'total_discount' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'total' => 'decimal:2',
        'order_date' => 'datetime',
    ];

    /**
     * Get the branch that owns the order.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branches_id');
    }

    /**
     * Get the customer that owns the order.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the user (cashier) who created the order.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all of the items for the order.
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get all of the payments for the order.
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
