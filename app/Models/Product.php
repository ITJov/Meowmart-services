<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'medicine_detail',
        'name',
        'item_code',
        'unit_id',
        'category_id',
        'brand_id',
        'description',
        'user_id',
        'image',
        'slug',
        'barcode_symbology',
        'company_id'
    ];

    public function users()
    {
        return $this->hasOne('App\Models\User', 'id', 'user_id');
    }

    public function scopeFilter($query, array $params)
    {

        $currentUser = User::where('email', $params['idUser'])->first();
        $query->leftJoin('product_details', function ($join) {
            $join->on('products.id', '=', 'product_details.product_id');
        });
        $query = $query->select('products.*','product_details.warehouse_id');
        $query->where('category_id', 93)->where('product_details.warehouse_id', $currentUser->warehouse_id);
        return $query;
    }

    public function scopeFilterService($query, array $params)
    {

        $currentUser = User::where('email', $params['idUser'])->first();
        $query->leftJoin('product_details', function ($join) {
            $join->on('products.id', '=', 'product_details.product_id');
        });
        $query->where('category_id', 84)->where('product_details.warehouse_id', $currentUser->warehouse_id);
        $query = $query->select('products.*','product_details.current_stock','product_details.mrp'
        ,'product_details.mrp','product_details.purchase_price','product_details.sales_price','product_details.tax_id','product_details.opening_stock','product_details.discount_amount');

        return $query;

    }

    public function scopeFilterAll($query, array $params)
    {

        $currentUser = User::where('email', $params['idUser'])->first();
        $query->leftJoin('product_details', function ($join) {
            $join->on('products.id', '=', 'product_details.product_id');
        });
        $query->where('product_details.warehouse_id', $currentUser->warehouse_id);
        $query = $query->select('products.*');

        return $query;

    }

    public function scopeFilterBulk($query, array $params)
    {
        $currentUser = User::where('email', $params['idUser'])->first();
        $query->leftJoin('product_details', function ($join) {
            $join->on('products.id', '=', 'product_details.product_id');
        });
        if(isset($params['categoryGroup'])){
            $query->where('category_id', (int)$params['categoryGroup']);
        }else{
            $query->where('category_id', 93);
        }
        $query->where('product_details.warehouse_id', $currentUser->warehouse_id);
        $query = $query->select('products.*','product_details.current_stock','product_details.mrp'
            ,'product_details.mrp','product_details.purchase_price','product_details.sales_price','product_details.tax_id','product_details.opening_stock');

        return $query;

    }

    public function scopeSearch($query, array $params)
    {

        $query = $query->where('name', $params['q']);
        return $query;

    }
}
