<?php

namespace App\Services\Crud;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * Mengisi stub di folder stubs/crud dengan nama & field dari CrudBlueprint.
 *
 * Stub = template teks dengan penanda {{ nama }}. Ubah stub bila ingin
 * semua CRUD berikutnya punya gaya lain; file yang SUDAH dibuat tidak ikut
 * berubah.
 *
 * Keamanan: semua nilai yang masuk ke kode (nama tabel, field, opsi select)
 * sudah dibatasi regex huruf/angka/spasi/_/- oleh CrudInput, jadi tidak
 * bisa menyisipkan kode PHP/JS.
 */
class CrudRenderer
{
    /** Kolom ULID untuk URL admin (pengenal acak; primary key tetap "id"). */
    private const ULID_COLUMN = '            $table->ulid()->unique();';

    public function __construct(private readonly ?string $stubPath = null) {}

    /**
     * Semua file baru: path relatif → isi.
     *
     * $web = false ("API saja"): tanpa controller admin, halaman React, form
     * & test web. Model, factory, policy & request tetap dibuat karena
     * dipakai API (lihat apiFiles()).
     *
     * @return array<string, string>
     */
    public function files(CrudBlueprint $bp, string $timestamp, bool $web = true): array
    {
        $vars = $this->vars($bp);
        $pages = 'resources/js/pages/admin/'.$bp->slug();

        return [
            ...array_merge([], ...array_map(fn (CrudBlueprint $d) => $this->detailFiles($bp, $d, $pages, $web), $bp->details)),
            // Tabel yang sudah ada tidak butuh migration.
            ...($bp->usesExistingTable() ? [] : [$this->migrationPath($bp, $timestamp) => $this->stub('migration', $vars)]),
            "app/Models/{$bp->model}.php" => $this->stub('model', $vars),
            "database/factories/{$bp->model}Factory.php" => $this->stub('factory', $vars),
            "app/Policies/{$bp->model}Policy.php" => $this->stub('policy', $vars),
            "app/Http/Requests/Admin/Save{$bp->model}Request.php" => $this->stub('request', $vars),
            ...($web ? [
                "app/Http/Controllers/Admin/{$bp->model}Controller.php" => $this->controller($bp, $vars),
                "tests/Feature/Admin/Generated/{$bp->model}CrudTest.php" => $this->stub('test', $vars),
                'resources/js/components/admin/'.$this->formFile($bp).'.tsx' => $this->stub('form', $vars),
                "{$pages}/index.tsx" => $this->stub('page-index', $vars),
                "{$pages}/create.tsx" => $this->stub('page-create', $vars),
                "{$pages}/edit.tsx" => $this->stub('page-edit', $vars),
            ] : []),
        ];
    }

    /**
     * File REST API (/api/v1/<slug>): controller, Resource (+ Resource tiap
     * tabel detail) dan feature test. Model, policy & request dipakai bersama
     * dengan halaman web, jadi aturan izin & validasinya selalu sama.
     *
     * @return array<string, string>
     */
    public function apiFiles(CrudBlueprint $bp): array
    {
        $vars = [...$this->vars($bp), ...$this->apiVars($bp)];
        $files = [
            $this->apiControllerPath($bp) => $this->apiController($vars),
            "app/Http/Resources/V1/{$bp->model}Resource.php" => $this->stub('api-resource', $vars),
        ];

        foreach ($bp->details as $d) {
            $files["app/Http/Resources/V1/{$d->model}Resource.php"] = $this->stub('api-resource', [
                ...$this->detailVars($bp, $d),
                ...$this->apiVars($d),
            ]);
        }

        $files["tests/Feature/Api/Generated/{$bp->model}ApiTest.php"] = $this->stub('api-test', $vars);

        return $files;
    }

    public function apiControllerPath(CrudBlueprint $bp): string
    {
        return "app/Http/Controllers/Api/V1/{$bp->model}Controller.php";
    }

    /** Blok route API (disisipkan ke routes/api/generated.php). */
    public function apiRoutes(CrudBlueprint $bp): string
    {
        return "Route::apiResource('{$bp->slug()}', App\\Http\\Controllers\\Api\\V1\\{$bp->model}Controller::class);";
    }

    /**
     * File untuk SATU tabel detail (master–detail): model, factory, request,
     * controller bertingkat, form, bagian di halaman edit induk, baris isian
     * di form tambah induk, halaman tambah/edit. Tanpa web: hanya model,
     * factory & request (dipakai API untuk menyimpan baris detail).
     *
     * @return array<string, string>
     */
    private function detailFiles(CrudBlueprint $bp, CrudBlueprint $d, string $pages, bool $web = true): array
    {
        $vars = $this->detailVars($bp, $d);

        return [
            "app/Models/{$d->model}.php" => $this->stub('model', $vars),
            "database/factories/{$d->model}Factory.php" => $this->stub('factory', $vars),
            "app/Http/Requests/Admin/Save{$d->model}Request.php" => $this->stub('detail-request', $vars),
            ...($web ? [
                "app/Http/Controllers/Admin/{$d->model}Controller.php" => $this->stub('detail-controller', $vars),
                'resources/js/components/admin/'.$this->formFile($d).'.tsx' => $this->stub('form', $vars),
                'resources/js/components/admin/'.$this->sectionFile($d).'.tsx' => $this->stub('details-section', $vars),
                'resources/js/components/admin/'.$this->rowsFile($d).'.tsx' => $this->stub('detail-rows', $vars),
                "{$pages}/{$d->detailSegment()}/create.tsx" => $this->stub('detail-create', $vars),
                "{$pages}/{$d->detailSegment()}/edit.tsx" => $this->stub('detail-edit', $vars),
            ] : []),
        ];
    }

