<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Menu\CreateMenuAction;
use App\Actions\Menu\DeleteMenuAction;
use App\Actions\Menu\ReorderMenusAction;
use App\Actions\Menu\SyncMenuAudienceAction;
use App\Actions\Menu\UpdateMenuAction;
use App\Enums\MenuLocation;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderMenusRequest;
use App\Http\Requests\Admin\SaveMenuRequest;
use App\Http\Requests\Admin\SetMenuVisibilityRequest;
use App\Http\Resources\V1\MenuResource;
use App\Models\Menu;
use App\Models\Role;
use App\Services\Access\AccessService;
use App\Services\Crud\CrudBlueprint;
use App\Services\Crud\CrudField;
use App\Services\Crud\CrudGenerator;
use App\Services\Crud\CrudInput;
use App\Services\Menu\MenuTreeService;
use App\Support\MenuIcons;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

/**
 * Admin → Menus: kelola menu sidebar dashboard & navbar website.
 *
 * Satu halaman dengan dua tab (?location=admin | website), daftar yang bisa
 * di-drag & drop, dan pratinjau sidebar "lihat sebagai role".
 */
class MenuController extends Controller
{
    public function __construct(
        private readonly MenuTreeService $menus,
        private readonly AccessService $access,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Menu::class);

        $location = $this->locationFrom($request);
        $rolesByPermission = $this->access->rolesByPermission();
        // url → tabel modul hasil CRUD generator (hanya development).
        $generated = CrudGenerator::enabled() && $location === MenuLocation::Admin
            ? app(CrudGenerator::class)->generated()
            : [];

