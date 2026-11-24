<?php

namespace App\Actions\Role;

use App\Enums\RoleName;
use App\Exceptions\BusinessException;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class UpdateRoleAction
{
    /**
     * @param  array{name: string, permissions?: list<string>}  $data
     */
    public function execute(Role $role, array $data): Role
    {
        if ($role->name === RoleName::Admin->value) {
            throw new BusinessException('The admin role is locked: it always has every permission.');
        }

        if (RoleName::isSystem($role->name) && $data['name'] !== $role->name) {
            throw new BusinessException("The system role \"{$role->name}\" cannot be renamed.");
        }

        return DB::transaction(function () use ($role, $data) {
            $role->update(['name' => $data['name']]);
            $role->syncPermissions($data['permissions'] ?? []);

            return $role;
        });
    }
}
