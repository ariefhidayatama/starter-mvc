<?php

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function userWithRole(RoleName $role, array $attributes = []): User
{
    return User::factory()->create($attributes)->assignRole($role);
}

beforeEach(function () {
    $this->admin = userWithRole(RoleName::Admin);
});

describe('otorisasi', function () {
    it('menolak customer membuka daftar user (403)', function () {
        $this->actingAs(userWithRole(RoleName::Customer))
            ->get('/admin/users')
            ->assertForbidden();
    });

    it('mengizinkan staff melihat daftar user (punya users.view)', function () {
        $this->actingAs(userWithRole(RoleName::Staff))
            ->get('/admin/users')
            ->assertOk();
    });

    it('menolak staff membuat user (tidak punya users.create)', function () {
        $this->actingAs(userWithRole(RoleName::Staff))
            ->post('/admin/users', [])
            ->assertForbidden();
    });

    it('mengarahkan tamu ke halaman login', function () {
        $this->get('/admin/users')->assertRedirect('/login');
    });
});

describe('daftar user', function () {
    it('menampilkan user beserta role & mendukung filter', function () {
        userWithRole(RoleName::Customer, ['name' => 'Budi Santoso']);
        userWithRole(RoleName::Customer, ['name' => 'Siti Aminah', 'is_active' => false]);

        $this->actingAs($this->admin)
            ->get('/admin/users?search=Budi')
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/index')
                ->has('users.data', 1)
                ->where('users.data.0.name', 'Budi Santoso')
                ->where('users.data.0.roles', ['customer'])
                ->where('filters.search', 'Budi'));

        $this->actingAs($this->admin)
            ->get('/admin/users?status=inactive')
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.name', 'Siti Aminah'));
    });

    it('mendukung pilihan jumlah baris per halaman (10/25/50/100)', function () {
        User::factory(30)->create();

        $this->actingAs($this->admin)
            ->get('/admin/users?per_page=25')
            ->assertInertia(fn (Assert $page) => $page
                ->where('users.per_page', 25)
                ->has('users.data', 25)
                ->where('filters.per_page', 25));

        $this->actingAs($this->admin)
            ->get('/admin/users?per_page=7')
            ->assertSessionHasErrors('per_page');
    });

    it('menolak filter di luar whitelist', function () {
        $this->actingAs($this->admin)
            ->get('/admin/users?status=hapus-semua')
            ->assertSessionHasErrors('status');
    });

    it('tidak terkena N+1 query saat jumlah user bertambah', function () {
        User::factory(30)->create()->each->assignRole(RoleName::Customer);

        DB::enableQueryLog();
        $this->actingAs($this->admin)->get('/admin/users?per_page=25')->assertOk();
        $queries = count(DB::getQueryLog());

        // Jumlah query tetap kecil & konstan, bukan 25+ (1 per user).
        expect($queries)->toBeLessThan(15);
    });
});

describe('tambah, ubah, hapus', function () {
    it('admin bisa membuat user dengan role', function () {
        $this->actingAs($this->admin)
            ->post('/admin/users', [
                'name' => 'Kasir Baru',
                'email' => 'kasir@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'is_active' => '1',
                'roles' => ['staff'],
            ])
            ->assertRedirect('/admin/users');

        $user = User::firstWhere('email', 'kasir@example.com');
        expect($user->hasRole(RoleName::Staff))->toBeTrue()
            ->and($user->email_verified_at)->not->toBeNull();
    });

    it('memvalidasi input', function () {
        $this->actingAs($this->admin)
            ->post('/admin/users', ['email' => 'bukan-email', 'roles' => []])
            ->assertSessionHasErrors(['name', 'email', 'password', 'roles']);
    });

    it('admin bisa mengubah role & menonaktifkan user', function () {
        $target = userWithRole(RoleName::Customer);
        $target->createToken('HP');

        $this->actingAs($this->admin)
            ->put("/admin/users/{$target->ulid}", [
                'name' => $target->name,
                'email' => $target->email,
                'is_active' => '0',
                'roles' => ['staff'],
            ])
            ->assertRedirect('/admin/users');

        $target->refresh();
        expect($target->is_active)->toBeFalse()
            ->and($target->getRoleNames()->all())->toBe(['staff'])
            ->and($target->tokens()->count())->toBe(0); // dicabut oleh observer
    });

    it('admin bisa menghapus user lain', function () {
        $target = userWithRole(RoleName::Customer);

        $this->actingAs($this->admin)
            ->delete("/admin/users/{$target->ulid}")
            ->assertRedirect('/admin/users');

        expect(User::find($target->id))->toBeNull();
    });

    it('bisa logout paksa user dari semua device', function () {
        $target = userWithRole(RoleName::Customer);
        $target->createToken('HP');
        $target->createToken('Tablet');

        $this->actingAs($this->admin)
            ->post("/admin/users/{$target->ulid}/logout-devices")
            ->assertRedirect();

        expect($target->tokens()->count())->toBe(0);
    });
});

describe('aturan pengaman', function () {
    it('admin tidak bisa menghapus akunnya sendiri', function () {
        userWithRole(RoleName::Admin); // admin lain ada

        $this->actingAs($this->admin)
            ->from('/admin/users')
            ->delete("/admin/users/{$this->admin->ulid}")
            ->assertRedirect('/admin/users');

        expect(User::find($this->admin->id))->not->toBeNull();
    });

    it('admin tidak bisa menonaktifkan akunnya sendiri', function () {
        $this->actingAs($this->admin)
            ->put("/admin/users/{$this->admin->ulid}", [
                'name' => $this->admin->name,
                'email' => $this->admin->email,
                'is_active' => '0',
                'roles' => ['admin'],
            ]);

        expect($this->admin->fresh()->is_active)->toBeTrue();
    });

    it('admin aktif terakhir tidak bisa kehilangan role admin', function () {
        $staffWithUpdate = userWithRole(RoleName::Staff);
        $staffWithUpdate->givePermissionTo('users.update');

        $this->actingAs($staffWithUpdate)
            ->put("/admin/users/{$this->admin->ulid}", [
                'name' => $this->admin->name,
                'email' => $this->admin->email,
                'is_active' => '1',
                'roles' => ['staff'],
            ]);

        expect($this->admin->fresh()->hasRole(RoleName::Admin))->toBeTrue();
    });

    it('menjawab 409 JSON untuk pelanggaran aturan bila diminta lewat JSON', function () {
        $this->actingAs($this->admin)
            ->deleteJson("/admin/users/{$this->admin->ulid}")
            ->assertStatus(409)
            ->assertJsonPath('code', 'CONFLICT');
    });
});
