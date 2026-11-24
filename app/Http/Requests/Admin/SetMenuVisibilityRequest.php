<?php

namespace App\Http\Requests\Admin;

use App\Models\Menu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PATCH /admin/menus/{menu}/visibility (matriks "Menu visibility").
 * { audience: "everyone" } atau { audience: "roles", roles: ["staff", ...] }
 */
class SetMenuVisibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $menu = $this->route('menu');

        return $menu instanceof Menu && (bool) $this->user()?->can('update', $menu);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'audience' => ['required', Rule::in(['everyone', 'roles'])],
            'roles' => ['exclude_unless:audience,roles', 'required', 'array', 'min:1'],
            'roles.*' => ['string', 'distinct', Rule::exists('roles', 'name')],
        ];
    }
}
