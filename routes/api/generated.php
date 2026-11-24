<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Route API hasil CRUD generator
|--------------------------------------------------------------------------
|
| File ini di-require dari routes/api/v1.php, di dalam grup
| auth:sanctum + api.access (URL /api/v1/..., nama api.v1.*).
| Izin tiap endpoint dicek Policy modulnya: GET → <modul>.view,
| POST → .create, PUT/PATCH → .update, DELETE → .delete.
|
| Blok "crud:<tabel>" ditambah & dihapus otomatis oleh generator; route lain
| boleh ditulis di luar blok. Jangan hapus baris penanda "@crud:api-routes".
|
*/

// crud:messages
Route::apiResource('messages', App\Http\Controllers\Api\V1\MessageController::class);
// end crud:messages
// @crud:api-routes
