<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hapus token Sanctum yang sudah kedaluwarsa > 24 jam, setiap hari.
// Jalankan scheduler di server: * * * * * php artisan schedule:run
// (lokal: php artisan schedule:work)
Schedule::command('sanctum:prune-expired --hours=24')->daily();
