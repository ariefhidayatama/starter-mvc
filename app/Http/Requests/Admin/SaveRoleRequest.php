<?php

namespace App\Http\Requests\Admin;

use App\Enums\PermissionName;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Dipakai untuk tambah (POST) dan ubah (PUT) role. Aturannya sama;
 * bedanya hanya pengecualian unique saat mengubah.
 */
class SaveRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role
            ? (bool) $this->user()?->can('update', $role)
            : (bool) $this->user()?->can('create', Role::class);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => [
                'required', 'string', 'max:50',
                // huruf kecil, angka, tanda hubung: "kasir", "gudang-pusat"
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('roles', 'name')
                    ->where('guard_name', 'web')
                    ->ignore($role instanceof Role ? $role->id : null),
            ],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(PermissionName::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'The role name may only contain lowercase letters, numbers, and hyphens (e.g. warehouse-staff).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'role name'];
    }
}
