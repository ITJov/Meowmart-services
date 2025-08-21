<?php

namespace App\Http\Controllers;

use App\Models\Pet;
use App\Models\User;
use App\Models\PetType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PetController extends Controller
{
    /**
     * Menampilkan daftar hewan peliharaan milik perusahaan yang sedang login.
     */
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string',
            'customer_id' => 'nullable|integer|exists:users,id',
        ]);

        $user = $request->user();

        $query = Pet::with(['customer', 'petType'])
                    ->where('company_id', $user->company_id);

        $query->where('warehouse_id', $user->warehouse_id);

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhereHas('customer', function ($q) use ($request) {
                      $q->where('name', 'like', '%' . $request->search . '%');
                  });
        }
        
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        $pets = $query->latest()->paginate(15);

        return response()->json(['success' => true, 'data' => $pets]);
    }

    /**
     * Menyimpan data hewan peliharaan baru.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'customer_id' => 'required|integer|exists:users,id', // Pastikan customer ada di tabel users
            'pet_type_id' => 'required|integer|exists:pet_types,id',
            'breed' => 'nullable|string',
            'color' => 'nullable|string',
            'age' => 'nullable|string',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $user = $request->user();

        // Tambahkan ID warehouse & company secara otomatis dari staf yang login
        $validatedData['warehouse_id'] = $user->warehouse_id;
        $validatedData['company_id'] = $user->company_id;

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
     * Menampilkan detail satu hewan peliharaan.
     */
    public function show(Pet $pet, Request $request)
    {
        // Keamanan: Pastikan staf hanya bisa melihat data hewan di perusahaannya
        if ($pet->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $pet->load(['customer', 'petType']); // Muat data relasi
        return response()->json(['success' => true, 'data' => $pet]);
    }

    /**
     * Memperbarui data hewan peliharaan.
     */
    public function update(Request $request, Pet $pet)
    {
        // Keamanan: Pastikan staf hanya bisa mengedit data hewan di perusahaannya
        if ($pet->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'customer_id' => 'required|integer|exists:users,id',
            'pet_type_id' => 'required|integer|exists:pet_types,id',
            'breed' => 'nullable|string',
            'color' => 'nullable|string',
            'age' => 'nullable|string',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($request->hasFile('photo')) {
            // Hapus foto lama jika ada
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
    public function destroy(Pet $pet, Request $request)
    {
        if ($pet->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($pet->photo) {
            Storage::disk('public')->delete($pet->photo);
        }

        $pet->delete();

        return response()->json(['success' => true, 'message' => 'Pet deleted successfully']);
    }

    // Di dalam PetController.php

    public function allData(Request $request)
    {
        $user = $request->user();

        $pets = Pet::query()
                    ->where('company_id', $user->company_id)
                    ->get();

        $formatted_pets = [];
        foreach ($pets as $pet) {
            $formatted_pets[] = ['id' => $pet->id, 'text' => $pet->name];
        }

        return response()->json($formatted_pets);
    }
    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
}


