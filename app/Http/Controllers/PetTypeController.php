<?php

namespace App\Http\Controllers;

use App\Models\PetType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PetTypeController extends Controller
{
    /**
     * Menampilkan daftar tipe hewan milik perusahaan yang login.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = PetType::query()->where('company_id', $user->company_id);

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $petTypes = $query->latest()->paginate(15);

        return response()->json(['success' => true, 'data' => $petTypes]);
    }

    /**
     * Menyimpan tipe hewan baru.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $validatedData = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('pet_types')->where('company_id', $user->company_id)
            ],
            'description' => 'nullable|string',
        ]);

        $validatedData['company_id'] = $user->company_id;
        $petType = PetType::create($validatedData);

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
        // Keamanan: Pastikan user hanya bisa melihat data di perusahaannya
        if ($petType->company_id !== auth()->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        return response()->json(['success' => true, 'data' => $petType]);
    }

    /**
     * Memperbarui tipe hewan.
     */
    public function update(Request $request, PetType $petType)
    {
        if ($petType->company_id !== auth()->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $user = $request->user();
        $validatedData = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('pet_types')->where('company_id', $user->company_id)->ignore($petType->id)
            ],
            'description' => 'nullable|string',
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
        if ($petType->company_id !== auth()->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

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