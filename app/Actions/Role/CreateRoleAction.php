<?php

namespace App\Actions\Role;

use App\Models\Role;
use Illuminate\Support\Facades\DB;

class CreateRoleAction
{
    /**
     * @param  array{name: string, permissions?: list<string>}  $data
     */
    public function execute(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            /** @var Role $role */
            $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);

            // Fungsi spatie. Cache permission otomatis dikosongkan.
            $role->syncPermissions($data['permissions'] ?? []);

            return $role;
        });
    }
}
