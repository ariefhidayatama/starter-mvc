<?php

namespace App\Http\Requests\Admin;

use App\Models\Message;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Tambah & ubah baris detail message. Boleh bila user boleh
 * mengubah induknya. Dibuat oleh CRUD generator.
 */
class SaveMessageDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        $message = $this->route('message');

        return $message instanceof Message
            && (bool) $this->user()?->can('update', $message);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return self::fieldRules();
    }

    /**
     * Aturan per kolom msg_det. Dipakai juga oleh SaveMessageRequest untuk
     * baris detail di form "Add message" (details.*.kolom), supaya aturan
     * cukup ditulis di satu tempat.
     *
     * @return array<string, list<mixed>>
     */
    public static function fieldRules(): array
    {
        return [
            'trans_code' => ['required', 'string', 'max:10'],
            'trans_no' => ['required', 'string', 'max:30'],
            'ref_no' => ['required', 'string', 'max:30'],
            'po_no' => ['required', 'string', 'max:30'],
            'prod_code' => ['nullable', 'string', 'max:30'],
            'loc_code' => ['nullable', 'string', 'max:30'],
            'pallet_no' => ['nullable', 'string', 'max:30'],
            'lot_no' => ['nullable', 'string', 'max:30'],
            'inv_status' => ['nullable', 'string', 'max:30'],
            'qty' => ['nullable', 'integer', 'between:-2147483648,2147483647'],
        ];
    }
}
