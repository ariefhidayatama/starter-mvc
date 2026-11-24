<?php

namespace App\Http\Requests\Admin;

use App\Enums\MenuLocation;
use App\Enums\PermissionName;
use App\Models\Menu;
use App\Services\Crud\CrudGenerator;
use App\Services\Crud\CrudInput;
use App\Support\MenuIcons;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Tambah (POST) & ubah (PUT/PATCH) menu.
 *
 * Aturan penting:
 * - Maksimal 2 tingkat: induk harus menu tingkat atas di lokasi yang sama,
 *   dan menu yang sudah punya submenu tidak boleh menjadi submenu.
 * - URL di-whitelist per lokasi (mencegah `javascript:alert(1)` disimpan
 *   lalu dijalankan saat menu diklik).
 * - Lokasi hanya dipilih saat membuat; setelah itu tetap.
 * - Visibilitas sidebar: audience = everyone | roles | permission.
 *   "permission" dipakai bila halamannya dikunci permission di kode.
 *   Menu bawaan tidak bisa diubah (selalu mengikuti permission fiturnya).
 */
class SaveMenuRequest extends FormRequest
{
    /** Sidebar: hanya path internal, mis. /admin/products (bukan //evil.com). */
    private const ADMIN_URL = '/^\/(?!\/)\S*$/';

    /** Website: path internal, anchor (#features), atau http(s)://... */
    private const WEBSITE_URL = '/^(\/(?!\/)|#|https?:\/\/)\S*$/i';

    public function authorize(): bool
    {
        $menu = $this->menu();

        return $menu
            ? (bool) $this->user()?->can('update', $menu)
            : (bool) $this->user()?->can('create', Menu::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('url'))) {
            $this->merge(['url' => trim($this->input('url')) ?: null]);
        }

        // Nama resource kosong → pakai judul menu.
        if ($this->wantsCrud() && blank($this->input('crud.name'))) {
            $this->merge(['crud' => [...(array) $this->input('crud'), 'name' => $this->input('title')]]);
        }
    }

    /**
     * Menu baru di sidebar + "Generate CRUD page" dicentang (hanya development).
     * URL & visibilitas lalu diisi otomatis dari modul yang di-generate.
     */
    public function wantsCrud(): bool
    {
        return $this->menu() === null
            && $this->input('location') === MenuLocation::Admin->value
            && $this->boolean('crud.enabled')
            && CrudGenerator::enabled();
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $menu = $this->menu();
        $location = $this->location();

        // Siapa yang melihat: hanya untuk menu sidebar buatan sendiri.
        // Menu bawaan mengikuti permission fiturnya; website selalu publik.
        $isSystem = $menu !== null && $menu->is_system;
        $crud = $this->wantsCrud();
        $hasAudience = $location === MenuLocation::Admin && ! $isSystem && ! $crud;

        return [
            ...($crud
                ? [
                    'crud' => ['required', 'array'],
                    'crud.enabled' => ['boolean'],
                    'crud.token' => ['nullable', 'string', 'regex:/^[A-Za-z0-9-]{8,64}$/'],
                    ...CrudInput::rules(app(CrudGenerator::class), 'crud.', (array) $this->input('crud')),
                ]
                : ['crud' => ['exclude']]),
            'audience' => $hasAudience ? ['required', Rule::in(['everyone', 'roles', 'permission'])] : ['exclude'],
            'permission' => $hasAudience
                ? ['exclude_unless:audience,permission', 'required', 'string', Rule::in(PermissionName::values())]
                : ['exclude'],
            'roles' => $hasAudience
                ? ['exclude_unless:audience,roles', 'required', 'array', 'min:1']
                : ['exclude'],
            'roles.*' => $hasAudience
                ? ['string', 'distinct', Rule::exists('roles', 'name')]
                : ['exclude'],
            // Saat ubah, lokasi diabaikan (tidak ikut validated()).
            'location' => $menu ? ['exclude'] : ['required', Rule::enum(MenuLocation::class)],
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('menus', 'id')
                    ->where('location', $location?->value)
                    ->whereNull('parent_id'),
                Rule::notIn(array_filter([$menu?->id])),
                function (string $attribute, mixed $value, Closure $fail) use ($menu): void {
                    if ($value !== null && $menu?->children()->exists()) {
                        $fail('This menu has submenus, so it cannot become a submenu itself.');
                    }
                },
            ],
            'title' => ['required', 'string', 'max:100'],
            // Submenu wajib punya URL. Menu tingkat atas boleh kosong
            // (berfungsi sebagai pembuka daftar submenu).
            'url' => $crud ? ['exclude'] : [
                'nullable', 'required_with:parent_id', 'string', 'max:500',
                'regex:'.($location === MenuLocation::Admin ? self::ADMIN_URL : self::WEBSITE_URL),
            ],
            'icon' => ['nullable', 'string', Rule::in(MenuIcons::NAMES)],
            'open_in_new_tab' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'url.regex' => $this->location() === MenuLocation::Admin
                ? 'Dashboard menus must link to an internal path that starts with "/", e.g. /admin/products.'
                : 'Use an internal path (/about), an anchor (#features), or a full http(s):// link.',
            'url.required_with' => 'A submenu needs a URL.',
            'parent_id.exists' => 'The parent must be a top-level menu in the same location.',
            'parent_id.not_in' => 'A menu cannot be its own parent.',
            'roles.required' => 'Choose at least one role, or show the menu to everyone.',
            'permission.required' => 'Choose the permission that protects this page.',
            ...CrudInput::messages('crud.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return CrudInput::attributes('crud.');
    }

    public function menu(): ?Menu
    {
        $menu = $this->route('menu');

        return $menu instanceof Menu ? $menu : null;
    }

    public function location(): ?MenuLocation
    {
        return $this->menu()->location ?? MenuLocation::tryFrom((string) $this->input('location'));
    }
}