    /**
     * Penanda khusus file API. $bp boleh blueprint DETAIL (untuk Resource-nya).
     *
     * @return array<string, string>
     */
    private function apiVars(CrudBlueprint $bp): array
    {
        $var = "\${$bp->variable()}";
        $url = '/api/v1/'.$bp->slug();
        $details = $bp->details;
        $required = array_values(array_filter($bp->fields, fn (CrudField $f) => $f->required));
        $timestamps = $bp->timestamps
            ? array_map(
                fn (string $column) => "            '{$column}' => \$this->{$column}?->toIso8601String(),",
                array_values(array_unique([(string) $this->createdAtColumn($bp), (string) $this->updatedAtColumn($bp)])),
            )
            : [];
        $connection = $bp->connection !== null ? "'{$bp->connection}'" : '';

        return [
            '{{ apiUrl }}' => $url,
            '{{ resourceFields }}' => $this->lines([
                // Detail: kolom penghubung ke induk (mis. msg_id).
                ...($bp->foreignKey !== null ? ["            '{$bp->foreignKey}' => \$this->getAttribute('{$bp->foreignKey}'),"] : []),
                ...array_map($this->resourceField(...), $bp->fields),
                ...$timestamps,
            ])."\n",
            '{{ resourceRelations }}' => implode('', array_map(
                fn (CrudBlueprint $d) => "            // Hanya ada di detail (show) & hasil tambah/ubah.\n"
                    ."            '{$d->detailKey}' => {$d->model}Resource::collection(\$this->whenLoaded('{$d->relation()}')),\n",
                $details,
            )),
            '{{ apiLoad }}' => $details === []
                ? ''
                : '->load(['.implode(', ', array_map(fn (CrudBlueprint $d) => "'{$d->relation()}'", $details)).'])',
            '{{ apiControllerImports }}' => $details === [] ? '' : "use Illuminate\\Support\\Facades\\DB;\n",
            '{{ apiStoreBody }}' => $details === []
                ? "        {$var} = {$bp->model}::create(\$request->validated());"
                : $this->lines([
                    "        {$var} = DB::connection({$connection})->transaction(function () use (\$request) {",
                    "            {$var} = {$bp->model}::create(\$request->headerData());",
                    ...array_merge(...array_map(fn (CrudBlueprint $d) => [
                        '',
                        "            foreach (\$request->detailRows('{$d->detailKey}') as \$row) {",
                        "                {$var}->{$d->relation()}()->create(\$row);",
                        '            }',
                    ], $details)),
                    '',
                    "            return {$var};",
                    '        });',
                ]),
            '{{ apiDetailTest }}' => $details === [] ? '' : $this->lines([
                '',
                "it('{$bp->table} API: tambah beserta baris detail dalam SATU request', function () {",
                '    Sanctum::actingAs($this->admin);',
                '',
                "    \$this->postJson('{$url}', [",
                "        ...Arr::only({$bp->model}::factory()->make()->toArray(), \$this->fields),",
                ...array_map(
                    fn (CrudBlueprint $d) => "        '{$d->detailKey}' => [Arr::only({$d->model}::factory()->make()->toArray(), ["
                        .implode(', ', array_map(fn (CrudField $f) => "'{$f->name}'", $d->fields)).'])],',
                    $details,
                ),
                '    ])',
                '        ->assertCreated()',
                ...array_map(
                    fn (CrudBlueprint $d, int $i) => "        ->assertJsonCount(1, 'data.{$d->detailKey}')".($i === count($details) - 1 ? ';' : ''),
                    $details,
                    array_keys($details),
                ),
                '})'.$this->crudTestSkip($bp->fields).';',
            ])."\n",
            '{{ apiRequiredTest }}' => $required === [] ? '' : $this->lines([
                '',
                "it('{$bp->table} API: field wajib → 422 VALIDATION_ERROR', function () {",
                '    Sanctum::actingAs($this->admin);',
                '',
                "    \$this->postJson('{$url}', [])",
                '        ->assertUnprocessable()',
                "        ->assertJsonPath('code', 'VALIDATION_ERROR')",
                '        ->assertJsonValidationErrors(['.implode(', ', array_map(fn (CrudField $f) => "'{$f->name}'", $required)).']);',
                '});',
            ])."\n",
        ];
    }

    /** Satu kolom di Resource API, mis. 'joined_on' => $this->joined_on?->format('Y-m-d'). */
    private function resourceField(CrudField $f): string
    {
        $access = $f->required ? '->' : '?->';

        $value = match ($f->type) {
            // Format sama dengan cast model (dan input form web).
            'date' => "\$this->{$f->name}{$access}format('Y-m-d')",
            'datetime' => "\$this->{$f->name}{$access}format('Y-m-d H:i')",
            default => "\$this->{$f->name}",
        };

        return "            '{$f->name}' => {$value},";
    }

    /**
     * @param  array<string, string>  $vars
     */
    private function apiController(array $vars): string
    {
        $content = $this->stub('api-controller', $vars);

        // Tanpa kolom pencarian, import Builder tidak terpakai.
        return str_contains($content, 'Builder $q')
            ? $content
            : str_replace("use Illuminate\\Database\\Eloquent\\Builder;\n", '', $content);
    }

    private function sectionFile(CrudBlueprint $d): string
    {
        return Str::kebab($d->model).'-section';
    }

    private function rowsFile(CrudBlueprint $d): string
    {
        return Str::kebab($d->model).'-rows';
    }

    public function migrationPath(CrudBlueprint $bp, string $timestamp): string
    {
        return "database/migrations/{$timestamp}_create_{$bp->table}_table.php";
    }

    /** Baris case enum PermissionName yang disisipkan. */
    public function enumCases(CrudBlueprint $bp): string
    {
        return $this->lines(array_map(
            fn (string $action) => "    case {$bp->enumPrefix()}".Str::studly($action)." = '{$bp->permission($action)}';",
            ['view', 'create', 'update', 'delete'],
        ));
    }

    public function enumDescriptions(CrudBlueprint $bp): string
    {
        $what = Str::lower($bp->plural());
        $texts = [
            'view' => "View {$what}",
            'create' => "Create {$what}",
            'update' => "Edit {$what}",
            'delete' => "Delete {$what}",
        ];

        return $this->lines(array_map(
            fn (string $action, string $text) => "            self::{$bp->enumPrefix()}".Str::studly($action)." => '{$text}',",
            array_keys($texts),
            $texts,
        ));
    }

    public function enumLabel(CrudBlueprint $bp): string
    {
        return "            '{$bp->table}' => '{$bp->plural()}',";
    }

    public function routes(CrudBlueprint $bp): string
    {
        return $this->lines([
            "Route::middleware('can:{$bp->permission('view')}')->group(function () {",
            "    Route::resource('{$bp->slug()}', App\\Http\\Controllers\\Admin\\{$bp->model}Controller::class)->except('show');",
            // Detail bertingkat & scoped: baris detail harus milik induk di URL.
            // Parameter {po_item} → relasi poItems() di induk (tebakan Laravel).
            ...array_map(
                fn (CrudBlueprint $d) => "    Route::resource('{$bp->slug()}.{$d->detailSegment()}', App\\Http\\Controllers\\Admin\\{$d->model}Controller::class)->except(['index', 'show'])->scoped();",
                $bp->details,
            ),
            '});',
        ]);
    }

    public function formFile(CrudBlueprint $bp): string
    {
        return Str::kebab($bp->model).'-form';
    }

