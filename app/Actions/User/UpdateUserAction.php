<?php

namespace App\Actions\User;

use App\Enums\RoleName;
use App\Exceptions\BusinessException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Admin mengubah data, password, status & role user.
 */
class UpdateUserAction
{
    use EnsuresAdminRemains;

    /**
     * @param  array{name: string, email: string, password?: string|null, is_active: bool, roles: list<string>}  $data
     */
    public function execute(User $actor, User $target, array $data): User
    {
        $this->guardAgainstLockout($actor, $target, $data);

        return DB::transaction(function () use ($target, $data) {
            $target->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'is_active' => $data['is_active'],
            ]);

            if (filled($data['password'] ?? null)) {
                $target->password = $data['password'];
            }

            if ($target->isDirty('email')) {
                $target->email_verified_at = null;
            }

            // Observer (UserObserver) otomatis mencabut token bila
            // password berubah atau akun dinonaktifkan.
            $target->save();

            $target->syncRoles($data['roles']);

            return $target;
        });
    }

    /**
     * @param  array{is_active: bool, roles: list<string>}  $data
     */
    private function guardAgainstLockout(User $actor, User $target, array $data): void
    {
        $keepsAdmin = in_array(RoleName::Admin->value, $data['roles'], true);

        if ($actor->is($target)) {
            if (! $data['is_active']) {
                throw new BusinessException('You cannot deactivate your own account.');
            }

            if ($target->hasRole(RoleName::Admin) && ! $keepsAdmin) {
                throw new BusinessException('You cannot remove the admin role from your own account.');
            }
        }

        if (! $keepsAdmin || ! $data['is_active']) {
            $this->ensureNotLastActiveAdmin($target, 'This user is the only active admin.');
        }
    }
}
