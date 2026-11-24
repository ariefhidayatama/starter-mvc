<?php

namespace App\Services\User;

use App\Models\User;
use App\Support\QuerySort;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Query BACA untuk daftar user (dipakai halaman admin; kelak juga API).
 */
class UserQueryService
{
    /** Kunci ?sort= yang diizinkan → kolom. */
    public const SORTS = [
        'name' => 'users.name',
        'created_at' => 'users.created_at',
    ];

    /**
     * @param  array{search?: string|null, role?: string|null, status?: string|null, sort?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = User::query()
            // Hanya kolom yang ditampilkan (bukan SELECT *).
            ->select(['id', 'ulid', 'name', 'email', 'email_verified_at', 'is_active', 'created_at'])
            // Eager loading: 1 query untuk semua role, bukan 1 query per user (N+1).
            ->with('roles:id,name')
            ->when($filters['search'] ?? null, function (Builder $q, string $search) {
                // Prefix search ("budi%") masih bisa memakai index.
                // Optimasi pencarian dibahas lengkap di Tahap 5.
                $q->where(fn (Builder $q) => $q
                    ->where('name', 'like', "{$search}%")
                    ->orWhere('email', 'like', "{$search}%"));
            })
            ->when($filters['role'] ?? null, fn (Builder $q, string $role) => $q->role($role))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('is_active', $status === 'active'));

        return QuerySort::apply($query, $filters['sort'] ?? null, self::SORTS, '-created_at')
            ->paginate((int) ($filters['per_page'] ?? 10))
            ->withQueryString();
    }
}
