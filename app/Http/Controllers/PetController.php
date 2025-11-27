<?php

namespace App\Http\Controllers;

use App\Models\Pet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PetController extends Controller
{
    /**
     * Menampilkan daftar hewan peliharaan berdasarkan cabang yang aktif.
     */
    public function index(Request $request)
    {
        $request->validate([
            'branches_id' => 'required|integer|exists:branches,id',
            'search' => 'nullable|string',
        ]);
        
        $query = Pet::with(['customer', 'petType']) 
                      ->where('branches_id', $request->branches_id);

        if ($request->filled('search')) {
            $searchTerm = '%' . $request->search . '%';
            
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                  
                  ->orWhereHas('customer', function($cq) use ($searchTerm) {
                      $cq->where('name', 'like', $searchTerm)
                         ->orWhere('phone', 'like', $searchTerm);
                  });
            });
        }

        $pets = $query->latest()->paginate($request->per_page ?? 10); 
        return response()->json(['data' => $pets]);
    }
    /**
     * Menyimpan data hewan peliharaan baru ke cabang yang aktif.
     */
    public function store(Request $request)
    {
        // DIUBAH: Tambahkan validasi untuk branches_id
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'customer_id' => 'required|integer|exists:customers,id',
            'pet_type_id' => 'required|integer|exists:pet_types,id',
            'breed' => 'nullable|string',
            'color' => 'nullable|string',
            'date_of_birth' => 'nullable|date', 
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'branches_id' => 'required|integer|exists:branches,id',
        ]);

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('pets', 'public');
            $validatedData['photo'] = $path;
        }

        $pet = Pet::create($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Pet created successfully',
            'data' => $pet
        ], 201);
    }

    /**
     * Menampilkan detail satu hewan peliharaan (dengan keamanan).
     */
    public function show(Pet $pet)
    {
        $pet->load(['customer', 'petType']);
        return response()->json(['data' => $pet]);
    }

    /**
     * Memperbarui data hewan peliharaan.
     */
    public function update(Request $request, Pet $pet)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'customer_id' => 'required|integer|exists:customers,id',
            'pet_type_id' => 'required|integer|exists:pet_types,id',
            'breed' => 'nullable|string',
            'color' => 'nullable|string',
            'date_of_birth' => 'nullable|date', 
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($request->hasFile('photo')) {
            if ($pet->photo) {
                Storage::disk('public')->delete($pet->photo);
            }
            $path = $request->file('photo')->store('pets', 'public');
            $validatedData['photo'] = $path;
        }

        $pet->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Pet updated successfully',
            'data' => $pet
        ]);
    }

    /**
     * Menghapus data hewan peliharaan.
     */
    public function destroy(Pet $pet)
    {
        if ($pet->photo) {
            Storage::disk('public')->delete($pet->photo);
        }
        $pet->delete();
        return response()->json(['success' => true, 'message' => 'Pet deleted successfully']);
    }
}