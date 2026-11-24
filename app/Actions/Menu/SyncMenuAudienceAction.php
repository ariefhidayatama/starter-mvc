<?php

namespace App\Actions\Menu;

use App\Enums\RoleName;
use App\Models\Menu;
use App\Models\Role;
use App\Services\Menu\MenuTreeService;

/**
 * Simpan "siapa yang melihat menu ini" (form menu, matriks Menu visibility).
 *
 * - everyone   → semua user login (tanpa role, tanpa permission).
 * - roles      → role terpilih + admin (admin selalu melihat).
 * - permission → user yang punya permission itu. Dipakai untuk menu yang
 *                halamannya DIKUNCI permission di kode, sehingga menu dan
 *                akses halaman selalu sejalan.
 */
class SyncMenuAudienceAction
{
    public function __construct(private readonly MenuTreeService $menus) {}

    /**
     * @param  'everyone'|'roles'|'permission'|string  $audience
     * @param  list<string>  $roleNames
     */
    public function execute(Menu $menu, string $audience, array $roleNames = [], ?string $permission = null): void
    {
        $menu->permission = $audience === 'permission' ? $permission : null;
        $menu->save();

        $ids = $audience === 'roles'
            ? Role::query()
                ->whereIn('name', [...$roleNames, RoleName::Admin->value])
                ->pluck('id')
                ->all()
            : [];

        $menu->roles()->sync($ids);

        // sync() mengubah tabel pivot, bukan baris menu → event `saved`
        // tidak terpanggil untuk perubahan role, jadi cache dibuang manual.
        $this->menus->flush();
    }
}