    /**
     * @return array<string, string>
     */
    private function vars(CrudBlueprint $bp): array
    {
        $fields = $bp->fields;
        $title = $bp->titleField();
        $searchable = array_values(array_filter($fields, fn (CrudField $f) => $f->searchable()));
        $listed = array_values(array_filter($fields, fn (CrudField $f) => $f->listed()));
        $hasBadge = (bool) array_filter($listed, fn (CrudField $f) => in_array($f->type, ['boolean', 'select'], true));
        $required = array_values(array_filter($fields, fn (CrudField $f) => $f->required));

        return [
            '{{ table }}' => $bp->table,
            '{{ model }}' => $bp->model,
            '{{ variable }}' => $bp->variable(),
            '{{ slug }}' => $bp->slug(),
            '{{ url }}' => $bp->url(),
            '{{ enum }}' => $bp->enumPrefix(),
            '{{ routeParameter }}' => $bp->routeParameter(),
            '{{ singular }}' => $bp->singular(),
            '{{ singularTitle }}' => Str::ucfirst($bp->singular()),
            '{{ plural }}' => $bp->plural(),
            '{{ pluralLower }}' => Str::lower($bp->plural()),
            '{{ formFile }}' => $this->formFile($bp),
            '{{ titleExpr }}' => match (true) {
                $title === null => 'item.id',
                $title->required => "item.{$title->name}",
                default => "item.{$title->name} ?? item.id",
            },

            // PHP
            '{{ ulidColumn }}' => $bp->ulid ? self::ULID_COLUMN."\n" : '',
            '{{ columns }}' => $this->lines(array_map($this->migrationColumn(...), $fields)),
            '{{ properties }}' => $this->lines(array_map($this->docProperty(...), $fields)),
            '{{ fillable }}' => implode(', ', array_map(fn (CrudField $f) => "'{$f->name}'", $fields)),
            '{{ casts }}' => $this->casts($fields),
            '{{ definition }}' => $this->lines(array_map(
                fn (CrudField $f) => "            '{$f->name}' => {$this->fakeValue($f)},",
                $fields,
            )),
            '{{ rules }}' => $this->lines(array_map(
                fn (CrudField $f) => "            '{$f->name}' => [".implode(', ', array_map(
                    fn (string $r) => "'{$r}'",
                    $this->rules($f),
                )).'],',
                $fields,
            )),
            '{{ sorts }}' => $this->lines([
                ...array_map(
                    fn (CrudField $f) => "        '{$f->name}' => '{$f->name}',",
                    array_values(array_filter($fields, fn (CrudField $f) => $f->sortable() && $f->name !== $this->defaultSortColumn($bp))),
                ),
                "        '{$this->defaultSortColumn($bp)}' => '{$this->defaultSortColumn($bp)}',",
            ]),
            // Tabel tanpa created_at diurutkan berdasarkan id (terbaru dulu).
            '{{ defaultSort }}' => '-'.$this->defaultSortColumn($bp),
            '{{ modelExtras }}' => $this->modelExtras($bp),
            '{{ modelImports }}' => $this->auditImports($bp)
                .($bp->hasDetails() ? "use Illuminate\\Database\\Eloquent\\Relations\\HasMany;\n" : ''),
            '{{ classAttributes }}' => $this->hasAuditUser($bp) ? "#[ObservedBy([FillAuditUser::class])]\n" : '',
            '{{ implements }}' => $this->hasAuditUser($bp) ? ' implements HasAuditUser' : '',
            '{{ auditMethod }}' => $this->auditMethod($bp),
            '{{ relations }}' => $this->relations($bp),
            '{{ timestampProperties }}' => $bp->timestamps
                ? "\n * @property Carbon|null \${$this->createdAtColumn($bp)}\n * @property Carbon|null \${$this->updatedAtColumn($bp)}"
                : '',
            '{{ dbTable }}' => $bp->dbTable(),
            // Migration: tabel dibuat (dan di-drop) di database tujuan.
            '{{ schema }}' => $bp->connection !== null ? "Schema::connection('{$bp->connection}')->" : 'Schema::',
            '{{ migrationNote }}' => $bp->connection !== null
                ? "\n *\n * Tabel dibuat di koneksi \"{$bp->connection}\" (config/database.php), bukan\n * database aplikasi. Catatan migration tetap di tabel migrations database aplikasi."
                : '',
            ...$this->detailMigrationVars($bp),
            '{{ testSetup }}' => $this->testSetup($bp),
            '{{ crudTestSkip }}' => $this->crudTestSkip($fields),
            '{{ tsTimestamps }}' => $bp->timestamps
                ? "    {$this->createdAtColumn($bp)}: string;\n    {$this->updatedAtColumn($bp)}: string;\n"
                : '',

            // Izin akun database (SQL Server): lapisan kedua setelah permission aplikasi.
            '{{ policyImports }}' => $bp->connection !== null ? "use App\\Support\\TableAccess;\n" : '',
            '{{ policyNote }}' => $bp->connection !== null
                ? "\n *\n * Tabel ada di koneksi \"{$bp->connection}\": aksi juga butuh izin akun database\n * (lihat App\\Support\\TableAccess), mis. INSERT untuk create."
                : '',
            '{{ dbView }}' => $this->dbCheck($bp, 'SELECT'),
            '{{ dbCreate }}' => $this->dbCheck($bp, 'INSERT'),
            '{{ dbUpdate }}' => $this->dbCheck($bp, 'UPDATE'),
            '{{ dbDelete }}' => $this->dbCheck($bp, 'DELETE'),

            ...$this->masterDetailVars($bp),

            // Form: tombol Cancel.
            '{{ factoryImports }}' => '',
            '{{ formControllerImport }}' => "import {$bp->model}Controller from '@/actions/App/Http/Controllers/Admin/{$bp->model}Controller';\n",
            '{{ cancelDefault }}' => "{$bp->model}Controller.index.url()",
            '{{ fkTsField }}' => '',
            '{{ query }}' => $this->searchQuery($bp, $searchable),
            '{{ fieldNames }}' => implode(', ', array_map(fn (CrudField $f) => "'{$f->name}'", $fields)),
            '{{ requiredTest }}' => $required === [] ? '' : $this->lines([
                '',
                "it('{$bp->table}: field wajib harus diisi', function () {",
                '    $this->actingAs($this->admin)',
                "        ->post('{$bp->url()}', [])",
                '        ->assertSessionHasErrors(['.implode(', ', array_map(fn (CrudField $f) => "'{$f->name}'", $required)).']);',
                '});',
            ]),

            // React: halaman daftar
            '{{ searchIcon }}' => $searchable === [] ? '' : 'Search, ',
            '{{ statusBadgeImport }}' => $hasBadge ? "import { StatusBadge } from '@/components/status-badge';\n" : '',
            '{{ inputImport }}' => $searchable === [] ? '' : "import { Input } from '@/components/ui/input';\n",
            '{{ tableQueryVars }}' => $searchable === [] ? 'apply' : 'search, setSearch, apply',
            '{{ searchBox }}' => $searchable === [] ? '' : $this->searchBox($bp, $searchable),
            '{{ emptyDescription }}' => $searchable === []
                ? "'Add the first one to get started.'"
                : "search ? 'Try a different keyword.' : 'Add the first one to get started.'",
            ...$this->formVars($fields),
        ];
    }

