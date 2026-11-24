<?php

use App\Http\Controllers\Admin\ApiGeneratorController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin (Web)
|--------------------------------------------------------------------------
|
| URL   : /admin/...
| Nama  : admin.*   (contoh: route('admin.users.index'))
|
| Middleware di sini hanya memastikan user login & email terverifikasi.
| Izin per aksi diperiksa oleh Policy (UserPolicy, RolePolicy, ...).
|
*/

Route::middleware(['auth', 'verified'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Katalog. Policy kategori/produk mengizinkan PUBLIK membaca (untuk
        // API katalog), jadi panel admin dijaga tambahan "can:<modul>.view".
        //
        // URL bersih: baris dibuka lewat ULID acak, bukan id berurutan
        // ({x:ulid} / ->scoped([...=> 'ulid'])), mis.
        //   /admin/products/01jd8q3x7k2m9vz4p6r8t0w2yb/edit
        // API (/api/v1/...) tetap memakai id.
        Route::middleware('can:categories.view')->group(function () {
            Route::resource('categories', CategoryController::class)
                ->except('show')
                ->scoped(['category' => 'ulid']);
        });

        // Products juga menyimpan filter daftar di session (remember.table).
        Route::middleware('can:products.view')->group(function () {
            // Bulk delete (checklist di daftar). Didaftarkan SEBELUM resource
            // agar "bulk" tidak dianggap {product}.
            Route::delete('products/bulk', [ProductController::class, 'bulkDestroy'])
                ->name('products.bulk-destroy');
            // Ekspor baris yang dicentang (?ids[]=<ulid>...): PDF / Excel / CSV.
            Route::get('products/pdf', [ProductController::class, 'pdf'])
                ->name('products.pdf');
            Route::get('products/excel', [ProductController::class, 'excel'])
                ->name('products.excel');
            Route::get('products/csv', [ProductController::class, 'csv'])
                ->name('products.csv');
            // Upload produk dari Excel / CSV (+ template kosong).
            Route::post('products/import', [ProductController::class, 'importExcel'])
                ->name('products.import');
            Route::get('products/import/template', [ProductController::class, 'importTemplate'])
                ->name('products.import-template');
            Route::resource('products', ProductController::class)
                ->except('show')
                ->middlewareFor('index', 'remember.table')
                ->scoped(['product' => 'ulid']);
        });

        Route::resource('users', UserController::class)->except('show')->scoped(['user' => 'ulid']);
        Route::post('users/{user:ulid}/logout-devices', [UserController::class, 'logoutDevices'])
            ->name('users.logout-devices');

        Route::resource('roles', RoleController::class)->except('show')->scoped(['role' => 'ulid']);

        // Access Control: matriks permission × role (klik untuk beri/cabut).
        Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::patch('roles/{role:ulid}/permissions', [PermissionController::class, 'update'])
            ->name('roles.permissions.update');

        // Contoh halaman yang DIKUNCI permission (bukan cuma menunya disembunyikan).
        // Tanpa testing.view → 403, walau URL diketik langsung.
        Route::inertia('testing', 'admin/testing')
            ->middleware('can:testing.view')
            ->name('testing');

        // Menu sidebar dashboard & navbar website (?location=admin|website).
        // "reorder" didaftarkan SEBELUM resource agar tidak dianggap {menu}.
        Route::put('menus/reorder', [MenuController::class, 'reorder'])->name('menus.reorder');
        Route::patch('menus/{menu:ulid}/visibility', [MenuController::class, 'visibility'])->name('menus.visibility');
        Route::get('menus/generator-status/{token}', [MenuController::class, 'generatorStatus'])
            ->where('token', '[A-Za-z0-9-]{8,64}')
            ->name('menus.generator-status');
        // CRUD generator → "Use an existing table" (JSON, hanya development).
        Route::get('menus/generator/tables', [MenuController::class, 'generatorTables'])->name('menus.generator-tables');
        Route::get('menus/generator/table', [MenuController::class, 'generatorTable'])->name('menus.generator-table');
        Route::resource('menus', MenuController::class)->except('show')->scoped(['menu' => 'ulid']);

        // API Generator (hanya development): REST API untuk modul hasil generator
        // & tabel yang sudah ada (mis. tabel ERP project lama), tanpa halaman admin.
        Route::get('api-generator', [ApiGeneratorController::class, 'index'])->name('api-generator.index');
        Route::post('api-generator', [ApiGeneratorController::class, 'store'])->name('api-generator.store');
        Route::post('api-generator/{table}/api', [ApiGeneratorController::class, 'addApi'])
            ->where('table', '[a-z0-9_]+')
            ->name('api-generator.add-api');
        Route::delete('api-generator/{table}/api', [ApiGeneratorController::class, 'removeApi'])
            ->where('table', '[a-z0-9_]+')
            ->name('api-generator.remove-api');
        Route::delete('api-generator/{table}', [ApiGeneratorController::class, 'destroy'])
            ->where('table', '[a-z0-9_]+')
            ->name('api-generator.destroy');

        // Modul dari CRUD generator (Admin → Menus → "Generate CRUD page").
        require __DIR__.'/generated.php';
    });
