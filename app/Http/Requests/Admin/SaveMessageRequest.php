<?php

namespace App\Http\Requests\Admin;

use App\Models\Message;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Tambah (POST) & ubah (PUT/PATCH) message. Dibuat oleh CRUD generator.
 *
 * Saat TAMBAH, form juga boleh mengirim baris detail (details[]) yang
 * disimpan bersama header dalam satu transaksi (lihat MessageController@store).
 * Saat UBAH, detail dikelola terpisah lewat MessageDetailController.
 */
class SaveMessageRequest extends FormRequest
{
    /** Batas baris detail sekali simpan, agar satu request tidak terlalu besar. */
    public const MAX_DETAILS = 100;

    public function authorize(): bool
    {
        $message = $this->route('message');

        return $message instanceof Message
            ? (bool) $this->user()?->can('update', $message)
            : (bool) $this->user()?->can('create', Message::class);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'whs_code' => ['required', 'string', 'max:10'],
            'owner_code' => ['required', 'string', 'max:10'],
            'msg_group' => ['required', 'string', 'max:15'],
            'msg_description' => ['required', 'string', 'max:5000'],
            'photo1' => ['nullable', 'string', 'max:500'],
            'photo2' => ['nullable', 'string', 'max:500'],
            'photo3' => ['nullable', 'string', 'max:500'],
            'photo4' => ['nullable', 'string', 'max:500'],
            'photo5' => ['nullable', 'string', 'max:500'],
            'document1' => ['nullable', 'string', 'max:500'],
            'document2' => ['nullable', 'string', 'max:500'],
            'document3' => ['nullable', 'string', 'max:500'],
        ];

        if ($this->isCreating()) {
            $rules['details'] = ['nullable', 'array', 'max:'.self::MAX_DETAILS];

            foreach (SaveMessageDetailRequest::fieldRules() as $field => $fieldRules) {
                $rules["details.*.{$field}"] = $fieldRules;
            }
        }

        return $rules;
    }

    /**
     * Nama kolom di pesan error baris detail, mis. "row 2 trans no".
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        if (! $this->isCreating()) {
            return [];
        }

        $attributes = [];

        foreach (array_keys((array) $this->input('details', [])) as $index) {
            foreach (array_keys(SaveMessageDetailRequest::fieldRules()) as $field) {
                $attributes["details.{$index}.{$field}"] = 'row '.((int) $index + 1).' '.str_replace('_', ' ', $field);
            }
        }

        return $attributes;
    }

    /**
     * Data header saja (tanpa details), untuk Message::create()/update().
     *
     * @return array<string, mixed>
     */
    public function headerData(): array
    {
        return collect($this->validated())->except('details')->all();
    }

    /**
     * Baris detail yang sudah lolos validasi (kosong saat ubah).
     *
     * @return list<array<string, mixed>>
     */
    public function detailRows(): array
    {
        $details = $this->validated('details');

        return is_array($details) ? array_values(array_filter($details, is_array(...))) : [];
    }

    private function isCreating(): bool
    {
        return ! $this->route('message') instanceof Message;
    }
}
