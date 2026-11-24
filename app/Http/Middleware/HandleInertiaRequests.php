<?php

namespace App\Http\Middleware;

use App\Enums\MenuLocation;
use App\Models\User;
use App\Services\Menu\MenuTreeService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(private readonly MenuTreeService $menus) {}

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                // withoutRelations(): relasi roles/permissions yang dimuat di bawah
                // tidak ikut terkirim berulang di dalam objek user.
                'user' => $user?->withoutRelations(),
                // Dipakai React (hook useCan) untuk menyembunyikan menu/tombol.
                // HANYA untuk tampilan; keamanan tetap dijaga Policy di server.
                'roles' => $user ? $user->getRoleNames()->values() : [],
                'permissions' => $user ? $user->getAllPermissions()->pluck('name')->values() : [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Menu dari database (Admin → Menus), sudah disaring per permission.
            // Closure = hanya dihitung bila prop ini dikirim (dilewati saat
            // partial reload yang tidak memintanya).
            'menus' => fn () => [
                'admin' => $user ? $this->menus->forUser(MenuLocation::Admin, $user) : [],
                'website' => $this->menus->forUser(MenuLocation::Website, $user),
            ],
        ];
    }
}
