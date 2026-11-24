<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — pintu masuk versi
|--------------------------------------------------------------------------
|
| File ini hanya memetakan prefix versi ke file route masing-masing.
| Semua URL di sini otomatis diawali "/api" (diatur bootstrap/app.php)
| dan memakai middleware group "api" (stateless, tanpa session/cookie).
|
| Versi baru (v2) HANYA dibuat jika ada breaking change pada kontrak JSON.
|
*/

Route::prefix('v1')
    ->name('api.v1.')
    ->group(base_path('routes/api/v1.php'));
