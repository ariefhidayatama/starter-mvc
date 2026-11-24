<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GenerateApiRequest;
use App\Models\Menu;
use App\Services\Crud\CrudGenerator;
use App\Services\Crud\CrudInput;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Admin → API Generator: membuat REST API (/api/v1/...) tanpa halaman admin.
 *
 * - Modul hasil CRUD generator: tambah / hapus API-nya (= php artisan crud:api).
 * - Tabel yang sudah ada (mis. tabel ERP project lama): modul "API saja"
 *   (= php artisan make:crud ... --table=... --api-only).
 *
 * Akses API-nya diatur di halaman Permissions (permission modul + api.access).
 * Sama seperti CRUD generator: HANYA development, dan butuh izin menus.create.
 *
 * Vite me-reload browser saat file route ditulis, jadi setiap aksi membawa
 * "token" dan hasilnya juga ditulis ke status (dibaca CrudGeneratingOverlay
 * lewat menus.generator-status).
 */
class ApiGeneratorController extends Controller
{
    public function index(CrudGenerator $generator): Response
    {
        Gate::authorize('create', Menu::class);

        $enabled = CrudGenerator::enabled();

        return Inertia::render('admin/api-generator/index', [
            'enabled' => $enabled,
            'modules' => $enabled ? $generator->modules() : [],
            'connections' => $enabled
                ? [
                    ['value' => '', 'label' => 'App database ('.DB::connection()->getDriverName().')'],
                    ...array_map(
                        fn (string $name, string $label) => ['value' => $name, 'label' => $label],
                        array_keys($generator->connections()),
                        $generator->connections(),
                    ),
                ]
                : [],
        ]);
    }

    /** "New API from an existing table": modul API saja (tanpa halaman admin). */
    public function store(GenerateApiRequest $request, CrudGenerator $generator): RedirectResponse
    {
        /** @var array{name: string, token?: string|null, table: string, connection?: string|null, columns?: list<string>, details?: list<array{table?: string, name?: string|null}>} $crud */
        $crud = $request->validated('crud');

        $blueprint = $generator->blueprintForTable(
            $crud['name'],
            $crud['table'],
            $crud['columns'] ?? [],
            filled($crud['connection'] ?? null) ? (string) $crud['connection'] : null,
            CrudInput::detailSpecs($crud, array_column($crud['details'] ?? [], 'table')),
        );
        $url = '/api/v1/'.$blueprint->slug();

        return $this->run(
            $generator,
            $crud['token'] ?? null,
            $blueprint->plural(),
            fn () => $generator->generate($blueprint, web: false, api: true)['warnings'],
            "REST API {$url} created for table \"{$blueprint->dbTable()}\". Grant {$blueprint->table}.view / create / update / delete on the Permissions page.",
            errorKey: 'crud.name',
        );
    }

    /** Modul hasil generator yang belum punya API → buat API-nya. */
    public function addApi(Request $request, CrudGenerator $generator, string $table): RedirectResponse
    {
        $this->authorizeGenerator();

        return $this->run(
            $generator,
            $this->token($request),
            $table,
            fn () => $generator->addApi($table)['warnings'],
            'REST API /api/v1/'.str_replace('_', '-', $table)." created. Access follows the {$table}.* permissions.",
        );
    }

    /** Hapus API saja (halaman admin modul tetap ada). */
    public function removeApi(Request $request, CrudGenerator $generator, string $table): RedirectResponse
    {
        $this->authorizeGenerator();

        $kept = [];

        return $this->run(
            $generator,
            $this->token($request),
            $table,
            function () use ($generator, $table, &$kept) {
                $result = $generator->removeApi($table);
                $kept = $result['kept'];

                return [
                    ...$result['warnings'],
                    ...($kept === [] ? [] : ['Kept '.count($kept).' file(s) you changed: '.implode(', ', array_map(basename(...), $kept)).'.']),
                ];
            },
            "REST API of \"{$table}\" removed. The admin pages are kept.",
        );
    }

    /**
     * Hapus modul "API saja" seluruhnya (model, policy, request, API,
     * permission). Tabelnya TIDAK pernah di-drop: tabelnya bukan milik generator.
     */
    public function destroy(Request $request, CrudGenerator $generator, string $table): RedirectResponse
    {
        $this->authorizeGenerator();

        $manifest = $generator->manifest($table);

        if ($manifest !== null && ($manifest['web'] ?? true) !== false) {
            throw ValidationException::withMessages([
                'module' => "\"{$table}\" has admin pages. Delete it from the Menus page (Delete → also remove the generated code).",
            ]);
        }

        return $this->run(
            $generator,
            $this->token($request),
            $table,
            function () use ($generator, $table) {
                $result = $generator->remove($table, dropTable: false);

                return [
                    ...$result['warnings'],
                    ...($result['kept'] === [] ? [] : ['Kept '.count($result['kept']).' file(s) you changed: '.implode(', ', array_map(basename(...), $result['kept'])).'.']),
                ];
            },
            "API module \"{$table}\" removed. The database table was kept.",
        );
    }

    /**
     * Jalankan satu aksi generator + tulis status untuk halaman yang
     * ter-reload Vite di tengah proses.
     *
     * @param  Closure(): list<string>  $action  mengembalikan peringatan
     */
    private function run(CrudGenerator $generator, ?string $token, string $name, Closure $action, string $success, string $errorKey = 'module'): RedirectResponse
    {
        ignore_user_abort(true);
        set_time_limit(300);

        $token && $generator->writeStatus($token, 'running', ['name' => $name]);

        try {
            $warnings = $action();
        } catch (RuntimeException $e) {
            $token && $generator->writeStatus($token, 'failed', ['message' => $e->getMessage()]);

            throw ValidationException::withMessages([$errorKey => $e->getMessage()]);
        }

        $toast = $warnings === []
            ? ['type' => 'success', 'message' => $success]
            : ['type' => 'warning', 'message' => $success.' But: '.implode(' ', $warnings)];

        $token && $generator->writeStatus($token, 'done', [
            ...$toast,
            'redirect' => route('admin.api-generator.index', absolute: false),
        ]);
        Inertia::flash('toast', $toast);

        return to_route('admin.api-generator.index');
    }

    private function authorizeGenerator(): void
    {
        Gate::authorize('create', Menu::class);
        abort_unless(CrudGenerator::enabled(), 404);
    }

    private function token(Request $request): ?string
    {
        $token = $request->string('token')->toString();

        return preg_match('/^[A-Za-z0-9-]{8,64}$/', $token) === 1 ? $token : null;
    }
}
