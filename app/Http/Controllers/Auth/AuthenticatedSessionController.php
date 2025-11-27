<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $user = $request->user();
        
        if (is_null($user->branches_id)) {
            $user->tokens()->delete();
            
            throw ValidationException::withMessages([
                'email' => 'User does not have an assigned branch. Please contact administrator.',
            ]);
        }

        $user->load(['role', 'branches']); 
        
        $token = $user->createToken('auth_token')->plainTextToken;

        $branch = $user->branches;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_name' => $user->role->name ?? 'default', // Menggunakan role_name
            ],
            // PENTING: Mengirim data cabang yang diperlukan frontend
            'active_branch' => [
                'id' => $user->branches_id,
                'name' => $branch->name,
            ],
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }


    /**
     * Destroy an authenticated session (Logout).
     */
    public function destroy(Request $request)
    {
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logout successful'
        ]);
    }
}