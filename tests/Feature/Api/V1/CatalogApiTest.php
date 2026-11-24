<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

function actingAsRole(RoleName $role): User
{
    $user = User::factory()->create()->assignRole($role);
    Sanctum::actingAs($user);

    return $user;
}

describe('kategori', function () {
    it('tanpa token ditolak 401 (katalog tidak lagi publik)', function () {
        $this->getJson('/api/v1/categories')->assertUnauthorized();
        $this->getJson('/api/v1/products')->assertUnauthorized();
    });

    it('daftar kategori untuk user API dengan jumlah produk', function () {
        actingAsRole(RoleName::Customer);
        Category::factory()->hasProducts(3)->create(['name' => 'Coffee']);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Coffee')
            ->assertJsonPath('data.0.products_count', 3)
            ->assertJsonStructure(['success', 'message', 'data', 'meta' => ['current_page', 'per_page', 'total', 'last_page'], 'links']);
    });

    it('staff bisa membuat kategori, slug dibuat otomatis & unik', function () {
        actingAsRole(RoleName::Staff);
        Category::factory()->create(['name' => 'Iced Coffee', 'slug' => 'iced-coffee']);

        $this->postJson('/api/v1/categories', ['name' => 'Iced Coffee'])
            ->assertCreated()
            ->assertJsonPath('message', 'Category created.')
            ->assertJsonPath('data.slug', 'iced-coffee-2');
    });

    it('staff tidak bisa menghapus kategori (tidak punya categories.delete)', function () {
        actingAsRole(RoleName::Staff);
        $category = Category::factory()->create();

        $this->deleteJson("/api/v1/categories/{$category->id}")->assertForbidden();
    });

    it('admin bisa menghapus kategori kosong', function () {
        actingAsRole(RoleName::Admin);
        $category = Category::factory()->create();

        $this->deleteJson("/api/v1/categories/{$category->id}")->assertNoContent();
        expect(Category::find($category->id))->toBeNull();
    });
});

