<?php

namespace App\Http\Requests\Catalog;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Tambah & ubah produk, dipakai BERSAMA oleh Web dan API.
 *
 * - POST         : semua field wajib (kecuali description & is_active).
 * - PUT / PATCH  : field yang tidak dikirim tidak diubah ("sometimes"),
 *                  sehingga mobile bisa mengirim { "stock": 10 } saja.
 */
class SaveProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->product();

        return $product
            ? (bool) $this->user()?->can('update', $product)
            : (bool) $this->user()?->can('create', Product::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('sku'))) {
            $this->merge(['sku' => strtoupper(trim($this->input('sku')))]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $product = $this->product();
        // Saat update, setiap aturan diawali "sometimes": divalidasi hanya jika dikirim.
        $required = $product ? 'sometimes' : 'required';

        return [
            'category_id' => [$required, 'integer', Rule::exists('categories', 'id')],
            'sku' => [
                $required, 'string', 'max:50', 'regex:/^[A-Z0-9][A-Z0-9-]*$/',
                Rule::unique('products', 'sku')->ignore($product?->id),
            ],
            'name' => [$required, 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => [$required, 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'stock' => [$required, 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sku.regex' => 'The SKU may only contain letters, numbers, and hyphens (e.g. KOP-001).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['category_id' => 'category', 'sku' => 'SKU', 'is_active' => 'status'];
    }

    private function product(): ?Product
    {
        $product = $this->route('product');

        return $product instanceof Product ? $product : null;
    }
}
