<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Konfigurasi Pest
|--------------------------------------------------------------------------
|
| Test bergaya Pest (function test()/it()) di folder Feature otomatis memakai
| TestCase Laravel + RefreshDatabase (database di-reset tiap test, memakai
| database MySQL "starter_mvc_test" dari phpunit.xml).
|
| Test bawaan starter kit yang berbentuk class PHPUnit tetap berjalan normal.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
