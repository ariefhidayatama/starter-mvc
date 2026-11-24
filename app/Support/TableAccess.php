<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Apakah AKUN DATABASE aplikasi boleh SELECT / INSERT / UPDATE / DELETE
 * pada sebuah tabel?
 *
 * Ini lapisan kedua setelah permission aplikasi (Policy). Contoh: akun SQL
 * Server ERP hanya diberi SELECT → tombol tambah/ubah/hapus disembunyikan
 * dan server menjawab 403, bukan error SQL.
 *
 * Hanya SQL Server yang diperiksa (fn_my_permissions). Koneksi lain dianggap
 * boleh semua. Hasil di-cache 10 menit: setelah DBA mengubah izin, tunggu
 * sebentar atau jalankan `php artisan cache:clear`.
 */
final class TableAccess
{
    /**
     * @param  'SELECT'|'INSERT'|'UPDATE'|'DELETE'  $action
     */
    public static function allows(?string $connection, string $table, string $action): bool
    {
        return in_array($action, self::permissions($connection, $table), true);
    }

    /**
     * @return list<string>
     */
    public static function permissions(?string $connection, string $table): array
    {
        $all = ['SELECT', 'INSERT', 'UPDATE', 'DELETE'];
        $db = DB::connection($connection);

        if ($db->getDriverName() !== 'sqlsrv') {
            return $all;
        }

        $key = 'table-access:'.$db->getName().':'.$table;

        /** @var list<string> */
        return Cache::remember($key, now()->addMinutes(10), function () use ($db, $table, $all): array {
            try {
                $rows = $db->select("SELECT permission_name FROM fn_my_permissions(?, 'OBJECT') WHERE subentity_name = ''", [$table]);
            } catch (Throwable) {
                return [];
            }

            $granted = array_map(fn (object $r) => strtoupper((string) ($r->permission_name ?? '')), $rows);

            return array_values(array_intersect($all, $granted));
        });
    }
}
