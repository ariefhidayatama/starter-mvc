<?php

use App\Enums\RoleName;
use App\Models\Product;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create()->assignRole(RoleName::Admin);
});

it('menghapus beberapa produk sekaligus lewat checklist', function () {
    [$a, $b, $keep] = Product::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->delete('/admin/products/bulk', ['ids' => [$a->ulid, $b->ulid]])
        ->assertRedirect('/admin/products');

    expect(Product::find($a->id))->toBeNull()
        ->and(Product::find($b->id))->toBeNull()
        ->and(Product::find($keep->id))->not->toBeNull();
});

it('menolak bulk delete tanpa pilihan atau dengan ulid tidak dikenal', function () {
    $product = Product::factory()->create();

    $this->actingAs($this->admin)
        ->from('/admin/products')
        ->delete('/admin/products/bulk', ['ids' => []])
        ->assertSessionHasErrors('ids');

    $this->actingAs($this->admin)
        ->from('/admin/products')
        ->delete('/admin/products/bulk', ['ids' => [$product->ulid, '01jd8q3x7k2m9vz4p6r8t0w2yb']])
        ->assertSessionHasErrors('ids.1');

    expect(Product::find($product->id))->not->toBeNull();
});

it('menolak bulk delete untuk user tanpa izin products.delete', function () {
    $customer = User::factory()->create()->assignRole(RoleName::Customer);
    $product = Product::factory()->create();

    $this->actingAs($customer)
        ->delete('/admin/products/bulk', ['ids' => [$product->ulid]])
        ->assertForbidden();

    expect(Product::find($product->id))->not->toBeNull();
});
