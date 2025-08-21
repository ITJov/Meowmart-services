<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Shanmuga\LaravelEntrust\Contracts\LaravelEntrustRoleInterface;
use Shanmuga\LaravelEntrust\Traits\LaravelEntrustRoleTrait;

class Role extends Model
{
    use HasFactory;
}
