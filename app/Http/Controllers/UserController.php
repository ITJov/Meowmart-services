<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Menampilkan daftar staf berdasarkan cabang yang dipilih di frontend.
     */
    public function index(Request $request)
    {
        $request->validate([
            'branches_id' => 'required|integer|exists:branches,id',
            'search' => 'nullable|string',
        ]);

        $query = User::with(['role', 'branches'])
                    ->where('branches_id', $request->branches_id);

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $staff = $query->latest()->paginate(15);
        return response()->json(['data' => $staff]);
    }

    /**
     * Menyimpan data staf baru ke cabang yang ditentukan.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->where('branches_id', $request->branches_id)],
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string', 
            'address' => 'nullable|string', 
            'role_id' => 'required|integer|exists:roles,id',
            'branches_id' => 'required|integer|exists:branches,id',
        ]);

        $validatedData['password'] = Hash::make($validatedData['password']);

        $staff = User::create($validatedData);

        return response()->json(['success' => true, 'message' => 'Staff created', 'data' => $staff], 201);
    }

    /**
     * Menampilkan detail satu staf.
     */
    public function show(User $user)
    {
        $user->load(['role', 'branches']);
        return response()->json(['data' => $user]);
    }

    /**
     * Memperbarui data staf.
     */
    public function update(Request $request, User $user)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->where('branches_id', $user->branches_id)->ignore($user->id)],
            'phone' => 'nullable|string', 
            'address' => 'nullable|string', 
            'role_id' => 'required|integer|exists:roles,id',
            'branches_id' => 'required|integer|exists:branches,id',
        ]);

        $user->update($validatedData);
        return response()->json(['success' => true, 'message' => 'Staff updated', 'data' => $user]);
    }

    /**
     * Menghapus data staf.
     */
    public function destroy(User $user)
    {
        $user->delete();
        return response()->json(['success' => true, 'message' => 'Staff deleted successfully']);
    }
}
