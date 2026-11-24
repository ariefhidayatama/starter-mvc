<?php

namespace App\Http\Requests\Admin;

use App\Enums\PermissionName;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PATCH /admin/roles/{role}/permissions
 * { permissions: ["users.view", ...], granted: true|false }
 */
class SetRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role && (bool) $this->user()?->can('update', $role);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', 'distinct', Rule::in(PermissionName::values())],
            'granted' => ['required', 'boolean'],
        ];
    }
}
