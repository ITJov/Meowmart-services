<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WarehouseController extends Controller
{
    /**
     * Menampilkan daftar cabang HANYA untuk perusahaan user yang login.
     */
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string',
        ]);

        $user = $request->user();

        $query = Warehouse::query()->where('company_id', $user->company_id);

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $warehouses = $query->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Warehouses retrieved successfully',
            'data' => $warehouses
        ]);
    }

    /**
     * Menyimpan cabang baru untuk perusahaan user yang login.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('warehouses')->where('company_id', $user->company_id)],
            'phone' => 'required|string',
            'address' => 'required|string',
        ]);

        // Tambahkan company_id secara otomatis dari user yang login
        $validatedData['company_id'] = $user->company_id;

        $warehouse = Warehouse::create($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Warehouse created successfully',
            'data' => $warehouse
        ], 201);
    }

    /**
     * Menampilkan detail satu cabang.
     */
    public function show(Warehouse $warehouse, Request $request)
    {
        // Keamanan: Pastikan user hanya bisa melihat data di perusahaannya
        if ($warehouse->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        return response()->json(['success' => true, 'data' => $warehouse]);
    }

    /**
     * Memperbarui data cabang.
     */
    public function update(Request $request, Warehouse $warehouse)
    {
        // Keamanan: Pastikan user hanya bisa mengedit data di perusahaannya
        if ($warehouse->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $user = $request->user();
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('warehouses')->where('company_id', $user->company_id)->ignore($warehouse->id)],
            'phone' => 'required|string',
            'address' => 'required|string',
        ]);

        $warehouse->update($validatedData);

        return response()->json(['success' => true, 'message' => 'Warehouse updated successfully', 'data' => $warehouse]);
    }

    /**
     * Menghapus cabang.
     */
    public function destroy(Warehouse $warehouse, Request $request)
    {
        if ($warehouse->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $warehouse->delete();

        return response()->json(['success' => true, 'message' => 'Warehouse deleted successfully']);
    }
}