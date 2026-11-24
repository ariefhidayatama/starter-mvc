<?php

use App\Enums\MenuLocation;
use App\Enums\RoleName;
use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use App\Support\MenuIcons;
use Database\Seeders\MenuSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create()->assignRole(RoleName::Admin);
});

/*
|--------------------------------------------------------------------------
| Akses halaman kelola
|--------------------------------------------------------------------------
*/

it('hanya user dengan menus.view yang bisa membuka halaman menu', function () {
    $staff = User::factory()->create()->assignRole(RoleName::Staff);
    $customer = User::factory()->create()->assignRole(RoleName::Customer);

    $this->actingAs($staff)->get('/admin/menus')->assertForbidden();
    $this->actingAs($customer)->get('/admin/menus')->assertForbidden();
    $this->actingAs($this->admin)->get('/admin/menus')->assertOk();
});

it('menampilkan menu induk diikuti submenunya, per lokasi', function () {
    $resources = Menu::factory()->create(['title' => 'Resources', 'url' => null, 'sort_order' => 2]);
    Menu::factory()->childOf($resources)->create(['title' => 'Docs', 'sort_order' => 1]);
    Menu::factory()->create(['title' => 'Home', 'url' => '/', 'sort_order' => 1]);
    Menu::factory()->admin()->create(['title' => 'Reports']);

    $this->actingAs($this->admin)
        ->get('/admin/menus?location=website')
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/menus/index')
            ->where('location', 'website')
            ->has('rows', 3)
            ->where('rows.0.title', 'Home')
            ->where('rows.1.title', 'Resources')
            ->where('rows.1.children_count', 1)
            ->where('rows.2.title', 'Docs')
            ->where('rows.2.depth', 1)
            ->where('counts.website', 3)
            ->where('counts.admin', 1));
});

/*
|--------------------------------------------------------------------------
| CRUD
|--------------------------------------------------------------------------
*/

it('CRUD menu & submenu lewat web', function () {
    $this->actingAs($this->admin)
        ->post('/admin/menus', ['location' => 'website', 'title' => 'Company', 'url' => ''])
        ->assertRedirect('/admin/menus?location=website');

    $company = Menu::firstWhere('title', 'Company');
    expect($company->url)->toBeNull()
        ->and($company->sort_order)->toBe(1);   // otomatis paling bawah

    $this->actingAs($this->admin)
        ->post('/admin/menus', [
            'location' => 'website', 'parent_id' => $company->id,
            'title' => 'Blog', 'url' => 'https://blog.example.com', 'open_in_new_tab' => '1',
            // permission diabaikan untuk website
            'permission' => 'users.view',
        ])
        ->assertRedirect();

    $blog = Menu::firstWhere('title', 'Blog');
    expect($blog->parent_id)->toBe($company->id)
        ->and($blog->open_in_new_tab)->toBeTrue()
        ->and($blog->permission)->toBeNull();

    $this->actingAs($this->admin)
        ->put("/admin/menus/{$blog->ulid}", ['title' => 'Our Blog', 'url' => '/blog', 'parent_id' => $company->id, 'sort_order' => 5, 'is_active' => '0'])
        ->assertRedirect('/admin/menus?location=website');

    expect($blog->fresh())
        ->title->toBe('Our Blog')
        ->url->toBe('/blog')
        ->sort_order->toBe(5)
        ->is_active->toBeFalse();

    $this->actingAs($this->admin)->delete("/admin/menus/{$blog->ulid}")->assertRedirect();
    expect(Menu::find($blog->id))->toBeNull();
});

it('menolak menghapus menu yang masih punya submenu', function () {
    $parent = Menu::factory()->create(['url' => null]);
    Menu::factory()->childOf($parent)->create();

    $this->actingAs($this->admin)
        ->from('/admin/menus')
        ->delete("/admin/menus/{$parent->ulid}")
        ->assertRedirect('/admin/menus');

    expect(Menu::find($parent->id))->not->toBeNull();
});

