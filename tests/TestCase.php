<?php

namespace Tests;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Setiap test yang memakai RefreshDatabase otomatis diisi role &
     * permission (bukan seluruh DatabaseSeeder, agar data test tetap bersih).
     */
    protected bool $seed = true;

    protected string $seeder = RolePermissionSeeder::class;

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
