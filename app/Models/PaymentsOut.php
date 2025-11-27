<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentsOut extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terhubung dengan model ini.
     *
     * @var string
     */
    protected $table = 'payments_out';

    /**
     * Atribut yang dapat diisi secara massal.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'branches_id',
        'user_id',
        'purchase_id',
        'transaction_number',
        'payment_date',
        'amount',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    /**
     * Mendefinisikan relasi ke model User (staf yang mencatat).
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');    
    }

    /**
     * Mendefinisikan relasi ke model Purchase (transaksi pengadaan).
     */
    public function purchase()
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');    
    }

    /**
     * Mendefinisikan relasi ke model Branch.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branches_id');
    }
}
