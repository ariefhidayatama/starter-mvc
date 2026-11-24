<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Role\CreateRoleAction;
use App\Actions\Role\DeleteRoleAction;
use App\Actions\Role\UpdateRoleAction;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveRoleRequest;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Menu Admin → Roles (Web / Inertia).
 */
class RoleController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Role::class);

        $roles = Role::query()
            ->select(['id', 'ulid', 'name'])
            // withCount: jumlah dihitung di SQL (COUNT), bukan memuat semua user ke memori.
            ->withCount(['users', 'permissions'])
            ->orderBy('id')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'ulid' => $role->ulid,
                'name' => $role->name,
                'users_count' => $role->getAttribute('users_count'),
                'permissions_count' => $role->getAttribute('permissions_count'),
                'is_system' => RoleName::isSystem($role->name),
                'is_locked' => $role->name === RoleName::Admin->value,
            ]);

        return Inertia::render('admin/roles/index', [
            'roles' => $roles,
            'totalPermissions' => count(PermissionName::cases()),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Role::class);

        return Inertia::render('admin/roles/create', [
            'permissionGroups' => PermissionName::grouped(),
        ]);
    }

    public function store(SaveRoleRequest $request, CreateRoleAction $createRole): RedirectResponse
    {
        /** @var array{name: string, permissions?: list<string>} $data */
        $data = $request->validated();
        $role = $createRole->execute($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Role {$role->name} created."]);

        return to_route('admin.roles.index');
    }

    public function edit(Role $role): Response
    {
        Gate::authorize('update', $role);

        return Inertia::render('admin/roles/edit', [
            'role' => [
                'id' => $role->id,
                'ulid' => $role->ulid,
                'name' => $role->name,
                'permissions' => $role->permissions()->pluck('name'),
                'is_system' => RoleName::isSystem($role->name),
                'is_locked' => $role->name === RoleName::Admin->value,
            ],
            'permissionGroups' => PermissionName::grouped(),
        ]);
    }

    public function update(SaveRoleRequest $request, Role $role, UpdateRoleAction $updateRole): RedirectResponse
    {
        /** @var array{name: string, permissions?: list<string>} $data */
        $data = $request->validated();
        $updateRole->execute($role, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Role {$role->name} updated."]);

        return to_route('admin.roles.index');
    }

    public function destroy(Role $role, DeleteRoleAction $deleteRole): RedirectResponse
    {
        Gate::authorize('delete', $role);

        $deleteRole->execute($role);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Role {$role->name} deleted."]);

        return to_route('admin.roles.index');
    }
}
