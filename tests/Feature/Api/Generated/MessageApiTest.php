<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Message;
use App\Models\MessageDetail;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Arr;
use Laravel\Sanctum\Sanctum;

/*
 * Dibuat oleh CRUD generator: REST API /api/v1/messages (Messages).
 * Tambahkan test untuk aturan bisnis Anda sendiri di sini.
 */

beforeEach(function () {
    $this->markTestSkipped('Uses the "wmsreports" connection, which RefreshDatabase does not reset. Test it manually on a training database.');

    $this->admin = User::factory()->create()->assignRole(RoleName::Admin);
    $this->fields = ['whs_code', 'owner_code', 'msg_group', 'msg_description', 'photo1', 'photo2', 'photo3', 'photo4', 'photo5', 'document1', 'document2', 'document3'];
});

it('messages API: wajib token', function () {
    $this->getJson('/api/v1/messages')->assertUnauthorized();
});

it('messages API: role tanpa permission messages.* ditolak', function () {
    Sanctum::actingAs(User::factory()->create()->assignRole(RoleName::Customer));

    $this->getJson('/api/v1/messages')->assertForbidden();
    $this->postJson('/api/v1/messages', [])->assertForbidden();
});

it('messages API: izin mengikuti matriks Permissions', function () {
    Role::findByName(RoleName::Customer->value, 'web')->givePermissionTo(PermissionName::MessagesView);
    Sanctum::actingAs(User::factory()->create()->assignRole(RoleName::Customer));
    Message::factory()->count(2)->create();

    // Punya messages.view → boleh membaca, tetapi belum boleh menambah.
    $this->getJson('/api/v1/messages')->assertOk()->assertJsonCount(2, 'data');
    $this->postJson('/api/v1/messages', [])->assertForbidden();
});

it('messages API: daftar, detail, tambah, ubah & hapus', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/messages', Arr::only(Message::factory()->make()->toArray(), $this->fields))
        ->assertCreated()
        ->assertJsonPath('success', true);

    $item = Message::query()->latest('id')->firstOrFail();
    $key = $item->getRouteKey();

    $this->getJson('/api/v1/messages')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonStructure(['success', 'message', 'data', 'meta', 'links']);
    $this->getJson("/api/v1/messages/{$key}")->assertOk()->assertJsonPath('data.id', $item->id);

    $this->putJson("/api/v1/messages/{$key}", Arr::only(Message::factory()->make()->toArray(), $this->fields))->assertOk();
    $this->deleteJson("/api/v1/messages/{$key}")->assertNoContent();

    expect(Message::find($item->id))->toBeNull();
});

it('messages API: tambah beserta baris detail dalam SATU request', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/messages', [
        ...Arr::only(Message::factory()->make()->toArray(), $this->fields),
        'details' => [Arr::only(MessageDetail::factory()->make()->toArray(), ['trans_code', 'trans_no', 'ref_no', 'po_no', 'prod_code', 'loc_code', 'pallet_no', 'lot_no', 'inv_status', 'qty'])],
    ])
        ->assertCreated()
        ->assertJsonCount(1, 'data.details');
});

it('messages API: field wajib → 422 VALIDATION_ERROR', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/messages', [])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonValidationErrors(['whs_code', 'owner_code', 'msg_group', 'msg_description']);
});