    /**
     * Penanda master–detail untuk file INDUK (controller, request, form,
     * halaman tambah & edit). Tanpa detail: semuanya kosong/bawaan, jadi
     * CRUD biasa tetap sama persis seperti sebelumnya.
     *
     * Form tambah induk berisi baris isian setiap detail; header + semua
     * baris disimpan dalam SATU transaksi. Di halaman edit, setiap detail
     * punya bagian sendiri (daftar + tambah/ubah/hapus per baris).
     *
     * @return array<string, string>
     */
    private function masterDetailVars(CrudBlueprint $bp): array
    {
        $var = "\${$bp->variable()}";
        $details = $bp->details;

        if ($details === []) {
            return [
                '{{ controllerImports }}' => '',
                '{{ createProps }}' => '',
                '{{ storeDoc }}' => '',
                '{{ storeBody }}' => "        {$bp->model}::create(\$request->validated());",
                '{{ editProps }}' => '',
                '{{ destroyBody }}' => "        {$var}->delete();",
                '{{ requestImports }}' => '',
                '{{ requestNote }}' => '',
                '{{ requestConstants }}' => '',
                '{{ detailRulesMerge }}' => '',
                '{{ requestMethods }}' => '',
                '{{ detailImports }}' => '',
                '{{ detailTypeImports }}' => '',
                '{{ detailParams }}' => '',
                '{{ detailPropTypes }}' => '',
                '{{ detailRender }}' => '',
                '{{ createPageProps }}' => '',
                '{{ createDescription }}' => "Create a new {$bp->singular()}.",
                '{{ createFormProps }}' => '',
                '{{ detailRowsImports }}' => '',
                '{{ formDetailParam }}' => '',
                '{{ formDetailPropType }}' => '',
                '{{ formClass }}' => 'max-w-3xl space-y-6',
                '{{ cardClass }}' => '',
                '{{ detailRowsRender }}' => '',
                '{{ testImports }}' => '',
                '{{ detailTest }}' => '',
            ];
        }

        $connection = $bp->connection !== null ? "'{$bp->connection}'" : '';
        $keys = array_map(fn (CrudBlueprint $d) => (string) $d->detailKey, $details);
        $titles = implode(' & ', array_map(fn (CrudBlueprint $d) => Str::lower($d->detailTitle()), $details));

        return [
            // Controller
            '{{ controllerImports }}' => "use Illuminate\\Support\\Facades\\DB;\n",
            '{{ createProps }}' => ", [\n            'maxDetails' => Save{$bp->model}Request::MAX_DETAILS,\n        ]",
            '{{ storeDoc }}' => $this->lines([
                '    /**',
                '     * '.Str::ucfirst($bp->singular())." + baris {$titles} disimpan dalam SATU transaksi:",
                '     * bila satu baris gagal, header & baris lain ikut dibatalkan.',
                '     * Validasi semua baris sudah selesai sebelum transaksi dimulai.',
                '     */',
            ])."\n",
            '{{ storeBody }}' => $this->lines([
                "        DB::connection({$connection})->transaction(function () use (\$request) {",
                "            {$var} = {$bp->model}::create(\$request->headerData());",
                ...array_merge(...array_map(fn (CrudBlueprint $d) => [
                    '',
                    "            // {$d->dbTable()}.{$d->foreignKey} diisi otomatis lewat relasi {$d->relation()}().",
                    "            foreach (\$request->detailRows('{$d->detailKey}') as \$row) {",
                    "                {$var}->{$d->relation()}()->create(\$row);",
                    '            }',
                ], $details)),
                '        });',
            ]),
            '{{ editProps }}' => implode('', array_map(
                fn (CrudBlueprint $d) => "            // {$d->detailTitle()} milik {$bp->singular()} ini, 10 per halaman (?{$d->detailKey}_page=2).\n"
                    ."            '{$d->relation()}' => {$var}->{$d->relation()}()->orderByDesc('id')->paginate(10, ['*'], '{$d->detailKey}_page')->withQueryString(),\n",
                $details,
            )),
            '{{ destroyBody }}' => $this->lines([
                '        // Hapus semua detail lalu induknya dalam SATU transaksi: berhasil semua atau batal semua.',
                "        DB::connection({$connection})->transaction(function () use ({$var}) {",
                ...array_map(fn (CrudBlueprint $d) => "            {$var}->{$d->relation()}()->delete();", $details),
                "            {$var}->delete();",
                '        });',
            ]),

            // Request induk: aturan baris detail (hanya saat TAMBAH)
            '{{ requestImports }}' => "use Illuminate\\Support\\Str;\n",
            '{{ requestNote }}' => $this->lines([
                '',
                ' *',
                ' * Saat TAMBAH, form juga mengirim baris detail ('.implode(', ', array_map(fn (string $k) => "{$k}[]", $keys)).')',
                ' * yang disimpan bersama header dalam satu transaksi. Saat UBAH, detail',
                ' * dikelola terpisah lewat controller detail masing-masing.',
            ]),
            '{{ requestConstants }}' => $this->lines([
                '    /** Batas baris per detail sekali simpan, agar satu request tidak terlalu besar. */',
                '    public const MAX_DETAILS = 100;',
                '',
                '    /** Kunci input baris detail (sama dengan nama detail). */',
                '    private const DETAIL_KEYS = ['.implode(', ', array_map(fn (string $k) => "'{$k}'", $keys)).'];',
                '',
            ])."\n",
            '{{ detailRulesMerge }}' => ' + $this->detailRules()',
            '{{ requestMethods }}' => $this->requestMethods($bp),

            // Halaman edit: satu bagian per detail.
            '{{ detailImports }}' => implode('', array_map(
                fn (CrudBlueprint $d) => "import { {$d->model}Section } from '@/components/admin/{$this->sectionFile($d)}';\n"
                    ."import type { {$d->model} } from '@/components/admin/{$this->formFile($d)}';\n",
                $details,
            )),
            '{{ detailTypeImports }}' => "import type { Paginated } from '@/types';\n",
            '{{ detailParams }}' => implode('', array_map(fn (CrudBlueprint $d) => "\n    {$d->relation()},", $details))."\n    abilities,",
            '{{ detailPropTypes }}' => implode('', array_map(fn (CrudBlueprint $d) => "\n    {$d->relation()}: Paginated<{$d->model}>;", $details))
                ."\n    abilities: { update: boolean };",
            '{{ detailRender }}' => implode('', array_map(
                fn (CrudBlueprint $d) => "\n                <{$d->model}Section\n                    parentKey={routeKey(item)}\n                    details={{$d->relation()}}\n                    canManage={abilities.update}\n                />",
                $details,
            )),

            // Halaman tambah + form induk: baris isian setiap detail.
            '{{ createPageProps }}' => '{ maxDetails }: { maxDetails: number }',
            '{{ createDescription }}' => "Create a new {$bp->singular()} with its {$titles} in one save.",
            '{{ createFormProps }}' => ' maxDetails={maxDetails}',
            '{{ detailRowsImports }}' => implode('', array_map(
                fn (CrudBlueprint $d) => "import { {$d->model}Rows } from '@/components/admin/{$this->rowsFile($d)}';\n",
                $details,
            )),
            '{{ formDetailParam }}' => "\n    maxDetails,",
            '{{ formDetailPropType }}' => "\n    /**\n     * Add page only: rows for "
                .implode(', ', array_map(fn (CrudBlueprint $d) => $d->dbTable(), $details))
                .", saved together with the\n     * {$bp->singular()} in one transaction. The edit page manages them separately.\n     */\n    maxDetails?: number;",
            '{{ formClass }}' => 'space-y-6',
            '{{ cardClass }}' => ' className="max-w-3xl"',
            '{{ detailRowsRender }}' => $this->lines([
                '',
                '                    {maxDetails !== undefined && (',
                '                        <>',
                ...array_map(fn (CrudBlueprint $d) => "                            <{$d->model}Rows errors={errors} max={maxDetails} />", $details),
                '                        </>',
                '                    )}',
            ]),

            // Test: header + satu baris tiap detail tersimpan bersama.
            '{{ testImports }}' => implode('', array_map(fn (CrudBlueprint $d) => "use App\\Models\\{$d->model};\n", $details)),
            '{{ detailTest }}' => $this->lines([
                '',
                "it('{$bp->table}: tambah beserta baris {$titles} dalam satu simpan', function () {",
                '    $payload = [',
                "        ...Arr::only({$bp->model}::factory()->make()->toArray(), \$this->fields),",
                ...array_map(
                    fn (CrudBlueprint $d) => "        '{$d->detailKey}' => [Arr::only({$d->model}::factory()->make()->toArray(), ["
                        .implode(', ', array_map(fn (CrudField $f) => "'{$f->name}'", $d->fields)).'])],',
                    $details,
                ),
                '    ];',
                '',
                "    \$this->actingAs(\$this->admin)->post('{$bp->url()}', \$payload)->assertRedirect('{$bp->url()}');",
                '',
                "    \$item = {$bp->model}::query()->latest('id')->firstOrFail();",
                '',
                ...array_map(fn (CrudBlueprint $d) => "    expect(\$item->{$d->relation()}()->count())->toBe(1);", $details),
                '})'.$this->crudTestSkip($bp->fields).';',
            ])."\n",
        ];
    }

