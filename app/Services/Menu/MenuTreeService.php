<?php

namespace App\Services\Menu;

use App\Enums\MenuLocation;
use App\Enums\RoleName;
use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Membaca menu untuk DITAMPILKAN (sidebar & navbar), untuk halaman kelola,
 * dan untuk PRATINJAU "lihat sebagai role".
 *
 * Alur tampil:
 *   1. tree()     → pohon menu AKTIF, di-cache (menu jarang berubah,
 *                   tetapi dibaca di SETIAP halaman).
 *   2. forUser()  → saring dari cache sesuai user. Bagian ini tidak
 *                   di-cache karena berbeda untuk tiap user.
 *
 * Aturan "boleh lihat" ada di SATU tempat (hiddenReason + evaluate), dipakai
 * oleh sidebar sungguhan maupun pratinjau, sehingga keduanya selalu sama.
 *
 * Cache dibuang otomatis oleh Model Menu (saved/deleted), oleh Action yang
 * mengubah role menu / urutan, dan saat Role diubah/dihapus.
 *
 * @phpstan-type TreeNode array{id: int, title: string, url: string|null, icon: string|null, permission: string|null, roles: list<string>, open_in_new_tab: bool, is_active: bool, children: list<array<string, mixed>>}
 * @phpstan-type PublicNode array{id: int, title: string, url: string|null, icon: string|null, open_in_new_tab: bool, children: list<array<string, mixed>>}
 * @phpstan-type PreviewNode array{id: int, title: string, url: string|null, icon: string|null, visible: bool, reason: string|null, children: list<array<string, mixed>>}
 */
class MenuTreeService
{
    /** Naikkan versi bila bentuk node berubah, agar cache lama tidak terbaca. */
    private const CACHE_PREFIX = 'menus.tree.v2.';

    /**
     * Pohon menu aktif (2 tingkat) untuk satu lokasi.
     *
     * @return list<TreeNode>
     */
    public function tree(MenuLocation $location): array
    {
        /** @var list<TreeNode> */
        return Cache::rememberForever(
            self::CACHE_PREFIX.$location->value,
            fn () => $this->buildTree($location, activeOnly: true),
        );
    }

    /**
     * Menu yang boleh dilihat user (tamu = null).
     *
     * PENTING: ini hanya soal TAMPILAN. Keamanan halaman tetap dijaga
     * middleware `can:` & Policy di server.
     *
     * @return list<PublicNode>
     */
    public function forUser(MenuLocation $location, ?User $user): array
    {
        $roleNames = $user === null
            ? []
            : array_values(array_filter($user->getRoleNames()->all(), is_string(...)));

        $evaluated = $this->evaluate(
            $this->tree($location),
            can: fn (string $permission): bool => $user !== null && $user->can($permission),
            roleNames: $roleNames,
            isAdmin: in_array(RoleName::Admin->value, $roleNames, true),
        );

        $visible = [];

        foreach ($evaluated as $node) {
            if (! $node['visible']) {
                continue;
            }

            $node['children'] = array_values(array_filter($node['children'], fn (array $c) => $c['visible']));
            $visible[] = $this->publicNode($node);
        }

        return $visible;
    }

    /**
     * Sidebar dashboard "dilihat sebagai" role tertentu, LENGKAP dengan menu
     * yang tersembunyi beserta alasannya (untuk halaman kelola menu).
     *
     * @return list<PreviewNode>
     */
    public function previewForRole(Role $role): array
    {
        $isAdmin = $role->name === RoleName::Admin->value;
        $permissions = $role->permissions->pluck('name')->all();

        return array_map(
            fn (array $node) => $this->previewNode($node),
            $this->evaluate(
                $this->buildTree(MenuLocation::Admin, activeOnly: false),
                can: fn (string $permission): bool => $isAdmin || in_array($permission, $permissions, true),
                roleNames: [$role->name],
                isAdmin: $isAdmin,
            ),
        );
    }

    /**
     * SEMUA menu (termasuk nonaktif) untuk halaman kelola: induk diikuti
     * submenunya, masing-masing urut `sort_order`.
     *
     * @return list<array{menu: Menu, depth: int, children_count: int}>
     */
    public function flatList(MenuLocation $location): array
    {
        $byParent = $this->query($location)->with('roles:id,name')->get()
            ->groupBy(fn (Menu $m) => $m->parent_id ?? 0);
        $rows = [];

        foreach ($byParent->get(0, collect()) as $parent) {
            /** @var Collection<int, Menu> $children */
            $children = $byParent->get($parent->id, collect());
            $rows[] = ['menu' => $parent, 'depth' => 0, 'children_count' => $children->count()];

            foreach ($children as $child) {
                $rows[] = ['menu' => $child, 'depth' => 1, 'children_count' => 0];
            }
        }

        return $rows;
    }

