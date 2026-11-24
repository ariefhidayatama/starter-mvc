<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Kolom "ulid" = pengenal acak untuk URL admin, sehingga id berurutan tidak
 * tampil di address bar (/admin/products/01jd8q3x7k2m9vz4p6r8t0w2yb/edit).
 * Primary key & foreign key tetap "id" (integer, cepat untuk JOIN).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->ulid()->nullable()->after('id');
        });

        // Isi baris lama. Huruf kecil = sama dengan HasUlids di model.
        DB::table('products')->whereNull('ulid')->select('id')->lazyById(500)
            ->each(fn (object $row) => DB::table('products')
                ->where('id', $row->id)
                ->update(['ulid' => Str::lower((string) Str::ulid())]));

        Schema::table('products', function (Blueprint $table) {
            $table->ulid()->nullable(false)->change();
            $table->unique('ulid');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['ulid']);
            $table->dropColumn('ulid');
        });
    }
};
