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
        // DIUBAH: Tambahkan validasi untuk branches_id
        $request->validate([
            'search' => 'nullable|string',
            'branches_id' => 'required|integer|exists:branches,id',
        ]);

        // DIUBAH: Filter query berdasarkan branches_id dari frontend
        $query = Pet::with(['customer', 'petType'])
                    ->where('branches_id', $request->branches_id);

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhereHas('customer', function ($subq) use ($request) {
                      $subq->where('name', 'like', '%' . $request->search . '%');
                  });
            });
        }

        $pets = $query->latest()->paginate(15);

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
            'age' => 'nullable|string',
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
            'age' => 'nullable|string',
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