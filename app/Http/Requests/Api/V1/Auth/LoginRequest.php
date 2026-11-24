<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi login API. Berbeda dengan login web karena wajib menyebut
 * nama device (mis. "Pixel 8 - Budi") untuk fitur multi-device.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // endpoint publik
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:10'],
            'recovery_code' => ['nullable', 'string', 'max:100'],
        ];
    }
}