    /**
     * Method tambahan request induk untuk baris detail.
     */
    private function requestMethods(CrudBlueprint $bp): string
    {
        $details = $bp->details;
        $rowRules = array_map(
            fn (CrudBlueprint $d) => "            ...\$this->rowRules('{$d->detailKey}', Save{$d->model}Request::fieldRules()),",
            $details,
        );

        return $this->lines([
            '',
            '    /**',
            '     * Baris detail di form "Add": {kunci}.*.{kolom}, aturan per kolom diambil',
            '     * dari request detail masing-masing. Saat ubah: tidak ada (kosong).',
            '     *',
            '     * @return array<string, list<mixed>>',
            '     */',
            '    private function detailRules(): array',
            '    {',
            '        if (! $this->isCreating()) {',
            '            return [];',
            '        }',
            '',
            '        return [',
            ...$rowRules,
            '        ];',
            '    }',
            '',
            '    /**',
            '     * @param  array<string, list<mixed>>  $fieldRules',
            '     * @return array<string, list<mixed>>',
            '     */',
            '    private function rowRules(string $key, array $fieldRules): array',
            '    {',
            "        \$rules = [\$key => ['nullable', 'array', 'max:'.self::MAX_DETAILS]];",
            '',
            '        foreach ($fieldRules as $field => $columnRules) {',
            '            $rules["{$key}.*.{$field}"] = $columnRules;',
            '        }',
            '',
            '        return $rules;',
            '    }',
            '',
            '    /**',
            '     * Nama kolom di pesan error baris detail, mis. "item 2 trans no".',
            '     *',
            '     * @return array<string, string>',
            '     */',
            '    public function attributes(): array',
            '    {',
            '        $attributes = [];',
            '',
            '        foreach (array_keys($this->detailRules()) as $rule) {',
            "            if (! str_contains(\$rule, '.*.')) {",
            '                continue;',
            '            }',
            '',
            "            [\$key, \$field] = explode('.*.', \$rule, 2);",
            '',
            '            foreach (array_keys((array) $this->input($key, [])) as $index) {',
            "                \$attributes[\"{\$key}.{\$index}.{\$field}\"] = Str::singular(str_replace('_', ' ', \$key)).' '.((int) \$index + 1).' '.str_replace('_', ' ', \$field);",
            '            }',
            '        }',
            '',
            '        return $attributes;',
            '    }',
            '',
            '    /**',
            '     * Data header saja (tanpa baris detail), untuk create().',
            '     *',
            '     * @return array<string, mixed>',
            '     */',
            '    public function headerData(): array',
            '    {',
            '        return collect($this->validated())->except(self::DETAIL_KEYS)->all();',
            '    }',
            '',
            '    /**',
            '     * Baris satu detail yang sudah lolos validasi (kosong saat ubah).',
            '     *',
            '     * @return list<array<array-key, mixed>>',
            '     */',
            '    public function detailRows(string $key): array',
            '    {',
            '        $rows = $this->validated($key);',
            '',
            '        return is_array($rows) ? array_values(array_filter($rows, is_array(...))) : [];',
            '    }',
            '',
            '    private function isCreating(): bool',
            '    {',
            "        return ! \$this->route('{$bp->routeParameter()}') instanceof {$bp->model};",
            '    }',
        ])."\n";
    }

    /**
     * Migration tabel detail BARU (mode "Create a new table"): dibuat setelah
     * induk (foreign key menunjuk ke induk) dan di-drop sebelum induk.
     *
     * @return array<string, string>
     */
    private function detailMigrationVars(CrudBlueprint $bp): array
    {
        $details = array_values(array_filter($bp->details, fn (CrudBlueprint $d) => ! $d->usesExistingTable()));
        $schema = $bp->connection !== null ? "Schema::connection('{$bp->connection}')->" : 'Schema::';

        if ($details === []) {
            return ['{{ detailTables }}' => '', '{{ detailDrops }}' => ''];
        }

        return [
            '{{ detailTables }}' => implode('', array_map(fn (CrudBlueprint $d) => $this->lines([
                '',
                "        // {$d->detailTitle()}: baris milik satu {$bp->singular()}.",
                "        {$schema}create('{$d->table}', function (Blueprint \$table) {",
                '            $table->id();',
                ...($d->ulid ? [self::ULID_COLUMN] : []),
                "            \$table->foreignId('{$d->foreignKey}')->constrained('{$bp->table}');",
                ...array_map($this->migrationColumn(...), $d->fields),
                '            $table->timestamps();',
                '        });',
            ])."\n", $details)),
            // Detail dulu, baru induk (foreign key).
            '{{ detailDrops }}' => implode('', array_map(
                fn (CrudBlueprint $d) => "        {$schema}dropIfExists('{$d->table}');\n",
                array_reverse($details),
            )),
        ];
    }

    /** " && TableAccess::allows(...)" untuk tabel di koneksi lain. */
    private function dbCheck(CrudBlueprint $bp, string $action): string
    {
        return $bp->connection === null
            ? ''
            : "\n            && TableAccess::allows('{$bp->connection}', '{$bp->dbTable()}', '{$action}')";
    }

    /**
     * Penanda untuk file DETAIL (master–detail): penanda biasa dari
     * blueprint detail + info induknya.
     *
     * @return array<string, string>
     */
    private function detailVars(CrudBlueprint $parent, CrudBlueprint $detail): array
    {
        $title = $parent->titleField();

        return [
            ...$this->vars($detail),
            '{{ parentModel }}' => $parent->model,
            '{{ parentVariable }}' => $parent->variable(),
            '{{ parentSlug }}' => $parent->slug(),
            '{{ parentParameter }}' => $parent->routeParameter(),
            '{{ parentSingular }}' => $parent->singular(),
            '{{ parentPlural }}' => $parent->plural(),
            '{{ parentTitleColumn }}' => $title !== null ? "'{$title->name}'" : '',
            '{{ foreignKey }}' => (string) $detail->foreignKey,
            '{{ relation }}' => $detail->relation(),
            '{{ detailSegment }}' => $detail->detailSegment(),
            '{{ detailParam }}' => $detail->detailParam(),
            '{{ detailVariable }}' => $detail->detailVariable(),
            '{{ detailInputKey }}' => (string) $detail->detailKey,
            '{{ detailTitle }}' => $detail->detailTitle(),
            '{{ detailTitleLower }}' => Str::lower($detail->detailTitle()),
            '{{ detailSingular }}' => $detail->detailSingular(),
            '{{ detailSingularTitle }}' => Str::ucfirst($detail->detailSingular()),
            '{{ rowColumns }}' => $this->lines(array_map($this->rowColumn(...), $detail->fields)),
            '{{ factoryImports }}' => "use App\\Models\\{$parent->model};\n",
            '{{ modelImports }}' => $this->auditImports($detail)."use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;\n",
            '{{ relations }}' => $this->relations($detail, $parent),
            // Tabel Details tidak bisa diurutkan (urut id terbaru), jadi tanpa ikon sort.
            '{{ tableColumns }}' => (string) preg_replace('/\n\s*enableSorting: true,/', '', $this->vars($detail)['{{ tableColumns }}']),
            // Kolom penghubung tidak ada di form, tetapi dikirim server (dipakai URL aksi baris).
            '{{ fkTsField }}' => "    {$detail->foreignKey}: number;\n",
            '{{ formControllerImport }}' => '',
            '{{ cancelDefault }}' => "''",
            // Factory detail membuat induknya juga.
            '{{ definition }}' => $this->lines([
                "            '{$detail->foreignKey}' => {$parent->model}::factory(),",
                ...array_map(fn (CrudField $f) => "            '{$f->name}' => {$this->fakeValue($f)},", $detail->fields),
            ]),
        ];
    }

