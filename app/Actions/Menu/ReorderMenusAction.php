<?php

namespace App\Actions\Menu;

use App\Enums\MenuLocation;
use App\Models\Menu;
use App\Services\Menu\MenuTreeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Simpan susunan menu hasil drag & drop (urutan + induk) dalam SATU transaksi.
 *
 * Aturan yang sama dengan form tetap dijaga di server:
 * - maksimal 2 tingkat (induk harus menu tingkat atas),
 * - submenu wajib punya URL,
 * - daftar harus lengkap (mencegah menimpa perubahan admin lain).
 */
class ReorderMenusAction
{
    public function __construct(private readonly MenuTreeService $menus) {}

    /**
     * @param  list<array{id: int|string, parent_id: int|string|null}>  $items
     */
    public function execute(MenuLocation $location, array $items): void
    {
        $existing = Menu::query()
            ->where('location', $location)
            ->get(['id', 'parent_id', 'title', 'url', 'sort_order'])
            ->keyBy('id');

        $items = array_map(fn (array $i) => [
            'id' => (int) $i['id'],
            'parent_id' => filled($i['parent_id']) ? (int) $i['parent_id'] : null,
        ], $items);

        $ids = array_column($items, 'id');

        if (count($ids) !== $existing->count() || array_diff($ids, $existing->keys()->all()) !== []) {
            $this->fail('The menu list has changed since you opened this page. Reload and try again.');
        }

        $topLevel = array_flip(array_column(
            array_filter($items, fn (array $i) => $i['parent_id'] === null),
            'id',
        ));

        $order = [];
        $updates = [];

        foreach ($items as $item) {
            /** @var Menu $menu */
            $menu = $existing[$item['id']];

            if ($item['parent_id'] !== null) {
                if (! isset($topLevel[$item['parent_id']])) {
                    $this->fail('Submenus can only be placed under a top-level menu (max 2 levels).');
                }

                if ($menu->url === null) {
                    $this->fail("\"{$menu->title}\" needs a URL before it can become a submenu.");
                }
            }

            $group = $item['parent_id'] ?? 0;
            $order[$group] = ($order[$group] ?? 0) + 1;

            if ($menu->parent_id !== $item['parent_id'] || $menu->sort_order !== $order[$group]) {
                $updates[$item['id']] = ['parent_id' => $item['parent_id'], 'sort_order' => $order[$group]];
            }
        }

        DB::transaction(function () use ($updates) {
            // Update lewat query builder: tanpa event per baris, cache dibuang sekali.
            foreach ($updates as $id => $values) {
                Menu::query()->whereKey($id)->update($values);
            }
        });

        $this->menus->flush();
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['items' => $message]);
    }
}
