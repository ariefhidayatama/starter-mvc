<?php

namespace App\Http\Requests\Admin;

use App\Models\Menu;
use App\Services\Crud\CrudGenerator;
use App\Services\Crud\CrudInput;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin → API Generator → "New API from an existing table".
 *
 * Aturannya sama dengan mode "Use an existing table" di form menu
 * (CrudInput, prefiks "crud."), tetapi tabel WAJIB dipilih: halaman ini
 * hanya membuat REST API untuk tabel yang sudah ada (tanpa halaman admin).
 */
class GenerateApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return CrudGenerator::enabled() && (bool) $this->user()?->can('create', Menu::class);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = CrudInput::rules(app(CrudGenerator::class), 'crud.', (array) $this->input('crud'));

        return [
            ...$rules,
            'crud' => ['required', 'array'],
            'crud.token' => ['nullable', 'string', 'regex:/^[A-Za-z0-9-]{8,64}$/'],
            'crud.table' => ['required', ...array_values(array_filter($rules['crud.table'], fn (mixed $rule) => $rule !== 'nullable'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...CrudInput::messages('crud.'),
            'crud.table.required' => 'Choose a table.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return CrudInput::attributes('crud.');
    }
}
