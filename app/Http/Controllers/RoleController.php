<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    /**
     * Menampilkan daftar role milik perusahaan user yang login.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        // Query hanya mengambil role milik perusahaan user tersebut
        $query = Role::query()->where('company_id', $user->company_id);

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $roles = $query->latest()->paginate(15);

        return response()->json(['success' => true, 'data' => $roles]);
    }

    /**
     * Menyimpan role baru.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $validatedData = $request->validate([
            'name' => [
                'required', 'string', 'alpha_dash',
                Rule::unique('roles')->where('company_id', $user->company_id)
            ],
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $validatedData['company_id'] = $user->company_id;
        $role = Role::create($validatedData);

        return response()->json(['success' => true, 'message' => 'Role created', 'data' => $role], 201);
    }

    /**
     * Menampilkan detail satu role.
     */
    public function show(Role $role, Request $request)
    {
        // Keamanan: Pastikan user hanya bisa melihat role di perusahaannya
        if ($role->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        return response()->json(['success' => true, 'data' => $role]);
    }

    /**
     * Memperbarui data role.
     */
    public function update(Request $request, Role $role)
    {
        // Keamanan: Pastikan user hanya bisa mengedit role di perusahaannya
        if ($role->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $user = $request->user();
        $validatedData = $request->validate([
            'name' => [
                'required', 'string', 'alpha_dash',
                Rule::unique('roles')->where('company_id', $user->company_id)->ignore($role->id)
            ],
            'display_name' => 'required|string|max:255',
        ]);

        $role->update($validatedData);

        return response()->json(['success' => true, 'message' => 'Role updated', 'data' => $role]);
    }

    /**
     * Menghapus role.
     */
    public function destroy(Role $role, Request $request)
    {
        // Keamanan: Pastikan user hanya bisa menghapus role di perusahaannya
        if ($role->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // Pengecekan apakah role masih digunakan
        $userCount = User::where('role_id', $role->id)->count();
        if ($userCount > 0) {
            return response()->json(['success' => false, 'message' => "Can't delete, Role is still used."], 400);
        }

        $role->delete();

        return response()->json(['success' => true, 'message' => 'Role deleted successfully']);
    }
}