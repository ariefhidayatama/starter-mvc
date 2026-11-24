<?php

namespace App\Services\Crud;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membaca struktur tabel yang SUDAH ADA di database, untuk mode
 * "Use an existing table" di CRUD generator. Bisa untuk koneksi mana pun
 * (MySQL default, atau SQL Server "wmsreports").
 *
 * Kolom → field memakai Schema::getColumns() (bawaan Laravel), contoh:
 *   varchar(50) / nvarchar(50)  → Short text (max 50)
 *   int unsigned                → Whole number (min 0)
 *   tinyint(1) / bit            → Yes / No
 *   enum('draft','paid')        → Choice list (draft, paid)
 *   money, decimal              → Decimal
 *   datetime, datetime2         → Date & time
 *   json, blob, time, ...       → dilewati (belum didukung)
 *
 * SQL Server 2014: Schema::getIndexes() & getForeignKeys() bawaan Laravel
 * memakai STRING_AGG (baru ada di SQL Server 2017), jadi untuk sqlsrv
 * primary key & foreign key dibaca dengan query sys.* sendiri.
 */
class CrudTableInspector
{
    public function __construct(private readonly ?string $connection = null) {}

    /** Nama koneksi (null = default). */
    public function connectionName(): ?string
    {
        return $this->connection;
    }

    public function exists(string $table): bool
    {
        return in_array($table, $this->tables(), true);
    }

    /** Driver database koneksi ini, mis. mysql / pgsql / sqlsrv / sqlite. */
    public function driverName(): string
    {
        return $this->driver();
    }

    /**
     * Bolehkah akun database membuat tabel baru? Dicek SEBELUM generator
     * menulis file, agar tidak ada kode tanpa tabel. null = boleh.
     *
     * - SQL Server: izin CREATE TABLE di database + ALTER di schema dbo.
     * - PostgreSQL: izin CREATE di schema aktif.
     * - MySQL / SQLite: tidak dicek di sini (error tampil saat migrate).
     */
    public function createTableProblem(): ?string
    {
        $db = $this->db();

        try {
            if ($this->driver() === 'sqlsrv') {
                $row = $db->selectOne("SELECT HAS_PERMS_BY_NAME(NULL, 'DATABASE', 'CREATE TABLE') AS create_table, HAS_PERMS_BY_NAME('dbo', 'SCHEMA', 'ALTER') AS alter_schema");
                $allowed = (int) ($row->create_table ?? 0) === 1 && (int) ($row->alter_schema ?? 0) === 1;
            } elseif ($this->driver() === 'pgsql') {
                $row = $db->selectOne("SELECT has_schema_privilege(current_schema(), 'CREATE') AS allowed");
                $allowed = (bool) ($row->allowed ?? false);
            } else {
                return null;
            }
        } catch (\Throwable $e) {
            return 'Cannot check the database account: '.$e->getMessage();
        }

        return $allowed
            ? null
            : "The database account \"{$db->getConfig('username')}\" may not create tables in \"{$db->getDatabaseName()}\". Ask the DBA for CREATE TABLE, or use an existing table.";
    }

    /**
     * Semua tabel di database koneksi ini. SQL Server: tabel di schema
     * "dbo" ditulis tanpa prefiks ("msg"), schema lain dengan prefiks
     * ("dccmattel.stock").
     *
     * @return list<string>
     */
    public function tables(): array
    {
        $db = $this->db();
        $tables = $db->getDriverName() === 'mysql'
            ? $this->schema()->getTables($db->getDatabaseName())
            : $this->schema()->getTables();

        $names = [];

        foreach ($tables as $t) {
            $name = (string) $t['name'];
            $schema = (string) ($t['schema'] ?? '');

            if ($db->getDriverName() === 'sqlsrv') {
                if ($name === 'sysdiagrams') {
                    continue;
                }

                $name = $schema === '' || $schema === 'dbo' ? $name : "{$schema}.{$name}";
            }

            $names[] = $name;
        }

        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }

