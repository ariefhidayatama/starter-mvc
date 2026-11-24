<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create()->assignRole(RoleName::Admin);
});

it('menolak staff mengelola role', function () {
    $staff = User::factory()->create()->assignRole(RoleName::Staff);

    $this->actingAs($staff)->get('/admin/roles')->assertForbidden();
    $this->actingAs($staff)->get('/admin/permissions')->assertForbidden();
});

it('menampilkan daftar role dengan jumlah user & permission', function () {
    $this->actingAs($this->admin)
        ->get('/admin/roles')
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/roles/index')
            ->has('roles', 3)
            ->where('roles.0.name', 'admin')
            ->where('roles.0.users_count', 1)
            ->where('roles.0.permissions_count', count(PermissionName::cases()))
            ->where('roles.0.is_locked', true));
});

it('membuat role baru beserta permission-nya', function () {
    $this->actingAs($this->admin)
        ->post('/admin/roles', [
            'name' => 'gudang-pusat',
            'permissions' => ['users.view', 'roles.view'],
        ])
        ->assertRedirect('/admin/roles');

    $role = Role::findByName('gudang-pusat', 'web');
    expect($role->permissions->pluck('name')->sort()->values()->all())->toBe(['roles.view', 'users.view']);
});

it('user langsung mendapat izin baru setelah permission role diubah', function () {
    $staff = User::factory()->create()->assignRole(RoleName::Staff);
    expect($staff->can('roles.view'))->toBeFalse();

    $this->actingAs($this->admin)->put('/admin/roles/'.Role::findByName('staff')->ulid, [
        'name' => 'staff',
        'permissions' => ['users.view', 'roles.view'],
    ]);

    expect($staff->fresh()->can('roles.view'))->toBeTrue();
});

it('memvalidasi nama role & permission yang tidak dikenal', function () {
    $this->actingAs($this->admin)
        ->post('/admin/roles', ['name' => 'Nama Ber Spasi', 'permissions' => ['hapus.database']])
        ->assertSessionHasErrors(['name', 'permissions.0']);
});

it('mengunci role admin', function () {
    $adminRole = Role::findByName('admin');

    $this->actingAs($this->admin)->put("/admin/roles/{$adminRole->ulid}", [
        'name' => 'admin',
        'permissions' => [],
    ]);

    expect($adminRole->fresh()->permissions()->count())->toBe(count(PermissionName::cases()));
});

it('menolak mengganti nama role sistem', function () {
    $staffRole = Role::findByName('staff');

    $this->actingAs($this->admin)->put("/admin/roles/{$staffRole->ulid}", [
        'name' => 'karyawan',
        'permissions' => [],
    ]);

    expect($staffRole->fresh()->name)->toBe('staff');
});

it('menolak menghapus role sistem', function () {
    $this->actingAs($this->admin)->delete('/admin/roles/'.Role::findByName('customer')->ulid);

    expect(Role::where('name', 'customer')->exists())->toBeTrue();
});

it('menolak menghapus role yang masih dipakai user', function () {
    $role = Role::create(['name' => 'kasir', 'guard_name' => 'web']);
    User::factory()->create()->assignRole($role);

    $this->actingAs($this->admin)->delete("/admin/roles/{$role->ulid}");

    expect(Role::where('name', 'kasir')->exists())->toBeTrue();
});

it('menghapus role yang tidak dipakai', function () {
    $role = Role::create(['name' => 'kasir', 'guard_name' => 'web']);

    $this->actingAs($this->admin)->delete("/admin/roles/{$role->ulid}")->assertRedirect('/admin/roles');

    expect(Role::where('name', 'kasir')->exists())->toBeFalse();
});

it('menampilkan matriks access control: permission per modul & role sebagai kolom', function () {
    $this->actingAs($this->admin)
        ->get('/admin/permissions')
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/permissions/index')
            ->where('permissionGroups.0.module', 'categories')
            ->where('permissionGroups.0.permissions.0.name', 'categories.view')
            // Kolom: admin dulu, lalu role sistem lain.
            ->where('roles.0.name', 'admin')
            ->where('roles.0.is_admin', true)
            ->where('roles.0.users_count', 1)
            ->where('roles.2.name', 'staff')
            ->where('roles.2.permissions', fn ($p) => collect($p)->contains('categories.view')
                && ! collect($p)->contains('categories.delete')));
});

it('mencentang sel matriks memberi / mencabut permission role', function () {
    $staff = Role::findByName(RoleName::Staff->value);

    $this->actingAs($this->admin)
        ->patch("/admin/roles/{$staff->ulid}/permissions", ['permissions' => ['users.view', 'users.create'], 'granted' => true])
        ->assertRedirect();

    expect($staff->fresh()->hasPermissionTo('users.view'))->toBeTrue()
        ->and($staff->fresh()->hasPermissionTo('users.create'))->toBeTrue();

    $this->actingAs($this->admin)
        ->patch("/admin/roles/{$staff->ulid}/permissions", ['permissions' => ['users.create'], 'granted' => false])
        ->assertRedirect();

    // Hanya yang disebut yang dicabut; izin lain tetap.
    expect($staff->fresh()->hasPermissionTo('users.create'))->toBeFalse()
        ->and($staff->fresh()->hasPermissionTo('users.view'))->toBeTrue()
        ->and($staff->fresh()->hasPermissionTo('products.view'))->toBeTrue();
});

it('matriks: admin terkunci, permission asing ditolak, staff tanpa izin dilarang', function () {
    $adminRole = Role::findByName(RoleName::Admin->value);
    $staffRole = Role::findByName(RoleName::Staff->value);

    $this->actingAs($this->admin)
        ->from('/admin/permissions')
        ->patch("/admin/roles/{$adminRole->ulid}/permissions", ['permissions' => ['users.view'], 'granted' => false])
        ->assertRedirect('/admin/permissions');
    expect($adminRole->fresh()->hasPermissionTo('users.view'))->toBeTrue();

    $this->actingAs($this->admin)
        ->patch("/admin/roles/{$staffRole->ulid}/permissions", ['permissions' => ['nuke.everything'], 'granted' => true])
        ->assertSessionHasErrors('permissions.0');

    $staffUser = User::factory()->create()->assignRole(RoleName::Staff);
    $this->actingAs($staffUser)
        ->patch("/admin/roles/{$staffRole->ulid}/permissions", ['permissions' => ['users.view'], 'granted' => true])
        ->assertForbidden();
});

it('membagikan roles & permissions ke React lewat shared props', function () {
    $staff = User::factory()->create()->assignRole(RoleName::Staff);

    $this->actingAs($staff)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.roles', ['staff'])
            ->where('auth.permissions', [
                'api.access',
                'categories.view', 'categories.create', 'categories.update',
                'products.view', 'products.create', 'products.update',
                'users.view',
            ])
            ->missing('auth.user.roles'));
});

it('memberi role customer pada user yang mendaftar sendiri', function () {
    $this->post('/register', [
        'name' => 'Pelanggan Baru',
        'email' => 'pelanggan@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(User::firstWhere('email', 'pelanggan@example.com')->hasRole(RoleName::Customer))->toBeTrue();
});

it('menolak login web untuk akun nonaktif', function () {
    $user = User::factory()->create(['is_active' => false]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});
