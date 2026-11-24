<?php

namespace App\Actions\Role;

use App\Enums\RoleName;
use App\Exceptions\BusinessException;
use App\Models\Role;

/**
 * Beri / cabut beberapa permission sekaligus pada satu role
 * (klik satu sel atau satu modul di halaman Access Control).
 *
 * Berbeda dengan UpdateRoleAction (sync seluruh daftar dari form edit),
 * action ini hanya mengubah permission yang disebut, sehingga dua admin
 * yang mengklik sel berbeda pada saat bersamaan tidak saling menimpa.
 */
class SetRolePermissionsAction
{
    /**
     * @param  list<string>  $permissions
     */
    public function execute(Role $role, array $permissions, bool $granted): Role
    {
        if ($role->name === RoleName::Admin->value) {
            throw new BusinessException('The admin role is locked: it always has every permission.');
        }

        // givePermissionTo/revokePermissionTo juga mereset cache permission spatie.
        if ($granted) {
            $role->givePermissionTo($permissions);
        } else {
            $role->revokePermissionTo($permissions);
        }

        return $role;
    }
}
