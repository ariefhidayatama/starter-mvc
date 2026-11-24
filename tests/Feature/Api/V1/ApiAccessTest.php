<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use App\Services\Access\AccessService;
use Laravel\Sanctum\Sanctum;

/*
 * RBAC API lapis 1: permission "api.access" per role, diatur di matriks
 * Permissions. Izin modul (mis. categories.create) tetap dicek terpisah.
 */

function customerWithoutApiAccess(): User
{
    Role::findByName(RoleName::Customer->value, 'web')->revokePermissionTo(PermissionName::ApiAccess);

    return User::factory()->create(['email' => 'no-api@example.com'])->assignRole(RoleName::Customer);
}

it('role baru staff & customer mendapat api.access sebagai nilai awal', function () {
    expect(Role::findByName(RoleName::Staff->value, 'web')->hasPermissionTo(PermissionName::ApiAccess))->toBeTrue()
        ->and(Role::findByName(RoleName::Customer->value, 'web')->hasPermissionTo(PermissionName::ApiAccess))->toBeTrue();
});

it('login ditolak 403 bila role tidak punya api.access', function () {
    customerWithoutApiAccess();

    $this->postJson('/api/v1/auth/login', ['email' => 'no-api@example.com', 'password' => 'password', 'device_name' => 'HP'])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN')
        ->assertJsonPath('message', 'Your role is not allowed to use the API.');
});

it('token lama langsung ditolak setelah api.access dicabut dari role', function () {
    $user = User::factory()->create()->assignRole(RoleName::Customer);
    $token = $user->createToken('HP')->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/categories')->assertOk();

    Role::findByName(RoleName::Customer->value, 'web')->revokePermissionTo(PermissionName::ApiAccess);
    app('auth')->forgetGuards();

    $this->withToken($token)->getJson('/api/v1/categories')
        ->assertForbidden()
        ->assertJsonPath('message', 'Your role is not allowed to use the API.');
    $this->withToken($token)->getJson('/api/v1/auth/me')->assertForbidden();
});

it('tetap bisa logout walau api.access sudah dicabut', function () {
    Sanctum::actingAs(customerWithoutApiAccess());

    $this->postJson('/api/v1/auth/logout')->assertNoContent();
});

it('admin selalu boleh memakai API', function () {
    // fresh(): ambil ulang dari database, seperti user dari token sungguhan.
    Sanctum::actingAs(User::factory()->create()->assignRole(RoleName::Admin)->fresh());

    $this->getJson('/api/v1/auth/me')->assertOk();
});

it('endpoint publik tetap terbuka tanpa token', function () {
    $this->getJson('/api/v1/ping')->assertOk();
    $this->getJson('/api/v1/menus/website')->assertOk();
});

it('matriks Permissions menampilkan endpoint API tiap permission', function () {
    $endpoints = app(AccessService::class)->apiEndpoints();

    expect($endpoints['categories.create'])->toContain('POST /api/v1/categories')
        ->and($endpoints['categories.delete'])->toContain('DELETE /api/v1/categories/{category}')
        ->and($endpoints['products.update'])->toContain('PUT /api/v1/products/{product}')
        // Membaca katalog cukup dengan api.access (tidak butuh categories.view).
        ->and($endpoints['api.access'])->toContain('GET /api/v1/categories', 'GET /api/v1/auth/me')
        ->and($endpoints)->not->toHaveKey('categories.view');
});
