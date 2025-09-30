<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use Exception; // Import Exception class for error handling

class BranchesController extends Controller
{
    /**
     * Menampilkan daftar cabang.
     * Dapat memberikan semua data jika ada parameter ?all=true
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Cek apakah ada query parameter 'all'
            if ($request->query('all')) {
                // Jika ya, ambil semua data cabang hanya kolom id dan name
                $branches = Branch::all(['id', 'name']);
            } else {
                // Jika tidak, ambil data dengan paginasi (misal: 10 per halaman)
                $branches = Branch::latest()->paginate(10);
            }

            // Kembalikan response dalam format JSON yang sukses
            return response()->json([
                'status' => 'success',
                'message' => 'Data cabang berhasil diambil',
                'data' => $branches,
            ], 200);

        } catch (Exception $e) {
            // Jika terjadi error, kembalikan response error
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil data cabang.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Anda bisa menambahkan method lain di sini nanti
    // seperti store (untuk membuat cabang baru), update, destroy, dll.
}