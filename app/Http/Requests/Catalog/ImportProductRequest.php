<?php

namespace App\Http\Requests\Catalog;

use App\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Upload produk dari Excel / CSV (Admin → Products → Import).
 * Izin per baris (buat baru vs. perbarui) diperiksa ProductImporter.
 */
class ImportProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && ($user->can(PermissionName::ProductsCreate) || $user->can(PermissionName::ProductsUpdate));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // "txt": sebagian browser/OS mengirim CSV sebagai text/plain.
            'file' => ['required', 'file', 'max:5120', 'mimes:xlsx,xls,csv,txt'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Choose an Excel or CSV file to upload.',
            'file.mimes' => 'The file must be an Excel (.xlsx, .xls) or CSV (.csv) file.',
            'file.max' => 'The file may not be larger than 5 MB.',
        ];
    }
}
