<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Session;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }


    public function scopeFilter($query, array $params)
    {
        $currentUser = Session::get('users');
        $query = $query->where('warehouse_id',$currentUser->warehouse_id);

        if(isset($params['all'])){

        }else{
            $query = $query->where('doctor_detail', '!=',null);
        }

        return $query;
    }
    public function scopeFilterOwner($query, array $params)
    {
        $currentUser = Session::get('users');
        $query = $query->where('company_id',$currentUser->company_id);
        $query = $query->where('user_type',"customers");
        return $query;
    }


    public function scopeFilterStaff($query, array $params)
    {
        $currentUser = Session::get('users');
        $query = $query->where('warehouse_id',$currentUser->warehouse_id);
        $query = $query->where('user_type',"staff_members");

        return $query;
    }

    public function scopeSearch($query, array $params)
    {

        $query = $query->where('name',$params['q']);

        return $query;
    }
}
