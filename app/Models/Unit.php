<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory;
	protected $guarded = ['id', 'is_deletable', 'created_at', 'updated_at'];

}
