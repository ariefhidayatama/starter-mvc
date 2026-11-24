<?php

use App\Services\Menu\MenuTreeService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Menu sidebar bawaan "Administration → API Generator" untuk database yang
 * SUDAH berisi menu (MenuSeeder hanya mengisi lokasi yang masih kosong).
 *
 * Diletakkan satu grup dengan menu "Menus" (Administration), mengikuti
 * permission menus.create. Tidak dibuat bila menu "Menus" tidak ditemukan
 * atau menu API Generator sudah ada.
 */
return new class extends Migration
{
    private const URL = '/admin/api-generator';

    public function up(): void
    {
        $menus = DB::table('menus')->where('location', 'admin')->where('url', '/admin/menus')->first(['parent_id']);

        if ($menus === null || DB::table('menus')->where('location', 'admin')->where('url', self::URL)->exists()) {
            return;
        }

        $order = (int) DB::table('menus')->where('location', 'admin')->where('parent_id', $menus->parent_id)->max('sort_order');
        $now = now();

        DB::table('menus')->insert([
            'ulid' => Str::lower((string) Str::ulid()),
            'location' => 'admin',
            'parent_id' => $menus->parent_id,
            'title' => 'API Generator',
            'url' => self::URL,
            'icon' => 'Plug',
            'permission' => 'menus.create',
            'open_in_new_tab' => false,
            'sort_order' => $order + 1,
            'is_active' => true,
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->flushMenuCache();
    }

    public function down(): void
    {
        DB::table('menus')->where('location', 'admin')->where('url', self::URL)->delete();

        $this->flushMenuCache();
    }

    /** Pohon menu sidebar di-cache (MenuTreeService); buang agar perubahan langsung tampil. */
    private function flushMenuCache(): void
    {
        rescue(fn () => app(MenuTreeService::class)->flush(), report: false);
    }
};
