<?php

namespace App\Actions\User;

use App\Enums\RoleName;
use App\Exceptions\BusinessException;
use App\Models\User;

/**
 * Aturan bersama: sistem harus selalu punya minimal satu admin aktif.
 * Tanpa ini, semua orang bisa terkunci dari menu administrasi.
 */
trait EnsuresAdminRemains
{
    protected function ensureNotLastActiveAdmin(User $target, string $message): void
    {
        if (! $target->hasRole(RoleName::Admin) || ! $target->is_active) {
            return;
        }

        $otherActiveAdmins = User::role(RoleName::Admin)
            ->where('is_active', true)
            ->whereKeyNot($target->getKey())
            ->count();

        if ($otherActiveAdmins === 0) {
            throw new BusinessException($message);
        }
    }
}
