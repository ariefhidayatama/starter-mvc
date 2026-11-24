<?php

namespace App\Http\Requests\Admin;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    // Aturan validasi nama/email/password dipakai ulang dari starter kit,
    // supaya sama persis dengan halaman register & profil.
    use PasswordValidationRules, ProfileValidationRules;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'is_active' => ['boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ];
    }

    /**
     * Label field dalam pesan error.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['roles' => 'role', 'is_active' => 'status'];
    }
}
