<?php

namespace App\Enums;

/**
 * Daftar SEMUA permission aplikasi (satu-satunya sumber kebenaran).
 *
 * Format nama: "<modul>.<aksi>". Permission hanya ditambahkan di sini
 * bila sudah ada kode yang memeriksanya (Policy / Gate). Setelah mengubah
 * enum ini jalankan: php artisan db:seed --class=RolePermissionSeeder
 *
 * Permission modul Pesanan & Laporan ditambahkan di tahap saat fitur
 * tersebut dibuat.
 */
enum PermissionName: string
{
    case CategoriesView = 'categories.view';
    case CategoriesCreate = 'categories.create';
    case CategoriesUpdate = 'categories.update';
    case CategoriesDelete = 'categories.delete';

    case ProductsView = 'products.view';
    case ProductsCreate = 'products.create';
    case ProductsUpdate = 'products.update';
    case ProductsDelete = 'products.delete';

    case UsersView = 'users.view';
    case UsersCreate = 'users.create';
    case UsersUpdate = 'users.update';
    case UsersDelete = 'users.delete';

    case RolesView = 'roles.view';
    case RolesCreate = 'roles.create';
    case RolesUpdate = 'roles.update';
    case RolesDelete = 'roles.delete';

    case MenusView = 'menus.view';
    case MenusCreate = 'menus.create';
    case MenusUpdate = 'menus.update';
    case MenusDelete = 'menus.delete';

    /** Contoh halaman yang dikunci permission (route admin.testing). */
    case TestingView = 'testing.view';

    /**
     * Boleh memakai REST API (/api/v1) sama sekali: login token & memanggil
     * endpoint. Izin per modul tetap memakai permission yang SAMA dengan web
     * (mis. messages.view), jadi ini hanya "pintu masuk" per role.
     */
    case ApiAccess = 'api.access';

    // Modul dari CRUD generator disisipkan DI ATAS penanda di bawah ini
    // (blok "// crud:<tabel>" ... "// end crud:<tabel>"). Jangan hapus penandanya.
    // crud:messages
    case MessagesView = 'messages.view';
    case MessagesCreate = 'messages.create';
    case MessagesUpdate = 'messages.update';
    case MessagesDelete = 'messages.delete';
    // end crud:messages
    // crud:kwitansis
    case KwitansisView = 'kwitansis.view';
    case KwitansisCreate = 'kwitansis.create';
    case KwitansisUpdate = 'kwitansis.update';
    case KwitansisDelete = 'kwitansis.delete';
    // end crud:kwitansis
    // @crud:cases

    /** Nama modul, mis. "users" (bagian sebelum titik). */
    public function module(): string
    {
        return explode('.', $this->value)[0];
    }

    /** Nama aksi, mis. "view" (bagian setelah titik). */
    public function action(): string
    {
        return explode('.', $this->value)[1];
    }

    public function description(): string
    {
        return match ($this) {
            self::CategoriesView => 'View categories in the admin panel',
            self::CategoriesCreate => 'Create new categories',
            self::CategoriesUpdate => 'Edit categories',
            self::CategoriesDelete => 'Delete categories (only when empty)',
            self::ProductsView => 'View all products, including inactive ones',
            self::ProductsCreate => 'Create new products',
            self::ProductsUpdate => 'Edit products, prices & stock',
            self::ProductsDelete => 'Delete products',
            self::UsersView => 'View the user list & details',
            self::UsersCreate => 'Create new users',
            self::UsersUpdate => 'Edit user details, roles & status',
            self::UsersDelete => 'Delete users',
            self::RolesView => 'View roles & permissions',
            self::RolesCreate => 'Create new roles',
            self::RolesUpdate => 'Edit role names & permissions',
            self::RolesDelete => 'Delete roles',
            self::MenusView => 'View dashboard & website menus',
            self::MenusCreate => 'Create menus & submenus',
            self::MenusUpdate => 'Edit menus, order & visibility',
            self::MenusDelete => 'Delete menus (not built-in ones)',
            self::TestingView => 'Open the Testing page',
            self::ApiAccess => 'Use the REST API (token login & API calls). Each endpoint still needs its module permission',
            // crud:messages
            self::MessagesView => 'View messages',
            self::MessagesCreate => 'Create messages',
            self::MessagesUpdate => 'Edit messages',
            self::MessagesDelete => 'Delete messages',
            // end crud:messages
            // crud:kwitansis
            self::KwitansisView => 'View kwitansis',
            self::KwitansisCreate => 'Create kwitansis',
            self::KwitansisUpdate => 'Edit kwitansis',
            self::KwitansisDelete => 'Delete kwitansis',
            // end crud:kwitansis
            // @crud:descriptions
        };
    }

    public static function moduleLabel(string $module): string
    {
        return match ($module) {
            'categories' => 'Categories',
            'products' => 'Products',
            'users' => 'Users',
            'roles' => 'Roles',
            'menus' => 'Menus',
            'testing' => 'Testing',
            'api' => 'REST API',
            // crud:messages
            'messages' => 'Messages',
            // end crud:messages
            // crud:kwitansis
            'kwitansis' => 'Kwitansis',
            // end crud:kwitansis
            // @crud:labels
            default => ucfirst($module),
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }

    /**
     * Permission dikelompokkan per modul, untuk matriks checkbox di UI.
     *
     * @return list<array{module: string, label: string, permissions: list<array{name: string, action: string, description: string}>}>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::cases() as $permission) {
            $module = $permission->module();
            $groups[$module] ??= ['module' => $module, 'label' => self::moduleLabel($module), 'permissions' => []];
            $groups[$module]['permissions'][] = [
                'name' => $permission->value,
                'action' => $permission->action(),
                'description' => $permission->description(),
            ];
        }

        return array_values($groups);
    }
}
