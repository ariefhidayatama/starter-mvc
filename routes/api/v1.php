<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\DeviceController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\PingController;
use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| URL      : /api/v1/...
| Nama     : api.v1.*   (contoh: route('api.v1.ping'))
| Auth     : Header "Authorization: Bearer <token>" (Sanctum)
| Limit    : 60 request/menit per user/IP (limiter "api")
|
| RBAC (diatur di halaman Admin → Permissions):
|   1. Token valid + akun aktif        → auth:sanctum
|   2. Role punya "api.access"          → middleware api.access
|   3. Role punya izin modul, mis. "messages.view" → Policy / Form Request
|      (permission yang SAMA dengan halaman web)
|
*/

// Publik: cek API hidup & versi (dipakai juga oleh k6 smoke test).
Route::get('ping', PingController::class)->name('ping');

// Publik: menu navbar WEBSITE (diatur dari Admin → Menus), dipakai halaman publik.
Route::get('menus/website', MenuController::class)->name('menus.website');

Route::prefix('auth')->name('auth.')->group(function () {
    // Publik. Limiter "login": 5 percobaan/menit per kombinasi email + IP.
    // Role tanpa "api.access" ditolak 403 (lihat IssueApiTokenAction).
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login');

    Route::middleware(['auth:sanctum', 'api.access'])->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
        Route::delete('devices/{tokenId}', [DeviceController::class, 'destroy'])
            ->whereNumber('tokenId')
            ->name('devices.destroy');
    });

    // Logout tetap boleh walau api.access sudah dicabut, agar token lama
    // bisa dibuang oleh pemiliknya.
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
    });
});

Route::middleware(['auth:sanctum', 'api.access'])->group(function () {
    /*
    | Katalog: SEMUA endpoint wajib token.
    | - GET: semua user ber-api.access; produk nonaktif hanya untuk products.view.
    | - POST/PUT/PATCH/DELETE: permission modul (dicek Form Request/Policy).
    */
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('products', ProductController::class);

    // Endpoint hasil CRUD generator (php artisan make:crud --api / crud:api).
    require __DIR__.'/generated.php';
});

// Route yang tidak dikenal di bawah /api/v1 → JSON 404 standar
// (diformat oleh App\Exceptions\ApiExceptionRenderer).
Route::fallback(fn () => abort(404, 'Endpoint not found.'));
