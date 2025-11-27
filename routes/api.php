<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\KandangController;
use App\Http\Controllers\PetTypeController;
use App\Http\Controllers\PetController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ReportController;
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
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentModeController;
use App\Http\Controllers\PaymentsOutController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\TransferStockController;
use App\Models\PaymentsOut;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// --- Rute Autentikasi dari Breeze ---
require __DIR__.'/auth.php';

Route::post('/register', [RegisteredUserController::class, 'store']);
Route::post('/login', [AuthenticatedSessionController::class, 'store']);


// =====================================================================
// === A. AKSES KHUSUS SUPER ADMIN & KONFIGURASI ========================
// =====================================================================

Route::middleware(['auth:sanctum', 'role:super-admin'])->group(function () {
    // Role Management (Hanya Super Admin)
    Route::apiResource('roles', RoleController::class);

    // Manajemen Cabang/Outlet (CRUD - Hanya Super Admin)
    Route::apiResource('branches', BranchesController::class)->except(['index']); 
    
    // Manajemen Pajak (Tax - CRUD)
    Route::apiResource('taxes', TaxController::class)->except(['index']);
});


// =====================================================================
// === B. AKSES ADMINISTRASI CABANG (CRUD) =============================
// =====================================================================

$adminRoles = ['super-admin', 'admin-cabang'];

Route::middleware(['auth:sanctum', 'role:' . implode(',', $adminRoles)])->group(function () {
    
    // Master Data Umum (CRUD - Kecuali index, yang dipindahkan ke Transaksi)
    Route::apiResource('units', UnitController::class)->except(['index']);
    Route::apiResource('brands', BrandController::class)->except(['index']);
    Route::apiResource('categories', CategoryController::class)->except(['index']);
    Route::apiResource('services', ServiceController::class)->except(['index']);
    Route::apiResource('petType', PetTypeController::class)->except(['index']);
    Route::apiResource('warehouse', WarehouseController::class)->except(['index']);

    // Manajemen Stok & Produk (CRUD - Kecuali index)
    Route::apiResource('products', ProductController::class)->except(['index']);
    Route::get('/products/find-brands', [ProductController::class, 'findBrands']); 
    Route::apiResource('purchases', PurchaseController::class);
    Route::apiResource('payments-out', PaymentsOutController::class);
    Route::apiResource('transfer-stock', TransferStockController::class);

    // Kandang/Hotel Management (CRUD & Availability Check - Kecuali index)
    Route::apiResource('kandangs', KandangController::class)->except(['show', 'create', 'edit', 'index']); 
    Route::get('kandangs/active-list', [KandangController::class, 'getActiveList']);
    
    // Laporan (Admin perlu akses ke semua laporan)
    Route::get('/reports/profit-loss', [ReportController::class, 'getProfitLoss']);
    Route::get('/reports/payment-report', [ReportController::class, 'getPaymentReport']);
    Route::get('/reports/minimum-stock', [ReportController::class, 'getMinimumStockReport']);
    Route::get('/reports/sales', [ReportController::class, 'getSalesReport']);
    Route::get('/reports/sales-by-product', [ReportController::class, 'getSalesByProductReport']);
    Route::get('/reports/stock-recap', [ReportController::class, 'getStockRecapReport']);
    Route::get('reports/profit-loss/download', [ReportController::class, 'downloadProfitLoss']);
});


// =====================================================================
// === C. AKSES TRANSAKSIONAL & BACA (SEMUA ROLE) =======================
// =====================================================================

$transactRoles = ['super-admin', 'admin-cabang', 'kasir'];

Route::middleware(['auth:sanctum', 'role:' . implode(',', $transactRoles)])->group(function () {

    // 1. AKSES BACA MASTER DATA (Wajib untuk POS)
    Route::apiResource('branches', BranchesController::class)->only(['index']);
    Route::apiResource('taxes', TaxController::class)->only(['index']);
    Route::apiResource('units', UnitController::class)->only(['index']);
    Route::apiResource('brands', BrandController::class)->only(['index']);
    Route::apiResource('categories', CategoryController::class)->only(['index']);
    Route::apiResource('services', ServiceController::class)->only(['index']);
    Route::apiResource('petType', PetTypeController::class)->only(['index']);
    Route::apiResource('warehouse', WarehouseController::class)->only(['index']);
    
    // Produk dan Kandang (BACA)
    Route::apiResource('products', ProductController::class)->only(['index']);
    Route::get('kandangs/availability', [KandangController::class, 'checkAvailability']); 
    Route::get('kandangs/find', [KandangController::class, 'find']); 
    Route::apiResource('kandangs', KandangController::class)->only(['index']); 

    // 2. AKSES TRANSAKSIONAL
    Route::apiResource('customers', CustomerController::class); 
    Route::get('customers/{customer}/pets', [CustomerController::class, 'getPetsByCustomer']);
    Route::apiResource('pets', PetController::class);
    
    // Registrasi & POS Core
    Route::apiResource('registrations', RegistrationController::class);
    Route::patch('/registrations/{registration}/status', [RegistrationController::class, 'updateStatus']);
    Route::get('/registrations/details', [RegistrationController::class, 'getDetailsByIds']); 
    Route::get('/registrations/counts', [RegistrationController::class, 'getCounts']);
    
    // Payment Process & Diskon
    Route::post('payments', [PaymentController::class, 'store']);
    Route::apiResource('payments', PaymentController::class)->only(['index', 'show']);
    Route::apiResource('payment-modes', PaymentModeController::class)->only(['index']);
    Route::apiResource('discounts', DiscountController::class);
    Route::post('discounts/validate', [DiscountController::class, 'validateCode']);
    
    // User Profile & Logout
    Route::apiResource('user', UserController::class); 
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
    Route::get('/dashboard-stats', [DashboardController::class, 'getStats']); 
});