    /**
     * @return array{
     *     fields: list<CrudField>,
     *     skipped: list<array{name: string, reason: string}>,
     *     timestamps: bool,
     *     audit: array{created_at: string|null, updated_at: string|null, created_by: string|null, updated_by: string|null, user_max: int},
     *     children: list<array{table: string, column: string}>,
     *     problem: string|null
     * }
     */
    public function describe(string $table): array
    {
        $columns = $this->schema()->getColumns($table);
        $audit = $this->auditColumns($columns);
        $auditNames = array_values(array_filter([$audit['created_at'], $audit['updated_at'], $audit['created_by'], $audit['updated_by']]));
        $foreign = $this->foreignKeys($table);

        $fields = [];
        $skipped = [];

        foreach ($columns as $column) {
            $name = (string) $column['name'];

            // "ulid" = pengenal URL, diisi otomatis oleh model (HasOptionalPublicUlid).
            if ($name === 'id' || $name === 'ulid' || in_array($name, $auditNames, true)) {
                continue;
            }

            $result = $this->mapColumn($column);

            if ($result instanceof CrudField) {
                // Foreign key: untuk sementara tetap input angka, ditandai agar
                // test & tampilan bisa memberi petunjuk.
                $fields[] = isset($foreign[$name])
                    ? new CrudField($result->name, $result->type, $result->required, $result->options, $result->max, $result->unsigned, $foreign[$name])
                    : $result;
            } else {
                $skipped[] = ['name' => $name, 'reason' => $result];
            }
        }

        return [
            'fields' => $fields,
            'skipped' => $skipped,
            'timestamps' => $audit['created_at'] !== null && $audit['updated_at'] !== null,
            'audit' => $audit,
            'children' => $this->children($table),
            'problem' => $this->primaryKeyProblem($table, $columns),
        ];
    }

    /**
     * Satu kolom → CrudField, atau alasan (string) kenapa dilewati.
     *
     * @param  array<string, mixed>  $column  satu baris Schema::getColumns()
     */
    public function mapColumn(array $column): CrudField|string
    {
        $name = (string) ($column['name'] ?? '');
        $typeName = strtolower((string) ($column['type_name'] ?? ''));
        $type = strtolower((string) ($column['type'] ?? $typeName));
        $nullable = (bool) ($column['nullable'] ?? true);
        $hasDefault = ($column['default'] ?? null) !== null;
        $sqlsrv = $this->driver() === 'sqlsrv';
        // SQL Server: tinyint selalu 0–255 (tanpa tanda minus).
        $unsigned = str_contains($type, 'unsigned') || ($sqlsrv && $typeName === 'tinyint');

        if (! preg_match('/^[a-z][a-z0-9_]{0,39}$/', $name)) {
            return 'Column name is not lowercase snake_case';
        }

        if (in_array($name, CrudField::RESERVED, true)) {
            return 'Handled by Laravel, not shown in the form';
        }

        if (($column['auto_increment'] ?? false) === true) {
            return 'Auto-increment column';
        }

        // Wajib diisi bila NOT NULL tanpa nilai default.
        $required = ! $nullable && ! $hasDefault;

        if ($type === 'tinyint(1)' || in_array($typeName, ['bool', 'boolean', 'bit'], true)) {
            return new CrudField($name, 'boolean', false);
        }

        // varchar(max) / nvarchar(max) = teks panjang.
        if (str_contains($type, '(max)')) {
            return new CrudField($name, 'text', $required);
        }

        return match (true) {
            in_array($typeName, ['varchar', 'char', 'character varying', 'nvarchar', 'nchar'], true) => new CrudField(
                $name, 'string', $required, max: $this->length($type) ?? 255,
            ),
            in_array($typeName, ['text', 'ntext', 'tinytext', 'mediumtext', 'longtext'], true) => new CrudField($name, 'text', $required),
            in_array($typeName, ['int', 'integer', 'bigint', 'mediumint', 'smallint', 'tinyint', 'int4', 'int8', 'int2'], true) => new CrudField(
                $name, 'integer', $required, max: $this->integerMax($typeName, $unsigned), unsigned: $unsigned,
            ),
            in_array($typeName, ['decimal', 'numeric', 'float', 'double', 'real', 'money', 'smallmoney'], true) => new CrudField(
                $name, 'decimal', $required, unsigned: $unsigned,
            ),
            $typeName === 'date' => new CrudField($name, 'date', $required),
            in_array($typeName, ['datetime', 'datetime2', 'smalldatetime', 'datetimeoffset', 'timestamp', 'timestamptz'], true) => new CrudField($name, 'datetime', $required),
            $typeName === 'enum' => $this->enumField($name, $type, $required),
            default => "Type \"{$type}\" is not supported yet",
        };
    }

