<?php

namespace App\Services\Crud;

use App\Enums\PermissionName;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * CRUD generator: membuat & menghapus satu modul CRUD lengkap.
 *
 * generate():
 *   1. Tulis file baru dari stub (CrudRenderer).
 *   2. Sisipkan blok permission ke App\Enums\PermissionName dan blok route
 *      ke routes/generated.php, di antara penanda "// crud:<tabel>".
 *   3. Rapikan format, catat hash tiap file ke manifest
 *      (storage/app/crud/<tabel>.json).
 *   4. Proses terpisah: migrate, seeder permission, Wayfinder.
 *   Gagal di tengah jalan → semua perubahan file dibatalkan.
 *
 * remove():
 *   File yang hash-nya MASIH SAMA dihapus. File yang sudah Anda ubah
 *   dibiarkan dan dilaporkan. Tabel hanya di-drop bila diminta.
 *
 * HANYA untuk development (lihat enabled()).
 */
class CrudGenerator
{
    /** Nama tabel yang dipakai aplikasi / dicadangkan untuk tahap berikutnya. */
    private const RESERVED_TABLES = [
        'admins', 'apis', 'caches', 'cache', 'cache_locks', 'categories', 'dashboards', 'failed_jobs',
        'jobs', 'job_batches', 'menus', 'menu_role', 'migrations', 'model_has_permissions',
        'model_has_roles', 'orders', 'order_items', 'password_reset_tokens', 'permissions',
        'personal_access_tokens', 'products', 'reports', 'roles', 'role_has_permissions',
        'sessions', 'settings', 'testings', 'users', 'idempotency_keys',
    ];

    /** Kata kunci PHP yang tidak boleh jadi nama class. */
    private const PHP_RESERVED = [
        'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch', 'class', 'clone',
        'const', 'continue', 'declare', 'default', 'do', 'echo', 'else', 'empty', 'enum', 'eval',
        'exit', 'extends', 'final', 'finally', 'fn', 'for', 'foreach', 'function', 'global', 'goto',
        'if', 'implements', 'include', 'instanceof', 'insteadof', 'interface', 'isset', 'list',
        'match', 'namespace', 'new', 'null', 'or', 'print', 'private', 'protected', 'public',
        'readonly', 'require', 'return', 'static', 'switch', 'throw', 'trait', 'try', 'unset',
        'use', 'var', 'while', 'xor', 'yield', 'model', 'controller', 'request', 'user', 'mixed',
    ];

    /** Batas jumlah tabel detail per induk (master–detail satu tingkat). */
    public const MAX_DETAILS = 5;

    /**
     * Nama detail yang tidak boleh dipakai: bentrok dengan props halaman
     * edit (item, abilities, parent) atau method bawaan Eloquent Model.
     */
    private const RESERVED_DETAIL_KEYS = [
        'abilities', 'attributes', 'casts', 'changes', 'connection', 'fillable', 'guarded',
        'item', 'original', 'parent', 'query', 'relations', 'table',
    ];

    private const ENUM_FILE = 'app/Enums/PermissionName.php';

    private const ROUTES_FILE = 'routes/generated.php';

    /** Route REST API hasil generator (di-require dari routes/api/v1.php). */
    private const API_ROUTES_FILE = 'routes/api/generated.php';

    private readonly string $root;

    private readonly CrudTableInspector $inspector;

    public function __construct(
        private readonly CrudRenderer $renderer,
        private readonly CrudPostProcessor $post,
        ?string $root = null,
        ?CrudTableInspector $inspector = null,
    ) {
        $this->root = rtrim(str_replace('\\', '/', $root ?? base_path()), '/');
        $this->inspector = $inspector ?? new CrudTableInspector;
    }

    /** Inspector untuk koneksi tertentu (null = koneksi default). */
    public function inspector(?string $connection = null): CrudTableInspector
    {
        return $connection === null ? $this->inspector : new CrudTableInspector($connection);
    }

