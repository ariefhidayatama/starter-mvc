<?php

namespace App\Http\Requests\Catalog;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Tambah (POST) & ubah (PUT/PATCH) kategori. Dipakai BERSAMA oleh
 * controller Web dan API, sehingga aturan validasinya hanya ditulis sekali.
 */
class SaveCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category instanceof Category
            ? (bool) $this->user()?->can('update', $category)
            : (bool) $this->user()?->can('create', Category::class);
    }

    /**
     * Normalisasi input SEBELUM divalidasi: slug "Kopi Susu " → "kopi-susu".
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('slug')) && trim($this->input('slug')) !== '') {
            $this->merge(['slug' => str($this->input('slug'))->slug()->toString()]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'nullable', 'string', 'max:120',
                Rule::unique('categories', 'slug')->ignore($category instanceof Category ? $category->id : null),
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
