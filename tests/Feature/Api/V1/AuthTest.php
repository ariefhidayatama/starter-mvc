<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Sanctum\PersonalAccessToken;

function apiLogin(array $overrides = []): TestResponse
{
    return test()->postJson('/api/v1/auth/login', array_merge([
        'email' => 'budi@example.com',
        'password' => 'password',
        'device_name' => 'Pixel 8',
    ], $overrides));
}

beforeEach(function () {
    $this->user = User::factory()->create(['email' => 'budi@example.com']);
    $this->user->assignRole(RoleName::Staff);
});

describe('login', function () {
    it('mengembalikan token, roles & permissions', function () {
        apiLogin()
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'budi@example.com')
            ->assertJsonPath('data.user.roles', ['staff'])
            ->assertJsonPath('data.user.permissions', [
                // Dibuat migration (sebelum seeder), jadi id-nya paling kecil.
                PermissionName::ApiAccess->value,
                PermissionName::CategoriesView->value,
                PermissionName::CategoriesCreate->value,
                PermissionName::CategoriesUpdate->value,
                PermissionName::ProductsView->value,
                PermissionName::ProductsCreate->value,
                PermissionName::ProductsUpdate->value,
                PermissionName::UsersView->value,
            ])
            ->assertJsonStructure(['data' => ['access_token', 'expires_at']])
            ->assertJsonMissingPath('data.user.password');

        expect($this->user->tokens()->count())->toBe(1);
    });

    it('menolak password salah dengan 422', function () {
        apiLogin(['password' => 'salah'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('mewajibkan device_name', function () {
        apiLogin(['device_name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['device_name']);
    });

    it('menolak akun nonaktif', function () {
        $this->user->update(['is_active' => false]);

        apiLogin()->assertUnprocessable()->assertJsonValidationErrors(['email']);
    });

    it('membuat token terpisah per device (multi-device)', function () {
        apiLogin(['device_name' => 'Pixel 8']);
        apiLogin(['device_name' => 'iPad']);

        expect($this->user->tokens()->pluck('name')->all())->toEqualCanonicalizing(['Pixel 8', 'iPad']);
    });

    it('mengganti token lama bila login ulang dari device yang sama', function () {
        apiLogin(['device_name' => 'Pixel 8']);
        apiLogin(['device_name' => 'Pixel 8']);

        expect($this->user->tokens()->count())->toBe(1);
    });

    it('membatasi percobaan login 5x per menit', function () {
        RateLimiter::clear('budi@example.com|127.0.0.1');

        foreach (range(1, 5) as $_) {
            apiLogin(['password' => 'salah'])->assertUnprocessable();
        }

        apiLogin()->assertTooManyRequests();
    });
});

describe('login dengan 2FA', function () {
    beforeEach(function () {
        $this->user->forceFill([
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ])->save();
    });

    it('meminta kode 2FA bila belum dikirim', function () {
        apiLogin()->assertUnprocessable()->assertJsonValidationErrors(['code']);
        expect($this->user->tokens()->count())->toBe(0);
    });

    it('menerima kode authenticator yang valid', function () {
        $this->mock(TwoFactorAuthenticationProvider::class)
            ->shouldReceive('verify')->with('secret', '123456')->andReturnTrue();

        apiLogin(['code' => '123456'])->assertOk();
    });

    it('menerima recovery code sekali saja', function () {
        apiLogin(['recovery_code' => 'recovery-code-1'])->assertOk();
        apiLogin(['recovery_code' => 'recovery-code-1'])->assertUnprocessable();
    });
});

describe('endpoint yang butuh token', function () {
    it('menolak tanpa token dengan 401', function () {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    });

    it('/auth/me mengembalikan profil user', function () {
        $token = apiLogin()->json('data.access_token');

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'budi@example.com')
            ->assertJsonPath('data.roles', ['staff']);
    });

    it('menolak token yang sudah kedaluwarsa', function () {
        $token = apiLogin()->json('data.access_token');
        PersonalAccessToken::query()->update(['expires_at' => now()->subMinute()]);

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    });
});

describe('logout & device', function () {
    it('logout hanya mencabut token device ini', function () {
        $phone = apiLogin(['device_name' => 'Pixel 8'])->json('data.access_token');
        apiLogin(['device_name' => 'iPad']);

        $this->withToken($phone)->postJson('/api/v1/auth/logout')->assertNoContent();

        expect($this->user->tokens()->pluck('name')->all())->toBe(['iPad']);
    });

    it('logout-all mencabut semua token', function () {
        $phone = apiLogin(['device_name' => 'Pixel 8'])->json('data.access_token');
        apiLogin(['device_name' => 'iPad']);

        $this->withToken($phone)->postJson('/api/v1/auth/logout-all')->assertNoContent();

        expect($this->user->tokens()->count())->toBe(0);
    });

    it('menampilkan daftar device dan menandai device saat ini', function () {
        $phone = apiLogin(['device_name' => 'Pixel 8'])->json('data.access_token');
        apiLogin(['device_name' => 'iPad']);

        $this->withToken($phone)->getJson('/api/v1/auth/devices')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['device_name' => 'Pixel 8', 'is_current' => true])
            ->assertJsonFragment(['device_name' => 'iPad', 'is_current' => false]);
    });

    it('bisa mencabut device lain milik sendiri', function () {
        $phone = apiLogin(['device_name' => 'Pixel 8'])->json('data.access_token');
        apiLogin(['device_name' => 'iPad']);
        $ipadId = $this->user->tokens()->where('name', 'iPad')->value('id');

        $this->withToken($phone)->deleteJson("/api/v1/auth/devices/{$ipadId}")->assertNoContent();

        expect($this->user->tokens()->pluck('name')->all())->toBe(['Pixel 8']);
    });

    it('tidak bisa mencabut token milik user lain (404)', function () {
        $other = User::factory()->create();
        $otherTokenId = $other->createToken('HP lain')->accessToken->getKey();
        $token = apiLogin()->json('data.access_token');

        $this->withToken($token)->deleteJson("/api/v1/auth/devices/{$otherTokenId}")->assertNotFound();

        expect($other->tokens()->count())->toBe(1);
    });
});

describe('pencabutan token otomatis', function () {
    it('mencabut semua token saat password diganti', function () {
        apiLogin();
        $this->user->update(['password' => 'password-baru']);

        expect($this->user->tokens()->count())->toBe(0);
    });

    it('mencabut semua token saat akun dinonaktifkan', function () {
        apiLogin();
        $this->user->update(['is_active' => false]);

        expect($this->user->tokens()->count())->toBe(0);
    });
});
