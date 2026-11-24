<?php

namespace App\Http\Requests\Admin;

use App\Enums\MenuLocation;
use App\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PUT /admin/menus/reorder: hasil drag & drop.
 *
 * `items` = SELURUH menu satu lokasi dalam urutan tampil:
 *   [{id: 1, parent_id: null}, {id: 3, parent_id: 1}, ...]
 * Urutan (sort_order) dihitung dari posisi di array, per induk.
 */
class ReorderMenusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(PermissionName::MenusUpdate);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'location' => ['required', Rule::enum(MenuLocation::class)],
            'items' => ['required', 'array', 'max:500'],
            'items.*.id' => ['required', 'integer', 'distinct'],
            'items.*.parent_id' => ['present', 'nullable', 'integer'],
        ];
    }
}
