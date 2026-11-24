<?php

namespace App\Services\Crud;

use Illuminate\Support\Str;

/**
 * "Cetak biru" satu modul CRUD: semua nama turunan dihitung dari satu nama.
 *
 * Contoh nama "Purchase orders":
 *   table      purchase_orders           (kunci modul: permission, manifest; juga nama tabel baru)
 *   model      PurchaseOrder             (App\Models\PurchaseOrder)
 *   variable   purchaseOrder             ($purchaseOrder)
 *   slug       purchase-orders           (URL /admin/purchase-orders, folder halaman)
 *   singular   purchase order            (teks UI, mis. "Add purchase order")
 *   plural     Purchase orders           (judul halaman)
 *
 * Memakai TABEL YANG SUDAH ADA: $existingTable berisi nama tabel aslinya
 * (mis. "supplier"). Nama modul tetap dari $name, jadi URL & permission
 * tetap rapi (/admin/suppliers, suppliers.view), tidak ada migration, dan
 * tabelnya tidak pernah di-drop saat modul dihapus.
 */
final readonly class CrudBlueprint
{
    public string $table;

    public string $model;

    /**
     * @param  list<CrudField>  $fields
     * @param  string|null  $existingTable  tabel database yang sudah ada (null = buat tabel baru)
     * @param  bool  $timestamps  tabel punya kolom waktu dibuat & diubah
     * @param  string|null  $connection  koneksi database (null = default/MySQL), mis. "wmsreports"
     * @param  array{created_at?: string|null, updated_at?: string|null, created_by?: string|null, updated_by?: string|null, user_max?: int}  $audit  kolom audit tabel lama
     * @param  list<CrudBlueprint>  $details  tabel-tabel DETAIL (master–detail, satu tingkat), mis. msg_det untuk msg
     * @param  string|null  $foreignKey  untuk blueprint detail: kolom yang menunjuk ke induk (mis. msg_id)
     * @param  string|null  $detailKey  untuk blueprint detail: nama detail di induk, snake_case jamak
     *                                  (mis. "details", "items") → relasi items(), URL /items, input items[]
     * @param  bool  $ulid  tabel BARU dibuat dengan kolom "ulid" untuk URL admin. Model hasil
     *                      generator mendeteksi kolom itu sendiri (HasOptionalPublicUlid), jadi
     *                      tabel lama / kolom yang ditambah belakangan juga langsung terpakai.
     */
    public function __construct(
        string $name,
        public array $fields,
        public ?string $existingTable = null,
        public bool $timestamps = true,
        public ?string $connection = null,
        public array $audit = [],
        public array $details = [],
        public ?string $foreignKey = null,
        public ?string $detailKey = null,
        public bool $ulid = false,
    ) {
        $words = self::words($name);
        // Tunggalkan dulu, baru jamakkan: "Menus" → menus (bukan "menuses").
        $this->table = Str::snake(Str::plural(Str::singular(Str::studly($words))));
        $this->model = Str::studly(Str::singular($this->table));
    }

    /**
     * Mode "Create a new table". $details = tabel detail BARU (master–detail,
     * satu tingkat), mis. [['name' => 'items', 'fields' => [...]]]. Untuk induk
     * "Purchase orders" + "items": tabel purchase_order_items, model
     * PurchaseOrderItem, kolom penghubung purchase_order_id (dibuat migration).
     *
     * @param  list<array{name: string, type: string, required?: bool|int|string|null, options?: string|list<string>|null, length?: int|string|null}>  $fields
     * @param  list<array{name?: string|null, fields?: list<array{name: string, type: string, required?: bool|int|string|null, options?: string|list<string>|null, length?: int|string|null}>}>  $details
     */
    public static function fromInput(string $name, array $fields, ?string $connection = null, array $details = [], bool $ulid = false): self
    {
        $parent = new self($name, self::toFields($fields), connection: $connection, ulid: $ulid);

        if ($details === []) {
            return $parent;
        }

        $blueprints = [];

        foreach ($details as $spec) {
            $key = CrudGenerator::detailKey($spec['name'] ?? null, '', count($details));

            $blueprints[] = new self(
                Str::singular(self::words($name)).' '.str_replace('_', ' ', $key),
                self::toFields($spec['fields'] ?? []),
                connection: $connection,
                foreignKey: $parent->foreignKeyName(),
                detailKey: $key,
                ulid: $ulid,
            );
        }

        return new self($name, $parent->fields, connection: $connection, details: $blueprints, ulid: $ulid);
    }

    /**
     * Blueprint dari manifest modul yang SUDAH di-generate
     * (storage/app/crud/<tabel>.json), mis. untuk menambahkan REST API-nya
     * belakangan tanpa menanyakan field lagi.
     *
     * Manifest lama menyimpan satu detail di kunci "detail" (tanpa
     * detail_key → "details"); manifest baru di "details".
     *
     * @param  array<string, mixed>  $manifest
     */
    public static function fromManifest(array $manifest, bool $isDetail = false): self
    {
        $details = is_array($manifest['details'] ?? null) ? $manifest['details'] : [];

        if (is_array($manifest['detail'] ?? null)) {
            $details[] = $manifest['detail'];
        }

        $fields = is_array($manifest['fields'] ?? null) ? $manifest['fields'] : [];
        $audit = is_array($manifest['audit'] ?? null) ? $manifest['audit'] : [];
        $dbTable = is_string($manifest['db_table'] ?? null) ? $manifest['db_table'] : null;

        /** @var array{created_at?: string|null, updated_at?: string|null, created_by?: string|null, updated_by?: string|null, user_max?: int} $audit */
        $bp = new self(
            is_string($manifest['name'] ?? null) ? $manifest['name'] : '',
            array_values(array_map(fn (mixed $f) => CrudField::fromManifest(is_array($f) ? $f : []), $fields)),
            existingTable: ($manifest['existing_table'] ?? false) === true ? $dbTable : null,
            timestamps: ($manifest['timestamps'] ?? true) !== false,
            connection: is_string($manifest['db_connection'] ?? null) ? $manifest['db_connection'] : null,
            audit: $audit,
            // array_filter sudah menyisakan array saja.
            details: array_values(array_map(
                fn (array $d) => self::fromManifest($d, isDetail: true),
                array_filter($details, is_array(...)),
            )),
            foreignKey: is_string($manifest['foreign_key'] ?? null) ? $manifest['foreign_key'] : null,
            detailKey: is_string($manifest['detail_key'] ?? null) ? $manifest['detail_key'] : ($isDetail ? 'details' : null),
            ulid: ($manifest['ulid'] ?? false) === true,
        );

        // Nama modul harus menghasilkan tabel & model yang sama dengan saat di-generate.
        if (is_string($manifest['table'] ?? null) && $bp->table !== $manifest['table']) {
            throw new \RuntimeException("The manifest for \"{$manifest['table']}\" does not match its name \"{$bp->plural()}\".");
        }

        return $bp;
    }

    /**
     * @param  list<array{name: string, type: string, required?: bool|int|string|null, options?: string|list<string>|null, length?: int|string|null}>  $fields
     * @return list<CrudField>
     */
    private static function toFields(array $fields): array
    {
        return array_map(fn (array $f) => CrudField::fromArray($f), $fields);
    }

    /** Kolom penghubung di tabel detail BARU, mis. purchase_order_id. */
    public function foreignKeyName(): string
    {
        return $this->routeParameter().'_id';
    }

    /**
     * Nama → huruf kecil & spasi saja, mis. "Purchase-Orders!" → "purchase orders".
     */
    public static function words(string $name): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::snake(trim($name)))));
    }

    /** Nama tabel di database: tabel lama, atau tabel baru = $table. */
    public function dbTable(): string
    {
        return $this->existingTable ?? $this->table;
    }

    public function usesExistingTable(): bool
    {
        return $this->existingTable !== null;
    }

    /** Memakai koneksi selain default (mis. SQL Server ERP). */
    public function usesExternalConnection(): bool
    {
        return $this->connection !== null;
    }

    /** Nama kolom audit, mis. auditColumn('created_at') → "created_date". */
    public function auditColumn(string $kind): ?string
    {
        $value = $this->audit[$kind] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function variable(): string
    {
        return Str::camel($this->model);
    }

    public function slug(): string
    {
        return str_replace('_', '-', $this->table);
    }

    public function singular(): string
    {
        return str_replace('_', ' ', Str::snake($this->model));
    }

    public function plural(): string
    {
        return Str::ucfirst(str_replace('_', ' ', $this->table));
    }

    /** Parameter route resource, mis. {purchase_order}. */
    public function routeParameter(): string
    {
        return Str::snake($this->model);
    }

    public function url(): string
    {
        return '/admin/'.$this->slug();
    }

    public function permission(string $action): string
    {
        return "{$this->table}.{$action}";
    }

    /** Prefiks case enum PermissionName, mis. "PurchaseOrders" → PurchaseOrdersView. */
    public function enumPrefix(): string
    {
        return Str::studly($this->table);
    }

    /** Field yang mewakili satu baris (judul di tabel & halaman edit). */
    public function titleField(): ?CrudField
    {
        foreach ($this->fields as $field) {
            if ($field->type === 'string') {
                return $field;
            }
        }

        return null;
    }

    public function hasDetails(): bool
    {
        return $this->details !== [];
    }

    /*
     * Nama-nama turunan untuk blueprint DETAIL, dari $detailKey.
     * Contoh $detailKey "po_items" (induk "Purchase orders"):
     *   relation        poItems        $purchaseOrder->poItems()
     *   detailSegment   po-items       /admin/purchase-orders/{purchase_order}/po-items
     *   detailParam     po_item        parameter route (scoped binding: relasi = jamak camel-nya)
     *   detailVariable  poItem         $poItem di controller detail
     *   detailTitle     Po items       judul bagian di halaman edit
     *   detailSingular  po item        teks UI, mis. "Add po item"
     */

    public function relation(): string
    {
        return Str::camel((string) $this->detailKey);
    }

    public function detailSegment(): string
    {
        return str_replace('_', '-', (string) $this->detailKey);
    }

    public function detailParam(): string
    {
        return Str::singular((string) $this->detailKey);
    }

    public function detailVariable(): string
    {
        return Str::camel($this->detailParam());
    }

    public function detailTitle(): string
    {
        return Str::ucfirst(str_replace('_', ' ', (string) $this->detailKey));
    }

    public function detailSingular(): string
    {
        return str_replace('_', ' ', $this->detailParam());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->plural(),
            'table' => $this->table,
            'db_table' => $this->dbTable(),
            'db_connection' => $this->connection,
            'existing_table' => $this->usesExistingTable(),
            'timestamps' => $this->timestamps,
            'audit' => $this->audit,
            'model' => $this->model,
            'fields' => array_map(fn (CrudField $f) => $f->toArray(), $this->fields),
            'details' => array_map(fn (CrudBlueprint $d) => $d->toArray(), $this->details),
            'foreign_key' => $this->foreignKey,
            'detail_key' => $this->detailKey,
            'ulid' => $this->ulid,
        ];
    }
}
