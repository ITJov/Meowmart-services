<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Session;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'id', 'name', 'email', 'phone', 'city', 'country', 'zipcode',
        'clinic_detail'
    ];
    protected $guarded = ['id', 'users', 'created_at', 'updated_at'];

    // protected $hidden = ['id'];

    protected $casts = [
        'clinic_detail'=>'json'
    ];

    public function scopeFilter($query, array $params)
    {
//         $currentUser = Session::get('users');
//         $query = $query->where('company_id',$currentUser->company_id);
//         if(isset($params['all'])){
// //            $query = $query->where('company_id',$currentUser->company_id);
//             // $query = $query->where('clinic_detail', '!=',null);
//         }else{
//             // $query = $query->where('clinic_detail', '!=',null);
//         }


        return $query;
    }
    public function scopeSearch($query, array $params)
    {

        // $query = $query->where('name',$params['q']);

        return $query;
    }
}
