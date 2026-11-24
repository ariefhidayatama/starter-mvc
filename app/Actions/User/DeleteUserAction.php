<?php

namespace App\Actions\User;

use App\Exceptions\BusinessException;
use App\Models\User;

class DeleteUserAction
{
    use EnsuresAdminRemains;

    public function execute(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw new BusinessException('You cannot delete your own account.');
        }

        $this->ensureNotLastActiveAdmin($target, 'The last active admin cannot be deleted.');

        // Token & session dibersihkan oleh UserObserver::deleting().
        // Relasi role (model_has_roles) dibersihkan otomatis oleh spatie.
        $target->delete();
    }
}