    /**
     * Kolom audit (waktu & user pembuat/pengubah), lihat config crud.audit_columns.
     *
     * @param  list<array<string, mixed>>  $columns
     * @return array{created_at: string|null, updated_at: string|null, created_by: string|null, updated_by: string|null, user_max: int}
     */
    private function auditColumns(array $columns): array
    {
        /** @var array<string, list<string>> $known */
        $known = config('crud.audit_columns', []);
        $byName = [];

        foreach ($columns as $c) {
            $byName[strtolower((string) $c['name'])] = $c;
        }

        $find = function (string $kind, array $typeNames) use ($known, $byName): ?string {
            foreach ($known[$kind] ?? [] as $candidate) {
                $column = $byName[$candidate] ?? null;

                if ($column !== null && in_array(strtolower((string) $column['type_name']), $typeNames, true)) {
                    return (string) $column['name'];
                }
            }

            return null;
        };

        $dates = ['datetime', 'datetime2', 'smalldatetime', 'timestamp', 'timestamptz'];
        $strings = ['varchar', 'nvarchar', 'char', 'nchar', 'character varying'];

        $createdBy = $find('created_by', $strings);
        $updatedBy = $find('updated_by', $strings);
        $lengths = array_filter(array_map(
            fn (?string $col) => $col === null ? null : $this->length(strtolower((string) $byName[strtolower($col)]['type'])),
            [$createdBy, $updatedBy],
        ));

        return [
            'created_at' => $find('created_at', $dates),
            'updated_at' => $find('updated_at', $dates),
            'created_by' => $createdBy,
            'updated_by' => $updatedBy,
            'user_max' => $lengths === [] ? 255 : min($lengths),
        ];
    }

    /**
     * Foreign key satu kolom: kolom → tabel tujuan.
     *
     * @return array<string, string>
     */
    private function foreignKeys(string $table): array
    {
        $result = [];

        if ($this->driver() === 'sqlsrv') {
            $rows = $this->db()->select(
                'SELECT pc.name AS col, OBJECT_NAME(fk.referenced_object_id) AS ref_table
                 FROM sys.foreign_key_columns fk
                 JOIN sys.columns pc ON pc.object_id = fk.parent_object_id AND pc.column_id = fk.parent_column_id
                 WHERE fk.parent_object_id = OBJECT_ID(?)
                   AND (SELECT COUNT(*) FROM sys.foreign_key_columns x WHERE x.constraint_object_id = fk.constraint_object_id) = 1',
                [$table],
            );

            foreach ($rows as $row) {
                $result[(string) $row->col] = (string) $row->ref_table;
            }

            return $result;
        }

        foreach ($this->schema()->getForeignKeys($table) as $key) {
            if (count($key['columns']) === 1) {
                $result[(string) $key['columns'][0]] = (string) $key['foreign_table'];
            }
        }

        return $result;
    }

