<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;

/**
 * Siapa boleh mengelola user lain (menu Admin → Users).
 *
 * Catatan: admin lolos semua method ini lewat Gate::before
 * (AppServiceProvider). Karena itu aturan pengaman seperti "tidak boleh
 * menghapus diri sendiri" diletakkan di Action, bukan di sini.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(PermissionName::UsersView);
    }

    public function create(User $actor): bool
    {
        return $actor->can(PermissionName::UsersCreate);
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->can(PermissionName::UsersUpdate);
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->can(PermissionName::UsersDelete);
    }
}
