<?php

namespace App\Http\Controllers\Admin;

use App\Actions\User\CreateUserAction;
use App\Actions\User\DeleteUserAction;
use App\Actions\User\UpdateUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexUserRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\User\UserQueryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Menu Admin → Users (Web / Inertia).
 */
class UserController extends Controller
{
    public function index(IndexUserRequest $request, UserQueryService $query): Response
    {
        /** @var array{search?: string|null, role?: string|null, status?: string|null, sort?: string|null, per_page?: int|string|null} $filters */
        $filters = $request->validated();

        $users = $query->paginate($filters)->through(fn (User $user) => [
            'id' => $user->id,
            'ulid' => $user->ulid,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->email_verified_at !== null,
            'is_active' => $user->is_active,
            'roles' => $user->roles->pluck('name'),
            'created_at' => $user->created_at?->toIso8601String(),
        ]);

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'role' => $filters['role'] ?? '',
                'status' => $filters['status'] ?? '',
                'sort' => $filters['sort'] ?? '-created_at',
                'per_page' => (int) ($filters['per_page'] ?? 10),
            ],
            'roleOptions' => $this->roleOptions(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('admin/users/create', [
            'roleOptions' => $this->roleOptions(),
        ]);
    }

    public function store(StoreUserRequest $request, CreateUserAction $createUser): RedirectResponse
    {
        /** @var array{name: string, email: string, password: string, is_active?: bool, roles: list<string>} $data */
        $data = $request->validated();
        $user = $createUser->execute($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => "User {$user->name} created."]);

        return to_route('admin.users.index');
    }

    public function edit(Request $request, User $user): Response
    {
        Gate::authorize('update', $user);

        $user->load('roles:id,name');

        return Inertia::render('admin/users/edit', [
            'user' => [
                'id' => $user->id,
                'ulid' => $user->ulid,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'roles' => $user->roles->pluck('name'),
                'is_self' => $request->user()?->is($user) ?? false,
                'active_devices' => $user->tokens()->count(),
            ],
            'roleOptions' => $this->roleOptions(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUserAction $updateUser): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        /** @var array{name: string, email: string, password?: string|null, is_active: bool, roles: list<string>} $data */
        $data = $request->validated();
        $updateUser->execute($actor, $user, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => "User {$user->name} updated."]);

        return to_route('admin.users.index');
    }

    public function destroy(Request $request, User $user, DeleteUserAction $deleteUser): RedirectResponse
    {
        Gate::authorize('delete', $user);

        /** @var User $actor */
        $actor = $request->user();
        $deleteUser->execute($actor, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "User {$user->name} deleted."]);

        return to_route('admin.users.index');
    }

    /**
     * Logout paksa user dari semua device (token API + session web).
     */
    public function logoutDevices(User $user, TokenService $tokens): RedirectResponse
    {
        Gate::authorize('update', $user);

        $tokens->logoutEverywhere($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->name} has been logged out from all devices."]);

        return back();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function roleOptions(): array
    {
        return array_values(Role::query()
            ->orderBy('name')
            ->pluck('name')
            ->map(fn (string $name) => ['value' => $name, 'label' => ucfirst($name)])
            ->all());
    }
}