    /** Kolom waktu "dibuat" (created_at, atau mis. created_date di tabel ERP). */
    private function createdAtColumn(CrudBlueprint $bp): ?string
    {
        return $bp->timestamps ? ($bp->auditColumn('created_at') ?? 'created_at') : null;
    }

    private function updatedAtColumn(CrudBlueprint $bp): ?string
    {
        return $bp->timestamps ? ($bp->auditColumn('updated_at') ?? 'updated_at') : null;
    }

    private function defaultSortColumn(CrudBlueprint $bp): string
    {
        return $this->createdAtColumn($bp) ?? 'id';
    }

    private function hasAuditUser(CrudBlueprint $bp): bool
    {
        return $bp->auditColumn('created_by') !== null || $bp->auditColumn('updated_by') !== null;
    }

    /**
     * Properti tambahan Model untuk tabel yang tidak mengikuti konvensi
     * Laravel: koneksi lain, nama tabel, nama kolom waktu, kolom user audit.
     */
    private function modelExtras(CrudBlueprint $bp): string
    {
        $lines = [];

        if ($bp->connection !== null) {
            $lines[] = "    /** Database lain (config/database.php), bukan koneksi default. */\n    protected \$connection = '{$bp->connection}';\n";
        }

        // Konvensi Laravel: model Supplier ↔ tabel "suppliers".
        if ($bp->dbTable() !== Str::snake(Str::pluralStudly($bp->model))) {
            $lines[] = "    /** Tabel yang sudah ada (bukan nama bawaan Laravel). */\n    protected \$table = '{$bp->dbTable()}';\n";
        }

        if (! $bp->timestamps) {
            $lines[] = "    /** Tabel ini tidak punya kolom waktu dibuat & diubah. */\n    public \$timestamps = false;\n";
        } elseif ($this->createdAtColumn($bp) !== 'created_at' || $this->updatedAtColumn($bp) !== 'updated_at') {
            $lines[] = "    /** Nama kolom waktu di tabel ini (Laravel mengisinya otomatis). */\n"
                ."    const CREATED_AT = '{$this->createdAtColumn($bp)}';\n\n"
                ."    const UPDATED_AT = '{$this->updatedAtColumn($bp)}';\n";
        }

        return $lines === [] ? '' : implode("\n", $lines)."\n";
    }

    private function auditImports(CrudBlueprint $bp): string
    {
        return $this->hasAuditUser($bp)
            ? "use App\\Contracts\\HasAuditUser;\nuse App\\Observers\\FillAuditUser;\nuse Illuminate\\Database\\Eloquent\\Attributes\\ObservedBy;\n"
            : '';
    }

    /** Kolom "dibuat/diubah oleh": diisi App\Observers\FillAuditUser. */
    private function auditMethod(CrudBlueprint $bp): string
    {
        if (! $this->hasAuditUser($bp)) {
            return '';
        }

        $quote = fn (?string $column) => $column === null ? 'null' : "'{$column}'";
        $max = (int) ($bp->audit['user_max'] ?? 255);

        return $this->lines([
            '',
            '    /**',
            '     * Kolom user audit, diisi otomatis dengan NAMA user yang login',
            '     * (App\Observers\FillAuditUser). Tidak tampil di form.',
            '     *',
            '     * @return array{created_by: string|null, updated_by: string|null, max: int}',
            '     */',
            '    public function auditUserColumns(): array',
            '    {',
            "        return ['created_by' => {$quote($bp->auditColumn('created_by'))}, 'updated_by' => {$quote($bp->auditColumn('updated_by'))}, 'max' => {$max}];",
            '    }',
        ])."\n";
    }

    /** Relasi master–detail. */
    private function relations(CrudBlueprint $bp, ?CrudBlueprint $parent = null): string
    {
        if ($bp->hasDetails()) {
            return implode('', array_map(fn (CrudBlueprint $d) => $this->lines([
                '',
                '    /**',
                "     * Baris {$d->detailSingular()} ({$d->dbTable()}.{$d->foreignKey} → {$bp->dbTable()}.id).",
                '     *',
                "     * @return HasMany<{$d->model}, \$this>",
                '     */',
                "    public function {$d->relation()}(): HasMany",
                '    {',
                "        return \$this->hasMany({$d->model}::class, '{$d->foreignKey}');",
                '    }',
            ])."\n", $bp->details));
        }

        if ($parent !== null && $bp->foreignKey !== null) {
            $method = Str::camel($parent->model);

            return $this->lines([
                '',
                '    /**',
                "     * @return BelongsTo<{$parent->model}, \$this>",
                '     */',
                "    public function {$method}(): BelongsTo",
                '    {',
                "        return \$this->belongsTo({$parent->model}::class, '{$bp->foreignKey}');",
                '    }',
            ])."\n";
        }

        return '';
    }

    /**
     * Tabel yang sudah ada TIDAK dibuat oleh migration, jadi tidak ada di
     * database test (RefreshDatabase hanya menjalankan migration). Test
     * dilewati sampai Anda membuat migration untuk tabel itu.
     */
    private function testSetup(CrudBlueprint $bp): string
    {
        // Koneksi lain (mis. SQL Server ERP) TIDAK di-reset RefreshDatabase:
        // test akan menulis data sungguhan, jadi tidak dijalankan otomatis.
        if ($bp->connection !== null) {
            return $this->lines([
                "    \$this->markTestSkipped('Uses the \"{$bp->connection}\" connection, which RefreshDatabase does not reset. Test it manually on a training database.');",
                '',
            ])."\n";
        }

        if (! $bp->usesExistingTable()) {
            return '';
        }

        return $this->lines([
            "    if (! Schema::hasTable('{$bp->dbTable()}')) {",
            "        \$this->markTestSkipped('Table \"{$bp->dbTable()}\" is not created by a migration, so it does not exist in the test database.');",
            '    }',
            '',
        ])."\n";
    }

    /**
     * Foreign key wajib menunjuk ke baris yang ada; factory belum tahu caranya.
     *
     * @param  list<CrudField>  $fields
     */
    private function crudTestSkip(array $fields): string
    {
        $foreign = array_values(array_filter($fields, fn (CrudField $f) => $f->references !== null));

        if ($foreign === []) {
            return '';
        }

        $names = implode(', ', array_map(fn (CrudField $f) => $f->name, $foreign));

        return "->skip('Foreign key column(s) {$names}: set them in the factory first')";
    }

    /**
     * @param  array<string, string>  $vars
     */
    private function stub(string $name, array $vars): string
    {
        $path = ($this->stubPath ?? base_path('stubs/crud'))."/{$name}.stub";

        if (! is_file($path)) {
            throw new RuntimeException("CRUD stub not found: {$path}");
        }

        $content = (string) file_get_contents($path);

        // Kolom tabel React memakai penanda yang sama ({{ columns }}) dengan
        // kolom migration, jadi dibedakan per stub.
        if ($name === 'page-index' || $name === 'details-section') {
            $vars['{{ columns }}'] = $vars['{{ tableColumns }}'];
        }

        return strtr($content, $vars);
    }

    /**
     * @param  array<string, string>  $vars
     */
    private function controller(CrudBlueprint $bp, array $vars): string
    {
        $content = $this->stub('controller', $vars);

        // Tanpa kolom pencarian, import Builder tidak terpakai.
        return str_contains($content, 'Builder $q')
            ? $content
            : str_replace("use Illuminate\\Database\\Eloquent\\Builder;\n", '', $content);
    }

