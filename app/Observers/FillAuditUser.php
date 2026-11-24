<?php

namespace App\Observers;

use App\Contracts\HasAuditUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Mengisi kolom "siapa yang membuat / mengubah" secara otomatis dengan
 * NAMA user yang login (mis. created_by & updated_by di tabel ERP).
 *
 * Dipasang di model dengan atribut:
 *
 *   #[ObservedBy([FillAuditUser::class])]
 *   class Message extends Model implements HasAuditUser { ... }
 *
 * Model menentukan nama kolom & panjang maksimalnya lewat auditUserColumns().
 * Tanpa user login (mis. perintah artisan / queue) diisi "system".
 * Waktunya (created_date / updated_date) diurus Laravel lewat konstanta
 * CREATED_AT / UPDATED_AT di model.
 */
class FillAuditUser
{
    public function creating(Model $model): void
    {
        $this->fill($model, creating: true);
    }

    public function updating(Model $model): void
    {
        $this->fill($model, creating: false);
    }

    private function fill(Model $model, bool $creating): void
    {
        if (! $model instanceof HasAuditUser) {
            return;
        }

        $columns = $model->auditUserColumns();
        $user = auth()->user();
        $name = mb_substr($user instanceof User ? $user->name : 'system', 0, $columns['max']);

        if ($creating && $columns['created_by'] !== null) {
            $model->setAttribute($columns['created_by'], $name);
        }

        if ($columns['updated_by'] !== null) {
            $model->setAttribute($columns['updated_by'], $name);
        }
    }
}
