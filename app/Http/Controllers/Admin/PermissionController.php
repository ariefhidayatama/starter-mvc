<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Role\SetRolePermissionsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SetRolePermissionsRequest;
use App\Models\Role;
use App\Services\Access\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Permissions: matriks Access Control (permission × role).
 *
 * Daftar permission tetap berasal dari kode (App\Enums\PermissionName),
 * karena hanya izin yang diperiksa kode yang punya efek. Yang diatur di
 * sini adalah ROLE MANA yang memiliki izin tersebut.
 */
class PermissionController extends Controller
{
    public function __construct(private readonly AccessService $access) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', Role::class);

        return Inertia::render('admin/permissions/index', [
            'permissionGroups' => $this->access->permissionGroups(),
            'roles' => $this->access->roles(),
            // Menu buatan sendiri yang diatur per role (bagian kedua halaman).
            'menuVisibility' => $this->access->menuVisibility(),
        ]);
    }

    /** Klik sel/modul di matriks: beri atau cabut izin untuk satu role. */
    public function update(SetRolePermissionsRequest $request, Role $role, SetRolePermissionsAction $setPermissions): RedirectResponse
    {
        /** @var array{permissions: list<string>, granted: bool|string} $data */
        $data = $request->validated();

        $setPermissions->execute($role, $data['permissions'], (bool) $data['granted']);

        return back();
    }
}
