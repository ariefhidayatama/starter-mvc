<?php

use App\Enums\RoleName;
use App\Models\Kwitansi;
use App\Models\KwitansiKwitansiDet;
use App\Models\User;
use Illuminate\Support\Arr;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Dibuat oleh CRUD generator (Kwitansis).
 * Tambahkan test untuk aturan bisnis Anda sendiri di sini.
 */

beforeEach(function () {
    $this->admin = User::factory()->create()->assignRole(RoleName::Admin);
    $this->fields = ['kwitansi_no', 'customer', 'ship_to', 'ttf_no'];
});

it('kwitansis: user tanpa permission tidak bisa membuka halaman', function () {
    $customer = User::factory()->create()->assignRole(RoleName::Customer);

    $this->actingAs($customer)->get('/admin/kwitansis')->assertForbidden();
    $this->actingAs($customer)->post('/admin/kwitansis', [])->assertForbidden();
});

it('kwitansis: menampilkan daftar', function () {
    Kwitansi::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get('/admin/kwitansis')
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/kwitansis/index')
            ->has('items.data', 3));
});

it('kwitansis: tambah, ubah & hapus', function () {
    $payload = Arr::only(Kwitansi::factory()->make()->toArray(), $this->fields);

    $this->actingAs($this->admin)->post('/admin/kwitansis', $payload)->assertRedirect('/admin/kwitansis');

    $item = Kwitansi::query()->latest('id')->firstOrFail();

    $this->actingAs($this->admin)
        ->get("/admin/kwitansis/{$item->getRouteKey()}/edit")
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/kwitansis/edit')
            ->where('item.id', $item->id));

    $changes = Arr::only(Kwitansi::factory()->make()->toArray(), $this->fields);

    $this->actingAs($this->admin)->put("/admin/kwitansis/{$item->getRouteKey()}", $changes)->assertRedirect('/admin/kwitansis');
    $this->actingAs($this->admin)->delete("/admin/kwitansis/{$item->getRouteKey()}")->assertRedirect('/admin/kwitansis');

    expect(Kwitansi::find($item->id))->toBeNull();
});

it('kwitansis: tambah beserta baris kwitansi dets dalam satu simpan', function () {
    $payload = [
        ...Arr::only(Kwitansi::factory()->make()->toArray(), $this->fields),
        'kwitansi_dets' => [Arr::only(KwitansiKwitansiDet::factory()->make()->toArray(), ['customer_po', 'si_no'])],
    ];

    $this->actingAs($this->admin)->post('/admin/kwitansis', $payload)->assertRedirect('/admin/kwitansis');

    $item = Kwitansi::query()->latest('id')->firstOrFail();

    expect($item->kwitansiDets()->count())->toBe(1);
});

it('kwitansis: field wajib harus diisi', function () {
    $this->actingAs($this->admin)
        ->post('/admin/kwitansis', [])
        ->assertSessionHasErrors(['kwitansi_no', 'customer', 'ship_to', 'ttf_no']);
});
