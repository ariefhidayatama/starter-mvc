<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Kolom "ulid" untuk URL admin kwitansis: /admin/.../5/edit → /admin/.../01jd8q3x.../edit.
 * Dibuat oleh: php artisan make:ulid-migration kwitansis
 *
 * Aman dijalankan ulang: dilewati bila tabelnya tidak ada atau kolomnya sudah ada
 * (mis. tabel sudah di-drop karena modulnya dihapus).
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection(null);

        if (! $schema->hasTable('kwitansis') || $schema->hasColumn('kwitansis', 'ulid')) {
            return;
        }

        $schema->table('kwitansis', function (Blueprint $table) {
            $table->ulid()->nullable()->after('id');
        });

        // Isi baris lama. Huruf kecil = sama dengan HasUlids di model.
        DB::connection()->table('kwitansis')->whereNull('ulid')->select('id')->lazyById(500)
            ->each(fn (object $row) => DB::connection()->table('kwitansis')
                ->where('id', $row->id)
                ->update(['ulid' => Str::lower((string) Str::ulid())]));

        $schema->table('kwitansis', function (Blueprint $table) {
            $table->ulid()->nullable(false)->change();
            $table->unique('ulid');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection(null);

        if (! $schema->hasTable('kwitansis') || ! $schema->hasColumn('kwitansis', 'ulid')) {
            return;
        }

        $schema->table('kwitansis', function (Blueprint $table) {
            $table->dropUnique(['ulid']);
            $table->dropColumn('ulid');
        });
    }
};
