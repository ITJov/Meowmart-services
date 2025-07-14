<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Product;
use App\Models\User;
use App\Models\Category;
use App\Models\ProductDetail;

class ProductController extends CrudController
{
    protected $crudModel = Product::class;
    protected $validationUpdate = [
        'name' => '',
        'item_code' => '',
        'unit_id' => '',
        'stock_quantitiy_alert' => '',
        'category_id' => '',
        'brand_id' => '',
        'opening_stock' => '',
        'current_stock' => '',
        'opening_stock_date' => '',
        'purchase_price' => '',
        'purchase_tax_type' => '',
        'sales_price' => '',
        'sales_tax_type' => '',
        'wholesale_price' => '',
        'description' => '',
    ];
    protected $validationStore = [
        'name' => '',
        'item_code' => '',
        'unit_id' => '',
        'stock_quantitiy_alert' => '',
        'category_id' => '',
        'brand_id' => '',
        'opening_stock' => '',
        'current_stock' => '',
        'opening_stock_date' => '',
        'purchase_price' => '',
        'purchase_tax_type' => '',
        'sales_price' => '',
        'sales_tax_type' => '',
        'wholesale_price' => '',
        'description' => '',
    ];

    protected $photoKey = [ 'photo' => 'required'];
    protected $detailKey = [ 'product_id','warehouse_id','current_stock','mrp','purchase_price','sales_price','tax_id','purchase_tax_type','sales_tax_type',
        'stock_quantitiy_alert','opening_stock','opening_stock_date','wholesale_price','wholesale_quantity','status'];

    protected $relation = [ 'unit','category','detail','brand'];

    public function index($id)
    {
        //
//        $user = $this->crudModel::findOrFail($id);
//
//        $userAll =  $this->crudModel::where('warehouse_id',$user->warehouse_id)->get();
//
//        return response()->json(['message' => 'success','data' => $userAll], 200);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $user = User::where('email',$request->email)->first();

        $validateJson = [];
        $mergedValidate = array_merge($this->validationStoreJson,$this->validationStore);
        foreach ($mergedValidate as $key=>$dataJsonMap){
            if(is_array($dataJsonMap)){
                $validateJson =  array_merge((array) $validateJson, (array) $dataJsonMap);
            }else{
                $validateJson[$key]= $dataJsonMap;
            }
        }
        //todo add
        //define validation rules
        $validator = Validator::make($request->all(),$validateJson);

        //check if validation fails
        if ($validator->fails()) {
            // return response()->json($validator->errors(), 422);
        }
        //MAPPING array
        $inputField = array();
        $inputFieldDetail = array();

        if ($request->hasFile('photo')) {

            //upload image
            $image = $request->file('photo');
            $image->storeAs('public/doctors', $image->hashName());


        }

        foreach ($this->validationStore as $key=>$dataValidate){
            if ($key == 'category_id') {
                $detailCategory= Category::findOrFail($request[$key]);
                $inputField['slug'] = $detailCategory->slug;
                $inputField['barcode_symbology'] = $request->item_code;
                $inputField['category_id'] = $request[$key];
            }
            else if ($request->hasFile('photo') && $key == 'photo') {
                $image = $request->file('photo');
                $image->storeAs('public/doctors', $image->hashName());

                $inputField[$key] = env('DIGITALOCEAN_SPACES_ENDPOINT').'/'.'public/doctors/'.$image->hashName();
            }elseif(in_array($request[$key],$this->detailKey)){
                $inputFieldDetail[$key] = $request[$key];
            }else{
                $inputField[$key] = $request[$key];
            }

        }
        foreach ($this->detailKey as $key=>$dataValidate){
            $inputFieldDetail[$dataValidate] = $request[$dataValidate];
        }
        $inputFieldDetail['warehouse_id']  = $user->warehouse_id;
        $inputFieldDetail['status']  = (int)$request->current_stock > 0 ? 'in_stock' : 'out_of_stock';
        $inputField['warehouse_id'] =  $user->warehouse_id;
        $inputField['company_id'] =  $user->company_id;
        $inputField['user_id'] =  $user->id;

        foreach ($this->validationStoreJson as $key=>$dataJson){
            if(is_array($dataJson)){
                foreach ($dataJson as $key2=>$dataJson2){
                    if ($request->hasFile('photo') && $key2 == 'photo') {
                        $image = $request->file('photo');
                        $image->storeAs('public/doctors', $image->hashName());
                        $inputField[$key][$key2] = env('DIGITALOCEAN_SPACES_ENDPOINT').'/'.'public/doctors/'.$image->hashName();
                    }else{
                        $inputField[$key][$key2]=$request[$key2];
                    }

                }
            }
        }

        $model = $this->crudModel::create($inputField);
        $detailProduct =  ProductDetail::where('product_id',$model->id)->first();
        if(isset($detailProduct)){
            $detailProduct->update($inputFieldDetail);
        }else{
            $inputFieldDetail['status']  = 'in_stock';
            // $inputFieldDetail['current_stock']  = $request->stock;
            $inputFieldDetail['product_id']  = $model->id;
            $detailProduct =  ProductDetail::create($inputFieldDetail);
        }

        //check if image is not empty
//        }
        return $model;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

}
