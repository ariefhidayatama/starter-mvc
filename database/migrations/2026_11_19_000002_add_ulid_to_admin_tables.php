<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Kolom "ulid" untuk URL admin yang tidak memperlihatkan id berurutan:
 *   /admin/users/28/edit → /admin/users/01jd8q3x7k2m9vz4p6r8t0w2yb/edit
 * Sama seperti products (2026_11_19_000001). Primary key & foreign key tetap "id".
 */
return new class extends Migration
{
    /** @return list<string> */
    private function tables(): array
    {
        /** @var string $roles */
        $roles = config('permission.table_names.roles', 'roles');

        return ['users', $roles, 'menus', 'categories'];
    }

    public function up(): void
    {
        foreach ($this->tables() as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->ulid()->nullable()->after('id');
            });

            // Isi baris lama. Huruf kecil = sama dengan HasUlids di model.
            DB::table($name)->whereNull('ulid')->select('id')->lazyById(500)
                ->each(fn (object $row) => DB::table($name)
                    ->where('id', $row->id)
                    ->update(['ulid' => Str::lower((string) Str::ulid())]));

            Schema::table($name, function (Blueprint $table) {
                $table->ulid()->nullable(false)->change();
                $table->unique('ulid');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables() as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropUnique(['ulid']);
                $table->dropColumn('ulid');
            });
        }
    }
};