    private function migrationColumn(CrudField $f): string
    {
        $column = match ($f->type) {
            // Panjang bawaan Laravel = 255; panjang lain ditulis eksplisit.
            'string' => $f->max !== null && $f->max !== 255
                ? "\$table->string('{$f->name}', {$f->max})"
                : "\$table->string('{$f->name}')",
            'text' => "\$table->text('{$f->name}')",
            'integer' => "\$table->integer('{$f->name}')",
            // Uang/angka pecahan SELALU decimal, bukan float.
            'decimal' => "\$table->decimal('{$f->name}', 15, 2)",
            'boolean' => "\$table->boolean('{$f->name}')->default(false)",
            'date' => "\$table->date('{$f->name}')",
            'datetime' => "\$table->dateTime('{$f->name}')",
            'select' => "\$table->string('{$f->name}', 50)",
            default => throw new RuntimeException("Unknown field type [{$f->type}]."),
        };

        if (! $f->required && $f->type !== 'boolean') {
            $column .= '->nullable()';
        }

        return "            {$column};";
    }

    private function docProperty(CrudField $f): string
    {
        $type = match ($f->type) {
            'integer' => 'int',
            'boolean' => 'bool',
            'date', 'datetime' => 'Carbon',
            default => 'string',
        };
        $nullable = ! $f->required && $f->type !== 'boolean' ? '|null' : '';

        return " * @property {$type}{$nullable} \${$f->name}";
    }

    /**
     * @param  list<CrudField>  $fields
     */
    private function casts(array $fields): string
    {
        $casts = [];

        foreach ($fields as $f) {
            $cast = match ($f->type) {
                'integer' => 'integer',
                'decimal' => 'decimal:2',
                'boolean' => 'boolean',
                'date' => 'date:Y-m-d',
                // Format yang langsung cocok untuk <input type="datetime-local"> (setelah spasi → "T").
                'datetime' => 'datetime:Y-m-d H:i',
                default => null,
            };

            if ($cast !== null) {
                $casts[] = "            '{$f->name}' => '{$cast}',";
            }
        }

        return $casts === [] ? '[]' : "[\n".$this->lines($casts)."\n        ]";
    }

    private function fakeValue(CrudField $f): string
    {
        return match ($f->type) {
            // Kolom pendek (mis. varchar(10)) → teks acak sesuai panjangnya.
            'string' => $f->max !== null && $f->max < 30
                ? "fake()->lexify('".str_repeat('?', min($f->max, 10))."')"
                : "ucwords(fake()->word().' '.fake()->word())",
            'text' => 'fake()->paragraph()',
            // Rentang kecil agar muat juga di kolom tinyint/smallint.
            'integer' => 'fake()->numberBetween(0, 100)',
            'decimal' => 'fake()->randomFloat(2, 0, 999)',
            'boolean' => 'fake()->boolean()',
            'date' => 'fake()->date()',
            'datetime' => "fake()->dateTime()->format('Y-m-d H:i')",
            'select' => 'fake()->randomElement(['.implode(', ', array_map(fn (string $o) => "'{$o}'", $f->options)).'])',
            default => 'null',
        };
    }

    /**
     * @return list<string>
     */
    private function rules(CrudField $f): array
    {
        if ($f->type === 'boolean') {
            return ['sometimes', 'boolean'];
        }

        $presence = $f->required ? 'required' : 'nullable';

        // Batas angka mengikuti kolom (tinyint, unsigned, ...) agar database tidak menolak.
        $intMax = $f->max ?? 2147483647;
        $intMin = $f->unsigned ? 0 : -$intMax - 1;

        return match ($f->type) {
            'string' => [$presence, 'string', 'max:'.($f->max ?? 255)],
            'text' => [$presence, 'string', 'max:5000'],
            'integer' => [$presence, 'integer', "between:{$intMin},{$intMax}"],
            'decimal' => [$presence, 'numeric', 'decimal:0,2', $f->unsigned ? 'between:0,9999999999999.99' : 'between:-9999999999999.99,9999999999999.99'],
            'date', 'datetime' => [$presence, 'date'],
            'select' => [$presence, 'string', 'in:'.implode(',', $f->options)],
            default => [$presence],
        };
    }

    /**
     * @param  list<CrudField>  $searchable
     */
    private function searchQuery(CrudBlueprint $bp, array $searchable): string
    {
        if ($searchable === []) {
            return "        \$query = {$bp->model}::query();";
        }

        $wheres = [];

        foreach ($searchable as $i => $f) {
            $method = $i === 0 ? 'where' : 'orWhere';
            $wheres[] = "                ->{$method}('{$f->name}', 'like', \"%{\$search}%\")";
        }

        $last = count($wheres) - 1;
        $wheres[$last] .= '));';

        return $this->lines([
            "        \$query = {$bp->model}::query()",
            "            ->when(\$search !== '', fn (Builder \$q) => \$q->where(fn (Builder \$q) => \$q",
            ...$wheres,
        ]);
    }

    /**
     * @param  list<CrudField>  $searchable
     */
    private function searchBox(CrudBlueprint $bp, array $searchable): string
    {
        $names = implode(' or ', array_map(fn (CrudField $f) => Str::lower($f->label()), array_slice($searchable, 0, 2)));

        return $this->lines([
            '                        <div className="relative w-full sm:max-w-xs lg:w-72">',
            '                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />',
            '                            <Input',
            '                                value={search}',
            '                                onChange={(e) => setSearch(e.target.value)}',
            "                                placeholder=\"Search {$names}…\"",
            '                                className="h-9 rounded-lg pl-9"',
            "                                aria-label=\"Search {$bp->singular()}\"",
            '                            />',
            '                        </div>',
        ]);
    }

    /**
     * Penanda khusus form & kolom tabel React.
     *
     * @param  list<CrudField>  $fields
     * @return array<string, string>
     */
    private function formVars(array $fields): array
    {
        $types = array_map(fn (CrudField $f) => $f->type, $fields);
        $has = fn (string $type) => in_array($type, $types, true);
        $optionalSelect = (bool) array_filter($fields, fn (CrudField $f) => $f->type === 'select' && ! $f->required);

        $ui = [];

        if ($has('string') || $has('integer') || $has('decimal') || $has('date') || $has('datetime')) {
            $ui[] = "import { Input } from '@/components/ui/input';";
        }

        $ui[] = "import { Label } from '@/components/ui/label';";

        if ($has('select')) {
            $ui[] = "import {\n    Select,\n    SelectContent,\n    SelectItem,\n    SelectTrigger,\n    SelectValue,\n} from '@/components/ui/select';";
        }

        if ($has('text')) {
            $ui[] = "import { Textarea } from '@/components/ui/textarea';";
        }

        $stateful = array_values(array_filter($fields, fn (CrudField $f) => in_array($f->type, ['boolean', 'select'], true)));

        return [
            '{{ reactImport }}' => $stateful === [] ? '' : "import { useState } from 'react';\n",
            '{{ componentImports }}' => $has('boolean') ? "import { ToggleSwitch } from '@/components/toggle-switch';\n" : '',
            '{{ uiImports }}' => implode("\n", $ui)."\n",
            '{{ tsFields }}' => $this->lines(array_map($this->tsField(...), $fields)),
            '{{ constants }}' => $optionalSelect
                ? "\n/** Radix Select cannot use \"\" as a value, so \"none\" stands for empty. */\nconst NONE = 'none';\n"
                : '',
            '{{ state }}' => $stateful === [] ? '' : $this->lines([
                ...array_map($this->stateLine(...), $stateful),
                '',
            ]),
            '{{ inputs }}' => implode("\n\n", array_map($this->input(...), $fields)),
            '{{ tableColumns }}' => $this->lines(array_map($this->tableColumn(...), array_values(array_filter($fields, fn (CrudField $f) => $f->listed())))),
        ];
    }

