<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            if ($request->query('all')) {
                $branches = Branch::all(['id', 'name']);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Data cabang berhasil diambil',
                    'data' => $branches,
                ], 200);

            } else {
                $request->validate([
                    'search' => 'nullable|string',
                    'per_page' => 'nullable|integer',
                ]);

                $query = Branch::query();

                if ($request->filled('search')) {
                    $query->where('name', 'like', '%' . $request->search . '%')
                          ->orWhere('email', 'like', '%' . $request->search . '%');
                }

                $branches = $query->latest()->paginate($request->input('per_page', 10));

                return response()->json([
                    'status' => 'success',
                    'message' => 'Data cabang berhasil diambil',
                    'data' => $branches,
                ], 200);
            }

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil data cabang.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Menyimpan cabang baru (CREATE).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:branches,name',
            'email' => 'required|email|unique:branches,email',
            'phone' => 'required|string|max:50',
            'address' => 'required|string',
        ]);

        $branch = Branch::create($validated);
        return response()->json(['message' => 'Cabang berhasil dibuat.', 'data' => $branch], 201);
    }

    /**
     * Memperbarui cabang (UPDATE).
     */
    public function update(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('branches')->ignore($branch->id)],
            'email' => ['required', 'email', Rule::unique('branches')->ignore($branch->id)],
            'phone' => 'required|string|max:50',
            'address' => 'required|string',
        ]);

        $branch->update($validated);
        return response()->json(['message' => 'Cabang berhasil diperbarui.', 'data' => $branch]);
    }
}