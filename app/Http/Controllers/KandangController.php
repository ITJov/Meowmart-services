<?php

namespace App\Http\Controllers; 

use App\Models\Kandang;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Validation\Rule; 

class KandangController extends Controller
{
    /**
     * Menampilkan daftar kandang dengan paginasi, search, dan filter.
     */
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string',
            'status' => 'nullable|string|in:Aktif,Tidak Aktif,Perbaikan',
        ]);

        $user = $request->user();

        $query = Kandang::query()
                    ->where('warehouse_id', $user->warehouse_id)
                    ->where('flag_deleted', false);

        if ($request->filled('search')) {
            $query->where('kode_room', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $kandangs = $query->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Kandangs retrieved successfully',
            'data' => $kandangs
        ]);
    }

    /**
     * Menyimpan kandang baru ke database.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validatedData = $request->validate([
            'kode_room' => [
                'required',
                'string',
                Rule::unique('kandangs')->where(function ($query) use ($user) {
                    return $query->where('warehouse_id', $user->warehouse_id);
                }),
            ],
            'quota' => 'required|integer|min:1',
            'status' => 'required|string|in:Aktif,Tidak Aktif,Perbaikan',
        ]);
        
        $validatedData['warehouse_id'] = $user->warehouse_id;
        $validatedData['company_id'] = $user->company_id;

        $kandang = Kandang::create($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Kandang created successfully',
            'data' => $kandang
        ], 201);
    }

    /**
     * Menampilkan detail satu kandang
     */
    public function show(Kandang $kandang ,Request $request)
    {
        if ($kandang->warehouse_id !== $request->user()->warehouse_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($kandang->flag_deleted) {
             return response()->json(['success' => false, 'message' => 'Kandang not found'], 404);
        }
        return response()->json(['success' => true, 'data' => $kandang]);
    }

    /**
     * Memperbarui data kandang yang ada.
     */
    public function update(Request $request, Kandang $kandang)
    {
        // Keamanan: Pastikan user hanya bisa mengedit kandang di warehousenya sendiri
        if ($kandang->warehouse_id !== $request->user()->warehouse_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $user = $request->user();

        $validatedData = $request->validate([
            'kode_room' => [
                'required',
                'string',
                Rule::unique('kandangs')->where(function ($query) use ($user) {
                    return $query->where('warehouse_id', $user->warehouse_id);
                })->ignore($kandang->id),
            ],
            'quota' => 'required|integer|min:1',
            'status' => 'required|string|in:Aktif,Tidak Aktif,Perbaikan',
        ]);

        $kandang->update($validatedData);

        return response()->json(['success' => true, 'message' => 'Kandang updated successfully', 'data' => $kandang]);
    }

    /**
     * Menghapus kandang (soft delete).
     */
    public function destroy(Kandang $kandang)
    {
        $kandang->update([
            'status' => 'Tidak Aktif',
            'flag_deleted' => true
        ]);

        return response()->json(['success' => true, 'message' => 'Kandang soft deleted successfully']);
    }

    /**
     * Fungsi untuk autocomplete/pencarian dropdown.
     */
    public function find(Request $request)
    {
        $request->validate(['searchTerm' => 'nullable|string']);
        $term = $request->searchTerm;

        $query = Kandang::query()
                    ->where('flag_deleted', false)
                    ->where('status', 'Aktif');

        if ($term) {
            $query->where('kode_room', 'like', '%' . $term . '%');
        }

        $kandangs = $query->limit(10)->get();

        $formatted_kandangs = $kandangs->map(function ($kandang) {
            return ['id' => $kandang->id, 'text' => $kandang->kode_room . ' (Kuota: ' . $kandang->quota . ')'];
        });

        return response()->json($formatted_kandangs);
    }
}