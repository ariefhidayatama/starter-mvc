<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Mengelola "device" login milik user:
 * - Token Sanctum (aplikasi mobile / klien API), satu token per device.
 * - Session web (hanya bila SESSION_DRIVER=database).
 */
class TokenService
{
    /**
     * Membuat token baru untuk satu device.
     *
     * Token lama dengan nama device yang sama dihapus dulu, supaya login
     * berulang dari HP yang sama tidak menumpuk token yang tidak terpakai.
     */
    public function issue(User $user, string $deviceName): NewAccessToken
    {
        $user->tokens()->where('name', $deviceName)->delete();

        $minutes = config('sanctum.expiration');

        return $user->createToken(
            name: $deviceName,
            expiresAt: is_numeric($minutes) ? now()->addMinutes((int) $minutes) : null,
        );
    }

    /**
     * Daftar device (token) yang masih aktif, terbaru dipakai di atas.
     *
     * @return Collection<int, PersonalAccessToken>
     */
    public function devices(User $user): Collection
    {
        return $user->tokens()
            ->select(['id', 'tokenable_type', 'tokenable_id', 'name', 'last_used_at', 'expires_at', 'created_at'])
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Mencabut satu token milik user. Mengembalikan false jika token
     * tidak ditemukan ATAU milik user lain (tidak dibedakan, agar penyerang
     * tidak bisa menebak ID token milik orang lain).
     */
    public function revoke(User $user, int $tokenId): bool
    {
        return $user->tokens()->whereKey($tokenId)->delete() > 0;
    }

    public function revokeAll(User $user): int
    {
        return $user->tokens()->delete();
    }

    /**
     * Logout paksa dari semua device: token API + session web.
     */
    public function logoutEverywhere(User $user): void
    {
        $this->revokeAll($user);

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->delete();
        }
    }
}
