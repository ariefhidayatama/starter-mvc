<?php

namespace App\Http\Requests\Admin;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->target());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $passwordRules = $this->passwordRules();
        $passwordRules[0] = 'nullable'; // kosong = password tidak diubah

        return [
            ...$this->profileRules($this->target()->id),
            'password' => $passwordRules,
            'is_active' => ['required', 'boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['roles' => 'role', 'is_active' => 'status'];
    }

    /** User yang sedang diedit (route model binding {user}). */
    public function target(): User
    {
        /** @var User */
        return $this->route('user');
    }
}
