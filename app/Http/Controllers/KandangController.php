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
            'branches_id' => 'required|integer|exists:branches,id',
            'search' => 'nullable|string',
            'status' => 'nullable|string|in:Aktif,Tidak Aktif,Perbaikan',
        ]);

        $query = Kandang::query()
                    ->where('branches_id', $request->branches_id)
                    ->where('flag_deleted', false);

        if ($request->filled('search')) {
            $query->where('kode_room', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $kandangs = $query->latest()->paginate(15);

        return response()->json(['data' => $kandangs]);
    }

    /**
     * Menyimpan kandang baru ke cabang yang ditentukan.
     */
    public function store(Request $request)
    {
        // DIUBAH: Tambahkan validasi untuk branches_id
        $validatedData = $request->validate([
            'branches_id' => 'required|integer|exists:branches,id',
            'kode_room' => ['required', 'string', Rule::unique('kandangs')->where('branches_id', $request->branches_id)],
            'quota' => 'required|integer|min:1',
            'status' => 'required|string',
        ]);
        
        // Data kandang akan dibuat dengan branches_id yang sudah divalidasi
        $kandang = Kandang::create($validatedData);

        return response()->json(['success' => true, 'message' => 'Kandang created', 'data' => $kandang], 201);
    }

    /**
     * Memperbarui data kandang yang ada.
     */
    public function update(Request $request, Kandang $kandang)
    {
        $validatedData = $request->validate([
            'kode_room' => [
                'required',
                'string',
                // Pastikan kode unik di dalam cabang yang sama, kecuali untuk data itu sendiri
                Rule::unique('kandangs')->where('branches_id', $kandang->branches_id)->ignore($kandang->id),
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