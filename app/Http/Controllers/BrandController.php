<?php

namespace App\Http\Controllers; 

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBrandRequest;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class BrandController extends Controller
{
    /**
     * Menampilkan daftar brand.
     * Mendukung paginasi, pencarian, dan parameter 'all' untuk dropdown.
     */
    public function index(Request $request)
    {
        // Jika ada parameter ?all=true, ambil semua data tanpa paginasi
        if ($request->boolean('all')) {
            $data = Brand::orderBy('name')->get();
            return response()->json(['message' => 'Success', 'data' => ['data' => $data]]);
        }

        $query = Brand::query();

        if ($request->has('search') && $request->search != "") {
            $query->where('name', 'LIKE', "%{$request->search}%");
        }

        $data = $query->latest()->paginate($request->input('per_page', 10));

        return response()->json(['message' => 'Success', 'data' => $data]);
    }

    /**
     * Menyimpan brand baru.
     */
    public function store(StoreBrandRequest $request)
    {
        $validatedData = $request->validated();
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('brands', 'public');
        }

        $validatedData['image'] = $imagePath;
        $validatedData['slug'] = Str::slug($validatedData['name']);

        $brand = Brand::create($validatedData);

        return response()->json(['message' => 'Success Store Data', 'data' => $brand], Response::HTTP_CREATED);
    }

    /**
     * Menampilkan satu brand spesifik.
     */
    public function show(Brand $brand)
    {
        return response()->json(['message' => 'Data Found', 'data' => $brand]);
    }

    /**
     * Memperbarui brand yang ada.
     */
    public function update(StoreBrandRequest $request, Brand $brand)
    {
        $validatedData = $request->validated();
        $imagePath = $brand->image;

        if ($request->hasFile('image')) {
            if ($brand->image) {
                Storage::disk('public')->delete($brand->image);
            }
            $imagePath = $request->file('image')->store('brands', 'public');
        }

        $validatedData['image'] = $imagePath;
        $validatedData['slug'] = Str::slug($validatedData['name']);

        $brand->update($validatedData);

        return response()->json(['message' => 'Success Update Data', 'data' => $brand]);
    }

    /**
     * Menghapus brand.
     */
    public function destroy(Brand $brand)
    {
        // PENTING: Cek apakah brand ini masih digunakan oleh produk lain.
        if ($brand->products()->exists()) {
            return response()->json([
                'message' => 'Gagal menghapus: Brand masih digunakan oleh produk lain.'
            ], Response::HTTP_FORBIDDEN); // 403 Forbidden
        }

        if ($brand->image) {
            Storage::disk('public')->delete($brand->image);
        }

        $brand->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT); // 204 No Content
    }
}
