<?php

namespace App\Actions\Menu;

use App\Enums\MenuLocation;
use App\Models\Menu;
use Illuminate\Support\Facades\DB;

class CreateMenuAction
{
    public function __construct(private readonly SyncMenuAudienceAction $syncAudience) {}

    /**
     * @param  array{location: string, parent_id?: int|string|null, title: string, url?: string|null, icon?: string|null, audience?: string, roles?: list<string>, permission?: string|null, open_in_new_tab?: bool|string|null, sort_order?: int|string|null, is_active?: bool|string|null}  $data
     */
    public function execute(array $data): Menu
    {
        $location = MenuLocation::from($data['location']);
        $parentId = filled($data['parent_id'] ?? null) ? (int) $data['parent_id'] : null;

        return DB::transaction(function () use ($data, $location, $parentId) {
            $menu = Menu::create([
                'location' => $location,
                'parent_id' => $parentId,
                'title' => $data['title'],
                'url' => $data['url'] ?? null,
                'icon' => $data['icon'] ?? null,
                'open_in_new_tab' => (bool) ($data['open_in_new_tab'] ?? false),
                // Urutan dikosongkan → taruh paling bawah di antara saudaranya.
                'sort_order' => filled($data['sort_order'] ?? null)
                    ? (int) $data['sort_order']
                    : $this->nextSortOrder($location, $parentId),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            if (isset($data['audience'])) {
                $this->syncAudience->execute($menu, $data['audience'], $data['roles'] ?? [], $data['permission'] ?? null);
            }

            return $menu;
        });
    }

    private function nextSortOrder(MenuLocation $location, ?int $parentId): int
    {
        $max = Menu::query()
            ->where('location', $location)
            ->where('parent_id', $parentId)   // where(..., null) → "IS NULL"
            ->max('sort_order');

        return is_numeric($max) ? (int) $max + 1 : 1;
    }
}
