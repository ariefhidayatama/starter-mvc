<?php

use App\Enums\RoleName;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

/*
| Semua error API memakai format yang sama:
| { success: false, message: string, code: string, errors: object|null }
*/

$shape = ['success', 'message', 'code', 'errors'];

it('401 UNAUTHENTICATED tanpa token', function () use ($shape) {
    $this->postJson('/api/v1/categories', ['name' => 'X'])
        ->assertUnauthorized()
        ->assertJsonStructure($shape)
        ->assertJson(['success' => false, 'code' => 'UNAUTHENTICATED', 'errors' => null]);
});

it('403 FORBIDDEN tanpa permission', function () use ($shape) {
    Sanctum::actingAs(User::factory()->create()->assignRole(RoleName::Customer));

    $this->postJson('/api/v1/categories', ['name' => 'X'])
        ->assertForbidden()
        ->assertJsonStructure($shape)
        ->assertJsonPath('code', 'FORBIDDEN')
        ->assertJsonPath('message', 'You are not allowed to perform this action.');
});

it('404 NOT_FOUND tanpa membocorkan nama model', function () {
    Sanctum::actingAs(User::factory()->create()->assignRole(RoleName::Customer));

    $this->getJson('/api/v1/categories/999999')
        ->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND')
        ->assertJsonPath('message', 'Resource not found.')
        ->assertDontSee('App\\\\Models', false);
});

it('405 METHOD_NOT_ALLOWED', function () {
    $this->patchJson('/api/v1/ping')
        ->assertStatus(405)
        ->assertJsonPath('code', 'METHOD_NOT_ALLOWED');
});

it('422 VALIDATION_ERROR dengan daftar error per field', function () {
    Sanctum::actingAs(User::factory()->create()->assignRole(RoleName::Admin));

    $this->postJson('/api/v1/products', [])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['errors' => ['category_id', 'sku', 'name', 'price', 'stock']]);
});

it('409 CONFLICT untuk pelanggaran aturan bisnis', function () {
    Sanctum::actingAs(User::factory()->create()->assignRole(RoleName::Admin));
    $category = Category::factory()->hasProducts(2)->create();

    $this->deleteJson("/api/v1/categories/{$category->id}")
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT');
});

it('429 TOO_MANY_REQUESTS dengan header Retry-After', function () {
    foreach (range(1, 5) as $_) {
        $this->postJson('/api/v1/auth/login', ['email' => 'a@b.c', 'password' => 'x', 'device_name' => 'd']);
    }

    $this->postJson('/api/v1/auth/login', ['email' => 'a@b.c', 'password' => 'x', 'device_name' => 'd'])
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'TOO_MANY_REQUESTS')
        ->assertHeader('Retry-After');
});

it('500 SERVER_ERROR tanpa detail teknis saat APP_DEBUG=false', function () {
    config(['app.debug' => false]);
    Route::get('/api/v1/__boom', fn () => throw new RuntimeException('SQLSTATE rahasia'));

    $this->getJson('/api/v1/__boom')
        ->assertStatus(500)
        ->assertJsonPath('code', 'SERVER_ERROR')
        ->assertJsonMissingPath('debug')
        ->assertDontSee('SQLSTATE rahasia');
});

it('500 menyertakan info debug saat APP_DEBUG=true (lokal)', function () {
    config(['app.debug' => true]);
    Route::get('/api/v1/__boom', fn () => throw new RuntimeException('detail lokal'));

    $this->getJson('/api/v1/__boom')
        ->assertStatus(500)
        ->assertJsonPath('debug.message', 'detail lokal');
});

it('tidak mengubah error halaman web', function () {
    $this->get('/admin/users')->assertRedirect('/login');
});
