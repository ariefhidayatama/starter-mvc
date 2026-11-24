<?php

namespace App\Models;

use App\Contracts\HasAuditUser;
use App\Observers\FillAuditUser;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Dibuat oleh CRUD generator (Messages). Silakan diubah sesuai kebutuhan.
 *
 * @property int $id
 * @property string $whs_code
 * @property string $owner_code
 * @property string $msg_group
 * @property string $msg_description
 * @property string|null $photo1
 * @property string|null $photo2
 * @property string|null $photo3
 * @property string|null $photo4
 * @property string|null $photo5
 * @property string|null $document1
 * @property string|null $document2
 * @property string|null $document3
 * @property Carbon|null $created_date
 * @property Carbon|null $updated_date
 */
#[Fillable(['whs_code', 'owner_code', 'msg_group', 'msg_description', 'photo1', 'photo2', 'photo3', 'photo4', 'photo5', 'document1', 'document2', 'document3'])]
#[ObservedBy([FillAuditUser::class])]
class Message extends Model implements HasAuditUser
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    /** Database lain (config/database.php), bukan koneksi default. */
    protected $connection = 'wmsreports';

    /** Tabel yang sudah ada (bukan nama bawaan Laravel). */
    protected $table = 'msg';

    /** Nama kolom waktu di tabel ini (Laravel mengisinya otomatis). */
    const CREATED_AT = 'created_date';

    const UPDATED_AT = 'updated_date';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }

    /**
     * Kolom user audit, diisi otomatis dengan NAMA user yang login
     * (App\Observers\FillAuditUser). Tidak tampil di form.
     *
     * @return array{created_by: string|null, updated_by: string|null, max: int}
     */
    public function auditUserColumns(): array
    {
        return ['created_by' => 'created_by', 'updated_by' => 'updated_by', 'max' => 30];
    }

    /**
     * Baris detail (msg_det.msg_id → msg.id).
     *
     * @return HasMany<MessageDetail, $this>
     */
    public function details(): HasMany
    {
        return $this->hasMany(MessageDetail::class, 'msg_id');
    }
}
