<?php

use App\Enums\RoleName;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('menampilkan statistik user untuk admin', function () {
    $admin = User::factory()->create()->assignRole(RoleName::Admin);
    User::factory(3)->create();
    User::factory()->create(['is_active' => false]);

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('stats.total_users', 5)
            ->where('stats.active_users', 4)
            ->where('stats.new_this_week', 5)
            ->has('stats.recent_users', 5)
            ->where('account.roles', ['admin']));
});

it('tidak mengirim statistik ke customer', function () {
    $customer = User::factory()->create()->assignRole(RoleName::Customer);

    $this->actingAs($customer)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('stats', null)
            ->where('account.roles', ['customer'])
            ->where('account.two_factor_enabled', false));
});
