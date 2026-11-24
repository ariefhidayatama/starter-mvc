<?php

/*
|--------------------------------------------------------------------------
| CRUD Generator
|--------------------------------------------------------------------------
|
| Membuat Migration, Model, Factory, Policy, Request, Controller, halaman
| React, dan test dari form "Add menu" (atau `php artisan make:crud`).
|
| HANYA untuk development: generator menulis FILE KODE ke disk. Di server
| production fitur ini selalu mati (lihat CrudGenerator::enabled()),
| apa pun isi .env.
|
*/

return [

    // Default: aktif bila APP_ENV=local. Bisa dipaksa lewat CRUD_GENERATOR=true|false.
    'enabled' => (bool) env('CRUD_GENERATOR', env('APP_ENV', 'production') === 'local'),

    // Perintah PHP & Node untuk langkah lanjutan (migrate, seeder, Wayfinder,
    // formatter). Kosong = dicari otomatis. Isi bila web server memakai
    // PHP berbeda dari terminal, mis. C:\wamp64\bin\php\php8.4.0\php.exe
    'php_binary' => env('CRUD_PHP_BINARY'),
    'node_binary' => env('CRUD_NODE_BINARY', 'node'),

    // Database LAIN (selain default) yang bisa dipilih di generator, untuk
    // membuat tabel baru maupun memakai tabel yang sudah ada.
    // Nama = kunci di config/database.php → label.
    // Disembunyikan otomatis bila host di .env kosong atau driver PHP-nya
    // (pdo_mysql / pdo_pgsql / pdo_sqlsrv) tidak aktif.
    'connections' => [
        'wmsreports' => 'WMS Reports (SQL Server)',
        'postgres' => 'PostgreSQL',
    ],

    // Kolom audit yang dikenali otomatis pada tabel yang sudah ada:
    // tidak tampil di form, diisi otomatis (waktu & nama user yang login).
    'audit_columns' => [
        'created_at' => ['created_at', 'created_date', 'create_date', 'createdate', 'date_created'],
        'updated_at' => ['updated_at', 'updated_date', 'update_date', 'updatedate', 'modified_date', 'date_modified'],
        'created_by' => ['created_by', 'create_by', 'createdby', 'user_created'],
        'updated_by' => ['updated_by', 'update_by', 'updatedby', 'modified_by', 'user_modified'],
    ],

];
