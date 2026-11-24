<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permission baru "api.access" (boleh memakai REST API).
 *
 * Sistem lain SUDAH memanggil API, jadi semua role yang ada saat ini
 * diberi api.access agar tidak ada integrasi yang tiba-tiba ditolak (403).
 * Setelah itu admin bisa mencabutnya per role di halaman Permissions.
 *
 * Database baru (migrate:fresh) belum punya role di tahap ini; nilai awalnya
 * diatur RolePermissionSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->insertOrIgnore([
            'name' => 'api.access',
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $permissionId = DB::table('permissions')
            ->where('name', 'api.access')
            ->where('guard_name', 'web')
            ->value('id');

        $rows = DB::table('roles')
            ->where('guard_name', 'web')
            ->pluck('id')
            ->map(fn ($roleId) => ['permission_id' => $permissionId, 'role_id' => $roleId])
            ->all();

        DB::table('role_has_permissions')->insertOrIgnore($rows);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'api.access')->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