    /**
     * Koneksi LAIN yang boleh dipakai (config crud.connections), hanya yang
     * sudah dikonfigurasi (host terisi di .env).
     *
     * @return array<string, string> nama → label
     */
    public function connections(): array
    {
        /** @var array<string, string> $configured */
        $configured = config('crud.connections', []);

        $drivers = \PDO::getAvailableDrivers();

        return array_filter(
            $configured,
            function (string $label, string $name) use ($drivers): bool {
                $config = config("database.connections.{$name}");

                if (! is_array($config) || ! is_string($config['driver'] ?? null)) {
                    return false;
                }

                // Driver PHP harus aktif (pdo_mysql / pdo_pgsql / pdo_sqlsrv / pdo_sqlite).
                if (! in_array($config['driver'], $drivers, true)) {
                    return false;
                }

                return $config['driver'] === 'sqlite' || filled($config['host'] ?? null);
            },
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * Tabel yang boleh dipakai mode "Use an existing table": semua tabel
     * di database KECUALI tabel aplikasi/Laravel (khusus koneksi default)
     * dan tabel yang sudah dipakai modul hasil generate.
     *
     * @return list<string>
     */
    public function candidateTables(?string $connection = null): array
    {
        if ($connection !== null && ! array_key_exists($connection, $this->connections())) {
            return [];
        }

        $used = [];

        foreach (glob($this->path('storage/app/crud/*.json')) ?: [] as $file) {
            $manifest = $this->manifest(basename($file, '.json')) ?? [];

            if (($manifest['db_connection'] ?? null) === $connection) {
                $used[] = is_string($manifest['db_table'] ?? null) ? $manifest['db_table'] : ($manifest['table'] ?? null);

                foreach (self::manifestDetails($manifest) as $detail) {
                    $used[] = $detail['db_table'] ?? null;
                }
            }
        }

        return array_values(array_filter(
            $this->inspector($connection)->tables(),
            fn (string $t) => ($connection !== null || ! in_array($t, self::RESERVED_TABLES, true))
                && ! in_array($t, $used, true)
                && ! str_starts_with($t, 'telescope_')
                && ! str_starts_with($t, 'pulse_'),
        ));
    }

    /**
     * Blueprint dari tabel yang sudah ada. $columns = kolom yang dipilih
     * (null = semua kolom yang didukung). $details = tabel-tabel detail
     * (master–detail, satu tingkat); tiap tabel harus punya foreign key ke
     * tabel ini. Contoh: [['table' => 'po_det', 'name' => 'items'], ['table' => 'po_attach']].
     *
     * @param  list<string>|null  $columns
     * @param  list<array{table: string, name?: string|null}>  $details
     */
    public function blueprintForTable(string $name, string $table, ?array $columns = null, ?string $connection = null, array $details = []): CrudBlueprint
    {
        $inspector = $this->inspector($connection);
        $info = $inspector->describe($table);
        $fields = $columns === null
            ? $info['fields']
            : array_values(array_filter($info['fields'], fn (CrudField $f) => in_array($f->name, $columns, true)));

        // Nama detail diturunkan dari induk: "Messages" + "details" → "Message details" (model MessageDetail).
        $parentWords = Str::singular(CrudBlueprint::words($name));
        $blueprints = [];

        foreach ($details as $spec) {
            $detailTable = $spec['table'];
            $link = collect($info['children'])->firstWhere('table', $detailTable)
                ?? throw new RuntimeException("\"{$detailTable}\" has no foreign key to \"{$table}\".");
            $child = $inspector->describe($detailTable);
            $key = self::detailKey($spec['name'] ?? null, $detailTable, count($details));

            $blueprints[] = new CrudBlueprint(
                $parentWords.' '.str_replace('_', ' ', $key),
                // Kolom penghubung (msg_id) diisi dari induk, bukan dari form.
                array_values(array_filter($child['fields'], fn (CrudField $f) => $f->name !== $link['column'])),
                $detailTable,
                $child['timestamps'],
                $connection,
                $child['audit'],
                foreignKey: $link['column'],
                detailKey: $key,
            );
        }

        return new CrudBlueprint($name, $fields, $table, $info['timestamps'], $connection, $info['audit'], $blueprints);
    }

    /**
     * Nama detail (snake_case jamak) → relasi, URL & input form.
     * Diisi user: "Line items" → line_items. Kosong: "details" bila hanya
     * satu tabel detail, selain itu dari nama tabelnya (po_attach → po_attaches).
     */
    public static function detailKey(?string $name, string $table, int $count): string
    {
        $words = CrudBlueprint::words((string) $name);

        if ($words === '') {
            $words = $count === 1 ? 'details' : CrudBlueprint::words($table);
        }

        return Str::snake(Str::plural(Str::singular(Str::studly($words))));
    }

    /**
     * "msg_det" atau "msg_det:lines" (opsi --detail di terminal) → spesifikasi detail.
     *
     * @return array{table: string, name: string|null}
     */
    public static function parseDetailSpec(string $spec): array
    {
        [$table, $name] = array_pad(explode(':', $spec, 2), 2, null);

        return ['table' => trim((string) $table), 'name' => $name !== null && trim($name) !== '' ? trim($name) : null];
    }

    /** Alasan nama detail ini tidak bisa dipakai (null = boleh). */
    public function detailKeyProblem(string $key, CrudBlueprint $parent): ?string
    {
        return match (true) {
            $key === '' || ! preg_match('/^[a-z][a-z0-9_]*$/', $key) => 'Use letters, numbers and spaces only, starting with a letter (e.g. "items").',
            strlen($key) > 40 => 'The detail name is too long.',
            in_array($key, self::RESERVED_DETAIL_KEYS, true),
            in_array(Str::singular($key), self::PHP_RESERVED, true),
            in_array($key, self::PHP_RESERVED, true) => "\"{$key}\" is a reserved name. Choose another detail name.",
            Str::singular($key) === $parent->routeParameter() => 'The detail name must differ from the resource name.',
            default => null,
        };
    }

    /**
     * Daftar detail di manifest. Manifest lama menyimpan satu detail di
     * kunci "detail"; manifest baru menyimpan daftar di "details".
     *
     * @param  array<string, mixed>  $manifest
     * @return list<array<string, mixed>>
     */
    private static function manifestDetails(array $manifest): array
    {
        $details = is_array($manifest['details'] ?? null) ? $manifest['details'] : [];

        if (is_array($manifest['detail'] ?? null)) {
            $details[] = $manifest['detail'];
        }

        return array_values(array_filter($details, is_array(...)));
    }

    /** Aktif hanya di development. Di production SELALU mati. */
    public static function enabled(): bool
    {
        return (bool) config('crud.enabled') && ! app()->isProduction();
    }

    /**
     * Alasan nama ini tidak bisa dipakai (kosong = boleh).
     *
     * @return list<string>
     */
    public function problems(CrudBlueprint $bp): array
    {
        if (in_array($bp->table, self::RESERVED_TABLES, true) || in_array(Str::lower($bp->model), self::PHP_RESERVED, true)) {
            return ["\"{$bp->plural()}\" is a reserved name. Choose another one."];
        }

        if (strlen($bp->table) > 50) {
            return ['The name is too long.'];
        }

        $modules = array_map(fn (PermissionName $p) => $p->module(), PermissionName::cases());

        if ($bp->existingTable !== null) {
            $candidates = $this->candidateTables($bp->connection);
            $tableProblem = in_array($bp->existingTable, $candidates, true)
                ? $this->inspector($bp->connection)->describe($bp->existingTable)['problem']
                : "The table \"{$bp->existingTable}\" cannot be used (not found, used by the app, or already generated).";

            $tableProblem ??= $this->detailProblem($bp, $candidates);

            if ($tableProblem !== null) {
                return [$tableProblem];
            }
        }

        // Tabel BARU di database lain: koneksinya harus terdaftar & akunnya boleh CREATE TABLE.
        if (! $bp->usesExistingTable() && $bp->connection !== null) {
            if (! array_key_exists($bp->connection, $this->connections())) {
                return ["The database \"{$bp->connection}\" is not available."];
            }

            if (($problem = $this->inspector($bp->connection)->createTableProblem()) !== null) {
                return [$problem];
            }
        }

        // Tabel BARU + tabel detail baru (master–detail).
        if (! $bp->usesExistingTable() && ($problem = $this->detailProblem($bp, [])) !== null) {
            return [$problem];
        }

        return match (true) {
            $this->manifest($bp->table) !== null => ["\"{$bp->plural()}\" was already generated."],
            in_array($bp->table, $modules, true) => ["Permissions for \"{$bp->table}\" already exist."],
            ! $bp->usesExistingTable() && $this->inspector($bp->connection)->exists($bp->table) => [
                "A database table named \"{$bp->table}\" already exists. Choose \"Use an existing table\" to build pages for it.",
            ],
            is_file($this->path("app/Models/{$bp->model}.php")) => ["The model App\\Models\\{$bp->model} already exists."],
            is_file($this->path("app/Http/Controllers/Admin/{$bp->model}Controller.php")) => ["{$bp->model}Controller already exists."],
            is_dir($this->path('resources/js/pages/admin/'.$bp->slug())) => ["The page folder admin/{$bp->slug()} already exists."],
            default => $this->apiProblem($bp) === null ? [] : [(string) $this->apiProblem($bp)],
        };
    }

    /**
     * Alasan REST API untuk blueprint ini tidak bisa dibuat (null = boleh):
     * controller API / Resource dengan nama yang sama sudah ada, mis. modul
     * buatan tangan seperti Products & Categories.
     */
    public function apiProblem(CrudBlueprint $bp): ?string
    {
        if (is_file($this->path($this->renderer->apiControllerPath($bp)))) {
            return "Api\\V1\\{$bp->model}Controller already exists. Hand-written APIs (e.g. products, categories) are not generated.";
        }

        foreach ([$bp, ...$bp->details] as $b) {
            if (is_file($this->path("app/Http/Resources/V1/{$b->model}Resource.php"))) {
                return "{$b->model}Resource already exists.";
            }
        }

        return null;
    }

    /**
     * Masalah pertama pada tabel-tabel detail (null = semua boleh).
     *
     * @param  list<string>  $candidates  tabel yang boleh dipakai di koneksi ini
     */
    public function detailProblem(CrudBlueprint $bp, array $candidates): ?string
    {
        if (count($bp->details) > self::MAX_DETAILS) {
            return 'Use at most '.self::MAX_DETAILS.' detail tables.';
        }

        $tables = [];
        $keys = [];
        $models = [$bp->model];

        foreach ($bp->details as $d) {
            $problem = match (true) {
                $d->dbTable() === $bp->dbTable() => 'A table cannot be its own detail table.',
                $d->usesExistingTable() && in_array($d->dbTable(), $tables, true) => "The detail table \"{$d->dbTable()}\" is chosen twice.",
                in_array((string) $d->detailKey, $keys, true) => "The detail name \"{$d->detailKey}\" is used twice. Give each detail table its own name.",
                default => $this->detailKeyProblem((string) $d->detailKey, $bp),
            };

            // Tabel detail yang sudah ada harus calon yang sah; tabel detail
            // BARU (dibuat migration) tidak boleh sudah ada / dicadangkan.
            $problem ??= $d->usesExistingTable()
                ? (in_array($d->dbTable(), $candidates, true)
                    ? $this->inspector($bp->connection)->describe($d->dbTable())['problem']
                    : "The detail table \"{$d->dbTable()}\" cannot be used.")
                : match (true) {
                    in_array($d->table, self::RESERVED_TABLES, true) => "\"{$d->table}\" is a reserved table name. Choose another detail name.",
                    strlen($d->table) > 60 => "The detail table name \"{$d->table}\" is too long. Use a shorter detail name.",
                    $this->inspector($bp->connection)->exists($d->table) => "A database table named \"{$d->table}\" already exists. Choose another detail name.",
                    default => null,
                };

            $problem ??= match (true) {
                in_array($d->model, $models, true) => "Two details would both be named {$d->model}. Give them different names.",
                is_file($this->path("app/Models/{$d->model}.php")) => "The model App\\Models\\{$d->model} already exists.",
                default => null,
            };

            if ($problem !== null) {
                return $problem;
            }

            $tables[] = $d->dbTable();
            $keys[] = (string) $d->detailKey;
            $models[] = $d->model;
        }

        return null;
    }

    /**
     * $web = halaman admin (controller, React, menu); $api = REST API
     * /api/v1/<slug>. Keduanya memakai model, policy, request & permission
     * yang sama. Minimal salah satu harus true.
     *
     * @return array{manifest: array<string, mixed>, files: list<string>, warnings: list<string>}
     */
    public function generate(CrudBlueprint $bp, bool $web = true, bool $api = false): array
    {
        $this->guard();

        if (! $web && ! $api) {
            throw new RuntimeException('Choose at least one: admin pages or REST API.');
        }

        if (($problems = $this->problems($bp)) !== []) {
            throw new RuntimeException($problems[0]);
        }

        $timestamp = now()->format('Y_m_d_His');
        $apiFiles = $api ? $this->renderer->apiFiles($bp) : [];
        $files = [...$this->renderer->files($bp, $timestamp, $web), ...$apiFiles];
        // Tabel yang sudah ada: tidak ada migration (tabelnya bukan milik generator).
        $migration = $bp->usesExistingTable() ? null : $this->renderer->migrationPath($bp, $timestamp);

        $backups = [];
        $written = [];

        try {
            foreach ($files as $path => $content) {
                $this->write($path, $content);
                $written[] = $path;
            }

            $backups[self::ENUM_FILE] = $this->read(self::ENUM_FILE);
            $this->write(self::ENUM_FILE, $this->insertEnum($backups[self::ENUM_FILE], $bp));

            if ($web) {
                $backups[self::ROUTES_FILE] = $this->read(self::ROUTES_FILE);
                $this->write(self::ROUTES_FILE, $this->insertBlock($backups[self::ROUTES_FILE], '// @crud:routes', $bp->table, $this->renderer->routes($bp), ''));
            }

            if ($api) {
                $backups[self::API_ROUTES_FILE] = $this->read(self::API_ROUTES_FILE);
                $this->write(self::API_ROUTES_FILE, $this->insertBlock($backups[self::API_ROUTES_FILE], '// @crud:api-routes', $bp->table, $this->renderer->apiRoutes($bp), ''));
            }
        } catch (Throwable $e) {
            foreach ($written as $path) {
                @unlink($this->path($path));
            }

            foreach ($backups as $path => $content) {
                $this->write($path, $content);
            }

            foreach ($bp->details as $d) {
                $this->removeEmptyDir('resources/js/pages/admin/'.$bp->slug().'/'.$d->detailSegment());
            }

            $this->removeEmptyDir('resources/js/pages/admin/'.$bp->slug());

            throw $e;
        }

        $warnings = $this->post->format($this->root, array_keys($files));

        $manifest = [
            ...$bp->toArray(),
            // "API saja" tidak punya halaman admin (dan tidak muncul di dialog Delete menu).
            'url' => $web ? $bp->url() : null,
            'web' => $web,
            'api' => $api,
            'api_url' => $api ? '/api/v1/'.$bp->slug() : null,
            'api_files' => array_keys($apiFiles),
            'migration' => $migration,
            'generated_at' => now()->toIso8601String(),
            // Hash setelah diformat: dipakai remove() untuk tahu file mana yang sudah Anda ubah.
            'files' => array_map(
                fn (string $path) => sha1_file($this->path($path)) ?: '',
                array_combine(array_keys($files), array_keys($files)),
            ),
            // Sidik jari tanpa format: file yang hanya diformat ulang (Prettier/vp fmt
            // seluruh proyek, format-on-save) tetap dianggap belum diubah.
            'fingerprints' => array_map(
                fn (string $path) => $this->fingerprint($path),
                array_combine(array_keys($files), array_keys($files)),
            ),
        ];

        $this->write("storage/app/crud/{$bp->table}.json", (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return [
            'manifest' => $manifest,
            'files' => array_keys($files),
            'warnings' => [...$warnings, ...$this->post->afterGenerate($this->root, $migration)],
        ];
    }

    /**
     * @return array{deleted: list<string>, kept: list<string>, table_dropped: bool, warnings: list<string>}
     */
    public function remove(string $table, bool $dropTable): array
    {
        $this->guard();

        $manifest = $this->manifest($table) ?? throw new RuntimeException("No generated CRUD named \"{$table}\".");
        /** @var array<string, string> $files */
        $files = is_array($manifest['files'] ?? null) ? $manifest['files'] : [];
        /** @var array<string, string> $fingerprints */
        $fingerprints = is_array($manifest['fingerprints'] ?? null) ? $manifest['fingerprints'] : [];
        $migration = is_string($manifest['migration'] ?? null) ? $manifest['migration'] : null;
        $slug = str_replace('_', '-', $table);

        $deleted = [];
        $kept = [];

        foreach ($files as $path => $hash) {
            // Migration diurus terpisah: dihapus hanya bersama tabelnya.
            if ($path === $migration || ! is_file($this->path($path))) {
                continue;
            }

            $unchanged = sha1_file($this->path($path)) === $hash
                || (isset($fingerprints[$path]) && $this->fingerprint($path) === $fingerprints[$path]);

            if ($unchanged) {
                unlink($this->path($path));
                $deleted[] = $path;
            } else {
                $kept[] = $path;
            }
        }

        $details = self::manifestDetails($manifest);

        foreach ($details as $detail) {
            // Manifest lama tanpa detail_key: foldernya selalu "details".
            $segment = str_replace('_', '-', is_string($detail['detail_key'] ?? null) ? $detail['detail_key'] : 'details');
            $this->removeEmptyDir("resources/js/pages/admin/{$slug}/{$segment}");
        }

        $this->removeEmptyDir("resources/js/pages/admin/{$slug}");
        $this->removeEmptyDir('tests/Feature/Admin/Generated');
        $this->removeEmptyDir('tests/Feature/Api/Generated');

        $this->write(self::ENUM_FILE, $this->removeBlocks($this->read(self::ENUM_FILE), $table));
        $this->removeRouteBlock(self::ROUTES_FILE, $table, 'Admin', [$manifest['model'] ?? null, ...array_column($details, 'model')]);

        if (is_file($this->path(self::API_ROUTES_FILE))) {
            $this->removeRouteBlock(self::API_ROUTES_FILE, $table, 'Api\\V1', [$manifest['model'] ?? null]);
        }

        $tableDropped = false;

        // Modul dari tabel yang SUDAH ADA tidak punya migration → tabelnya
        // tidak pernah di-drop, apa pun pilihan $dropTable.
        if ($dropTable && $migration !== null && is_file($this->path($migration))) {
            $this->rollback($migration);
            unlink($this->path($migration));
            $deleted[] = $migration;
            $tableDropped = true;

            // Kolom ulid yang ditambah belakangan (make:ulid-migration) ikut
            // hilang bersama tabelnya; migration-nya juga dibersihkan.
            $dbTables = [$manifest['db_table'] ?? $table, ...array_column($details, 'db_table')];

            foreach (array_filter($dbTables, 'is_string') as $dbTable) {
                foreach (glob($this->path("database/migrations/*_add_ulid_to_{$dbTable}_table.php")) ?: [] as $file) {
                    DB::table('migrations')->where('migration', basename($file, '.php'))->delete();
                    unlink($file);
                    $deleted[] = 'database/migrations/'.basename($file);
                }
            }
        }

        @unlink($this->path("storage/app/crud/{$table}.json"));

        return [
            'deleted' => $deleted,
            'kept' => $kept,
            'table_dropped' => $tableDropped,
            'warnings' => $this->post->afterRemove($this->root),
        ];
    }

    /**
     * Tambahkan REST API ke modul yang SUDAH di-generate (Opsi "API saja"
     * untuk modul lama). Field, tabel, koneksi & detail dibaca dari
     * manifest-nya; model, policy & request yang ada dipakai ulang.
     *
     * @return array{files: list<string>, warnings: list<string>, api_url: string}
     */
    public function addApi(string $table): array
    {
        $this->guard();

        $manifest = $this->manifest($table) ?? throw new RuntimeException(
            "No generated module named \"{$table}\". Only modules made by the CRUD generator can get a generated API; write hand-written APIs (e.g. products) yourself so they keep their business rules.",
        );

        if (($manifest['api'] ?? false) === true) {
            throw new RuntimeException("\"{$table}\" already has a REST API ({$this->apiUrl($manifest)}).");
        }

        $bp = CrudBlueprint::fromManifest($manifest);

        if (($problem = $this->apiProblem($bp)) !== null) {
            throw new RuntimeException($problem);
        }

        $files = $this->adaptToLegacyRequest($bp, $this->renderer->apiFiles($bp));
        $backup = $this->read(self::API_ROUTES_FILE);
        $written = [];

        try {
            foreach ($files as $path => $content) {
                $this->write($path, $content);
                $written[] = $path;
            }

            $this->write(self::API_ROUTES_FILE, $this->insertBlock($backup, '// @crud:api-routes', $bp->table, $this->renderer->apiRoutes($bp), ''));
        } catch (Throwable $e) {
            foreach ($written as $path) {
                @unlink($this->path($path));
            }

            $this->write(self::API_ROUTES_FILE, $backup);

            throw $e;
        }

        $warnings = $this->post->format($this->root, array_keys($files));

        /** @var array<string, string> $hashes */
        $hashes = is_array($manifest['files'] ?? null) ? $manifest['files'] : [];
        /** @var array<string, string> $fingerprints */
        $fingerprints = is_array($manifest['fingerprints'] ?? null) ? $manifest['fingerprints'] : [];

        foreach (array_keys($files) as $path) {
            $hashes[$path] = sha1_file($this->path($path)) ?: '';
            $fingerprints[$path] = $this->fingerprint($path);
        }

        $this->saveManifest($table, [
            ...$manifest,
            'web' => $manifest['web'] ?? true,
            'api' => true,
            'api_url' => '/api/v1/'.$bp->slug(),
            'api_files' => array_keys($files),
            'files' => $hashes,
            'fingerprints' => $fingerprints,
        ]);

        return [
            'files' => array_keys($files),
            // Route & Wayfinder perlu dibaca ulang (permission tidak berubah).
            'warnings' => [...$warnings, ...$this->post->afterGenerate($this->root, null)],
            'api_url' => '/api/v1/'.$bp->slug(),
        ];
    }

    /**
     * Modul master–detail dari generator versi awal (satu tabel detail,
     * mis. Messages) punya Save<Model>Request::detailRows() TANPA parameter.
     * Controller API menyesuaikan pemanggilannya agar cocok dengan request
     * yang sudah ada (yang tidak diubah generator).
     *
     * @param  array<string, string>  $files
     * @return array<string, string>
     */
    private function adaptToLegacyRequest(CrudBlueprint $bp, array $files): array
    {
        $request = "app/Http/Requests/Admin/Save{$bp->model}Request.php";
        $controller = $this->renderer->apiControllerPath($bp);

        if (! $bp->hasDetails() || ! isset($files[$controller]) || ! is_file($this->path($request))) {
            return $files;
        }

        if (preg_match('/function\s+detailRows\s*\(\s*\)/', $this->read($request)) === 1) {
            $files[$controller] = (string) preg_replace("/->detailRows\\('[a-z0-9_]+'\\)/", '->detailRows()', $files[$controller]);
        }

        return $files;
    }

    /**
     * Hapus HANYA REST API sebuah modul (halaman admin tetap). File API yang
     * sudah Anda ubah tidak dihapus. Modul "API saja" dihapus lewat remove().
     *
     * @return array{deleted: list<string>, kept: list<string>, warnings: list<string>}
     */
    public function removeApi(string $table): array
    {
        $this->guard();

        $manifest = $this->manifest($table) ?? throw new RuntimeException("No generated module named \"{$table}\".");

        if (($manifest['api'] ?? false) !== true) {
            throw new RuntimeException("\"{$table}\" has no generated REST API.");
        }

        if (($manifest['web'] ?? true) === false) {
            throw new RuntimeException("\"{$table}\" is an API-only module. Remove the whole module with: php artisan crud:remove {$table}");
        }

        /** @var array<string, string> $hashes */
        $hashes = is_array($manifest['files'] ?? null) ? $manifest['files'] : [];
        /** @var array<string, string> $fingerprints */
        $fingerprints = is_array($manifest['fingerprints'] ?? null) ? $manifest['fingerprints'] : [];
        /** @var list<string> $apiFiles */
        $apiFiles = is_array($manifest['api_files'] ?? null) ? array_values(array_filter($manifest['api_files'], is_string(...))) : [];

        $deleted = [];
        $kept = [];

        foreach ($apiFiles as $path) {
            if (is_file($this->path($path))) {
                $unchanged = sha1_file($this->path($path)) === ($hashes[$path] ?? null)
                    || (isset($fingerprints[$path]) && $this->fingerprint($path) === $fingerprints[$path]);

                if ($unchanged) {
                    unlink($this->path($path));
                    $deleted[] = $path;
                } else {
                    $kept[] = $path;
                }
            }

            // File yang Anda ubah tetap ada, tetapi tidak lagi dicatat sebagai milik generator.
            unset($hashes[$path], $fingerprints[$path]);
        }

        $this->removeEmptyDir('tests/Feature/Api/Generated');
        $this->removeRouteBlock(self::API_ROUTES_FILE, $table, 'Api\\V1', [$manifest['model'] ?? null]);

        $this->saveManifest($table, [
            ...$manifest,
            'api' => false,
            'api_url' => null,
            'api_files' => [],
            'files' => $hashes,
            'fingerprints' => $fingerprints,
        ]);

        return ['deleted' => $deleted, 'kept' => $kept, 'warnings' => $this->post->afterRemove($this->root)];
    }

    /**
     * Semua modul hasil generator + status web/API-nya (crud:api tanpa
     * argumen & halaman Admin → API Generator).
     *
     * @return list<array{table: string, name: string, web: bool, api: bool, api_url: string|null, url: string|null, db_table: string, db_connection: string|null, details: list<string>}>
     */
    public function modules(): array
    {
        $modules = [];

        foreach (glob($this->path('storage/app/crud/*.json')) ?: [] as $file) {
            $manifest = $this->manifest(basename($file, '.json'));

            if ($manifest === null || ! is_string($manifest['table'] ?? null)) {
                continue;
            }

            $modules[] = [
                'table' => $manifest['table'],
                'name' => is_string($manifest['name'] ?? null) ? $manifest['name'] : $manifest['table'],
                'web' => ($manifest['web'] ?? true) !== false,
                'api' => ($manifest['api'] ?? false) === true,
                'api_url' => ($manifest['api'] ?? false) === true ? $this->apiUrl($manifest) : null,
                'url' => is_string($manifest['url'] ?? null) ? $manifest['url'] : null,
                'db_table' => is_string($manifest['db_table'] ?? null) ? $manifest['db_table'] : $manifest['table'],
                'db_connection' => is_string($manifest['db_connection'] ?? null) ? $manifest['db_connection'] : null,
                // Tabel detail (master–detail), mis. ["msg_det"].
                'details' => array_values(array_filter(
                    array_map(fn (array $d) => $d['db_table'] ?? $d['table'] ?? null, self::manifestDetails($manifest)),
                    is_string(...),
                )),
            ];
        }

        usort($modules, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        return $modules;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function apiUrl(array $manifest): string
    {
        return is_string($manifest['api_url'] ?? null)
            ? $manifest['api_url']
            : '/api/v1/'.str_replace('_', '-', is_string($manifest['table'] ?? null) ? $manifest['table'] : '');
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function saveManifest(string $table, array $manifest): void
    {
        $this->write("storage/app/crud/{$table}.json", (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Buang blok "crud:<tabel>" dari file route, termasuk baris "use" controller
     * yang mungkin dipindahkan Pint ke atas file.
     *
     * @param  list<mixed>  $models
     */
    private function removeRouteBlock(string $file, string $table, string $namespace, array $models): void
    {
        $routes = $this->removeBlocks($this->read($file), $table);
        $ns = preg_quote('App\\Http\\Controllers\\'.$namespace.'\\', '/');

        foreach ($models as $model) {
            if (is_string($model) && $model !== '') {
                $quoted = preg_quote($model, '/');
                $routes = (string) preg_replace("/^use {$ns}{$quoted}Controller;\\R/m", '', $routes);
            }
        }

        $this->write($file, $routes);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function manifest(string $table): ?array
    {
        if (! preg_match('/^[a-z0-9_]+$/', $table)) {
            return null;
        }

        $path = $this->path("storage/app/crud/{$table}.json");

        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        /** @var array<string, mixed>|null */
        return is_array($data) ? $data : null;
    }

    /**
     * Info modul hasil generate per URL, untuk dialog Delete di halaman Menus.
     *
     * @return array<string, array{table: string, db_table: string, can_drop: bool}>
     */
    public function generated(): array
    {
        $result = [];

        foreach ($this->urls() as $url => $table) {
            $manifest = $this->manifest($table) ?? [];
            $result[$url] = [
                'table' => $table,
                'db_table' => is_string($manifest['db_table'] ?? null) ? $manifest['db_table'] : $table,
                // Hanya tabel yang DIBUAT generator (punya migration) yang boleh di-drop.
                'can_drop' => is_string($manifest['migration'] ?? null),
            ];
        }

        return $result;
    }

    /**
     * Semua CRUD hasil generate, dikunci per URL.
     *
     * @return array<string, string> url → tabel
     */
    public function urls(): array
    {
        $urls = [];

        foreach (glob($this->path('storage/app/crud/*.json')) ?: [] as $file) {
            $manifest = $this->manifest(basename($file, '.json'));

            if (is_string($manifest['url'] ?? null) && is_string($manifest['table'] ?? null)) {
                $urls[$manifest['url']] = $manifest['table'];
            }
        }

        return $urls;
    }

    /**
     * Status proses generate dari form "Add menu".
     *
     * Kenapa perlu? Saat development, Vite me-reload browser begitu file
     * route / halaman baru ditulis, jadi respons request generate tidak
     * pernah sampai ke halaman lama. Halaman yang ter-reload menanyakan
     * status lewat token ini sampai selesai.
     *
     * @param  array<string, string|null>  $data
     */
    public function writeStatus(string $token, string $state, array $data = []): void
    {
        if (! preg_match('/^[A-Za-z0-9-]{8,64}$/', $token)) {
            return;
        }

        // Buang status lama (> 1 jam) agar folder tidak menumpuk.
        foreach (glob($this->path('storage/app/crud-status/*.json')) ?: [] as $old) {
            if (filemtime($old) < time() - 3600) {
                @unlink($old);
            }
        }

        $this->write("storage/app/crud-status/{$token}.json", (string) json_encode(['state' => $state, ...$data]));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function status(string $token): ?array
    {
        $path = $this->path("storage/app/crud-status/{$token}.json");

        if (! preg_match('/^[A-Za-z0-9-]{8,64}$/', $token) || ! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        /** @var array<string, mixed>|null */
        return is_array($data) ? $data : null;
    }

    private function guard(): void
    {
        if (! self::enabled()) {
            throw new RuntimeException('The CRUD generator only runs in a local development environment.');
        }
    }

    private function insertEnum(string $source, CrudBlueprint $bp): string
    {
        $source = $this->insertBlock($source, '// @crud:cases', $bp->table, $this->renderer->enumCases($bp), '    ');
        $source = $this->insertBlock($source, '// @crud:descriptions', $bp->table, $this->renderer->enumDescriptions($bp), '            ');

        return $this->insertBlock($source, '// @crud:labels', $bp->table, $this->renderer->enumLabel($bp), '            ');
    }

    /**
     * Sisipkan blok tepat di atas baris penanda:
     *
     *     // crud:suppliers
     *     ...isi...
     *     // end crud:suppliers
     *     // @crud:cases
     */
    private function insertBlock(string $source, string $marker, string $table, string $body, string $indent): string
    {
        // Hanya baris yang isinya PERSIS penanda (bukan teks di dalam komentar).
        $pattern = '/^[ \t]*'.preg_quote($marker, '/').'[ \t]*$/m';

        if (preg_match($pattern, $source) !== 1) {
            throw new RuntimeException("Marker \"{$marker}\" not found. Do not remove the // @crud:... lines.");
        }

        $block = "{$indent}// crud:{$table}\n{$body}\n{$indent}// end crud:{$table}\n";

        return (string) preg_replace_callback($pattern, fn (array $m) => $block.$m[0], $source, 1);
    }

    private function removeBlocks(string $source, string $table): string
    {
        $name = preg_quote($table, '/');

        return (string) preg_replace("/^[ \\t]*\\/\\/ crud:{$name}\\R.*?^[ \\t]*\\/\\/ end crud:{$name}\\R/ms", '', $source);
    }

    /** Jalankan down() migration lalu hapus catatannya dari tabel migrations. */
    private function rollback(string $migration): void
    {
        $instance = require $this->path($migration);

        if (is_object($instance) && method_exists($instance, 'down')) {
            $instance->down();
        }

        DB::table('migrations')->where('migration', basename($migration, '.php'))->delete();
    }

    /**
     * Hash isi file tanpa whitespace & koma penutup: hasil format ulang
     * (pindah baris, indentasi, trailing comma) tidak mengubah sidik jarinya,
     * sedangkan perubahan kode yang sebenarnya tetap terdeteksi.
     */
    private function fingerprint(string $relative): string
    {
        $content = (string) preg_replace('/\s+/', '', $this->read($relative));

        return sha1((string) preg_replace('/,(?=[)\]}>])/', '', $content));
    }

    private function path(string $relative): string
    {
        return $this->root.'/'.$relative;
    }

    private function read(string $relative): string
    {
        $path = $this->path($relative);

        if (! is_file($path)) {
            throw new RuntimeException("File not found: {$relative}");
        }

        return (string) file_get_contents($path);
    }

    private function write(string $relative, string $content): void
    {
        $path = $this->path($relative);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);
    }

    private function removeEmptyDir(string $relative): void
    {
        $dir = $this->path($relative);

        if (is_dir($dir) && (scandir($dir) ?: []) === ['.', '..']) {
            rmdir($dir);
        }
    }
}
