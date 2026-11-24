<?php

use App\Enums\RoleName;
use App\Models\Product;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create()->assignRole(RoleName::Admin);
});

it('membuat PDF dari produk yang dicentang', function () {
    [$a, $b] = Product::factory()->count(2)->create();

    $response = $this->actingAs($this->admin)
        ->get('/admin/products/pdf?'.http_build_query(['ids' => [$a->ulid, $b->ulid]]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($response->getContent())->toStartWith('%PDF');
});

it('menolak PDF tanpa pilihan atau dengan ulid tidak dikenal', function () {
    $this->actingAs($this->admin)
        ->from('/admin/products')
        ->get('/admin/products/pdf')
        ->assertSessionHasErrors('ids');

    $this->actingAs($this->admin)
        ->from('/admin/products')
        ->get('/admin/products/pdf?ids[]=01jd8q3x7k2m9vz4p6r8t0w2yb')
        ->assertSessionHasErrors('ids.0');
});

it('menolak PDF untuk user tanpa izin products.view', function () {
    $customer = User::factory()->create()->assignRole(RoleName::Customer);
    $product = Product::factory()->create();

    $this->actingAs($customer)
        ->get('/admin/products/pdf?ids[]='.$product->ulid)
        ->assertForbidden();
});
