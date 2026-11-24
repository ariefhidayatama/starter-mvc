<?php

namespace App\Services\Crud;

use Illuminate\Support\Str;

/**
 * Satu kolom/field pada CRUD yang di-generate.
 *
 * Tipe yang didukung (FieldType): string, text, integer, decimal, boolean,
 * date, select. "select" menyimpan salah satu dari $options.
 */
final readonly class CrudField
{
    public const TYPES = ['string', 'text', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'select'];

    /** Nama kolom yang sudah dibuat otomatis / dipakai Laravel. */
    public const RESERVED = ['id', 'ulid', 'created_at', 'updated_at', 'deleted_at', 'remember_token', 'password'];

    /**
     * @param  list<string>  $options  hanya untuk tipe select
     * @param  int|null  $max  string: panjang maksimal (varchar(50) → 50); integer: nilai maksimal (tinyint → 127)
     * @param  bool  $unsigned  angka tidak boleh negatif (kolom UNSIGNED)
     * @param  string|null  $references  tabel tujuan bila kolom ini foreign key
     */
    public function __construct(
        public string $name,
        public string $type,
        public bool $required,
        public array $options = [],
        public ?int $max = null,
        public bool $unsigned = false,
        public ?string $references = null,
    ) {}

    /**
     * @param  array{name: string, type: string, required?: bool|int|string|null, options?: string|list<string>|null, length?: int|string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        $options = $data['options'] ?? [];

        if (is_string($options)) {
            $options = explode(',', str_replace('|', ',', $options));
        }

        $options = array_values(array_unique(array_filter(
            array_map(fn (string $o) => trim($o), $options),
            fn (string $o) => $o !== '',
        )));

        return new self(
            name: Str::snake(trim($data['name'])),
            type: $data['type'],
            // Boolean selalu NOT NULL (default false), jadi "required" tidak berlaku.
            required: $data['type'] !== 'boolean' && filter_var($data['required'] ?? false, FILTER_VALIDATE_BOOLEAN),
            options: $data['type'] === 'select' ? $options : [],
            // Short text: panjang kolom (varchar(N)). Kosong = 255.
            max: $data['type'] === 'string' && is_numeric($data['length'] ?? null) ? (int) $data['length'] : null,
        );
    }

    /**
     * Field dari manifest (storage/app/crud/<tabel>.json, bentuk toArray()).
     * Dipakai saat menambah API ke modul yang sudah di-generate.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromManifest(array $data): self
    {
        $name = is_string($data['name'] ?? null) ? $data['name'] : '';
        $type = is_string($data['type'] ?? null) && in_array($data['type'], self::TYPES, true) ? $data['type'] : 'string';
        $options = is_array($data['options'] ?? null) ? array_values(array_filter($data['options'], is_string(...))) : [];

        return new self(
            name: $name,
            type: $type,
            required: (bool) ($data['required'] ?? false),
            options: $options,
            max: is_numeric($data['max'] ?? null) ? (int) $data['max'] : null,
            unsigned: (bool) ($data['unsigned'] ?? false),
            references: is_string($data['references'] ?? null) ? $data['references'] : null,
        );
    }

    /** Label untuk tampilan, mis. "phone_number" → "Phone number". */
    public function label(): string
    {
        return Str::ucfirst(str_replace('_', ' ', $this->name));
    }

    /** Nama properti/variabel camelCase, mis. "phoneNumber". */
    public function camel(): string
    {
        return Str::camel($this->name);
    }

    /** Ikut dicari lewat kotak "Search". */
    public function searchable(): bool
    {
        return $this->type === 'string';
    }

    /** Bisa diurutkan lewat header tabel. */
    public function sortable(): bool
    {
        return $this->type !== 'text';
    }

    /** Ditampilkan sebagai kolom tabel di halaman daftar. */
    public function listed(): bool
    {
        return $this->type !== 'text';
    }

    /**
     * @return array{name: string, type: string, required: bool, options: list<string>, max: int|null, unsigned: bool, references: string|null}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'required' => $this->required,
            'options' => $this->options,
            'max' => $this->max,
            'unsigned' => $this->unsigned,
            'references' => $this->references,
        ];
    }
}
