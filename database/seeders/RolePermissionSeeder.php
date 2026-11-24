<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Menyinkronkan tabel permissions dengan enum PermissionName.
 *
 * AMAN dijalankan berulang (idempotent):
 *   php artisan db:seed --class=RolePermissionSeeder
 *
 * - Permission baru di enum → dibuat.
 * - Permission yang sudah dihapus dari enum → dihapus dari database.
 * - Role sistem dibuat bila belum ada.
 * - Admin selalu diberi SEMUA permission.
 * - Permission role lain yang sudah diatur dari UI TIDAK ditimpa,
 *   kecuali saat role itu baru pertama kali dibuat (diberi nilai awal).
 */
class RolePermissionSeeder extends Seeder
{
    private const GUARD = 'web';

    /**
     * Permission awal untuk role yang baru dibuat.
     *
     * @var array<string, list<PermissionName>>
     */
    private const DEFAULTS = [
        // Staff mengelola katalog sehari-hari, tetapi tidak menghapus.
        'staff' => [
            PermissionName::CategoriesView,
            PermissionName::CategoriesCreate,
            PermissionName::CategoriesUpdate,
            PermissionName::ProductsView,
            PermissionName::ProductsCreate,
            PermissionName::ProductsUpdate,
            PermissionName::UsersView,
            PermissionName::ApiAccess,
        ],
        // Customer: boleh memakai API (aplikasi mobile) untuk membaca katalog
        // (produk aktif bisa dilihat semua user login, lihat ProductPolicy).
        'customer' => [
            PermissionName::ApiAccess,
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionName::cases() as $permission) {
            Permission::findOrCreate($permission->value, self::GUARD);
        }

        Permission::query()
            ->where('guard_name', self::GUARD)
            ->whereNotIn('name', PermissionName::values())
            ->delete();

        foreach (RoleName::cases() as $roleName) {
            $role = Role::query()
                ->where('name', $roleName->value)
                ->where('guard_name', self::GUARD)
                ->first();

            if ($role === null) {
                $role = Role::create(['name' => $roleName->value, 'guard_name' => self::GUARD]);
                $role->syncPermissions(self::DEFAULTS[$roleName->value] ?? []);
            }

            if ($roleName === RoleName::Admin) {
                $role->syncPermissions(PermissionName::values());
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
