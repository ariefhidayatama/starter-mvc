<?php

namespace App\Http\Resources\V1;

use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Satu baris menu (halaman kelola & form edit).
 *
 * @mixin Menu
 */
class MenuResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Pengenal acak untuk URL admin (/admin/menus/{ulid}/edit).
            'ulid' => $this->ulid,
            'location' => $this->location->value,
            'parent_id' => $this->parent_id,
            'title' => $this->title,
            'url' => $this->url,
            'icon' => $this->icon,
            'permission' => $this->permission,
            'open_in_new_tab' => $this->open_in_new_tab,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'is_system' => $this->is_system,
            // Role yang boleh melihat (kosong = semua user login).
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->sort()->values()->all(), []),
        ];
    }
}
