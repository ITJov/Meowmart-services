<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\KandangController;
use App\Http\Controllers\PetTypeController;
use App\Http\Controllers\PetController;
use App\Http\Controllers\PetHotelController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\BranchesController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\BrandController as ControllersBrandController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PurchaseController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// --- Rute Autentikasi dari Breeze ---
// Endpoint seperti /login & /register ada di sini.
// Tidak perlu login untuk mengakses ini.

require __DIR__.'/auth.php';

Route::post('/register', [RegisteredUserController::class, 'store']);
Route::post('/login', [AuthenticatedSessionController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    // dashboard
    Route::get('/dashboard-stats', [DashboardController::class, 'getStats']);

    // role
    Route::apiResource('roles', RoleController::class);

    // customers
    Route::apiResource('customers', CustomerController::class);
    Route::get('customers/{customer}/pets', [CustomerController::class, 'getPetsByCustomer']);

    // user
    Route::apiResource('user', UserController::class);

    // warehouse
    Route::apiResource('warehouse', WarehouseController::class);

    // branches
    Route::apiResource('branches', BranchesController::class);

    // pet type
    Route::apiResource('petType', PetTypeController::class);

    // pet
    Route::apiResource('pets', PetController::class);

    // pet hotel
    Route::apiResource('pet-hotel', PetHotelController::class);

    // kandangs
    Route::apiResource('kandangs', KandangController::class);

    // products
    Route::apiResource('products', ProductController::class);
    Route::get('/products/find-brands', [ProductController::class, 'findBrands']);

    // purchases
    Route::apiResource('purchases', PurchaseController::class);

    // category
    Route::apiResource('categories', CategoryController::class);

    // brands
    Route::apiResource('brands', BrandController::class);

    // units
    Route::apiResource('units', UnitController::class);

    // units
    Route::apiResource('services', ServiceController::class);

    // payment
    Route::apiResource('payment', PaymentController::class);

    // registrations
    Route::get('/registrations/details', [RegistrationController::class, 'getDetailsByIds']); 
    Route::get('/registrations/counts', [RegistrationController::class, 'getCounts']);
    Route::patch('/registrations/{registration}/status', [RegistrationController::class, 'updateStatus']);
    Route::apiResource('registrations', RegistrationController::class);

    // logout
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
});