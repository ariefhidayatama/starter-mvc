<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Message;
use App\Models\User;
use App\Support\TableAccess;

/**
 * Setiap aksi = satu permission (messages.view / create / update / delete).
 * Atur siapa yang boleh di halaman Permissions. Dibuat oleh CRUD generator.
 *
 * Tabel ada di koneksi "wmsreports": aksi juga butuh izin akun database
 * (lihat App\Support\TableAccess), mis. INSERT untuk create.
 */
class MessagePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(PermissionName::MessagesView)
            && TableAccess::allows('wmsreports', 'msg', 'SELECT');
    }

    public function view(User $actor, Message $message): bool
    {
        return $actor->can(PermissionName::MessagesView)
            && TableAccess::allows('wmsreports', 'msg', 'SELECT');
    }

    public function create(User $actor): bool
    {
        return $actor->can(PermissionName::MessagesCreate)
            && TableAccess::allows('wmsreports', 'msg', 'INSERT');
    }

    public function update(User $actor, Message $message): bool
    {
        return $actor->can(PermissionName::MessagesUpdate)
            && TableAccess::allows('wmsreports', 'msg', 'UPDATE');
    }

    public function delete(User $actor, Message $message): bool
    {
        return $actor->can(PermissionName::MessagesDelete)
            && TableAccess::allows('wmsreports', 'msg', 'DELETE');
    }
}
