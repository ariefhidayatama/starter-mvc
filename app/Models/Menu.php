<?php

namespace App\Models;

use App\Enums\MenuLocation;
use App\Models\Concerns\HasPublicUlid;
use App\Services\Menu\MenuTreeService;
use Database\Factories\MenuFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property MenuLocation $location
 * @property int|null $parent_id
 * @property string $title
 * @property string|null $url
 * @property string|null $icon
 * @property string|null $permission
 * @property bool $open_in_new_tab
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_system
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Role> $roles
 *
 * `permission` hanya untuk menu BAWAAN (mengikuti izin fitur). Menu buatan
 * sendiri dibatasi lewat relasi `roles`.
 */
// `is_system` sengaja TIDAK fillable: hanya seeder yang boleh menandai menu bawaan.
#[Fillable(['location', 'parent_id', 'title', 'url', 'icon', 'permission', 'open_in_new_tab', 'sort_order', 'is_active'])]
class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use HasFactory;

    use HasPublicUlid;

    protected function casts(): array
    {
        return [
            'location' => MenuLocation::class,
            'open_in_new_tab' => 'boolean',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Pohon menu di-cache. Setiap kali ada menu yang disimpan/dihapus
     * (dari mana pun: Action, seeder, tinker), cache dibuang otomatis.
     */
    protected static function booted(): void
    {
        static::saved(fn () => app(MenuTreeService::class)->flush());
        static::deleted(fn () => app(MenuTreeService::class)->flush());
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    /**
     * @return HasMany<Menu, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Menu::class, 'parent_id');
    }

    /**
     * Role yang boleh MELIHAT menu ini di sidebar. Kosong = semua user login.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'menu_role');
    }
}
