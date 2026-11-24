<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Role;
use App\Models\User;

/**
 * Siapa boleh mengelola role (menu Admin → Roles & Permissions).
 * Didaftarkan manual di AppServiceProvider karena model Role milik
 * paket spatie (bukan App\Models), sehingga tidak ditemukan otomatis.
 */
class RolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(PermissionName::RolesView);
    }

    public function create(User $actor): bool
    {
        return $actor->can(PermissionName::RolesCreate);
    }

    public function update(User $actor, Role $role): bool
    {
        return $actor->can(PermissionName::RolesUpdate);
    }

    public function delete(User $actor, Role $role): bool
    {
        return $actor->can(PermissionName::RolesDelete);
    }
}
