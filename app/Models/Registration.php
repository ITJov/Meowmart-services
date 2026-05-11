<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Registration extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'pet_id',
        'registration_type',
        'registration_date',
        'status',
        'branches_id',
        'notes',
        
        'service_id', 
        'kandang_id', 
        'start_date', 
        'end_date',
        
        'service_id',
        'slot_id',
        'booking_id',
    ];

    protected $dates = [
        'start_date',
        'end_date',
        'registration_date',
        'created_at',
        'updated_at',
    ];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function pet() { return $this->belongsTo(Pet::class); }
    public function slot() { return $this->belongsTo(Slot::class)->with('user'); }
    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}