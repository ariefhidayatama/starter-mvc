<?php

use App\Enums\RoleName;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create()->assignRole(RoleName::Admin);
});

it('customer tidak bisa membuka halaman admin katalog', function () {
    $customer = User::factory()->create()->assignRole(RoleName::Customer);

    $this->actingAs($customer)->get('/admin/categories')->assertForbidden();
    $this->actingAs($customer)->get('/admin/products')->assertForbidden();
});

it('menampilkan daftar kategori dengan sort', function () {
    Category::factory()->create(['name' => 'Alpha']);
    Category::factory()->hasProducts(2)->create(['name' => 'Zulu']);

    $this->actingAs($this->admin)
        ->get('/admin/categories?sort=-products_count')
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/categories/index')
            ->where('categories.data.0.name', 'Zulu')
            ->where('categories.data.0.products_count', 2)
            ->where('filters.sort', '-products_count'));
});

it('CRUD kategori lewat web', function () {
    $this->actingAs($this->admin)
        ->post('/admin/categories', ['name' => 'Frozen Food', 'description' => 'Cold stuff'])
        ->assertRedirect('/admin/categories');

    $category = Category::firstWhere('slug', 'frozen-food');
    expect($category)->not->toBeNull();

    $this->actingAs($this->admin)
        ->put("/admin/categories/{$category->ulid}", ['name' => 'Frozen', 'slug' => 'Frozen Goods'])
        ->assertRedirect('/admin/categories');
    expect($category->fresh()->slug)->toBe('frozen-goods');

    $this->actingAs($this->admin)->delete("/admin/categories/{$category->ulid}")->assertRedirect('/admin/categories');
    expect(Category::find($category->id))->toBeNull();
});

it('menolak menghapus kategori yang masih punya produk (toast error)', function () {
    $category = Category::factory()->hasProducts(1)->create();

    $this->actingAs($this->admin)
        ->from('/admin/categories')
        ->delete("/admin/categories/{$category->ulid}")
        ->assertRedirect('/admin/categories');

    expect(Category::find($category->id))->not->toBeNull();
});

it('menampilkan daftar produk dengan filter kategori & status', function () {
    $coffee = Category::factory()->create();
    Product::factory()->for($coffee)->create(['name' => 'Arabica', 'is_active' => true]);
    Product::factory()->for($coffee)->inactive()->create(['name' => 'Old Robusta']);
    Product::factory()->create(['name' => 'Other', 'is_active' => true]);

    $this->actingAs($this->admin)
        ->get("/admin/products?filter[category_id]={$coffee->id}&filter[status]=inactive")
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/products/index')
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Old Robusta')
            // Kategori bersarang harus sampai ke React sebagai objek biasa
            // (bukan dibungkus { data: ... }).
            ->where('products.data.0.category.id', $coffee->id)
            ->where('products.data.0.category.name', $coffee->name)
            ->where('filters.filter.status', 'inactive')
            ->has('categoryOptions', 2));
});

it('CRUD produk lewat web', function () {
    $category = Category::factory()->create();

    $this->actingAs($this->admin)->post('/admin/products', [
        'category_id' => $category->id,
        'sku' => 'web-001',
        'name' => 'Web Product',
        'price' => '15000',
        'stock' => '7',
        'is_active' => '1',
    ])->assertRedirect('/admin/products');

    $product = Product::firstWhere('sku', 'WEB-001');
    expect($product->stock)->toBe(7);

    $this->actingAs($this->admin)->put("/admin/products/{$product->ulid}", [
        'category_id' => $category->id,
        'sku' => 'WEB-001',
        'name' => 'Web Product v2',
        'price' => '17500.5',
        'stock' => '0',
        'is_active' => '0',
    ])->assertRedirect('/admin/products');

    $product->refresh();
    expect($product->name)->toBe('Web Product v2')
        ->and($product->price)->toBe('17500.50')
        ->and($product->is_active)->toBeFalse();

    $this->actingAs($this->admin)->delete("/admin/products/{$product->ulid}")->assertRedirect('/admin/products');
    expect(Product::find($product->id))->toBeNull();
});

it('staff bisa mengelola produk tetapi tidak menghapus', function () {
    $staff = User::factory()->create()->assignRole(RoleName::Staff);
    $product = Product::factory()->create();

    $this->actingAs($staff)->get('/admin/products')->assertOk();
    $this->actingAs($staff)->get("/admin/products/{$product->ulid}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.category.id', $product->category_id));
    $this->actingAs($staff)->delete("/admin/products/{$product->ulid}")->assertForbidden();
});
