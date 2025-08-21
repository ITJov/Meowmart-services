<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PetType extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function scopeFilter($query, array $params)
    {
//        $currentUser = User::where('email',$params['idUser'])->first();

        return $query;
    }
}
