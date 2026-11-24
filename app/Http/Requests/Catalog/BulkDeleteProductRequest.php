<?php

namespace App\Http\Requests\Catalog;

use App\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Hapus banyak produk sekaligus dari checklist daftar Products.
 * Baris dikirim lewat ULID (sama seperti URL admin), bukan id.
 */
class BulkDeleteProductRequest extends FormRequest
{
    /** Batas aman per permintaan (satu halaman maksimal 100 baris). */
    public const MAX_ITEMS = 100;

    public function authorize(): bool
    {
        return $this->user()?->can(PermissionName::ProductsDelete) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'ids.*' => ['required', 'string', 'distinct', 'exists:products,ulid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Select at least one product.',
            'ids.max' => 'You can delete at most '.self::MAX_ITEMS.' products at once.',
            'ids.*.exists' => 'One or more selected products no longer exist.',
        ];
    }
}
