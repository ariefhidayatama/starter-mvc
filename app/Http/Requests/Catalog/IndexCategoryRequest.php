<?php

namespace App\Http\Requests\Catalog;

use App\Services\Catalog\CategoryQueryService;
use App\Support\QuerySort;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query string daftar kategori (Web & API):
 *   ?search=kopi&sort=-products_count&per_page=25
 */
class IndexCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Daftar kategori publik (CategoryPolicy::viewAny). Halaman admin
        // web dilindungi middleware "can:categories.view" di route.
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(QuerySort::options(array_keys(CategoryQueryService::SORTS)))],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ];
    }
}
