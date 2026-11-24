<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Product;
use App\Models\User;

/**
 * Produk AKTIF bisa dilihat siapa saja (katalog publik / aplikasi mobile).
 * Produk NONAKTIF hanya untuk user yang punya products.view (admin/staff).
 */
class ProductPolicy
{
    public function viewAny(?User $actor): bool
    {
        return true;
    }

    public function view(?User $actor, Product $product): bool
    {
        return $product->is_active || $this->canSeeInactive($actor);
    }

    public function create(User $actor): bool
    {
        return $actor->can(PermissionName::ProductsCreate);
    }

    public function update(User $actor, Product $product): bool
    {
        return $actor->can(PermissionName::ProductsUpdate);
    }

    public function delete(User $actor, Product $product): bool
    {
        return $actor->can(PermissionName::ProductsDelete);
    }

    /**
     * Dipakai juga oleh ProductQueryService untuk memutuskan apakah produk
     * nonaktif ikut ditampilkan di daftar.
     */
    public function canSeeInactive(?User $actor): bool
    {
        return $actor !== null && $actor->can(PermissionName::ProductsView);
    }
}