    /**
     * Calon induk untuk dropdown "Parent": menu tingkat atas di lokasi yang sama.
     *
     * @return list<array{value: string, label: string}>
     */
    public function parentOptions(MenuLocation $location, ?Menu $except = null): array
    {
        return array_values(Menu::query()
            ->where('location', $location)
            ->whereNull('parent_id')
            ->when($except, fn ($q, Menu $m) => $q->whereKeyNot($m->id))
            ->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'title'])
            ->map(fn (Menu $m) => ['value' => (string) $m->id, 'label' => $m->title])
            ->all());
    }

    /** Buang cache pohon menu di semua lokasi. */
    public function flush(): void
    {
        foreach (MenuLocation::cases() as $location) {
            Cache::forget(self::CACHE_PREFIX.$location->value);
        }
    }

    /**
     * Tandai setiap node `visible` + `reason` (alasan tersembunyi).
     *
     * @param  list<TreeNode>  $tree
     * @param  Closure(string): bool  $can
     * @param  list<string>  $roleNames
     * @return list<array<string, mixed>>
     */
    private function evaluate(array $tree, Closure $can, array $roleNames, bool $isAdmin): array
    {
        $result = [];

        foreach ($tree as $node) {
            $reason = $node['is_active']
                ? $this->hiddenReason($node, $can, $roleNames, $isAdmin)
                : 'Inactive';

            $children = [];

            /** @var TreeNode $child */
            foreach ($node['children'] as $child) {
                $childReason = match (true) {
                    ! $child['is_active'] => 'Inactive',
                    $reason !== null => 'Parent menu is hidden',
                    default => $this->hiddenReason($child, $can, $roleNames, $isAdmin),
                };
                $children[] = [...$child, 'visible' => $childReason === null, 'reason' => $childReason];
            }

            // Tanpa URL dan tanpa submenu yang terlihat = tidak ada yang bisa dibuka.
            $anyChildVisible = array_filter($children, fn (array $c) => $c['visible']) !== [];

            if ($reason === null && $node['url'] === null && ! $anyChildVisible) {
                $reason = $children === [] ? 'No URL and no submenus' : 'No URL and no visible submenus';
            }

            $result[] = [...$node, 'children' => $children, 'visible' => $reason === null, 'reason' => $reason];
        }

        return $result;
    }

    /**
     * @param  TreeNode  $node
     * @param  Closure(string): bool  $can
     * @param  list<string>  $roleNames
     */
    private function hiddenReason(array $node, Closure $can, array $roleNames, bool $isAdmin): ?string
    {
        if ($node['permission'] !== null && ! $can($node['permission'])) {
            return "Needs permission {$node['permission']}";
        }

        // Admin selalu lolos (sejalan dengan Gate::before).
        if ($node['roles'] !== [] && ! $isAdmin && array_intersect($node['roles'], $roleNames) === []) {
            return 'Only for: '.implode(', ', $node['roles']);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return PublicNode
     */
    private function publicNode(array $node): array
    {
        /** @var PublicNode */
        return [
            'id' => $node['id'],
            'title' => $node['title'],
            'url' => $node['url'],
            'icon' => $node['icon'],
            'open_in_new_tab' => $node['open_in_new_tab'],
            'children' => array_map(fn (array $c) => $this->publicNode($c), $node['children']),
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return PreviewNode
     */
    private function previewNode(array $node): array
    {
        /** @var PreviewNode */
        return [
            'id' => $node['id'],
            'title' => $node['title'],
            'url' => $node['url'],
            'icon' => $node['icon'],
            'visible' => $node['visible'],
            'reason' => $node['reason'],
            'children' => array_map(fn (array $c) => $this->previewNode($c), $node['children']),
        ];
    }

    /**
     * @return list<TreeNode>
     */
    private function buildTree(MenuLocation $location, bool $activeOnly): array
    {
        // SATU query menu (+1 untuk role), lalu disusun di PHP (tanpa N+1).
        $byParent = $this->query($location)
            ->with('roles:id,name')
            ->when($activeOnly, fn (Builder $q) => $q->where('is_active', true))
            ->get()
            ->groupBy(fn (Menu $m) => $m->parent_id ?? 0);

        $tree = [];

        // Submenu dari induk yang NONAKTIF tidak ikut di pohon aktif, karena
        // hanya induk aktif yang ditelusuri.
        foreach ($byParent->get(0, collect()) as $parent) {
            $node = $this->node($parent);
            $node['children'] = array_values($byParent->get($parent->id, collect())
                ->map(fn (Menu $child) => $this->node($child))
                ->all());
            $tree[] = $node;
        }

        return $tree;
    }

    /**
     * @return Builder<Menu>
     */
    private function query(MenuLocation $location): Builder
    {
        return Menu::query()
            ->where('location', $location)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @return TreeNode
     */
    private function node(Menu $menu): array
    {
        return [
            'id' => $menu->id,
            'title' => $menu->title,
            'url' => $menu->url,
            'icon' => $menu->icon,
            'permission' => $menu->permission,
            // Website publik tidak dibatasi role.
            'roles' => $menu->location === MenuLocation::Admin
                ? array_values($menu->roles->pluck('name')->sort()->all())
                : [],
            'open_in_new_tab' => $menu->open_in_new_tab,
            'is_active' => $menu->is_active,
            'children' => [],
        ];
    }
}
