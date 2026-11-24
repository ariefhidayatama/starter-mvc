<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Kwitansi;
use App\Models\User;

/**
 * Setiap aksi = satu permission (kwitansis.view / create / update / delete).
 * Atur siapa yang boleh di halaman Permissions. Dibuat oleh CRUD generator.
 */
class KwitansiPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(PermissionName::KwitansisView);
    }

    public function view(User $actor, Kwitansi $kwitansi): bool
    {
        return $actor->can(PermissionName::KwitansisView);
    }

    public function create(User $actor): bool
    {
        return $actor->can(PermissionName::KwitansisCreate);
    }

    public function update(User $actor, Kwitansi $kwitansi): bool
    {
        return $actor->can(PermissionName::KwitansisUpdate);
    }

    public function delete(User $actor, Kwitansi $kwitansi): bool
    {
        return $actor->can(PermissionName::KwitansisDelete);
    }
}
