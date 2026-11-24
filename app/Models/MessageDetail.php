<?php

namespace App\Models;

use App\Contracts\HasAuditUser;
use App\Observers\FillAuditUser;
use Database\Factories\MessageDetailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Dibuat oleh CRUD generator (Message details). Silakan diubah sesuai kebutuhan.
 *
 * @property int $id
 * @property string $trans_code
 * @property string $trans_no
 * @property string $ref_no
 * @property string $po_no
 * @property string|null $prod_code
 * @property string|null $loc_code
 * @property string|null $pallet_no
 * @property string|null $lot_no
 * @property string|null $inv_status
 * @property int|null $qty
 * @property Carbon|null $created_date
 * @property Carbon|null $updated_date
 */
#[Fillable(['trans_code', 'trans_no', 'ref_no', 'po_no', 'prod_code', 'loc_code', 'pallet_no', 'lot_no', 'inv_status', 'qty'])]
#[ObservedBy([FillAuditUser::class])]
class MessageDetail extends Model implements HasAuditUser
{
    /** @use HasFactory<MessageDetailFactory> */
    use HasFactory;

    /** Database lain (config/database.php), bukan koneksi default. */
    protected $connection = 'wmsreports';

    /** Tabel yang sudah ada (bukan nama bawaan Laravel). */
    protected $table = 'msg_det';

    /** Nama kolom waktu di tabel ini (Laravel mengisinya otomatis). */
    const CREATED_AT = 'created_date';

    const UPDATED_AT = 'updated_date';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty' => 'integer',
        ];
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
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'msg_id');
    }
}
