<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  array<string>  $roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // 1. Cek apakah user sudah login
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // 2. Cek apakah user memiliki salah satu dari peran yang diizinkan
        $user = Auth::user();
        if ($user->hasAnyRole($roles)) {
            // Jika ya, izinkan request untuk melanjutkan
            return $next($request);
        }

        // 3. Jika tidak punya peran, tolak akses
        return response()->json(['message' => 'This action is unauthorized.'], 403);
    }
}
