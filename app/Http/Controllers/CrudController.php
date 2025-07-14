<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


class CrudController extends Controller
{
    protected $crudModel;
    protected $validationStore = array();
    protected $validationUpdateJson = array();
    protected $validationUpdate = array();
    protected $validationStoreJson = array();


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        $data = $this->crudModel::paginate(25);
        if ($data) {
            return $this->sendResponse($data, 'Data retrieved successfully.');
        }
        return $this->sendError('Data Not found.',[],200);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */

     public function create(Request $request)
     {
        return $this->sendResponse(null, 'Create method placeholder.');
     }

     public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->validationStore);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), [], 400);
        }

        $data = $request->except($this->validationStoreJson);

        foreach ($this->validationStoreJson as $field) {
            $data[$field] = json_encode($request->input($field));
        }

        $model = ($this->crudModel)::create($data);

        return $this->sendResponse($model, 'Data created successfully.');
    }
public function update(Request $request, $id)
    {

        $model = ($this->crudModel)::findOrFail($id);

        $validateJson = array_merge($this->validationUpdate, ...array_values($this->validationUpdateJson));
        $validator = Validator::make($request->all(), $validateJson);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), [], 422);
        }

        $input = [];
        foreach ($this->validationUpdate as $key => $rule) {
            $input[$key] = $request[$key];
        }

        foreach ($this->validationUpdateJson as $key => $jsonFields) {
            $input[$key] = [];
            foreach ($jsonFields as $jsonKey => $_) {
                $input[$key][$jsonKey] = $request[$jsonKey];
            }
        }

        $model->update($input);

        return $this->sendResponse($model, 'Data updated successfully.');
    }

    public function destroy($id)
    {
        $model = ($this->crudModel)::findOrFail($id);
        $model->delete();

        return $this->sendResponse(null, 'Data deleted successfully.');
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    
public function datatable(Request $request)
    {
        $columns = $request->input('columns', []);
        $search = $request->input('search.value');
        $orderColumn = $columns[$request->input('order.0.column')]['data'] ?? 'id';
        $orderDir = $request->input('order.0.dir', 'asc');

        $query = ($this->crudModel)::query();

        if (!empty($search)) {
            $query->where(function($q) use ($columns, $search) {
                foreach ($columns as $col) {
                    $q->orWhere($col['data'], 'like', "%{$search}%");
                }
            });
        }

        $totalData = ($this->crudModel)::count();
        $totalFiltered = $query->count();

        $data = $query->offset($request->input('start'))
                     ->limit($request->input('length'))
                     ->orderBy($orderColumn, $orderDir)
                     ->get();

        return response()->json([
            "draw" => intval($request->input('draw')),
            "recordsTotal" => $totalData,
            "recordsFiltered" => $totalFiltered,
            "data" => $data
        ]);
    }

}
