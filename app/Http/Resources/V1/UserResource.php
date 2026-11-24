<?php

namespace App\Http\Resources\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bentuk JSON data user untuk API v1.
 *
 * Resource menentukan field apa yang BOLEH keluar. Kolom sensitif
 * (password, two_factor_secret, remember_token) tidak pernah disebut,
 * sehingga mustahil ikut terkirim.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    private bool $withPermissions = false;

    /**
     * Sertakan daftar permission (dipakai /auth/me & login), agar
     * aplikasi mobile bisa menyembunyikan menu yang tidak diizinkan.
     */
    public function withPermissions(): static
    {
        $this->withPermissions = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'is_active' => $this->is_active,
            'two_factor_enabled' => $this->hasEnabledTwoFactorAuthentication(),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'permissions' => $this->when(
                $this->withPermissions,
                fn () => $this->resource->getAllPermissions()->pluck('name')->values(),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
