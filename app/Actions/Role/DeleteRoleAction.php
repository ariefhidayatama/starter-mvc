<?php

namespace App\Actions\Role;

use App\Enums\RoleName;
use App\Exceptions\BusinessException;
use App\Models\Role;

class DeleteRoleAction
{
    public function execute(Role $role): void
    {
        if (RoleName::isSystem($role->name)) {
            throw new BusinessException("The system role \"{$role->name}\" cannot be deleted.");
        }

        $usersCount = $role->users()->count();

        if ($usersCount > 0) {
            throw new BusinessException("This role is still assigned to {$usersCount} user(s). Move them to another role first.");
        }

        $role->delete();
    }
}
