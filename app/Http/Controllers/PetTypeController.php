<?php

namespace App\Http\Controllers;

use App\Models\PetType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PetTypeController extends Controller
{
    /**
     * Menampilkan daftar tipe hewan.
     * Data difilter berdasarkan cabang yang aktif di frontend.
     */
    public function index(Request $request)
    {
        $query = PetType::query();

        // Mengambil ID cabang dari request (dikirim oleh fetchPetTypes di Vue) 
        // atau dari user yang sedang login
        $targetBranchId = $request->input('branches_id', $request->user()->branches_id);

        // Filter: Hanya menampilkan data milik cabang tersebut atau yang bersifat global (null)
        $query->where(function($q) use ($targetBranchId) {
            $q->where('branches_id', $targetBranchId)
              ->orWhereNull('branches_id'); 
        });

        // Fitur Pencarian Nama
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Jika request meminta semua data (untuk dropdown)
        if ($request->boolean('all')) {
            $petTypes = $query->orderBy('name', 'asc')->get();
            return response()->json(['success' => true, 'data' => $petTypes]);
        } 
        
        // Default: Pagination untuk tabel
        $petTypes = $query->latest()->paginate($request->input('per_page', 15));
        
        return response()->json(['success' => true, 'data' => $petTypes]);
    }

    /**
     * Menyimpan tipe hewan baru.
     * Kolom 'description' dihapus karena tidak ada di schema database.
     */
    public function store(Request $request)
    {
        // Gunakan branches_id dari request, jika tidak ada baru gunakan milik user
        $targetBranchId = $request->input('branches_id', $request->user()->branches_id);

        $validatedData = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                // Validasi unik harus mengecek cabang yang dituju
                Rule::unique('pet_types')->where('branches_id', $targetBranchId)
            ],
            'branches_id' => 'required|exists:branches,id' // Tambahkan validasi ini
        ]);

        $petType = PetType::create([
            'name' => $validatedData['name'],
            'branches_id' => $targetBranchId, // Simpan sesuai cabang yang dikirim frontend
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pet type created successfully',
            'data' => $petType
        ], 201);
    }

    /**
     * Menampilkan detail satu tipe hewan.
     */
    public function show(PetType $petType)
    {
        // Keamanan: Pastikan user hanya bisa melihat data di cabangnya
        if ($petType->branches_id !== auth()->user()->branches_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        return response()->json(['success' => true, 'data' => $petType]);
    }

    /**
     * Memperbarui tipe hewan.
     */
    public function update(Request $request, PetType $petType)
    {
        // Pastikan user tidak mengedit data milik cabang lain
        if ($petType->branches_id !== auth()->user()->branches_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $user = $request->user();
        $validatedData = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('pet_types')
                    ->where('branches_id', $user->branches_id)
                    ->ignore($petType->id)
            ],
        ]);

        $petType->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Pet type updated successfully',
            'data' => $petType
        ]);
    }

    /**
     * Menghapus tipe hewan.
     */
    public function destroy(PetType $petType)
    {
        if ($petType->branches_id !== auth()->user()->branches_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // Cek apakah tipe ini sedang digunakan oleh data hewan lain
        if ($petType->pets()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete, this type is still in use by pets.'
            ], 400);
        }

        $petType->delete();

        return response()->json(['success' => true, 'message' => 'Pet type deleted successfully']);
    }
}