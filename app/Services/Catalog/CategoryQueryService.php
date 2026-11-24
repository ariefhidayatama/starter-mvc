<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Support\QuerySort;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Query BACA kategori, dipakai bersama Web & API.
 */
class CategoryQueryService
{
    /** Kunci ?sort= yang diizinkan → kolom. */
    public const SORTS = [
        'name' => 'categories.name',
        'products_count' => 'products_count',
        'created_at' => 'categories.created_at',
    ];

    /**
     * @param  array{search?: string|null, sort?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, Category>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Category::query()
            ->select(['id', 'ulid', 'name', 'slug', 'description', 'created_at', 'updated_at'])
            // withCount → 1 subquery COUNT, bukan memuat semua produk ke memori.
            ->withCount('products')
            // Kondisi OR dibungkus where(fn) agar tidak "bocor" ke kondisi lain
            // (tanpa kurung: A AND B OR C → C lolos tanpa A).
            ->when($filters['search'] ?? null, fn (Builder $q, string $s) => $q->where(fn (Builder $q) => $q
                ->where('name', 'like', "{$s}%")
                ->orWhere('slug', 'like', str($s)->slug()->toString().'%')));

        return QuerySort::apply($query, $filters['sort'] ?? null, self::SORTS, 'name')
            ->paginate((int) ($filters['per_page'] ?? 10))
            ->withQueryString();
    }

    /**
     * Pilihan kategori untuk dropdown (form & filter produk).
     *
     * @return list<array{value: string, label: string}>
     */
    public function options(): array
    {
        return array_values(Category::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $c) => ['value' => (string) $c->id, 'label' => $c->name])
            ->all());
    }
}
