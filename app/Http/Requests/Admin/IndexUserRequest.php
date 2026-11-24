<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Services\User\UserQueryService;
use App\Support\QuerySort;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi query string daftar user: ?search=&role=&status=&per_page=
 * Nilai di luar whitelist ditolak (bukan diteruskan ke query).
 */
class IndexUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('viewAny', User::class);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', Rule::exists('roles', 'name')],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'sort' => ['nullable', Rule::in(QuerySort::options(array_keys(UserQueryService::SORTS)))],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ];
    }
}
