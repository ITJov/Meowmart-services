<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized: User not authenticated.'], 401);
        }

        $user = Auth::user();

        // PENTING: Eager Load relasi role Anda (bukan 'roles' dari Spatie)
        // Kita menggunakan belongsTo(Role::class) yang Anda definisikan.
        $user->load('role'); 
        
        // Ambil nama peran dari relasi role yang dimuat.
        // Asumsi: Tabel roles memiliki kolom 'name' (yang berisi 'kasir', 'super-admin', dll.)
        $userRole = strtolower($user->role->name ?? 'default'); // <-- Mengambil nama peran dari relasi FK
        
        // Cek apakah peran user diizinkan
        if (!in_array($userRole, $roles)) {
            // Blokade Akses
            return response()->json(['message' => 'Forbidden: Access denied for this role.'], 403);
        }

        return $next($request);
    }
}
