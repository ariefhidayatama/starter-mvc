<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\User;
use App\Policies\ProductPolicy;
use App\Support\QuerySort;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Query BACA produk: search, filter, sort, pagination.
 * Dipakai BERSAMA oleh halaman admin (Web) dan endpoint /api/v1/products.
 */
class ProductQueryService
{
    /** Kunci ?sort= yang diizinkan → kolom. */
    public const SORTS = [
        'name' => 'products.name',
        'price' => 'products.price',
        'stock' => 'products.stock',
        'created_at' => 'products.created_at',
    ];

    public function __construct(private readonly ProductPolicy $policy) {}

    /**
     * Pagination dengan total halaman (tabel admin, nomor halaman).
     *
     * @param  array<string, mixed>  $filters  data tervalidasi dari IndexProductRequest
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginate(array $filters, ?User $actor): LengthAwarePaginator
    {
        return $this->query($filters, $actor)
            ->paginate($this->perPage($filters))
            ->withQueryString();
    }

    /**
     * Cursor pagination (infinite scroll mobile): tanpa COUNT(*) dan tanpa
     * OFFSET besar, sehingga halaman ke-1000 tetap secepat halaman ke-1.
     *
     * @param  array<string, mixed>  $filters
     * @return CursorPaginator<int, Product>
     */
    public function cursor(array $filters, ?User $actor): CursorPaginator
    {
        return $this->query($filters, $actor)
            ->cursorPaginate($this->perPage($filters))
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Product>
     */
    private function query(array $filters, ?User $actor): Builder
    {
        // Data sudah divalidasi IndexProductRequest; di sini hanya diambil
        // dengan tipe yang jelas.
        $filter = is_array($filters['filter'] ?? null) ? $filters['filter'] : [];
        $search = is_string($filters['search'] ?? null) ? $filters['search'] : null;
        $status = is_string($filter['status'] ?? null) ? $filter['status'] : null;
        $categoryId = is_numeric($filter['category_id'] ?? null) ? (int) $filter['category_id'] : null;
        $priceMin = is_numeric($filter['price_min'] ?? null) ? $filter['price_min'] : null;
        $priceMax = is_numeric($filter['price_max'] ?? null) ? $filter['price_max'] : null;
        $stockMin = is_numeric($filter['stock_min'] ?? null) ? (int) $filter['stock_min'] : null;
        $stockMax = is_numeric($filter['stock_max'] ?? null) ? (int) $filter['stock_max'] : null;
        $inStock = filter_var($filter['in_stock'] ?? false, FILTER_VALIDATE_BOOL);
        $canSeeInactive = $this->policy->canSeeInactive($actor);

        $query = Product::query()
            ->select(['id', 'ulid', 'category_id', 'sku', 'name', 'price', 'stock', 'is_active', 'created_at', 'updated_at'])
            // Eager loading kategori: 1 query tambahan untuk SEMUA produk.
            ->with('category:id,name,slug')
            // Tamu & customer hanya melihat produk aktif.
            ->when(! $canSeeInactive, fn (Builder $q) => $q->where('is_active', true))
            ->when($canSeeInactive && $status !== null, fn (Builder $q) => $q->where('is_active', $status === 'active'))
            ->when($search, fn (Builder $q, string $s) => $q->where(fn (Builder $q) => $q
                // Prefix search ("kopi%") masih bisa memakai index.
                // Pencarian full-text & pengukurannya dibahas di Tahap 5.
                ->where('name', 'like', "{$s}%")
                ->orWhere('sku', 'like', strtoupper($s).'%')))
            ->when($categoryId, fn (Builder $q, int $id) => $q->where('category_id', $id))
            ->when($priceMin !== null, fn (Builder $q) => $q->where('price', '>=', $priceMin))
            ->when($priceMax !== null, fn (Builder $q) => $q->where('price', '<=', $priceMax))
            ->when($stockMin !== null, fn (Builder $q) => $q->where('stock', '>=', $stockMin))
            ->when($stockMax !== null, fn (Builder $q) => $q->where('stock', '<=', $stockMax))
            ->when($inStock, fn (Builder $q) => $q->where('stock', '>', 0));

        $sort = is_string($filters['sort'] ?? null) ? $filters['sort'] : null;

        return QuerySort::apply($query, $sort, self::SORTS, '-created_at');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function perPage(array $filters): int
    {
        return is_numeric($filters['per_page'] ?? null) ? (int) $filters['per_page'] : 10;
    }
}
