<?php

namespace App\Http\Controllers;

use App\Models\Tax;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaxController extends Controller
{
    /**
     * Menampilkan daftar tarif pajak.
     */
    public function index(Request $request)
    {
        if (!$request->has('page') && !$request->has('per_page')) {
            $taxes = Tax::all(['id', 'name', 'rate']);
            return response()->json(['success' => true, 'data' => $taxes]);
        }

        $query = Tax::query();
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $taxes = $query->latest()->paginate($request->input('per_page', 10));
        return response()->json(['success' => true, 'data' => $taxes]);
    }

    /**
     * Menyimpan tarif pajak baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:taxes,name',
            'rate' => 'required|numeric|min:0|max:100', 
        ]);

        $tax = Tax::create($validated);

        return response()->json(['message' => 'Tarif pajak berhasil dibuat.', 'data' => $tax], 201);
    }

    /**
     * Memperbarui tarif pajak.
     */
    public function update(Request $request, Tax $tax)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('taxes')->ignore($tax->id)],
            'rate' => 'required|numeric|min:0|max:100',
        ]);

        $tax->update($validated);

        return response()->json(['message' => 'Tarif pajak berhasil diperbarui.', 'data' => $tax]);
    }

    /**
     * Menghapus tarif pajak.
     */
    public function destroy(Tax $tax)
    {
        // Tambahkan validasi apakah pajak sedang digunakan di order sebelum dihapus (Disarankan)
        if ($tax->orders()->exists()) {
             return response()->json(['message' => 'Tarif pajak tidak dapat dihapus karena sudah digunakan dalam transaksi.'], 400);
        }
        
        $tax->delete();
        return response()->json(['message' => 'Tarif pajak berhasil dihapus.']);
    }
}