    /**
     * Tabel DETAIL: tabel lain yang punya foreign key satu kolom ke "id"
     * tabel ini (mis. msg_det.msg_id → msg.id).
     *
     * @return list<array{table: string, column: string}>
     */
    public function children(string $table): array
    {
        $children = [];

        if ($this->driver() === 'sqlsrv') {
            $rows = $this->db()->select(
                "SELECT SCHEMA_NAME(t.schema_id) AS sch, t.name AS tbl, pc.name AS col
                 FROM sys.foreign_key_columns fk
                 JOIN sys.tables t ON t.object_id = fk.parent_object_id
                 JOIN sys.columns pc ON pc.object_id = fk.parent_object_id AND pc.column_id = fk.parent_column_id
                 JOIN sys.columns rc ON rc.object_id = fk.referenced_object_id AND rc.column_id = fk.referenced_column_id
                 WHERE fk.referenced_object_id = OBJECT_ID(?) AND rc.name = 'id'
                   AND (SELECT COUNT(*) FROM sys.foreign_key_columns x WHERE x.constraint_object_id = fk.constraint_object_id) = 1",
                [$table],
            );

            foreach ($rows as $row) {
                $name = $row->sch === 'dbo' ? (string) $row->tbl : "{$row->sch}.{$row->tbl}";
                $children[] = ['table' => $name, 'column' => (string) $row->col];
            }

            return $children;
        }

        $plain = str_contains($table, '.') ? substr($table, strrpos($table, '.') + 1) : $table;

        foreach ($this->tables() as $other) {
            foreach ($this->schema()->getForeignKeys($other) as $key) {
                if (count($key['columns']) === 1 && $key['foreign_table'] === $plain && $key['foreign_columns'] === ['id'] && $other !== $table) {
                    $children[] = ['table' => $other, 'column' => (string) $key['columns'][0]];
                }
            }
        }

        return $children;
    }

    private function enumField(string $name, string $type, bool $required): CrudField|string
    {
        preg_match_all("/'((?:[^']|'')*)'/", $type, $matches);
        $options = array_map(fn (string $o) => str_replace("''", "'", $o), $matches[1]);

        foreach ($options as $option) {
            // Pilihan ditulis ke kode, jadi dibatasi seperti input manual.
            if (! preg_match('/^[A-Za-z0-9 _-]{1,50}$/', $option)) {
                return 'Enum value "'.$option.'" uses characters that are not supported';
            }
        }

        return $options === []
            ? 'Enum without values'
            : new CrudField($name, 'select', $required, options: $options);
    }

    /**
     * Generator butuh primary key auto-increment bernama "id" (route model binding & pagination).
     *
     * @param  list<array<string, mixed>>  $columns
     */
    private function primaryKeyProblem(string $table, array $columns): ?string
    {
        $id = array_values(array_filter($columns, fn (array $c) => $c['name'] === 'id'))[0] ?? null;

        if ($this->primaryKey($table) !== ['id'] || $id === null || ($id['auto_increment'] ?? false) !== true) {
            return "\"{$table}\" needs an auto-increment primary key named \"id\".";
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function primaryKey(string $table): array
    {
        if ($this->driver() === 'sqlsrv') {
            $rows = $this->db()->select(
                'SELECT c.name FROM sys.indexes i
                 JOIN sys.index_columns ic ON ic.object_id = i.object_id AND ic.index_id = i.index_id
                 JOIN sys.columns c ON c.object_id = i.object_id AND c.column_id = ic.column_id
                 WHERE i.is_primary_key = 1 AND i.object_id = OBJECT_ID(?) ORDER BY ic.key_ordinal',
                [$table],
            );

            $columns = [];

            foreach ($rows as $row) {
                $columns[] = (string) $row->name;
            }

            return $columns;
        }

        foreach ($this->schema()->getIndexes($table) as $index) {
            if ($index['primary']) {
                return $index['columns'];
            }
        }

        return [];
    }

    /** Nilai terbesar kolom integer (agar validasi tidak meloloskan angka yang ditolak database). */
    private function integerMax(string $typeName, bool $unsigned): int
    {
        return match ($typeName) {
            'tinyint' => $unsigned ? 255 : 127,
            'smallint', 'int2' => $unsigned ? 65535 : 32767,
            'mediumint' => $unsigned ? 16777215 : 8388607,
            'bigint', 'int8' => PHP_INT_MAX,
            default => $unsigned ? 4294967295 : 2147483647,
        };
    }

    private function length(string $type): ?int
    {
        return preg_match('/\((\d+)\)/', $type, $m) ? (int) $m[1] : null;
    }

    private function driver(): string
    {
        return $this->db()->getDriverName();
    }

    private function db(): Connection
    {
        return DB::connection($this->connection);
    }

    private function schema(): Builder
    {
        return Schema::connection($this->connection);
    }
}
