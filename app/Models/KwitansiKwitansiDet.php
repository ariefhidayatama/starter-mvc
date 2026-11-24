<?php

namespace App\Models;

use App\Models\Concerns\HasOptionalPublicUlid;
use Database\Factories\KwitansiKwitansiDetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Dibuat oleh CRUD generator (Kwitansi kwitansi dets). Silakan diubah sesuai kebutuhan.
 *
 * @property int $id
 * @property string|null $ulid pengenal acak untuk URL admin (bila tabel punya kolom ulid)
 * @property string $customer_po
 * @property string|null $si_no
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['customer_po', 'si_no'])]
class KwitansiKwitansiDet extends Model
{
    /** @use HasFactory<KwitansiKwitansiDetFactory> */
    use HasFactory;

    /** URL admin memakai kolom "ulid" bila tabelnya punya, selain itu id. */
    use HasOptionalPublicUlid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }

    /**
     * @return BelongsTo<Kwitansi, $this>
     */
    public function kwitansi(): BelongsTo
    {
        return $this->belongsTo(Kwitansi::class, 'kwitansi_id');
    }
}
