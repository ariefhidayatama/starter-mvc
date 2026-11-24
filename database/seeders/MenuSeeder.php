<?php

namespace Database\Seeders;

use App\Enums\MenuLocation;
use App\Enums\PermissionName;
use App\Models\Menu;
use Illuminate\Database\Seeder;

/**
 * Menu awal sidebar dashboard & navbar website.
 *
 *   php artisan db:seed --class=MenuSeeder
 *
 * Aman dijalankan berulang: setiap lokasi hanya diisi bila masih KOSONG,
 * sehingga menu yang sudah diubah admin dari UI tidak tertimpa.
 *
 * Menu sidebar ditandai is_system (menunjuk fitur bawaan aplikasi):
 * tidak bisa dihapus, URL & permission-nya dikunci.
 */
class MenuSeeder extends Seeder
{
    /**
     * @var list<array{title: string, url?: string, icon?: string, permission?: PermissionName, new_tab?: bool, children?: list<array{title: string, url: string, icon?: string, permission?: PermissionName, new_tab?: bool}>}>
     */
    private const ADMIN = [
        ['title' => 'Dashboard', 'url' => '/dashboard', 'icon' => 'LayoutGrid'],
        ['title' => 'Catalog', 'icon' => 'Store', 'children' => [
            ['title' => 'Products', 'url' => '/admin/products', 'icon' => 'Package', 'permission' => PermissionName::ProductsView],
            ['title' => 'Categories', 'url' => '/admin/categories', 'icon' => 'FolderOpen', 'permission' => PermissionName::CategoriesView],
        ]],
        ['title' => 'Administration', 'icon' => 'Settings', 'children' => [
            ['title' => 'Users', 'url' => '/admin/users', 'icon' => 'Users', 'permission' => PermissionName::UsersView],
            ['title' => 'Roles', 'url' => '/admin/roles', 'icon' => 'ShieldCheck', 'permission' => PermissionName::RolesView],
            ['title' => 'Permissions', 'url' => '/admin/permissions', 'icon' => 'KeyRound', 'permission' => PermissionName::RolesView],
            ['title' => 'Menus', 'url' => '/admin/menus', 'icon' => 'ListTree', 'permission' => PermissionName::MenusView],
            // Hanya berfungsi di development (seperti CRUD generator).
            ['title' => 'API Generator', 'url' => '/admin/api-generator', 'icon' => 'Plug', 'permission' => PermissionName::MenusCreate],
        ]],
    ];

    /**
     * @var list<array{title: string, url?: string, icon?: string, permission?: PermissionName, new_tab?: bool, children?: list<array{title: string, url: string, icon?: string, permission?: PermissionName, new_tab?: bool}>}>
     */
    private const WEBSITE = [
        ['title' => 'Home', 'url' => '/'],
        ['title' => 'Features', 'url' => '/#features'],
        ['title' => 'Resources', 'icon' => 'BookOpen', 'children' => [
            ['title' => 'Laravel Docs', 'url' => 'https://laravel.com/docs', 'new_tab' => true],
            ['title' => 'React Docs', 'url' => 'https://react.dev', 'new_tab' => true],
            ['title' => 'Inertia Docs', 'url' => 'https://inertiajs.com', 'new_tab' => true],
        ]],
    ];

    public function run(): void
    {
        $this->seed(MenuLocation::Admin, self::ADMIN, system: true);
        $this->seed(MenuLocation::Website, self::WEBSITE, system: false);
    }

    /**
     * @param  list<array{title: string, url?: string, icon?: string, permission?: PermissionName, new_tab?: bool, children?: list<array{title: string, url: string, icon?: string, permission?: PermissionName, new_tab?: bool}>}>  $items
     */
    private function seed(MenuLocation $location, array $items, bool $system): void
    {
        if (Menu::query()->where('location', $location)->exists()) {
            return;
        }

        foreach ($items as $i => $item) {
            $parent = $this->create($location, $item, $i + 1, null, $system);

            foreach ($item['children'] ?? [] as $j => $child) {
                $this->create($location, $child, $j + 1, $parent->id, $system);
            }
        }
    }

    /**
     * @param  array{title: string, url?: string, icon?: string, permission?: PermissionName, new_tab?: bool}  $item
     */
    private function create(MenuLocation $location, array $item, int $order, ?int $parentId, bool $system): Menu
    {
        $menu = new Menu([
            'location' => $location,
            'parent_id' => $parentId,
            'title' => $item['title'],
            'url' => $item['url'] ?? null,
            'icon' => $item['icon'] ?? null,
            'permission' => isset($item['permission']) ? $item['permission']->value : null,
            'open_in_new_tab' => $item['new_tab'] ?? false,
            'sort_order' => $order,
            'is_active' => true,
        ]);
        $menu->is_system = $system;   // bukan fillable → di-set langsung
        $menu->save();

        return $menu;
    }
}
