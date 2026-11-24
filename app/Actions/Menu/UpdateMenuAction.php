<?php

namespace App\Actions\Menu;

use App\Models\Menu;
use Illuminate\Support\Facades\DB;

class UpdateMenuAction
{
    public function __construct(private readonly SyncMenuAudienceAction $syncAudience) {}

    /**
     * Menu bawaan (is_system) menunjuk ke fitur aplikasi, jadi URL,
     * permission, dan status aktifnya DIKUNCI. Judul, ikon, induk, dan
     * urutan tetap boleh diubah.
     *
     * @param  array{parent_id?: int|string|null, title?: string, url?: string|null, icon?: string|null, audience?: string, roles?: list<string>, permission?: string|null, open_in_new_tab?: bool|string|null, sort_order?: int|string|null, is_active?: bool|string|null}  $data
     */
    public function execute(Menu $menu, array $data): Menu
    {
        $audience = $data['audience'] ?? null;
        $roles = $data['roles'] ?? [];
        $permission = $data['permission'] ?? null;
        // Diatur lewat SyncMenuAudienceAction, bukan fill().
        unset($data['audience'], $data['roles'], $data['permission']);

        if ($menu->is_system) {
            unset($data['url'], $data['is_active']);
        }

        if (array_key_exists('parent_id', $data)) {
            $data['parent_id'] = filled($data['parent_id']) ? (int) $data['parent_id'] : null;
        }

        if (array_key_exists('sort_order', $data) && ! filled($data['sort_order'])) {
            unset($data['sort_order']);
        }

        return DB::transaction(function () use ($menu, $data, $audience, $roles, $permission) {
            $menu->fill($data)->save();

            if ($audience !== null && ! $menu->is_system) {
                $this->syncAudience->execute($menu, $audience, $roles, $permission);
            }

            return $menu;
        });
    }
}
