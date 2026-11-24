<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Category;
use App\Models\User;

/**
 * Kategori bersifat publik untuk DIBACA (katalog), tetapi hanya user
 * dengan permission yang boleh mengubahnya.
 *
 * Parameter ?User (nullable) = method ini juga dipanggil untuk tamu.
 */
class CategoryPolicy
{
    public function viewAny(?User $actor): bool
    {
        return true;
    }

    public function view(?User $actor, Category $category): bool
    {
        return true;
    }

    public function create(User $actor): bool
    {
        return $actor->can(PermissionName::CategoriesCreate);
    }

    public function update(User $actor, Category $category): bool
    {
        return $actor->can(PermissionName::CategoriesUpdate);
    }

    public function delete(User $actor, Category $category): bool
    {
        return $actor->can(PermissionName::CategoriesDelete);
    }
}
