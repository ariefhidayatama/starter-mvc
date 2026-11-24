<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Support\Facades\Schema;

/**
 * ULID untuk URL admin, OTOMATIS mengikuti tabelnya:
 *
 * - Tabel punya kolom "ulid" → URL /admin/kwitansis/01jd8q3x.../edit, dan
 *   ULID diisi otomatis (huruf kecil) saat baris dibuat lewat Eloquent.
 * - Tidak punya → URL tetap memakai id, mis. /admin/kwitansis/5/edit.
 *
 * Dipakai model hasil CRUD generator, jadi kolom "ulid" bisa ditambahkan
 * belakangan (php artisan make:ulid-migration <tabel>) tanpa mengubah kode
 * modul. Primary key & foreign key TETAP "id".
 *
 * Modul yang PASTI punya kolom ulid (Products, Users, ...) memakai
 * HasPublicUlid + ->scoped([... => 'ulid']) di route.
 *
 * @property string|null $ulid
 */
trait HasOptionalPublicUlid
{
    use HasUlids;

    /**
     * Hasil cek kolom per koneksi+tabel, sekali per proses PHP.
     *
     * @var array<string, bool>
     */
    private static array $publicUlidColumns = [];

    public function hasPublicUlid(): bool
    {
        $key = ($this->getConnectionName() ?? '').'|'.$this->getTable();

        return self::$publicUlidColumns[$key] ??= Schema::connection($this->getConnectionName())
            ->hasColumn($this->getTable(), 'ulid');
    }

    /** Lupakan hasil cek kolom (mis. di test setelah migration menambah kolom). */
    public static function forgetPublicUlidColumns(): void
    {
        self::$publicUlidColumns = [];
    }

    /**
     * Kolom yang diisi ULID otomatis (bukan primary key).
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return $this->hasPublicUlid() ? ['ulid'] : [];
    }

    /** Route model binding & route('...', $model): ulid bila ada, selain itu id. */
    public function getRouteKeyName(): string
    {
        return $this->hasPublicUlid() ? 'ulid' : $this->getKeyName();
    }
}
