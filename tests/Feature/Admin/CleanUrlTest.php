<?php

use App\Enums\RoleName;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

/*
 * URL admin yang bersih:
 *  - filter daftar disimpan di session (middleware remember.table)
 *  - baris dibuka lewat ULID acak, bukan id berurutan
 */

beforeEach(function () {
    $this->admin = User::factory()->create()->assignRole(RoleName::Admin);
});

it('produk baru otomatis mendapat ULID huruf kecil', function () {
    $product = Product::factory()->create();

    expect($product->ulid)->toMatch('/^[0-9a-z]{26}$/');
});

it('halaman edit dibuka lewat ULID, bukan id', function () {
    $product = Product::factory()->create();

    $this->actingAs($this->admin)->get("/admin/products/{$product->ulid}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->url("/admin/products/{$product->ulid}/edit")
            ->where('product.ulid', $product->ulid));

    // id berurutan & ULID asal-asalan → 404.
    $this->actingAs($this->admin)->get("/admin/products/{$product->id}/edit")->assertNotFound();
    $this->actingAs($this->admin)->get('/admin/products/01jd8q3x7k2m9vz4p6r8t0w2yb/edit')->assertNotFound();
});

it('halaman edit users, roles, menus & categories memakai ULID', function (string $path, Closure $make) {
    $model = $make();

    expect($model->ulid)->toMatch('/^[0-9a-z]{26}$/');

    $this->actingAs($this->admin)->get("/admin/{$path}/{$model->ulid}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->url("/admin/{$path}/{$model->ulid}/edit"));

    // id berurutan → 404.
    $this->actingAs($this->admin)->get("/admin/{$path}/{$model->id}/edit")->assertNotFound();
})->with([
    'users' => ['users', fn () => User::factory()->create()],
    'roles' => ['roles', fn () => Role::create(['name' => 'auditor', 'guard_name' => 'web'])],
    'menus' => ['menus', fn () => Menu::factory()->create(['location' => 'website'])],
    'categories' => ['categories', fn () => Category::factory()->create()],
]);

it('role yang dibuat lewat spatie (findOrCreate) juga mendapat ULID', function () {
    expect(Role::findOrCreate('reviewer', 'web')->ulid)->toMatch('/^[0-9a-z]{26}$/');
});

it('"Add submenu" memakai ?parent=<ulid>', function () {
    $parent = Menu::factory()->create(['location' => 'website']);

    $this->actingAs($this->admin)
        ->get("/admin/menus/create?location=website&parent={$parent->ulid}")
        ->assertInertia(fn (Assert $page) => $page->where('parentId', $parent->id));
});

it('API produk tetap memakai id', function () {
    $product = Product::factory()->create(['is_active' => true]);
    // API wajib token (role customer punya api.access).
    Sanctum::actingAs(User::factory()->create()->assignRole(RoleName::Customer));

    $this->getJson("/api/v1/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.ulid', $product->ulid);
});

it('filter dipakai lalu URL halaman dibersihkan', function () {
    Product::factory()->create(['name' => 'Kopi Arabica']);
    Product::factory()->create(['name' => 'Teh Hijau']);

    $this->actingAs($this->admin)
        ->get('/admin/products?search=kopi&sort=name&per_page=25')
        ->assertInertia(fn (Assert $page) => $page
            ->url('/admin/products')
            ->has('products.data', 1)
            ->where('filters.search', 'kopi')
            ->where('filters.sort', 'name')
            ->where('filters.per_page', 25));
});

it('membuka /admin/products tanpa query memulihkan filter terakhir', function () {
    Product::factory()->create(['name' => 'Kopi Arabica']);
    Product::factory()->create(['name' => 'Teh Hijau']);

    $this->actingAs($this->admin)->get('/admin/products?search=kopi')->assertOk();

    // Refresh, klik menu sidebar, atau redirect setelah simpan/hapus.
    $this->actingAs($this->admin)->get('/admin/products')
        ->assertInertia(fn (Assert $page) => $page
            ->url('/admin/products')
            ->has('products.data', 1)
            ->where('filters.search', 'kopi'));
});

it('header X-Table-Query tanpa filter = user mengosongkan filter', function () {
    Product::factory()->count(2)->create();

    $this->actingAs($this->admin)->get('/admin/products?search=zzz')->assertOk();

    $this->actingAs($this->admin)
        ->get('/admin/products', ['X-Table-Query' => '1'])
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 2)
            ->where('filters.search', ''));

    $this->actingAs($this->admin)->get('/admin/products')
        ->assertInertia(fn (Assert $page) => $page->where('filters.search', ''));
});

it('link halaman hanya membawa ?page=, filter tetap dari session', function () {
    $category = Category::factory()->create();
    Product::factory()->count(12)->for($category)->create();
    Product::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get("/admin/products?filter[category_id]={$category->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 12)
            ->where('products.next_page_url', url('/admin/products').'?page=2'));

    $this->actingAs($this->admin)->get('/admin/products?page=2')
        ->assertInertia(fn (Assert $page) => $page
            ->url('/admin/products')
            ->where('products.current_page', 2)
            ->where('products.total', 12)
            ->where('filters.filter.category_id', (string) $category->id));

    // Halaman juga diingat (mis. kembali dari halaman edit).
    $this->actingAs($this->admin)->get('/admin/products')
        ->assertInertia(fn (Assert $page) => $page->where('products.current_page', 2));
});

it('filter tidak valid tidak pernah disimpan', function () {
    $this->actingAs($this->admin)->get('/admin/products?search=kopi')->assertOk();

    $this->actingAs($this->admin)
        ->from('/admin/products')
        ->get('/admin/products?sort=password')
        ->assertRedirect('/admin/products');

    $this->actingAs($this->admin)->get('/admin/products')
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.search', 'kopi')
            ->where('filters.sort', '-created_at'));
});

it('filter lama di session yang sudah tidak valid dilupakan', function () {
    $this->actingAs($this->admin)
        ->withSession(['table_query' => ['admin' => ['products' => ['index' => ['sort' => 'removed_column']]]]])
        ->from('/dashboard')
        ->get('/admin/products')
        ->assertRedirect('/dashboard')
        ->assertSessionMissing('table_query.admin.products.index');
});