it('menu bawaan tidak bisa dihapus, dan URL/permission/status-nya terkunci', function () {
    $this->seed(MenuSeeder::class);
    $users = Menu::firstWhere('url', '/admin/users');

    $this->actingAs($this->admin)->from('/admin/menus')->delete("/admin/menus/{$users->ulid}");
    expect(Menu::find($users->id))->not->toBeNull();

    $this->actingAs($this->admin)
        ->put("/admin/menus/{$users->ulid}", [
            'title' => 'Members', 'parent_id' => $users->parent_id,
            'url' => '/hacked', 'permission' => '', 'is_active' => '0',
        ])
        ->assertRedirect();

    expect($users->fresh())
        ->title->toBe('Members')            // boleh
        ->url->toBe('/admin/users')         // dikunci
        ->permission->toBe('users.view')    // dikunci
        ->is_active->toBeTrue();            // dikunci
});

/*
|--------------------------------------------------------------------------
| Validasi
|--------------------------------------------------------------------------
*/

it('menolak URL berbahaya atau yang tidak sesuai lokasi', function (string $location, string $url) {
    $this->actingAs($this->admin)
        ->post('/admin/menus', ['location' => $location, 'title' => 'X', 'url' => $url])
        ->assertSessionHasErrors('url');
})->with([
    'javascript: di website' => ['website', 'javascript:alert(1)'],
    'protocol-relative' => ['website', '//evil.com'],
    'link eksternal di sidebar' => ['admin', 'https://example.com'],
    'anchor di sidebar' => ['admin', '#top'],
]);

it('membatasi menu maksimal 2 tingkat', function () {
    $parent = Menu::factory()->create(['url' => null]);
    $child = Menu::factory()->childOf($parent)->create();

    // Induk tidak boleh berupa submenu (tingkat 3).
    $this->actingAs($this->admin)
        ->post('/admin/menus', ['location' => 'website', 'parent_id' => $child->id, 'title' => 'Deep', 'url' => '/deep'])
        ->assertSessionHasErrors('parent_id');

    // Menu yang punya submenu tidak boleh dijadikan submenu.
    $other = Menu::factory()->create(['url' => null]);
    $this->actingAs($this->admin)
        ->put("/admin/menus/{$parent->ulid}", ['title' => $parent->title, 'parent_id' => $other->id])
        ->assertSessionHasErrors('parent_id');

    // Induk harus dari lokasi yang sama.
    $adminMenu = Menu::factory()->admin()->create(['url' => null]);
    $this->actingAs($this->admin)
        ->post('/admin/menus', ['location' => 'website', 'parent_id' => $adminMenu->id, 'title' => 'X', 'url' => '/x'])
        ->assertSessionHasErrors('parent_id');
});

it('submenu wajib punya URL & ikon harus dari daftar', function () {
    $parent = Menu::factory()->create(['url' => null]);

    $this->actingAs($this->admin)
        ->post('/admin/menus', ['location' => 'website', 'parent_id' => $parent->id, 'title' => 'X', 'url' => '', 'icon' => 'Skull'])
        ->assertSessionHasErrors(['url', 'icon']);
});

/*
|--------------------------------------------------------------------------
| Menu yang tampil (shared props) & API
|--------------------------------------------------------------------------
*/

it('sidebar hanya menampilkan menu sesuai permission user', function () {
    $this->seed(MenuSeeder::class);

    $role = Role::create(['name' => 'catalog-viewer', 'guard_name' => 'web']);
    $role->givePermissionTo('products.view');
    $user = User::factory()->create()->assignRole($role);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            // Dashboard + Catalog (hanya Products). Administration hilang
            // karena semua submenunya butuh permission yang tidak dimiliki.
            ->has('menus.admin', 2)
            ->where('menus.admin.0.title', 'Dashboard')
            ->where('menus.admin.1.title', 'Catalog')
            ->has('menus.admin.1.children', 1)
            ->where('menus.admin.1.children.0.title', 'Products'));
});

/*
|--------------------------------------------------------------------------
| Visibilitas per role
|--------------------------------------------------------------------------
*/

