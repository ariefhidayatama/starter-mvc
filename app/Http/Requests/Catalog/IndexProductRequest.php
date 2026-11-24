<?php

namespace App\Http\Requests\Catalog;

use App\Services\Catalog\ProductQueryService;
use App\Support\QuerySort;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query string daftar produk (Web & API):
 *
 *   ?search=kopi
 *   &filter[category_id]=2&filter[status]=active&filter[price_min]=10000&filter[price_max]=50000
 *   &filter[stock_min]=1&filter[stock_max]=100&filter[in_stock]=1
 *   &sort=-price&per_page=25
 *   &pagination=cursor&cursor=eyJ...      (API: infinite scroll)
 *
 * Semua parameter di-whitelist. Kolom/nilai lain ditolak 422.
 */
class IndexProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Katalog publik (ProductPolicy::viewAny). Produk nonaktif disaring
        // oleh ProductQueryService untuk user tanpa products.view.
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'filter' => ['nullable', 'array'],
            'filter.category_id' => ['nullable', 'integer', 'min:1'],
            'filter.status' => ['nullable', Rule::in(['active', 'inactive'])],
            'filter.price_min' => ['nullable', 'numeric', 'min:0'],
            // "gte" hanya bila min diisi; tanpa itu "max saja" ikut ditolak.
            'filter.price_max' => ['nullable', 'numeric', 'min:0',
                Rule::when($this->filled('filter.price_min'), ['gte:filter.price_min'])],
            'filter.stock_min' => ['nullable', 'integer', 'min:0'],
            'filter.stock_max' => ['nullable', 'integer', 'min:0',
                Rule::when($this->filled('filter.stock_min'), ['gte:filter.stock_min'])],
            'filter.in_stock' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(QuerySort::options(array_keys(ProductQueryService::SORTS)))],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
            'pagination' => ['nullable', Rule::in(['page', 'cursor'])],
            'cursor' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'filter.price_min' => 'minimum price',
            'filter.price_max' => 'maximum price',
            'filter.stock_min' => 'minimum stock',
            'filter.stock_max' => 'maximum stock',
        ];
    }
}
