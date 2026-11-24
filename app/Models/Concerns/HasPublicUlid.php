<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * Kolom "ulid" = pengenal acak untuk URL admin, mis.
 * /admin/users/01jd8q3x7k2m9vz4p6r8t0w2yb/edit (bukan /admin/users/28/edit).
 *
 * - Diisi otomatis (huruf kecil) saat baris dibuat lewat Eloquent.
 * - Primary key & foreign key TETAP "id" (integer, cepat untuk JOIN).
 * - Route memakai {model:ulid}, mis. ->scoped(['user' => 'ulid']).
 *   ULID yang formatnya salah langsung 404 tanpa query ke database.
 *
 * Tabelnya perlu kolom: $table->ulid()->unique().
 *
 * @property string $ulid
 */
trait HasPublicUlid
{
    use HasUlids;

    /**
     * Kolom yang diisi ULID otomatis (bukan primary key).
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }
}
