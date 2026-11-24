<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Menu;
use App\Models\User;

/**
 * Siapa yang boleh MENGELOLA menu. (Siapa yang boleh MELIHAT sebuah menu
 * di sidebar diatur oleh kolom `permission` milik menu itu sendiri.)
 */
class MenuPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(PermissionName::MenusView);
    }

    public function create(User $actor): bool
    {
        return $actor->can(PermissionName::MenusCreate);
    }

    public function update(User $actor, Menu $menu): bool
    {
        return $actor->can(PermissionName::MenusUpdate);
    }

    /**
     * Menu bawaan (is_system) ditolak oleh DeleteMenuAction dengan pesan
     * yang jelas (409), bukan di sini (403 tanpa penjelasan).
     */
    public function delete(User $actor, Menu $menu): bool
    {
        return $actor->can(PermissionName::MenusDelete);
    }
}
