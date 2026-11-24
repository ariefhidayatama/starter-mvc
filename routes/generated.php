<?php

use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\MessageDetailController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Route hasil CRUD generator
|--------------------------------------------------------------------------
|
| File ini di-require dari routes/admin.php (di dalam grup /admin, nama
| admin.*, middleware auth + verified). Blok "crud:<tabel>" ditambah &
| dihapus otomatis oleh generator; route lain boleh ditulis di luar blok.
| Jangan hapus baris penanda "@crud:routes" di paling bawah.
|
*/

// crud:messages
Route::middleware('can:messages.view')->group(function () {
    Route::resource('messages', MessageController::class)->except('show');
    Route::resource('messages.details', MessageDetailController::class)->except(['index', 'show'])->scoped();
});
// end crud:messages
// crud:kwitansis
Route::middleware('can:kwitansis.view')->group(function () {
    Route::resource('kwitansis', App\Http\Controllers\Admin\KwitansiController::class)->except('show');
    Route::resource('kwitansis.kwitansi-dets', App\Http\Controllers\Admin\KwitansiKwitansiDetController::class)->except(['index', 'show'])->scoped();
});
// end crud:kwitansis
// @crud:routes
