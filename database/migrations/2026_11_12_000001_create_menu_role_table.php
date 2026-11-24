<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Visibilitas menu sidebar PER ROLE.
 *
 * - Tidak ada baris di menu_role  → menu terlihat oleh semua user login.
 * - Ada baris                     → hanya role tersebut (admin selalu lolos).
 *
 * Kolom menus.permission tetap ada, tetapi kini hanya dipakai menu BAWAAN
 * (is_system) agar otomatis mengikuti izin fitur. Menu buatan sendiri yang
 * sebelumnya memakai permission dipindah ke role yang memiliki izin itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_role', function (Blueprint $table) {
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->primary(['menu_id', 'role_id']);
            $table->index('role_id');
        });

        $custom = DB::table('menus')
            ->where('is_system', false)
            ->whereNotNull('permission')
            ->get(['id', 'permission']);

        foreach ($custom as $menu) {
            $roleIds = DB::table('role_has_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->where('permissions.name', $menu->permission)
                ->pluck('role_has_permissions.role_id')
                ->merge(DB::table('roles')->where('name', 'admin')->pluck('id'))
                ->unique();

            DB::table('menu_role')->insert(
                $roleIds->map(fn ($roleId) => ['menu_id' => $menu->id, 'role_id' => $roleId])->values()->all(),
            );

            DB::table('menus')->where('id', $menu->id)->update(['permission' => null]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_role');
    }
};
