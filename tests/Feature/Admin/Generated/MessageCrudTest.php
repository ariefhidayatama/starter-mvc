<?php

use App\Enums\RoleName;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Arr;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Dibuat oleh CRUD generator (Messages).
 * Tambahkan test untuk aturan bisnis Anda sendiri di sini.
 */

beforeEach(function () {
    $this->markTestSkipped('Uses the "wmsreports" connection, which RefreshDatabase does not reset. Test it manually on a training database.');

    $this->admin = User::factory()->create()->assignRole(RoleName::Admin);
    $this->fields = ['whs_code', 'owner_code', 'msg_group', 'msg_description', 'photo1', 'photo2', 'photo3', 'photo4', 'photo5', 'document1', 'document2', 'document3'];
});

it('messages: user tanpa permission tidak bisa membuka halaman', function () {
    $customer = User::factory()->create()->assignRole(RoleName::Customer);

    $this->actingAs($customer)->get('/admin/messages')->assertForbidden();
    $this->actingAs($customer)->post('/admin/messages', [])->assertForbidden();
});

it('messages: menampilkan daftar', function () {
    Message::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get('/admin/messages')
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/messages/index')
            ->has('items.data', 3));
});

it('messages: tambah, ubah & hapus', function () {
    $payload = Arr::only(Message::factory()->make()->toArray(), $this->fields);

    $this->actingAs($this->admin)->post('/admin/messages', $payload)->assertRedirect('/admin/messages');

    $item = Message::query()->latest('id')->firstOrFail();

    $this->actingAs($this->admin)
        ->get("/admin/messages/{$item->id}/edit")
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/messages/edit')
            ->where('item.id', $item->id));

    $changes = Arr::only(Message::factory()->make()->toArray(), $this->fields);

    $this->actingAs($this->admin)->put("/admin/messages/{$item->id}", $changes)->assertRedirect('/admin/messages');
    $this->actingAs($this->admin)->delete("/admin/messages/{$item->id}")->assertRedirect('/admin/messages');

    expect(Message::find($item->id))->toBeNull();
});

it('messages: field wajib harus diisi', function () {
    $this->actingAs($this->admin)
        ->post('/admin/messages', [])
        ->assertSessionHasErrors(['whs_code', 'owner_code', 'msg_group', 'msg_description']);
});
