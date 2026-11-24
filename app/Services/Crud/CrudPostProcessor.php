<?php

namespace App\Services\Crud;

/**
 * Langkah setelah file ditulis / dihapus (migrate, seeder, Wayfinder,
 * formatter). Dipisah dari CrudGenerator agar test bisa memakai versi palsu.
 */
interface CrudPostProcessor
{
    /**
     * Rapikan format file (Pint untuk PHP, Oxfmt untuk TSX).
     *
     * @param  list<string>  $paths  path relatif dari $root
     * @return list<string> peringatan (kosong = semua berhasil)
     */
    public function format(string $root, array $paths): array;

    /**
     * Setelah generate: jalankan migration (null = tabel sudah ada, dilewati),
     * sinkronkan permission, buat ulang Wayfinder.
     *
     * @return list<string> peringatan
     */
    public function afterGenerate(string $root, ?string $migrationPath): array;

    /**
     * Setelah remove: sinkronkan permission, buat ulang Wayfinder.
     *
     * @return list<string> peringatan
     */
    public function afterRemove(string $root): array;
}