it('menu "Only selected roles" hanya tampil untuk role itu (dan admin)', function () {
    $this->actingAs($this->admin)
        ->post('/admin/menus', [
            'location' => 'admin', 'title' => 'Testing', 'url' => '/admin/testing',
            'audience' => 'roles', 'roles' => [],   // hanya admin (dicentang terkunci di UI)
        ])
        ->assertSessionHasErrors('roles');

    $this->actingAs($this->admin)
        ->post('/admin/menus', [
            'location' => 'admin', 'title' => 'Testing', 'url' => '/admin/testing',
            'audience' => 'roles', 'roles' => ['staff'],
        ])
        ->assertRedirect();

    $testing = Menu::firstWhere('title', 'Testing');
    // Admin selalu ikut disimpan.
    expect($testing->roles()->pluck('name')->sort()->values()->all())->toBe(['admin', 'staff']);

    $titles = fn (User $user) => collect($this->actingAs($user)->get('/dashboard')
        ->viewData('page')['props']['menus']['admin'])->pluck('title')->all();

    $staff = User::factory()->create()->assignRole(RoleName::Staff);
    $customer = User::factory()->create()->assignRole(RoleName::Customer);

    expect($titles($this->admin))->toContain('Testing')
        ->and($titles($staff))->toContain('Testing')
        ->and($titles($customer))->not->toContain('Testing');

    // Ubah ke "Everyone" → semua user login melihatnya.
    $this->actingAs($this->admin)
        ->put("/admin/menus/{$testing->ulid}", ['title' => 'Testing', 'url' => '/admin/testing', 'audience' => 'everyone'])
        ->assertRedirect();

    expect($testing->roles()->count())->toBe(0)
        ->and($titles($customer))->toContain('Testing');
});

it('menu "Users with a permission" mengikuti permission, sejalan dengan halaman yang dikunci', function () {
    $this->actingAs($this->admin)
        ->post('/admin/menus', ['location' => 'admin', 'title' => 'Testing', 'url' => '/admin/testing', 'audience' => 'permission'])
        ->assertSessionHasErrors('permission');

    $this->actingAs($this->admin)
        ->post('/admin/menus', [
            'location' => 'admin', 'title' => 'Testing', 'url' => '/admin/testing',
            'audience' => 'permission', 'permission' => 'testing.view',
        ])
        ->assertRedirect();

    $testing = Menu::firstWhere('title', 'Testing');
    expect($testing->permission)->toBe('testing.view')
        ->and($testing->roles()->count())->toBe(0);

    $staff = User::factory()->create()->assignRole(RoleName::Staff);
    $staffRole = Role::findByName('staff');
    $titles = fn () => collect($this->actingAs($staff)->get('/dashboard')
        ->viewData('page')['props']['menus']['admin'])->pluck('title')->all();

    // Tanpa izin: menu tersembunyi DAN halaman 403.
    expect($titles())->not->toContain('Testing');
    $this->actingAs($staff)->get('/admin/testing')->assertForbidden();

    // Centang testing.view di matriks → menu muncul DAN halaman terbuka.
    $this->actingAs($this->admin)
        ->patch("/admin/roles/{$staffRole->ulid}/permissions", ['permissions' => ['testing.view'], 'granted' => true]);

    expect($titles())->toContain('Testing');
    $this->actingAs($staff)->get('/admin/testing')->assertOk();

    // Muncul sebagai "Shows menu: Testing" di matriks permission, bukan di Menu visibility.
    $this->actingAs($this->admin)->get('/admin/permissions')
        ->assertInertia(fn (Assert $page) => $page
            ->where('permissionGroups', fn ($groups) => collect($groups)->firstWhere('module', 'testing')['permissions'][0]['menus'] === ['Testing'])
            ->where('menuVisibility', []));
});

it('matriks Menu visibility mengatur role untuk menu buatan sendiri', function () {
    $this->seed(MenuSeeder::class);
    $reports = Menu::factory()->admin()->create(['title' => 'Reports', 'url' => '/admin/reports', 'sort_order' => 9]);

    // Hanya menu buatan sendiri yang tidak mengikuti permission.
    $this->actingAs($this->admin)->get('/admin/permissions')
        ->assertInertia(fn (Assert $page) => $page
            ->has('menuVisibility', 1)
            ->where('menuVisibility.0.title', 'Reports')
            ->where('menuVisibility.0.everyone', true));

    $this->actingAs($this->admin)
        ->patch("/admin/menus/{$reports->ulid}/visibility", ['audience' => 'roles', 'roles' => ['admin', 'staff']])
        ->assertRedirect();

    expect($reports->roles()->pluck('name')->sort()->values()->all())->toBe(['admin', 'staff']);

    $this->actingAs($this->admin)
        ->patch("/admin/menus/{$reports->ulid}/visibility", ['audience' => 'everyone'])
        ->assertRedirect();

    expect($reports->roles()->count())->toBe(0);

    // Menu bawaan ditolak: diatur lewat permission.
    $users = Menu::firstWhere('url', '/admin/users');
    $this->actingAs($this->admin)
        ->from('/admin/permissions')
        ->patch("/admin/menus/{$users->ulid}/visibility", ['audience' => 'everyone'])
        ->assertRedirect('/admin/permissions');
    expect($users->fresh()->permission)->toBe('users.view');

    // Tanpa menus.update dilarang.
    $staff = User::factory()->create()->assignRole(RoleName::Staff);
    $this->actingAs($staff)
        ->patch("/admin/menus/{$reports->ulid}/visibility", ['audience' => 'everyone'])
        ->assertForbidden();
});

