<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Registration extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function pet() { return $this->belongsTo(Pet::class); }
    public function slot() { return $this->belongsTo(Slot::class)->with('user'); } // Selalu ambil data staff
    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
