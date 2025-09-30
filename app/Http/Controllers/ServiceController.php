<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * Menampilkan daftar semua layanan dengan paginasi dan pencarian.
     */
    public function index(Request $request)
    {
        // Jika ada parameter ?all=true, ambil semua data untuk dropdown
        if ($request->boolean('all')) {
            $services = Service::orderBy('name')->get();
            return response()->json(['data' => $services]);
        }

        $query = Service::with('category');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $services = $query->latest()->paginate(10);

        return response()->json(['data' => $services]);
    }

    /**
     * Menyimpan layanan baru.
     */
    public function store(Request $request)
    {
        // DIUBAH: Validasi sekarang menggunakan 'category_id' dan merujuk ke tabel 'categories'
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|integer|exists:categories,id',
            'price' => 'required|numeric|min:0',
        ]);

        $service = Service::create($validated);

        return response()->json(['message' => 'Layanan berhasil dibuat', 'data' => $service], 201);
    }

    /**
     * Memperbarui layanan yang ada.
     */
    public function update(Request $request, Service $service)
    {
        // DIUBAH: Validasi sekarang menggunakan 'category_id' dan merujuk ke tabel 'categories'
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|integer|exists:categories,id',
            'price' => 'required|numeric|min:0',
        ]);

        $service->update($validated);

        return response()->json(['message' => 'Layanan berhasil diperbarui', 'data' => $service]);
    }

    /**
     * Menghapus layanan.
     */
    public function destroy(Service $service)
    {
        $service->delete();
        return response()->json(['message' => 'Layanan berhasil dihapus']);
    }

    // Fungsi getCategories tidak lagi diperlukan di sini,
    // karena frontend akan mengambil dari CategoryController.
}

