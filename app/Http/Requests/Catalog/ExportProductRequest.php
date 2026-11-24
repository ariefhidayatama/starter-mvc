<?php

namespace App\Http\Requests\Catalog;

use App\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ekspor produk yang dicentang di daftar Products (PDF / Excel / CSV):
 *   GET /admin/products/pdf?ids[]=01jd...&ids[]=01je...
 *   GET /admin/products/excel?ids[]=...
 *   GET /admin/products/csv?ids[]=...
 * Baris dikirim lewat ULID (sama seperti URL admin), bukan id.
 */
class ExportProductRequest extends FormRequest
{
    /** Satu halaman tabel maksimal 100 baris. */
    public const MAX_ITEMS = 100;

    public function authorize(): bool
    {
        return $this->user()?->can(PermissionName::ProductsView) ?? false;
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
            'ids.max' => 'You can export at most '.self::MAX_ITEMS.' products at once.',
            'ids.*.exists' => 'One or more selected products no longer exist.',
        ];
    }
}