describe('produk: membaca', function () {
    beforeEach(function () {
        $this->coffee = Category::factory()->create(['name' => 'Coffee']);
        $this->tea = Category::factory()->create(['name' => 'Tea']);
        // is_active ditulis eksplisit: factory mengacaknya (90% aktif), dan
        // test tidak boleh bergantung pada keberuntungan.
        Product::factory()->for($this->coffee)->create(['sku' => 'KOP-001', 'name' => 'Arabica Gayo', 'price' => 85000, 'stock' => 10, 'is_active' => true]);
        Product::factory()->for($this->coffee)->create(['sku' => 'KOP-002', 'name' => 'Robusta Lampung', 'price' => 45000, 'stock' => 0, 'is_active' => true]);
        Product::factory()->for($this->tea)->create(['sku' => 'TEH-001', 'name' => 'Jasmine Tea', 'price' => 30000, 'stock' => 5, 'is_active' => true]);
        Product::factory()->for($this->tea)->inactive()->create(['sku' => 'TEH-002', 'name' => 'Hidden Tea']);
    });

    it('customer (tanpa products.view) hanya melihat produk aktif', function () {
        actingAsRole(RoleName::Customer);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonMissing(['name' => 'Hidden Tea']);
    });

    it('staff (products.view) juga melihat produk nonaktif', function () {
        actingAsRole(RoleName::Staff);

        $this->getJson('/api/v1/products')->assertJsonCount(4, 'data');
    });

    it('detail produk nonaktif → 404 untuk customer', function () {
        actingAsRole(RoleName::Customer);
        $hidden = Product::firstWhere('sku', 'TEH-002');

        $this->getJson("/api/v1/products/{$hidden->id}")->assertNotFound();
    });

    it('mendukung search (nama & SKU), filter, dan sort', function () {
        actingAsRole(RoleName::Customer);

        $this->getJson('/api/v1/products?search=arab')->assertJsonCount(1, 'data')->assertJsonPath('data.0.sku', 'KOP-001');
        $this->getJson('/api/v1/products?search=teh-')->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/products?filter[category_id]={$this->coffee->id}")->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/products?filter[in_stock]=1')->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/products?filter[price_min]=40000&filter[price_max]=90000')->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/products?sort=-price')
            ->assertJsonPath('data.0.sku', 'KOP-001')
            ->assertJsonPath('data.2.sku', 'TEH-001');
    });

    it('menolak sort & filter di luar whitelist', function () {
        actingAsRole(RoleName::Customer);

        $this->getJson('/api/v1/products?sort=password')->assertUnprocessable()->assertJsonValidationErrors('sort');
        $this->getJson('/api/v1/products?per_page=1000')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson('/api/v1/products?filter[price_min]=90000&filter[price_max]=1')->assertJsonValidationErrors('filter.price_max');
    });

    it('mengirim harga sebagai string & menyertakan kategori', function () {
        actingAsRole(RoleName::Customer);

        $this->getJson('/api/v1/products?search=arab')
            ->assertJsonPath('data.0.price', '85000.00')
            ->assertJsonPath('data.0.in_stock', true)
            ->assertJsonPath('data.0.category.name', 'Coffee');
    });

    it('mendukung cursor pagination untuk infinite scroll', function () {
        actingAsRole(RoleName::Customer);

        $first = $this->getJson('/api/v1/products?pagination=cursor&per_page=10&sort=name')
            ->assertOk()
            ->assertJsonStructure(['meta' => ['per_page', 'next_cursor', 'prev_cursor']]);

        expect($first->json('meta.next_cursor'))->toBeNull(); // hanya 3 data
    });

    it('tidak terkena N+1: jumlah query tetap walau produk bertambah', function () {
        Product::factory(30)->for($this->coffee)->create(['is_active' => true]);
        $user = actingAsRole(RoleName::Customer);
        // Panaskan cache permission spatie (di aplikasi sungguhan tersimpan di
        // cache store): yang diukur hanya query daftar produk.
        $user->can(PermissionName::ApiAccess);

        DB::enableQueryLog();
        $this->getJson('/api/v1/products?per_page=25')->assertOk();

        // count + select produk + select kategori (eager load)
        expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(4);
    });
});

describe('produk: menulis', function () {
    it('staff bisa membuat produk; SKU dinormalisasi huruf besar', function () {
        actingAsRole(RoleName::Staff);
        $category = Category::factory()->create();

        $this->postJson('/api/v1/products', [
            'category_id' => $category->id,
            'sku' => 'kop-777',
            'name' => 'Toraja Kalosi',
            'price' => '125000.50',
            'stock' => 12,
        ])
            ->assertCreated()
            ->assertJsonPath('data.sku', 'KOP-777')
            ->assertJsonPath('data.price', '125000.50')
            ->assertJsonPath('data.is_active', true);
    });

    it('memvalidasi SKU unik, harga & stok', function () {
        actingAsRole(RoleName::Admin);
        Product::factory()->create(['sku' => 'DUP-001']);

        $this->postJson('/api/v1/products', [
            'category_id' => 999999,
            'sku' => 'dup-001',
            'name' => 'X',
            'price' => -1,
            'stock' => 1.5,
        ])->assertJsonValidationErrors(['category_id', 'sku', 'price', 'stock']);
    });

    it('PATCH hanya mengubah field yang dikirim', function () {
        actingAsRole(RoleName::Staff);
        $product = Product::factory()->create(['name' => 'Tetap', 'stock' => 3]);

        $this->patchJson("/api/v1/products/{$product->id}", ['stock' => 50])
            ->assertOk()
            ->assertJsonPath('data.stock', 50)
            ->assertJsonPath('data.name', 'Tetap');
    });

    it('customer tidak bisa membuat/mengubah produk', function () {
        actingAsRole(RoleName::Customer);
        $product = Product::factory()->create();

        $this->postJson('/api/v1/products', [])->assertForbidden();
        $this->patchJson("/api/v1/products/{$product->id}", ['stock' => 1])->assertForbidden();
        $this->deleteJson("/api/v1/products/{$product->id}")->assertForbidden();
    });
});