it('menu tanpa URL dan tanpa submenu tidak tampil di sidebar', function () {
    Menu::factory()->admin()->create(['title' => 'Empty group', 'url' => null]);

    $this->actingAs($this->admin)->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('menus.admin', []));
});

it('pratinjau sidebar per role menjelaskan kenapa menu tersembunyi', function () {
    $this->seed(MenuSeeder::class);
    $adminOnly = Menu::factory()->admin()->create(['title' => 'Testing', 'url' => '/admin/testing', 'sort_order' => 9]);
    $adminOnly->roles()->sync(Role::where('name', 'admin')->pluck('id'));

    $page = $this->actingAs($this->admin)->get('/admin/menus')->viewData('page')['props'];
    $staff = collect($page['previews'])->firstWhere('role', 'staff')['tree'];
    $byTitle = collect($staff)->keyBy('title');

    expect($byTitle['Catalog']['visible'])->toBeTrue()
        ->and($byTitle['Testing']['visible'])->toBeFalse()
        ->and($byTitle['Testing']['reason'])->toBe('Only for: admin')
        ->and(collect($byTitle['Administration']['children'])->firstWhere('title', 'Roles')['reason'])
        ->toBe('Needs permission roles.view');

    // Kolom "Visible to" untuk menu bawaan mengikuti permission
    // (staff punya users.view secara default).
    $users = collect($page['rows'])->firstWhere('title', 'Users');
    expect($users['audience'])->toBe(['type' => 'permission', 'roles' => ['admin', 'staff'], 'permission' => 'users.view']);
});

it('memberi izin di matriks langsung memunculkan menu bawaan untuk role itu', function () {
    $this->seed(MenuSeeder::class);
    $staff = User::factory()->create()->assignRole(RoleName::Staff);
    $staffRole = Role::findByName('staff');

    $adminChildren = fn () => collect(collect($this->actingAs($staff)->get('/dashboard')
        ->viewData('page')['props']['menus']['admin'])->firstWhere('title', 'Administration')['children'])
        ->pluck('title')->all();

    expect($adminChildren())->toBe(['Users']);

    $this->actingAs($this->admin)
        ->patch("/admin/roles/{$staffRole->ulid}/permissions", ['permissions' => ['menus.view'], 'granted' => true]);

    expect($adminChildren())->toBe(['Users', 'Menus']);
});

/*
|--------------------------------------------------------------------------
| Drag & drop (reorder)
|--------------------------------------------------------------------------
*/

it('drag & drop menyimpan urutan dan induk baru dalam satu request', function () {
    $home = Menu::factory()->create(['title' => 'Home', 'url' => '/', 'sort_order' => 1]);
    $about = Menu::factory()->create(['title' => 'About', 'url' => '/about', 'sort_order' => 2]);
    $company = Menu::factory()->create(['title' => 'Company', 'url' => null, 'sort_order' => 3]);

    // Company ke atas, About menjadi submenu Company, Home terakhir.
    $this->actingAs($this->admin)
        ->put('/admin/menus/reorder', [
            'location' => 'website',
            'items' => [
                ['id' => $company->id, 'parent_id' => null],
                ['id' => $about->id, 'parent_id' => $company->id],
                ['id' => $home->id, 'parent_id' => null],
            ],
        ])
        ->assertRedirect('/admin/menus?location=website');

    expect($company->fresh()->sort_order)->toBe(1)
        ->and($home->fresh()->sort_order)->toBe(2)
        ->and($about->fresh())->parent_id->toBe($company->id)->sort_order->toBe(1);

    // Cache dibuang: navbar langsung mengikuti susunan baru.
    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('menus.website.0.title', 'Company')
        ->where('menus.website.0.children.0.title', 'About'));
});

