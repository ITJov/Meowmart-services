<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller; // UBAH INI
use App\Http\Requests\StoreCategoryRequest;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CategoryController extends Controller
{
    /**
     */
    public function index(Request $request)
    {
        if ($request->boolean('all')) {
            $data = Category::orderBy('name')->get();
            return response()->json([
                'message' => 'Success',
                'data' => ['data' => $data]
            ]);
        }

        $query = Category::query();

        if ($request->has('search') && $request->search != "") {
            $query->where('name', 'LIKE', "%{$request->search}%");
        }

        $paginatedData = $query->latest()->paginate($request->input('per_page', 10));

        return response()->json([
            'message' => 'Success',
            'data' => $paginatedData
        ]);
    }

    /**
     * Menyimpan kategori baru ke database.
     */
    public function store(StoreCategoryRequest $request)
    {
        $validatedData = $request->validated();

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('categories', 'public');
        }

        $validatedData['image'] = $imagePath;
        $validatedData['slug'] = Str::slug($validatedData['name']);

        $category = Category::create($validatedData);

        return response()->json([
            'message' => 'Success Store Data',
            'data' => $category
        ], Response::HTTP_CREATED);
    }

    /**
     * Menampilkan satu kategori spesifik.
     */
    public function show(Category $category)
    {
        // Menggunakan Route Model Binding, lebih bersih
        return response()->json([
            'message' => 'Data Found',
            'data' => $category
        ]);
    }

    /**
     * Memperbarui kategori yang ada.
     */
    public function update(StoreCategoryRequest $request, Category $category)
    {
        $validatedData = $request->validated();

        $imagePath = $category->image; 
        if ($request->hasFile('image')) {
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }
            $imagePath = $request->file('image')->store('categories', 'public');
        }

        $validatedData['image'] = $imagePath;
        $validatedData['slug'] = Str::slug($validatedData['name']);

        $category->update($validatedData);

        return response()->json([
            'message' => 'Success Update Data',
            'data' => $category
        ]);
    }

    /**
     * Menghapus kategori dari database.
     */
    public function destroy(Category $category)
    {
        if ($category->image) {
            Storage::disk('public')->delete($category->image);
        }

        $category->delete();
        
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}

