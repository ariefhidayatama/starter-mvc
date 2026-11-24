<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Admin membuat user baru (bukan registrasi mandiri).
 */
class CreateUserAction
{
    /**
     * @param  array{name: string, email: string, password: string, is_active?: bool, roles: list<string>}  $data
     */
    public function execute(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            // Email dianggap sudah terverifikasi karena dibuat oleh admin.
            // forceFill: email_verified_at sengaja tidak ada di $fillable.
            $user->forceFill(['email_verified_at' => now()])->save();

            $user->syncRoles($data['roles']);

            return $user;
        });
    }
}