it('drag & drop tetap menjaga aturan menu di server', function (Closure $items, string $message) {
    $parent = Menu::factory()->create(['title' => 'Parent', 'url' => null, 'sort_order' => 1]);
    $child = Menu::factory()->childOf($parent)->create(['title' => 'Child', 'url' => '/child']);
    $noUrl = Menu::factory()->create(['title' => 'Group', 'url' => null, 'sort_order' => 2]);

    $this->actingAs($this->admin)
        ->put('/admin/menus/reorder', ['location' => 'website', 'items' => $items($parent, $child, $noUrl)])
        ->assertSessionHasErrors(['items' => $message]);
})->with([
    'submenu tanpa URL' => [
        fn ($p, $c, $g) => [['id' => $p->id, 'parent_id' => null], ['id' => $c->id, 'parent_id' => $p->id], ['id' => $g->id, 'parent_id' => $p->id]],
        '"Group" needs a URL before it can become a submenu.',
    ],
    'tingkat 3' => [
        // Parent ditaruh di bawah Child, yang sendirinya submenu.
        fn ($p, $c, $g) => [['id' => $g->id, 'parent_id' => null], ['id' => $c->id, 'parent_id' => $g->id], ['id' => $p->id, 'parent_id' => $c->id]],
        'Submenus can only be placed under a top-level menu (max 2 levels).',
    ],
    'daftar tidak lengkap' => [
        fn ($p, $c, $g) => [['id' => $p->id, 'parent_id' => null]],
        'The menu list has changed since you opened this page. Reload and try again.',
    ],
]);

it('hanya user dengan menus.update yang boleh mengubah urutan', function () {
    $menu = Menu::factory()->create();
    $staff = User::factory()->create()->assignRole(RoleName::Staff);

    $this->actingAs($staff)
        ->put('/admin/menus/reorder', ['location' => 'website', 'items' => [['id' => $menu->id, 'parent_id' => null]]])
        ->assertForbidden();
});

it('menu nonaktif (beserta submenunya) tidak tampil dan perubahan langsung terlihat', function () {
    $home = Menu::factory()->create(['title' => 'Home', 'url' => '/', 'sort_order' => 1]);
    $hidden = Menu::factory()->inactive()->create(['title' => 'Hidden', 'url' => null, 'sort_order' => 2]);
    Menu::factory()->childOf($hidden)->create(['title' => 'Inside hidden']);

    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->has('menus.website', 1)
        ->where('menus.website.0.title', 'Home')
        ->where('menus.admin', []));   // tamu: tanpa sidebar

    // Cache pohon menu dibuang otomatis saat menu disimpan.
    $home->update(['title' => 'Beranda']);

    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('menus.website.0.title', 'Beranda'));
});

it('API publik mengembalikan pohon menu website', function () {
    $parent = Menu::factory()->create(['title' => 'Resources', 'url' => null]);
    Menu::factory()->childOf($parent)->create(['title' => 'Docs', 'url' => 'https://example.com/docs', 'open_in_new_tab' => true]);
    Menu::factory()->admin()->create(['title' => 'Secret admin menu']);

    $this->getJson('/api/v1/menus/website')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Resources')
        ->assertJsonPath('data.0.children.0.title', 'Docs')
        ->assertJsonPath('data.0.children.0.open_in_new_tab', true);
});

it('seeder menu aman dijalankan berulang', function () {
    $this->seed(MenuSeeder::class);
    $count = Menu::count();

    $this->seed(MenuSeeder::class);

    expect(Menu::count())->toBe($count)
        // Dashboard, Catalog (+2), Administration (+5: Users, Roles, Permissions, Menus, API Generator).
        ->and(Menu::where('location', MenuLocation::Admin)->where('is_system', true)->count())->toBe(10);
});

it('daftar ikon PHP sama dengan peta ikon React', function () {
    $ts = file_get_contents(resource_path('js/lib/menu-icons.ts'));
    preg_match('/MENU_ICONS[^{]*\{([^}]*)\}/s', (string) $ts, $m);
    $keys = array_values(array_filter(array_map('trim', explode(',', $m[1] ?? ''))));

    expect($keys)->toEqualCanonicalizing(MenuIcons::NAMES);
});
