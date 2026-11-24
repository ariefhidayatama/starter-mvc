<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Data awal untuk development.
     *
     * php artisan migrate:fresh --seed
     *
     * Akun contoh (password semuanya: "password"):
     * - admin@example.com     → admin
     * - staff@example.com     → staff
     * - customer@example.com  → customer
     */
    public function run(): void
    {
        $this->call([RolePermissionSeeder::class, MenuSeeder::class, CatalogSeeder::class]);

        foreach (RoleName::cases() as $role) {
            User::factory()
                ->create([
                    'name' => $role->label().' Demo',
                    'email' => $role->value.'@example.com',
                ])
                ->assignRole($role);
        }

        User::factory(25)->create()->each(fn (User $user) => $user->assignRole(RoleName::Customer));
    }
}
