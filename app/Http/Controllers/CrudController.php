<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CrudController extends Controller 
{
    protected $model;
    protected $validationStore = [];
    protected $validationUpdate = [];

    public function sendResponse($result, $message)
    {
        return response()->json([
            'success' => true,
            'data'    => $result,
            'message' => $message,
        ], 200);
    }

    public function sendError($error, $errorMessages = [], $code = 404)
    {
        return response()->json([
            'success' => false,
            'message' => $error,
            'data' => $errorMessages,
        ], $code);
    }
    
    public function index(Request $request)
    {
        $query = $this->model::query();

        if ($request->has('search')) {
            $query->search($request->search);
        }
        
        $data = $query->latest()->paginate($request->per_page ?? 25);
        
        return $this->sendResponse($data, 'Data retrieved successfully.');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->validationStore);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $model = $this->model::create($request->all());

        return $this->sendResponse($model, 'Data created successfully.');
    }

    public function show($id)
    {
        $model = $this->model::find($id);

        if (is_null($model)) {
            return $this->sendError('Data not found.');
        }

        return $this->sendResponse($model, 'Data retrieved successfully.');
    }

    public function update(Request $request, $id)
    {
        $model = $this->model::find($id);

        if (is_null($model)) {
            return $this->sendError('Data not found.');
        }

        $validator = Validator::make($request->all(), $this->validationUpdate);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $model->update($request->all());

        return $this->sendResponse($model, 'Data updated successfully.');
    }

    public function destroy($id)
    {
        $model = $this->model::find($id);

        if (is_null($model)) {
            return $this->sendError('Data not found.');
        }
        
        $model->delete();

        return $this->sendResponse(null, 'Data deleted successfully.');
    }
}