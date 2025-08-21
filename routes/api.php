<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\KandangController;
use App\Http\Controllers\PetTypeController;
use App\Http\Controllers\PetController;
// ... tambahkan controller lain yang Anda buat

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// --- Rute Autentikasi dari Breeze ---
// Endpoint seperti /login & /register ada di sini.
// Tidak perlu login untuk mengakses ini.
require __DIR__.'/auth.php';


// --- Rute yang Membutuhkan Login (Dilindungi Sanctum) ---
Route::middleware('auth:sanctum')->group(function () {
    
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // --- Rute CRUD untuk Fitur-Fitur Anda ---
    Route::apiResource('warehouses', WarehouseController::class);
    Route::apiResource('kandangs', KandangController::class);
    Route::apiResource('pet-types', PetTypeController::class);
    Route::apiResource('pets', PetController::class);
    Route::apiResource('products', ProductController::class);
    
    // Rute ini hanya bisa diakses oleh user dengan role 'super-admin'
    Route::middleware('role:super-admin')->group(function () {
        Route::apiResource('roles', RoleController::class);
        // Route::apiResource('users', UserController::class); // Aktifkan jika sudah punya UserController
    });

});