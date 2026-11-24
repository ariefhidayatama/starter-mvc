<?php

namespace App\Http\Requests\Admin;

use App\Models\Kwitansi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Tambah (POST) & ubah (PUT/PATCH) kwitansi. Dibuat oleh CRUD generator.
 *
 * Saat TAMBAH, form juga mengirim baris detail (kwitansi_dets[])
 * yang disimpan bersama header dalam satu transaksi. Saat UBAH, detail
 * dikelola terpisah lewat controller detail masing-masing.
 */
class SaveKwitansiRequest extends FormRequest
{
    /** Batas baris per detail sekali simpan, agar satu request tidak terlalu besar. */
    public const MAX_DETAILS = 100;

    /** Kunci input baris detail (sama dengan nama detail). */
    private const DETAIL_KEYS = ['kwitansi_dets'];

    public function authorize(): bool
    {
        $kwitansi = $this->route('kwitansi');

        return $kwitansi instanceof Kwitansi
            ? (bool) $this->user()?->can('update', $kwitansi)
            : (bool) $this->user()?->can('create', Kwitansi::class);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'kwitansi_no' => ['required', 'string', 'max:50'],
            'customer' => ['required', 'string', 'max:100'],
            'ship_to' => ['required', 'string', 'max:100'],
            'ttf_no' => ['required', 'string', 'max:50'],
        ] + $this->detailRules();
    }

    /**
     * Baris detail di form "Add": {kunci}.*.{kolom}, aturan per kolom diambil
     * dari request detail masing-masing. Saat ubah: tidak ada (kosong).
     *
     * @return array<string, list<mixed>>
     */
    private function detailRules(): array
    {
        if (! $this->isCreating()) {
            return [];
        }

        return [
            ...$this->rowRules('kwitansi_dets', SaveKwitansiKwitansiDetRequest::fieldRules()),
        ];
    }

    /**
     * @param  array<string, list<mixed>>  $fieldRules
     * @return array<string, list<mixed>>
     */
    private function rowRules(string $key, array $fieldRules): array
    {
        $rules = [$key => ['nullable', 'array', 'max:'.self::MAX_DETAILS]];

        foreach ($fieldRules as $field => $columnRules) {
            $rules["{$key}.*.{$field}"] = $columnRules;
        }

        return $rules;
    }

    /**
     * Nama kolom di pesan error baris detail, mis. "item 2 trans no".
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (array_keys($this->detailRules()) as $rule) {
            if (! str_contains($rule, '.*.')) {
                continue;
            }

            [$key, $field] = explode('.*.', $rule, 2);

            foreach (array_keys((array) $this->input($key, [])) as $index) {
                $attributes["{$key}.{$index}.{$field}"] = Str::singular(str_replace('_', ' ', $key)).' '.((int) $index + 1).' '.str_replace('_', ' ', $field);
            }
        }

        return $attributes;
    }

    /**
     * Data header saja (tanpa baris detail), untuk create().
     *
     * @return array<string, mixed>
     */
    public function headerData(): array
    {
        return collect($this->validated())->except(self::DETAIL_KEYS)->all();
    }

    /**
     * Baris satu detail yang sudah lolos validasi (kosong saat ubah).
     *
     * @return list<array<array-key, mixed>>
     */
    public function detailRows(string $key): array
    {
        $rows = $this->validated($key);

        return is_array($rows) ? array_values(array_filter($rows, is_array(...))) : [];
    }

    private function isCreating(): bool
    {
        return ! $this->route('kwitansi') instanceof Kwitansi;
    }
}