        return Inertia::render('admin/menus/index', [
            'location' => $location->value,
            'rows' => array_map(fn (array $row) => [
                ...MenuResource::make($row['menu'])->resolve($request),
                'depth' => $row['depth'],
                'children_count' => $row['children_count'],
                'audience' => $this->audience($row['menu'], $rolesByPermission),
                'generated' => $generated[$row['menu']->url ?? ''] ?? null,
            ], $this->menus->flatList($location)),
            'counts' => Menu::query()
                ->toBase()
                ->selectRaw('location, COUNT(*) as total')
                ->groupBy('location')
                ->pluck('total', 'location'),
            // Pratinjau hanya untuk sidebar; website selalu publik.
            'previews' => $location === MenuLocation::Admin
                ? $this->access->orderedRoles()
                    ->map(fn (Role $role) => [
                        'role' => $role->name,
                        'tree' => $this->menus->previewForRole($role),
                    ])
                    ->all()
                : [],
        ]);
    }

    public function create(Request $request, CrudGenerator $generator): Response
    {
        Gate::authorize('create', Menu::class);

        $location = $this->locationFrom($request);

        return Inertia::render('admin/menus/create', [
            'location' => $location->value,
            // "Add submenu" dari menu baris (?parent=<ulid>) → induk sudah terpilih.
            'parentId' => $request->filled('parent')
                ? Menu::query()->where('ulid', $request->string('parent')->toString())->value('id')
                : null,
            // Opsi "Generate CRUD page" hanya muncul di development.
            'crudGenerator' => $location === MenuLocation::Admin && CrudGenerator::enabled()
                ? [
                    'types' => CrudField::TYPES,
                    'maxFields' => CrudInput::MAX_FIELDS,
                    'maxDetails' => CrudGenerator::MAX_DETAILS,
                    'maxStringLength' => CrudInput::MAX_STRING_LENGTH,
                    // "Use an existing table": koneksi yang boleh dipilih. Daftar tabel &
                    // kolomnya dimuat saat dibutuhkan (generatorTables / generatorTable).
                    'connections' => [
                        ['value' => '', 'label' => 'App database ('.$this->driverLabel(DB::connection()->getDriverName()).')'],
                        ...array_map(
                            fn (string $name, string $label) => ['value' => $name, 'label' => $label],
                            array_keys($generator->connections()),
                            $generator->connections(),
                        ),
                    ],
                ]
                : null,
            ...$this->formOptions($location),
        ]);
    }

    public function store(SaveMenuRequest $request, CreateMenuAction $createMenu, CrudGenerator $generator): RedirectResponse
    {
        /** @var array{location: string, parent_id?: int|string|null, title: string, url?: string|null, icon?: string|null, audience?: string, roles?: list<string>, permission?: string|null, open_in_new_tab?: bool|string|null, sort_order?: int|string|null, is_active?: bool|string|null, crud?: array{name: string, token?: string|null, ulid?: bool|string|null, api?: bool|string|null, table?: string|null, connection?: string|null, details?: list<array{table?: string, name?: string|null, fields?: list<array<string, mixed>>}>, columns?: list<string>, fields?: list<array{name: string, type: string, required?: bool|string|null, options?: string|null, length?: int|string|null}>}} $data */
        $data = $request->validated();
        $crud = $data['crud'] ?? null;
        unset($data['crud']);

        if ($crud === null || ! $request->wantsCrud()) {
            $menu = $createMenu->execute($data);
            Inertia::flash('toast', ['type' => 'success', 'message' => "Menu \"{$menu->title}\" created."]);

            return to_route('admin.menus.index', ['location' => $menu->location->value]);
        }

        // "Generate CRUD page". Vite akan me-reload browser di tengah proses
        // (file route/halaman baru), jadi proses JANGAN berhenti saat koneksi
        // browser putus; hasilnya dibaca halaman lewat generatorStatus().
        ignore_user_abort(true);
        set_time_limit(300);

        $token = $crud['token'] ?? null;
        $blueprint = filled($crud['table'] ?? null)
            // Tabel yang sudah ada: field dibaca dari struktur tabelnya.
            ? $generator->blueprintForTable(
                $crud['name'],
                (string) $crud['table'],
                $crud['columns'] ?? [],
                filled($crud['connection'] ?? null) ? (string) $crud['connection'] : null,
                // Tabel detail (master–detail): [{table, name?}, ...].
                CrudInput::detailSpecs($crud, array_column($crud['details'] ?? [], 'table')),
            )
            // Tabel baru (+ tabel detail baru: [{name, fields[]}, ...]).
            : CrudBlueprint::fromInput(
                $crud['name'],
                $crud['fields'] ?? [],
                filled($crud['connection'] ?? null) ? (string) $crud['connection'] : null,
                CrudInput::newDetailSpecs($crud),
                filter_var($crud['ulid'] ?? false, FILTER_VALIDATE_BOOLEAN),
            );
        $token && $generator->writeStatus($token, 'running', ['name' => $blueprint->plural()]);

        try {
            // Kode dulu; bila gagal, menu tidak dibuat.
            $withApi = filter_var($crud['api'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $warnings = $generator->generate($blueprint, web: true, api: $withApi)['warnings'];

            // Menu mengikuti permission <tabel>.view: tampil persis untuk yang boleh membuka halamannya.
            $menu = $createMenu->execute([
                ...$data,
                'url' => $blueprint->url(),
                'icon' => $data['icon'] ?? 'FolderOpen',
                'audience' => 'permission',
                'permission' => $blueprint->permission('view'),
            ]);
        } catch (RuntimeException $e) {
            $token && $generator->writeStatus($token, 'failed', ['message' => $e->getMessage()]);

            throw ValidationException::withMessages(['crud.name' => $e->getMessage()]);
        }

        $api = $withApi ? ' plus its REST API (/api/v1/'.$blueprint->slug().')' : '';
        $toast = $warnings === []
            ? ['type' => 'success', 'message' => "Menu \"{$menu->title}\" and its CRUD page ({$menu->url}){$api} were created."]
            : ['type' => 'warning', 'message' => "Menu \"{$menu->title}\" and its CRUD page were created, but: ".implode(' ', $warnings)];

        $token && $generator->writeStatus($token, 'done', [
            ...$toast,
            'redirect' => route('admin.menus.index', ['location' => MenuLocation::Admin->value], false),
        ]);
        Inertia::flash('toast', $toast);

        return to_route('admin.menus.index', ['location' => $menu->location->value]);
    }

    /** Status "Generate CRUD page" untuk halaman yang ter-reload Vite di tengah proses. */
    public function generatorStatus(CrudGenerator $generator, string $token): JsonResponse
    {
        Gate::authorize('create', Menu::class);

        return response()->json(
            CrudGenerator::enabled() ? ($generator->status($token) ?? ['state' => 'unknown']) : ['state' => 'disabled'],
        );
    }

    public function edit(Request $request, Menu $menu): Response
    {
        Gate::authorize('update', $menu);

        $menu->load('roles:id,name');

        return Inertia::render('admin/menus/edit', [
            'menu' => [
                ...MenuResource::make($menu)->resolve($request),
                'audience' => $this->audience($menu, $this->access->rolesByPermission()),
            ],
            'hasChildren' => $menu->children()->exists(),
            ...$this->formOptions($menu->location, $menu),
        ]);
    }

    public function update(SaveMenuRequest $request, Menu $menu, UpdateMenuAction $updateMenu): RedirectResponse
    {
        /** @var array{parent_id?: int|string|null, title?: string, url?: string|null, icon?: string|null, audience?: string, roles?: list<string>, open_in_new_tab?: bool|string|null, sort_order?: int|string|null, is_active?: bool|string|null} $data */
        $data = $request->validated();
        $updateMenu->execute($menu, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Menu \"{$menu->title}\" updated."]);

        return to_route('admin.menus.index', ['location' => $menu->location->value]);
    }

    /** Simpan hasil drag & drop. */
    public function reorder(ReorderMenusRequest $request, ReorderMenusAction $reorderMenus): RedirectResponse
    {
        /** @var array{location: string, items: list<array{id: int|string, parent_id: int|string|null}>} $data */
        $data = $request->validated();
        $location = MenuLocation::from($data['location']);

        $reorderMenus->execute($location, $data['items']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Menu order saved.']);

        return to_route('admin.menus.index', ['location' => $location->value]);
    }

    /**
     * Klik sel di matriks "Menu visibility" (halaman Permissions). Hanya
     * menu buatan sendiri yang diatur per role; menu bawaan & yang
     * mengikuti permission diatur lewat matriks permission.
     */
    public function visibility(SetMenuVisibilityRequest $request, Menu $menu, SyncMenuAudienceAction $syncAudience): RedirectResponse
    {
        if ($menu->location !== MenuLocation::Admin || $menu->is_system || $menu->permission !== null) {
            throw new BusinessException("\"{$menu->title}\" follows a permission. Change it in the permission list instead.");
        }

        /** @var array{audience: string, roles?: list<string>} $data */
        $data = $request->validated();
        $syncAudience->execute($menu, $data['audience'], $data['roles'] ?? []);

        return back();
    }

    /**
     * Hapus menu. Untuk menu hasil CRUD generator (hanya development), kodenya
     * ikut dihapus bila remove_code=1, dan tabelnya bila drop_table=1.
     */
    public function destroy(Request $request, Menu $menu, DeleteMenuAction $deleteMenu, CrudGenerator $generator): RedirectResponse
    {
        Gate::authorize('delete', $menu);

        $table = $request->boolean('remove_code') && CrudGenerator::enabled() && $menu->url !== null
            ? ($generator->urls()[$menu->url] ?? null)
            : null;

        $deleteMenu->execute($menu);

        $message = "Menu \"{$menu->title}\" deleted.";
        $type = 'success';

        if ($table !== null) {
            // Vite bisa me-reload browser di tengah proses; tetap selesaikan.
            ignore_user_abort(true);
            $result = $generator->remove($table, $request->boolean('drop_table'));
            $message .= $result['table_dropped']
                ? ' Its generated code and database table were removed.'
                : ' Its generated code was removed (the database table was kept).';

            if ($result['kept'] !== []) {
                $type = 'warning';
                $message .= ' Kept '.count($result['kept']).' file(s) you changed: '.implode(', ', array_map(basename(...), $result['kept'])).'.';
            }

            if ($result['warnings'] !== []) {
                $type = 'warning';
                $message .= ' '.implode(' ', $result['warnings']);
            }
        }

        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return to_route('admin.menus.index', ['location' => $menu->location->value]);
    }

    /** "Use an existing table": daftar tabel di satu koneksi (JSON). */
    public function generatorTables(Request $request, CrudGenerator $generator): JsonResponse
    {
        $connection = $this->generatorConnection($request, $generator);

        try {
            return response()->json(['tables' => $generator->candidateTables($connection)]);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Cannot read tables: '.$e->getMessage()], 422);
        }
    }

    /** "Use an existing table": kolom, kolom audit & tabel detail satu tabel (JSON). */
    public function generatorTable(Request $request, CrudGenerator $generator): JsonResponse
    {
        $connection = $this->generatorConnection($request, $generator);
        $table = $request->string('table')->toString();

        abort_unless(in_array($table, $generator->candidateTables($connection), true), 404);

        $inspector = $generator->inspector($connection);
        $info = $inspector->describe($table);

        return response()->json([
            'name' => $table,
            'fields' => array_map(fn (CrudField $f) => $f->toArray(), $info['fields']),
            'skipped' => $info['skipped'],
            'timestamps' => $info['timestamps'],
            'audit' => $info['audit'],
            'problem' => $info['problem'],
            // Calon tabel detail (master–detail) beserta kolomnya.
            'children' => array_map(function (array $child) use ($inspector) {
                $detail = $inspector->describe($child['table']);

                return [
                    'table' => $child['table'],
                    'column' => $child['column'],
                    'fields' => array_map(
                        fn (CrudField $f) => $f->toArray(),
                        array_values(array_filter($detail['fields'], fn (CrudField $f) => $f->name !== $child['column'])),
                    ),
                    'problem' => $detail['problem'],
                ];
            }, $info['children']),
        ]);
    }

    private function driverLabel(string $driver): string
    {
        return match ($driver) {
            'mysql' => 'MySQL',
            'mariadb' => 'MariaDB',
            'pgsql' => 'PostgreSQL',
            'sqlsrv' => 'SQL Server',
            'sqlite' => 'SQLite',
            default => $driver,
        };
    }

    private function generatorConnection(Request $request, CrudGenerator $generator): ?string
    {
        Gate::authorize('create', Menu::class);
        abort_unless(CrudGenerator::enabled(), 404);

        $connection = $request->string('connection')->toString();

        if ($connection === '') {
            return null;
        }

        abort_unless(array_key_exists($connection, $generator->connections()), 404);

        return $connection;
    }

    private function locationFrom(Request $request): MenuLocation
    {
        return MenuLocation::tryFrom($request->string('location')->toString()) ?? MenuLocation::Admin;
    }

    /**
     * Siapa yang melihat menu ini, dalam bentuk yang mudah ditampilkan.
     *
     * @param  array<string, list<string>>  $rolesByPermission
     * @return array{type: 'public'|'everyone'|'roles'|'permission', roles: list<string>, permission: string|null}
     */
    private function audience(Menu $menu, array $rolesByPermission): array
    {
        if ($menu->location === MenuLocation::Website) {
            return ['type' => 'public', 'roles' => [], 'permission' => null];
        }

        if ($menu->permission !== null) {
            return [
                'type' => 'permission',
                'roles' => $rolesByPermission[$menu->permission] ?? [RoleName::Admin->value],
                'permission' => $menu->permission,
            ];
        }

        $roles = array_values($menu->roles->pluck('name')->sort()->all());

        return $roles === []
            ? ['type' => 'everyone', 'roles' => [], 'permission' => null]
            : ['type' => 'roles', 'roles' => $roles, 'permission' => null];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(MenuLocation $location, ?Menu $menu = null): array
    {
        return [
            'parentOptions' => $this->menus->parentOptions($location, $menu),
            'iconOptions' => MenuIcons::NAMES,
            'roleOptions' => $location === MenuLocation::Admin
                ? $this->access->orderedRoles()->pluck('name')->all()
                : [],
            // Untuk "Users with a permission": halaman yang dikunci di kode.
            'permissionOptions' => $location === MenuLocation::Admin
                ? array_map(fn (PermissionName $p) => [
                    'value' => $p->value,
                    'label' => "{$p->value} — {$p->description()}",
                ], PermissionName::cases())
                : [],
        ];
    }
}
