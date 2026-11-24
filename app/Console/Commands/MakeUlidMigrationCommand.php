<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Membuat migration yang menambah kolom "ulid" ke tabel yang SUDAH ADA dan
 * mengisi ULID untuk semua baris lamanya.
 *
 *   php artisan make:ulid-migration kwitansis
 *   php artisan make:ulid-migration supplier --connection=wmsreports
 *   php artisan migrate
 *
 * Modul hasil CRUD generator (model memakai HasOptionalPublicUlid) langsung
 * memakai ULID di URL setelah migrate, tanpa mengubah kode. Model lain:
 * tambahkan trait-nya sendiri (lihat pesan setelah perintah ini jalan).
 */
class MakeUlidMigrationCommand extends Command
{
    protected $signature = 'make:ulid-migration
        {table : Existing table, e.g. kwitansis}
        {--connection= : Database connection of the table (default: the app database)}';

    protected $description = 'Create a migration that adds a "ulid" column to an existing table and fills it for every row';

    public function handle(): int
    {
        $table = (string) $this->argument('table');
        $connection = $this->option('connection');
        $connection = is_string($connection) && $connection !== '' ? $connection : null;

        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
            $this->components->error("\"{$table}\" is not a valid table name.");

            return self::FAILURE;
        }

        if ($connection !== null && config("database.connections.{$connection}") === null) {
            $this->components->error("Connection \"{$connection}\" is not in config/database.php.");

            return self::FAILURE;
        }

        try {
            $schema = Schema::connection($connection);

            if (! $schema->hasTable($table)) {
                $this->components->error("Table \"{$table}\" does not exist.");

                return self::FAILURE;
            }

            if ($schema->hasColumn($table, 'ulid')) {
                $this->components->info("Table \"{$table}\" already has a \"ulid\" column. Nothing to do.");

                return self::SUCCESS;
            }

            if (! $schema->hasColumn($table, 'id')) {
                $this->components->error("Table \"{$table}\" has no \"id\" column (needed to fill old rows).");

                return self::FAILURE;
            }
        } catch (Throwable $e) {
            $this->components->error('Cannot read the database: '.$e->getMessage());

            return self::FAILURE;
        }

        $path = database_path('migrations/'.now()->format('Y_m_d_His')."_add_ulid_to_{$table}_table.php");
        File::put($path, $this->migration($table, $connection));

        $this->components->info('Migration created: '.str_replace(base_path().DIRECTORY_SEPARATOR, '', $path));
        $this->components->bulletList([
            'Run <fg=yellow>php artisan migrate</>.',
            'Model made by the CRUD generator (uses <fg=yellow>HasOptionalPublicUlid</>): nothing else to change, URLs switch to the ULID.',
            'Other models: add <fg=yellow>use HasOptionalPublicUlid;</> (or HasPublicUlid + ->scoped([... => \'ulid\']) in the route), and use routeKey(item) / item.ulid in React.',
        ]);

        return self::SUCCESS;
    }

    private function migration(string $table, ?string $connection): string
    {
        $schema = $connection !== null ? "Schema::connection('{$connection}')" : 'Schema::connection(null)';
        $db = $connection !== null ? "DB::connection('{$connection}')" : 'DB::connection()';

        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Kolom "ulid" untuk URL admin {$table}: /admin/.../5/edit → /admin/.../01jd8q3x.../edit.
 * Dibuat oleh: php artisan make:ulid-migration {$table}
 *
 * Aman dijalankan ulang: dilewati bila tabelnya tidak ada atau kolomnya sudah ada
 * (mis. tabel sudah di-drop karena modulnya dihapus).
 */
return new class extends Migration
{
    public function up(): void
    {
        \$schema = {$schema};

        if (! \$schema->hasTable('{$table}') || \$schema->hasColumn('{$table}', 'ulid')) {
            return;
        }

        \$schema->table('{$table}', function (Blueprint \$table) {
            \$table->ulid()->nullable()->after('id');
        });

        // Isi baris lama. Huruf kecil = sama dengan HasUlids di model.
        {$db}->table('{$table}')->whereNull('ulid')->select('id')->lazyById(500)
            ->each(fn (object \$row) => {$db}->table('{$table}')
                ->where('id', \$row->id)
                ->update(['ulid' => Str::lower((string) Str::ulid())]));

        \$schema->table('{$table}', function (Blueprint \$table) {
            \$table->ulid()->nullable(false)->change();
            \$table->unique('ulid');
        });
    }

    public function down(): void
    {
        \$schema = {$schema};

        if (! \$schema->hasTable('{$table}') || ! \$schema->hasColumn('{$table}', 'ulid')) {
            return;
        }

        \$schema->table('{$table}', function (Blueprint \$table) {
            \$table->dropUnique(['ulid']);
            \$table->dropColumn('ulid');
        });
    }
};

PHP;
    }
}
