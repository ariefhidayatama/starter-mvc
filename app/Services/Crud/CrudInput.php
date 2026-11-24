<?php

namespace App\Services\Crud;

use Closure;
use Illuminate\Validation\Rule;

/**
 * Aturan validasi input generator, dipakai form "Add menu" (prefiks
 * "crud.") dan perintah `php artisan make:crud` (tanpa prefiks).
 *
 * Regex di sini juga pagar KEAMANAN: nilai ini akan ditulis ke file kode,
 * jadi hanya huruf, angka, spasi, "_" dan "-" yang diizinkan.
 */
final class CrudInput
{
    public const MAX_FIELDS = 20;

    /**
     * Batas panjang Short text. Lebih dari ini sebaiknya Long text: varchar
     * panjang menghabiskan batas ukuran baris MySQL (65.535 byte, utf8mb4 =
     * 4 byte/karakter) dan sulit diberi index.
     */
    public const MAX_STRING_LENGTH = 1000;

    /**
     * @param  array<mixed>  $input  isi input generator (name, table, connection, detail, columns, fields)
     * @return array<string, list<mixed>>
     */
    public static function rules(CrudGenerator $generator, string $prefix = '', array $input = []): array
    {
        $connections = array_keys($generator->connections());
        $connection = is_string($input['connection'] ?? null) && in_array($input['connection'], $connections, true)
            ? $input['connection']
            : null;
        $table = is_string($input['table'] ?? null) && $input['table'] !== '' ? $input['table'] : null;
        $candidates = $generator->candidateTables($connection);
        $existing = $table !== null && in_array($table, $candidates, true) ? $table : null;
        // Tabel detail yang boleh: tabel yang punya foreign key ke tabel ini.
        $children = $existing !== null
            ? array_column($generator->inspector($connection)->describe($existing)['children'], 'table')
            : [];
        $details = self::detailSpecs($input, $children);
        // Tabel BARU di database lain: akun database harus boleh CREATE TABLE.
        // Pesannya ditampilkan di pilihan Database, bukan di nama resource.
        $createProblem = $table === null && $connection !== null
            ? $generator->inspector($connection)->createTableProblem()
            : null;

        return [
            "{$prefix}name" => [
                'required', 'string', 'min:2', 'max:40', 'regex:/^[A-Za-z][A-Za-z0-9 _-]*$/',
                function (string $attribute, mixed $value, Closure $fail) use ($generator, $existing, $connection, $createProblem): void {
                    if (! is_string($value) || CrudBlueprint::words($value) === '') {
                        return;
                    }

                    // Masalah tabel detail dilaporkan di field "details" (lihat di bawah).
                    $blueprint = $existing !== null
                        ? $generator->blueprintForTable($value, $existing, [], $connection)
                        : new CrudBlueprint($value, [], connection: $connection);

                    foreach ($generator->problems($blueprint) as $problem) {
                        if ($problem === $createProblem) {
                            continue;
                        }

                        $fail($problem);

                        return;
                    }
                },
            ],
            // Database tujuan (kedua mode) + mode "Use an existing table": tabel (+ detail) + kolom.
            "{$prefix}connection" => [
                'nullable', 'string', Rule::in($connections),
                function (string $attribute, mixed $value, Closure $fail) use ($createProblem): void {
                    if ($createProblem !== null) {
                        $fail($createProblem);
                    }
                },
            ],
            "{$prefix}table" => ['nullable', 'string', Rule::in($candidates)],
            // Tabel baru: tambah kolom "ulid" untuk URL admin.
            "{$prefix}ulid" => ['nullable', 'boolean'],
            // Juga buat REST API /api/v1/<nama> (permission sama dengan halaman admin).
            "{$prefix}api" => ['nullable', 'boolean'],
            // Tabel-tabel detail (master–detail, satu tingkat): [{table, name?}, ...].
            ...($table !== null ? [
                "{$prefix}details" => [
                    'nullable', 'array', 'max:'.CrudGenerator::MAX_DETAILS,
                    function (string $attribute, mixed $value, Closure $fail) use ($generator, $existing, $connection, $details, $input): void {
                        $name = is_string($input['name'] ?? null) ? $input['name'] : '';

                        if ($existing === null || $details === [] || CrudBlueprint::words($name) === '') {
                            return;
                        }

                        $blueprint = $generator->blueprintForTable($name, $existing, [], $connection, $details);

                        if (($problem = $generator->detailProblem($blueprint, $generator->candidateTables($connection))) !== null) {
                            $fail($problem);
                        }
                    },
                ],
                "{$prefix}details.*" => ['array'],
                "{$prefix}details.*.table" => ['required', 'string', 'distinct', Rule::in($children)],
                "{$prefix}details.*.name" => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z][A-Za-z0-9 _-]*$/'],
            ] : self::newDetailRules($generator, $prefix, $input, $connection)),
            ...($table !== null ? self::columnRules($generator, $prefix, $existing, $connection) : []),
            ...($table !== null ? self::excludeFields($prefix) : self::fieldRules($prefix)),
        ];
    }

    /**
     * Mode "Create a new table": tabel detail BARU, details[] = [{name, fields[]}].
     * Nama detail wajib (mis. "items"); kolomnya divalidasi seperti kolom induk.
     *
     * @param  array<mixed>  $input
     * @return array<string, list<mixed>>
     */
    private static function newDetailRules(CrudGenerator $generator, string $prefix, array $input, ?string $connection): array
    {
        $name = is_string($input['name'] ?? null) ? $input['name'] : '';
        // Kolom penghubung (mis. purchase_order_id) dibuat otomatis oleh migration.
        $foreignKey = CrudBlueprint::words($name) !== '' ? (new CrudBlueprint($name, []))->foreignKeyName() : null;

        return [
            "{$prefix}details" => [
                'nullable', 'array', 'max:'.CrudGenerator::MAX_DETAILS,
                function (string $attribute, mixed $value, Closure $fail) use ($generator, $input, $name, $connection): void {
                    // Hanya nama detail yang dipakai di sini; kolomnya divalidasi aturan di bawah.
                    $specs = array_map(
                        fn (array $d) => ['name' => $d['name']],
                        array_values(array_filter(self::newDetailSpecs($input), fn (array $d) => $d['name'] !== null)),
                    );

                    if ($specs === [] || CrudBlueprint::words($name) === '') {
                        return;
                    }

                    $blueprint = CrudBlueprint::fromInput($name, [], $connection, $specs);

                    if (($problem = $generator->detailProblem($blueprint, [])) !== null) {
                        $fail($problem);
                    }
                },
            ],
            "{$prefix}details.*" => ['array'],
            "{$prefix}details.*.name" => ['required', 'string', 'max:40', 'regex:/^[A-Za-z][A-Za-z0-9 _-]*$/'],
            "{$prefix}details.*.fields" => [
                'required', 'array', 'min:1', 'max:'.self::MAX_FIELDS,
                // "distinct" bawaan Laravel membandingkan antar-detail juga, jadi dicek di sini per detail.
                function (string $attribute, mixed $value, Closure $fail) use ($foreignKey): void {
                    $names = array_values(array_filter(
                        array_map(fn (mixed $f) => is_array($f) && is_string($f['name'] ?? null) ? $f['name'] : null, is_array($value) ? $value : []),
                        is_string(...),
                    ));

                    if ($foreignKey !== null && in_array($foreignKey, $names, true)) {
                        $fail("\"{$foreignKey}\" is added automatically to link each row to its parent. Remove that field.");
                    } elseif (count($names) !== count(array_unique($names))) {
                        $fail('A field name is used twice in this detail table.');
                    }
                },
            ],
            ...self::fieldsetRules("{$prefix}details.*.fields", distinct: false),
        ];
    }

    /**
     * Detail BARU dari input details[] = [{name, fields[]}, ...].
     *
     * @param  array<mixed>  $input
     * @return list<array{name: string|null, fields: list<array{name: string, type: string, required?: bool|int|string|null, options?: string|null, length?: int|string|null}>}>
     */
    public static function newDetailSpecs(array $input): array
    {
        $specs = [];

        foreach (is_array($input['details'] ?? null) ? $input['details'] : [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = is_string($item['name'] ?? null) && trim($item['name']) !== '' ? trim($item['name']) : null;
            /** @var list<array{name: string, type: string, required?: bool|int|string|null, options?: string|null, length?: int|string|null}> $fields */
            $fields = is_array($item['fields'] ?? null) ? array_values(array_filter($item['fields'], is_array(...))) : [];
            $specs[] = ['name' => $name, 'fields' => $fields];
        }

        return $specs;
    }

    /**
     * Daftar detail yang tabelnya sah (punya foreign key ke tabel induk),
     * dari input details[] = [{table, name?}, ...].
     *
     * @param  array<mixed>  $input
     * @param  list<string>  $children
     * @return list<array{table: string, name: string|null}>
     */
    public static function detailSpecs(array $input, array $children): array
    {
        $specs = [];

        foreach (is_array($input['details'] ?? null) ? $input['details'] : [] as $item) {
            $table = is_array($item) && is_string($item['table'] ?? null) ? $item['table'] : null;

            if ($table !== null && in_array($table, $children, true)) {
                $name = is_string($item['name'] ?? null) && trim($item['name']) !== '' ? trim($item['name']) : null;
                $specs[] = ['table' => $table, 'name' => $name];
            }
        }

        return $specs;
    }

    /**
     * Kolom yang dipilih harus ada di tabel, dan kolom wajib (NOT NULL tanpa
     * default) tidak boleh ditinggalkan, karena INSERT pasti gagal.
     *
     * @return array<string, list<mixed>>
     */
    private static function columnRules(CrudGenerator $generator, string $prefix, ?string $table, ?string $connection): array
    {
        $fields = $table !== null ? $generator->inspector($connection)->describe($table)['fields'] : [];
        $names = array_map(fn (CrudField $f) => $f->name, $fields);
        $required = array_map(fn (CrudField $f) => $f->name, array_filter($fields, fn (CrudField $f) => $f->required));

        return [
            "{$prefix}columns" => [
                'required', 'array', 'min:1',
                function (string $attribute, mixed $value, Closure $fail) use ($required): void {
                    $missing = array_diff($required, is_array($value) ? $value : []);

                    if ($missing !== []) {
                        $fail('These columns are required by the table and must stay selected: '.implode(', ', $missing).'.');
                    }
                },
            ],
            "{$prefix}columns.*" => ['string', 'distinct', Rule::in($names)],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private static function excludeFields(string $prefix): array
    {
        return ["{$prefix}fields" => ['exclude'], "{$prefix}fields.*" => ['exclude']];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private static function fieldRules(string $prefix): array
    {
        return [
            "{$prefix}columns" => ['exclude'],
            "{$prefix}fields" => ['required', 'array', 'min:1', 'max:'.self::MAX_FIELDS],
            ...self::fieldsetRules("{$prefix}fields"),
        ];
    }

    /**
     * Aturan setiap kolom di satu daftar field ($path.*.name, $path.*.type, ...).
     *
     * @return array<string, list<mixed>>
     */
    private static function fieldsetRules(string $path, bool $distinct = true): array
    {
        return [
            "{$path}.*.name" => [
                'required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/',
                ...($distinct ? ['distinct'] : []),
                Rule::notIn(CrudField::RESERVED),
            ],
            "{$path}.*.type" => ['required', 'string', Rule::in(CrudField::TYPES)],
            "{$path}.*.required" => ['sometimes', 'boolean'],
            // Short text: panjang kolom varchar(N). Kosong = 255.
            "{$path}.*.length" => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_STRING_LENGTH],
            "{$path}.*.options" => [
                "required_if:{$path}.*.type,select", 'nullable', 'string', 'max:500',
                // Opsi dipisah koma: "draft, published, archived".
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || trim($value) === '') {
                        return;
                    }

                    foreach (explode(',', $value) as $option) {
                        if (! preg_match('/^[A-Za-z0-9 _-]{1,50}$/', trim($option))) {
                            $fail('Options may only use letters, numbers, spaces, "_" and "-", separated by commas.');

                            return;
                        }
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $prefix = ''): array
    {
        return [
            "{$prefix}name.regex" => 'Use letters, numbers and spaces only, starting with a letter (e.g. "Suppliers").',
            "{$prefix}fields.required" => 'Add at least one field.',
            "{$prefix}fields.*.name.regex" => 'Use lowercase letters, numbers and "_" (e.g. phone_number).',
            "{$prefix}fields.*.name.distinct" => 'This field name is used twice.',
            "{$prefix}fields.*.name.not_in" => 'This name is added automatically. Choose another one.',
            "{$prefix}fields.*.options.required_if" => 'List the choices, separated by commas.',
            "{$prefix}fields.*.length.max" => 'Use at most '.self::MAX_STRING_LENGTH.' characters, or choose Long text.',
            "{$prefix}details.max" => 'Use at most '.CrudGenerator::MAX_DETAILS.' detail tables.',
            "{$prefix}details.*.table.in" => 'This table has no foreign key to the chosen table.',
            "{$prefix}details.*.table.distinct" => 'This detail table is chosen twice.',
            "{$prefix}details.*.name.regex" => 'Use letters, numbers and spaces only, starting with a letter (e.g. "Items").',
            "{$prefix}details.*.name.required" => 'Give this detail table a name, e.g. "items".',
            "{$prefix}details.*.fields.required" => 'Add at least one field.',
            "{$prefix}details.*.fields.*.name.regex" => 'Use lowercase letters, numbers and "_" (e.g. unit_price).',
            "{$prefix}details.*.fields.*.name.not_in" => 'This name is added automatically. Choose another one.',
            "{$prefix}details.*.fields.*.options.required_if" => 'List the choices, separated by commas.',
            "{$prefix}details.*.fields.*.length.max" => 'Use at most '.self::MAX_STRING_LENGTH.' characters, or choose Long text.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(string $prefix = ''): array
    {
        return [
            "{$prefix}name" => 'resource name',
            "{$prefix}fields.*.name" => 'field name',
            "{$prefix}fields.*.type" => 'field type',
            "{$prefix}fields.*.options" => 'choices',
            "{$prefix}details.*.table" => 'detail table',
            "{$prefix}details.*.name" => 'detail name',
            "{$prefix}details.*.fields.*.name" => 'field name',
            "{$prefix}details.*.fields.*.type" => 'field type',
            "{$prefix}details.*.fields.*.options" => 'choices',
        ];
    }
}
