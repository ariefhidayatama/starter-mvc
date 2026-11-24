<?php

namespace App\Http\Requests\Admin;

use App\Models\Kwitansi;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Tambah & ubah baris kwitansi det milik kwitansi. Boleh bila user
 * boleh mengubah induknya. Dibuat oleh CRUD generator.
 */
class SaveKwitansiKwitansiDetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $kwitansi = $this->route('kwitansi');

        return $kwitansi instanceof Kwitansi
            && (bool) $this->user()?->can('update', $kwitansi);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return self::fieldRules();
    }

    /**
     * Aturan per kolom. Dipakai juga oleh SaveKwitansiRequest untuk
     * baris kwitansi det di form "Add kwitansi" (kwitansi_dets.*.kolom),
     * jadi aturannya cukup ditulis di satu tempat.
     *
     * @return array<string, list<mixed>>
     */
    public static function fieldRules(): array
    {
        return [
            'customer_po' => ['required', 'string', 'max:100'],
            'si_no' => ['nullable', 'string', 'max:50'],
        ];
    }
}
