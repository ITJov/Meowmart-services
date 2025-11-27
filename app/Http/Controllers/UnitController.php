<?php

namespace App\Http\Controllers; 

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule; // <-- Import Rule untuk validasi update
use Symfony\Component\HttpFoundation\Response;

class UnitController extends Controller
{
    /**
     * Menampilkan daftar unit.
     */
    public function index(Request $request)
    {
        // Cek apakah parameter 'all=true' diminta
        if ($request->boolean('all')) {
            // Jika ya, ambil SEMUA data unit
            $units = Unit::orderBy('name')->get(); 
            
            // Kembalikan dalam format yang diharapkan frontend { data: [...] }
            return response()->json([
                'success' => true,
                'data' => $units 
            ]);

        } else {
            // Jika tidak, lakukan paginasi seperti biasa (jika Anda punya halaman list unit)
            $perPage = $request->input('per_page', 10);
            $units = Unit::orderBy('name')->paginate($perPage);

             return response()->json([
                'success' => true,
                'data' => $units
            ]);
        }
    }

    /**
     * Menyimpan unit baru.
     */
    public function store(Request $request)
    {
        // Validasi ditempatkan langsung di sini
        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:units'],
            'short_name' => ['required', 'string', 'max:255', 'unique:units'],
        ]);

        $unit = Unit::create($validatedData);

        return response()->json(['message' => 'Success Store Data', 'data' => $unit], Response::HTTP_CREATED);
    }

    /**
     * Menampilkan satu unit spesifik.
     */
    public function show(Unit $unit)
    {
        return response()->json(['message' => 'Data Found', 'data' => $unit]);
    }

    /**
     * Memperbarui unit yang ada.
     */
    public function update(Request $request, Unit $unit)
    {
        // Validasi untuk update ditempatkan di sini
        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('units')->ignore($unit->id)],
            'short_name' => ['required', 'string', 'max:255', Rule::unique('units')->ignore($unit->id)],
        ]);
        
        $unit->update($validatedData);

        return response()->json(['message' => 'Success Update Data', 'data' => $unit]);
    }

    /**
     * Menghapus unit.
     */
    public function destroy(Unit $unit)
    {
        if ($unit->products()->exists()) {
            return response()->json([
                'message' => 'Gagal menghapus: Unit masih digunakan oleh produk lain.'
            ], Response::HTTP_FORBIDDEN); // 403 Forbidden
        }

        $unit->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT); // 204 No Content
    }
}

