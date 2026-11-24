<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Role spatie + kolom "ulid" untuk URL admin (/admin/roles/{ulid}/edit).
 * Didaftarkan di config/permission.php (models.role), jadi spatie
 * (assignRole, hasRole, ...) juga memakai kelas ini.
 *
 * Selalu import App\Models\Role, BUKAN Spatie\Permission\Models\Role:
 * Role::create() lewat kelas spatie tidak mengisi ulid.
 */
class Role extends SpatieRole
{
    use HasPublicUlid;
}
