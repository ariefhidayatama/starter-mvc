<?php

namespace App\Models;

use App\Models\Concerns\HasOptionalPublicUlid;
use Database\Factories\KwitansiFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Dibuat oleh CRUD generator (Kwitansis). Silakan diubah sesuai kebutuhan.
 *
 * @property int $id
 * @property string|null $ulid pengenal acak untuk URL admin (bila tabel punya kolom ulid)
 * @property string $kwitansi_no
 * @property string $customer
 * @property string $ship_to
 * @property string $ttf_no
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['kwitansi_no', 'customer', 'ship_to', 'ttf_no'])]
class Kwitansi extends Model
{
    /** @use HasFactory<KwitansiFactory> */
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
     * Baris kwitansi det (kwitansi_kwitansi_dets.kwitansi_id → kwitansis.id).
     *
     * @return HasMany<KwitansiKwitansiDet, $this>
     */
    public function kwitansiDets(): HasMany
    {
        return $this->hasMany(KwitansiKwitansiDet::class, 'kwitansi_id');
    }
}