    /**
     * Satu kolom di tabel isian baris detail (form tambah induk), mis.
     *     { name: 'qty', label: 'Qty', type: 'integer', min: 0 },
     */
    private function rowColumn(CrudField $f): string
    {
        $props = [
            "name: '{$f->name}'",
            "label: '{$f->label()}'",
            "type: '{$f->type}'",
            ...($f->required && $f->type !== 'boolean' ? ['required: true'] : []),
            ...($f->type === 'string' ? ['maxLength: '.($f->max ?? 255)] : []),
            ...($f->type === 'text' ? ['maxLength: 5000'] : []),
            ...(in_array($f->type, ['integer', 'decimal'], true) && $f->unsigned ? ['min: 0'] : []),
            ...($f->type === 'select' ? ['options: ['.implode(', ', array_map(fn (string $o) => "'{$o}'", $f->options)).']'] : []),
        ];

        return '    { '.implode(', ', $props).' },';
    }

    private function tsField(CrudField $f): string
    {
        $type = match ($f->type) {
            'integer' => 'number',
            'boolean' => 'boolean',
            default => 'string',
        };
        $nullable = ! $f->required && $f->type !== 'boolean' ? ' | null' : '';

        return "    {$f->name}: {$type}{$nullable};";
    }

    private function stateLine(CrudField $f): string
    {
        $setter = 'set'.Str::studly($f->name);

        return match (true) {
            $f->type === 'boolean' => "    const [{$f->camel()}, {$setter}] = useState(item?.{$f->name} ?? false);",
            $f->required => "    const [{$f->camel()}, {$setter}] = useState(item?.{$f->name} ?? '');",
            default => "    const [{$f->camel()}, {$setter}] = useState(item?.{$f->name} ?? NONE);",
        };
    }

    private function input(CrudField $f): string
    {
        $label = $f->label();
        // Penanda label: wajib → <span class="require">*</span>, opsional → (opt). Lihat components/ui/label.tsx.
        $marker = match (true) {
            $f->type === 'boolean' => '',
            $f->required => ' required',
            default => ' optional',
        };
        $required = $f->required ? "\n    required" : '';
        $error = "<InputError message={errors.{$f->name}} />";
        // Foreign key: sementara masih input angka, beri petunjuk tabel tujuannya.
        $hint = $f->references !== null
            ? "\n<p className=\"text-xs text-muted-foreground\">\n    ID of a row in <code>{$f->references}</code>.\n</p>"
            : '';
        $labelTag = "<Label htmlFor=\"{$f->name}\"{$marker}>\n    {$label}\n</Label>";
        // datetime-local butuh "2026-11-13T10:30", server mengirim "2026-11-13 10:30".
        $value = $f->type === 'datetime'
            ? "item?.{$f->name}?.replace(' ', 'T') ?? ''"
            : "item?.{$f->name} ?? ''";
        $min = $f->unsigned ? ['    min="0"'] : [];

        $block = match ($f->type) {
            'string', 'integer', 'decimal', 'date', 'datetime' => $this->wrap('', [
                $labelTag,
                '<Input',
                "    id=\"{$f->name}\"",
                "    name=\"{$f->name}\"",
                ...match ($f->type) {
                    'integer' => ['    type="number"', '    inputMode="numeric"', '    step="1"', ...$min],
                    'decimal' => ['    type="number"', '    inputMode="decimal"', '    step="0.01"', ...$min],
                    'date' => ['    type="date"'],
                    'datetime' => ['    type="datetime-local"'],
                    default => ['    maxLength={'.($f->max ?? 255).'}'],
                },
                "    defaultValue={{$value}}{$required}",
                '/>',
                ...($hint === '' ? [] : [ltrim($hint, "\n")]),
                $error,
            ]),
            'text' => $this->wrap(' sm:col-span-2', [
                $labelTag,
                '<Textarea',
                "    id=\"{$f->name}\"",
                "    name=\"{$f->name}\"",
                "    defaultValue={{$value}}",
                "    rows={4}{$required}",
                '/>',
                $error,
            ]),
            'boolean' => $this->wrap(' sm:col-span-2', [
                '<div className="flex items-center justify-between gap-4 rounded-xl border p-4">',
                "    <p className=\"font-medium\">{$label}</p>",
                '    <ToggleSwitch',
                "        name=\"{$f->name}\"",
                "        checked={{$f->camel()}}",
                '        onChange={set'.Str::studly($f->name).'}',
                "        label=\"{$label}\"",
                '    />',
                '</div>',
                $error,
            ]),
            'select' => $this->wrap('', [
                $labelTag,
                $f->required
                    ? "<input type=\"hidden\" name=\"{$f->name}\" value={{$f->camel()}} />"
                    : "<input\n    type=\"hidden\"\n    name=\"{$f->name}\"\n    value={{$f->camel()} === NONE ? '' : {$f->camel()}}\n/>",
                '<Select',
                "    value={{$f->camel()}}",
                '    onValueChange={set'.Str::studly($f->name).'}',
                '>',
                "    <SelectTrigger id=\"{$f->name}\" className=\"w-full\">",
                '        <SelectValue placeholder="Choose…" />',
                '    </SelectTrigger>',
                '    <SelectContent>',
                ...($f->required ? [] : ['        <SelectItem value={NONE}>None</SelectItem>']),
                ...array_map(
                    fn (string $o) => "        <SelectItem value=\"{$o}\">{$o}</SelectItem>",
                    $f->options,
                ),
                '    </SelectContent>',
                '</Select>',
                $error,
            ]),
            default => '',
        };

        return $this->indent($block, 28);
    }

    /**
     * @param  list<string>  $lines
     */
    private function wrap(string $extraClass, array $lines): string
    {
        $inner = implode("\n", array_map(fn (string $l) => $this->indent($l, 4), $lines));

        return "<div className=\"grid gap-2{$extraClass}\">\n{$inner}\n</div>";
    }

    private function tableColumn(CrudField $f): string
    {
        $value = "row.original.{$f->name}";
        $sortable = $f->sortable() ? "\n    enableSorting: true," : '';

        $cell = match ($f->type) {
            'string' => "<span className=\"font-medium\">{{$value} ?? '-'}</span>",
            'integer', 'decimal' => "<span className=\"tabular-nums\">{{$value} ?? '-'}</span>",
            'boolean' => "<StatusBadge tone={{$value} ? 'success' : 'neutral'}>\n        {{$value} ? 'Yes' : 'No'}\n    </StatusBadge>",
            'select' => "{$value} ? (\n        <StatusBadge tone=\"neutral\" dot={false}>\n            {{$value}}\n        </StatusBadge>\n    ) : (\n        '-'\n    )",
            default => "<span>{{$value} ?? '-'}</span>",
        };

        return $this->indent(implode("\n", [
            "column.accessor('{$f->name}', {",
            "    header: '{$f->label()}',{$sortable}",
            "    cell: ({ row }) => {$cell},",
            '}),',
        ]), 4);
    }

    private function indent(string $text, int $spaces): string
    {
        $pad = str_repeat(' ', $spaces);

        return implode("\n", array_map(
            fn (string $line) => $line === '' ? '' : $pad.$line,
            explode("\n", $text),
        ));
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function lines(array $lines): string
    {
        return implode("\n", $lines);
    }
}
