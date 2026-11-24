<?php

use App\Enums\RoleName;
use App\Models\Product;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create()->assignRole(RoleName::Admin);

    Product::factory()->create(['name' => 'Cheap', 'price' => 5000, 'stock' => 0]);
    Product::factory()->create(['name' => 'Mid', 'price' => 20000, 'stock' => 15]);
    Product::factory()->create(['name' => 'Pricey', 'price' => 90000, 'stock' => 200]);
});

it('menyaring produk berdasarkan rentang harga', function () {
    $this->actingAs($this->admin)
        ->get('/admin/products?filter[price_min]=10000&filter[price_max]=50000')
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Mid')
            ->where('filters.filter.price_min', '10000')
            ->where('filters.filter.price_max', '50000'));
});

it('menyaring produk berdasarkan rentang stok', function () {
    $this->actingAs($this->admin)
        ->get('/admin/products?filter[stock_min]=10&sort=name')
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 2)
            ->where('products.data.0.name', 'Mid')
            ->where('products.data.1.name', 'Pricey')
            ->where('filters.filter.stock_min', '10')
            ->where('filters.filter.stock_max', ''));

    $this->actingAs($this->admin)
        ->get('/admin/products?filter[stock_max]=0')
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Cheap'));
});

it('menerima harga maksimum tanpa harga minimum', function () {
    $this->actingAs($this->admin)
        ->get('/admin/products?filter[price_max]=10000')
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Cheap'));
});

it('menolak rentang terbalik (max < min)', function () {
    $this->actingAs($this->admin)
        ->from('/admin/products')
        ->get('/admin/products?filter[stock_min]=50&filter[stock_max]=10')
        ->assertSessionHasErrors('filter.stock_max');
});
