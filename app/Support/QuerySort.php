<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Menerapkan parameter ?sort= ke query dengan aman.
 *
 * Format: "price" (naik/ASC) atau "-price" (turun/DESC).
 *
 * Nama kolom TIDAK pernah diambil langsung dari input user: setiap kunci
 * sort dipetakan ke kolom lewat whitelist $allowed. Kunci di luar
 * whitelist sudah ditolak validasi Form Request, dan di sini diabaikan
 * sebagai lapisan pengaman kedua.
 */
class QuerySort
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, string>  $allowed  kunci sort → kolom, mis. ['price' => 'products.price']
     * @param  string  $default  sort bawaan, mis. '-created_at'
     * @return Builder<TModel>
     */
    public static function apply(Builder $query, ?string $sort, array $allowed, string $default, string $tieBreaker = 'id'): Builder
    {
        $sort = filled($sort) ? $sort : $default;
        $direction = str_starts_with((string) $sort, '-') ? 'desc' : 'asc';
        $key = ltrim((string) $sort, '-');

        if (! isset($allowed[$key])) {
            $direction = str_starts_with($default, '-') ? 'desc' : 'asc';
            $key = ltrim($default, '-');
        }

        $query->orderBy($allowed[$key], $direction);

        // Kolom unik sebagai penentu urutan terakhir: tanpa ini, data dengan
        // nilai sama (mis. harga sama) bisa muncul di 2 halaman berbeda,
        // dan cursor pagination tidak bisa bekerja.
        $query->orderBy($query->getModel()->qualifyColumn($tieBreaker), $direction);

        return $query;
    }

    /**
     * Daftar nilai ?sort= yang valid untuk Rule::in(), mis.
     * ['name', '-name', 'price', '-price'].
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    public static function options(array $keys): array
    {
        return array_merge(...array_map(fn (string $k) => [$k, "-{$k}"], $keys));
    }
}
