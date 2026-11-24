<?php

namespace App\Observers;

use App\Models\User;
use App\Services\Auth\TokenService;

/**
 * Reaksi otomatis terhadap perubahan User, dari jalur MANA PUN
 * (reset password, ganti password di pengaturan, diubah admin, dll).
 */
class UserObserver
{
    public function __construct(private readonly TokenService $tokens) {}

    public function updated(User $user): void
    {
        // Password berubah → semua token mobile dicabut. Jika password bocor
        // lalu diganti, perangkat milik penyerang otomatis ikut logout.
        // Session web user saat ini TIDAK diputus.
        if ($user->wasChanged('password')) {
            $this->tokens->revokeAll($user);
        }

        // Akun dinonaktifkan → putus semua token & session web.
        if ($user->wasChanged('is_active') && ! $user->is_active) {
            $this->tokens->logoutEverywhere($user);
        }
    }

    public function deleting(User $user): void
    {
        // personal_access_tokens memakai relasi polymorphic (tanpa foreign
        // key), jadi tidak terhapus otomatis oleh database.
        $this->tokens->logoutEverywhere($user);
    }
}
