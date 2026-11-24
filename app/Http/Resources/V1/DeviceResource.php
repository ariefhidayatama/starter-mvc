<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Satu device login (= satu token Sanctum). Nilai token TIDAK pernah
 * dikirim ulang; di database pun hanya tersimpan hash-nya.
 *
 * @mixin PersonalAccessToken
 */
class DeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $current = $request->user()?->currentAccessToken();

        return [
            'id' => $this->id,
            'device_name' => $this->name,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'is_current' => $current instanceof PersonalAccessToken && $current->getKey() === $this->id,
        ];
    }
}
