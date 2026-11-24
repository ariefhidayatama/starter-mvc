<?php

namespace App\Services\Access;

use App\Enums\MenuLocation;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Menu;
use App\Models\Role;
use App\Services\Menu\MenuTreeService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

/**
 * Data untuk halaman Access Control (matriks permission × role) dan untuk
 * menjelaskan "siapa yang melihat menu ini".
 */
class AccessService
{
    /**
     * Modul yang boleh DIBACA lewat API oleh semua user ber-api.access
     * (Policy viewAny/view tidak butuh <modul>.view): katalog.
     */
    private const OPEN_READS = ['categories', 'products'];

    /**
     * Permission → nama role yang memilikinya. Satu query.
     * Admin selalu dicantumkan karena lolos semua izin (Gate::before).
     *
     * @return array<string, list<string>>
     */
    public function rolesByPermission(): array
    {
        $map = DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->join('roles', 'roles.id', '=', 'role_has_permissions.role_id')
            ->orderBy('roles.name')
            ->get(['permissions.name as permission', 'roles.name as role'])
            ->groupBy('permission')
            ->map(fn ($rows) => $rows->pluck('role')->all())
            ->all();

        $result = [];

        foreach (PermissionName::values() as $permission) {
            $roles = $map[$permission] ?? [];
            $result[$permission] = array_values(array_unique([RoleName::Admin->value, ...$roles]));
        }

        return $result;
    }

    /**
     * Role untuk kolom matriks: admin dulu, lalu role sistem, lalu sisanya.
     *
     * Role spatie mendukung UUID, jadi `id` bertipe int|string.
     *
     * @return list<array{id: int|string, ulid: string, name: string, is_admin: bool, is_system: bool, users_count: int, permissions: list<string>}>
     */
    public function roles(): array
    {
        return array_values($this->orderedRoles(withUsersCount: true)
            ->map(fn (Role $r) => [
                'id' => $r->id,
                'ulid' => $r->ulid,
                'name' => $r->name,
                'is_admin' => $r->name === RoleName::Admin->value,
                'is_system' => RoleName::isSystem($r->name),
                'users_count' => (int) $r->getAttribute('users_count'),
                'permissions' => array_values($r->permissions->map(fn (Permission $p): string => $p->name)->all()),
            ])
            ->all());
    }

    /**
     * Semua role (beserta permission-nya): admin, role sistem, lalu sisanya A-Z.
     *
     * @return Collection<int, Role>
     */
    public function orderedRoles(bool $withUsersCount = false): Collection
    {
        return Role::query()
            ->with('permissions:id,name')
            ->when($withUsersCount, fn ($q) => $q->withCount('users'))
            ->get()
            ->sortBy(fn (Role $r) => sprintf(
                '%d-%s',
                $r->name === RoleName::Admin->value ? 0 : (RoleName::isSystem($r->name) ? 1 : 2),
                $r->name,
            ))
            ->values();
    }

    /**
     * Baris matriks "Menu visibility": menu sidebar BUATAN SENDIRI yang
     * diatur per role (bukan menu bawaan, bukan yang mengikuti permission,
     * karena keduanya diatur lewat matriks permission).
     *
     * @return list<array{id: int, ulid: string, title: string, parent_title: string|null, depth: int, url: string|null, is_active: bool, everyone: bool, roles: list<string>}>
     */
    public function menuVisibility(): array
    {
        $rows = [];
        $parentTitle = null;

        foreach (app(MenuTreeService::class)->flatList(MenuLocation::Admin) as $row) {
            $menu = $row['menu'];

            if ($row['depth'] === 0) {
                $parentTitle = $menu->title;
            }

            if ($menu->is_system || $menu->permission !== null) {
                continue;
            }

            $roles = array_values($menu->roles->map(fn (Role $r): string => $r->name)->sort()->all());

            $rows[] = [
                'id' => $menu->id,
                'ulid' => $menu->ulid,
                'title' => $menu->title,
                'parent_title' => $row['depth'] === 1 ? $parentTitle : null,
                'depth' => $row['depth'],
                'url' => $menu->url,
                'is_active' => $menu->is_active,
                'everyone' => $roles === [],
                'roles' => $roles,
            ];
        }

        return $rows;
    }

    /**
     * Grup permission per modul + menu bawaan yang mengikuti permission itu
     * (mis. users.view → menu "Users") + endpoint REST API yang dibukanya,
     * supaya efek mencentang terlihat jelas.
     *
     * @return list<array{module: string, label: string, permissions: list<array{name: string, action: string, description: string, menus: list<string>, endpoints: list<string>}>}>
     */
    public function permissionGroups(): array
    {
        $endpoints = $this->apiEndpoints();

        /** @var array<string, list<string>> $menusByPermission */
        $menusByPermission = Menu::query()
            ->whereNotNull('permission')
            ->orderBy('sort_order')
            ->get(['title', 'permission'])
            ->groupBy('permission')
            ->map(fn ($menus) => $menus->pluck('title')->all())
            ->all();

        return array_map(function (array $group) use ($menusByPermission, $endpoints) {
            $group['permissions'] = array_map(fn (array $p) => [
                ...$p,
                'menus' => $menusByPermission[$p['name']] ?? [],
                'endpoints' => $endpoints[$p['name']] ?? [],
            ], $group['permissions']);

            return $group;
        }, PermissionName::grouped());
    }

    /**
     * Endpoint REST API yang dibuka setiap permission, dibaca dari daftar
     * route (bukan ditulis manual), mis. messages.view → GET /api/v1/messages.
     *
     * Konvensi controller resource: index/show → .view, store → .create,
     * update → .update, destroy → .delete. Endpoint yang tidak butuh izin
     * modul (mis. membaca katalog, profil sendiri) dicantumkan di api.access,
     * karena cukup itu yang dibutuhkan.
     *
     * @return array<string, list<string>>
     */
    public function apiEndpoints(): array
    {
        $permissions = PermissionName::values();
        $map = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_starts_with($uri, 'api/v1/') || ! in_array('api.access', $route->gatherMiddleware(), true)) {
                continue;
            }

            $module = str_replace('-', '_', explode('/', substr($uri, strlen('api/v1/')))[0]);
            $action = match ($route->getActionMethod()) {
                'index', 'show' => 'view',
                'store' => 'create',
                'update' => 'update',
                'destroy' => 'delete',
                default => null,
            };
            $permission = $action === null || ($action === 'view' && in_array($module, self::OPEN_READS, true))
                ? null
                : "{$module}.{$action}";
            $key = $permission !== null && in_array($permission, $permissions, true) ? $permission : PermissionName::ApiAccess->value;
            $verb = collect($route->methods())->first(fn (string $m) => $m !== 'HEAD') ?? 'GET';

            $map[$key][] = "{$verb} /{$uri}";
        }

        return array_map(fn (array $list) => array_values(array_unique($list)), $map);
    }
}
