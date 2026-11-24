<?php

use App\Services\Crud\CrudBlueprint;
use App\Services\Crud\CrudGenerator;
use App\Services\Crud\CrudPostProcessor;
use App\Services\Crud\CrudRenderer;
use Illuminate\Support\Facades\File;

/*
 * Generator REST API: --api (halaman admin + API), --api-only, dan
 * crud:api untuk modul yang sudah ada. Semua file ditulis ke folder
 * SEMENTARA, langkah lanjutan (migrate, seeder, Wayfinder, formatter) dipalsukan.
 */

class FakeApiPostProcessor implements CrudPostProcessor
{
    /** @var list<string> */
    public array $calls = [];

    public function format(string $root, array $paths): array
    {
        $this->calls[] = 'format';

        return [];
    }

    public function afterGenerate(string $root, ?string $migrationPath): array
    {
        $this->calls[] = 'afterGenerate';

        return [];
    }

    public function afterRemove(string $root): array
    {
        $this->calls[] = 'afterRemove';

        return [];
    }
}

function apiSampleFields(): array
{
    return [
        ['name' => 'name', 'type' => 'string', 'required' => '1'],
        ['name' => 'notes', 'type' => 'text', 'required' => '0'],
        ['name' => 'rating', 'type' => 'integer', 'required' => '0'],
        ['name' => 'is_active', 'type' => 'boolean'],
        ['name' => 'joined_on', 'type' => 'date', 'required' => '0'],
        ['name' => 'met_at', 'type' => 'datetime', 'required' => '1'],
        ['name' => 'status', 'type' => 'select', 'required' => '1', 'options' => 'active, blocked'],
    ];
}

/** Semua file PHP hasil generate harus lolos `php -l`. */
function expectValidPhp(string $root, array $paths): void
{
    foreach (array_filter($paths, fn ($p) => str_ends_with($p, '.php')) as $path) {
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg("{$root}/{$path}").' 2>&1', $output, $code);
        expect($code)->toBe(0, $path.': '.implode("\n", $output));
    }
}

beforeEach(function () {
    config(['crud.enabled' => true]);

    $this->root = storage_path('framework/testing/crud-api-'.uniqid());
    File::ensureDirectoryExists($this->root.'/app/Enums');
    File::ensureDirectoryExists($this->root.'/routes/api');
    File::copy(app_path('Enums/PermissionName.php'), $this->root.'/app/Enums/PermissionName.php');
    File::copy(base_path('routes/generated.php'), $this->root.'/routes/generated.php');
    File::copy(base_path('routes/api/generated.php'), $this->root.'/routes/api/generated.php');

    $this->post = new FakeApiPostProcessor;
    $this->generator = new CrudGenerator(new CrudRenderer(base_path('stubs/crud')), $this->post, $this->root);
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

it('--api: halaman admin + REST API, route & manifest', function () {
    $result = $this->generator->generate(CrudBlueprint::fromInput('Api samples', apiSampleFields()), web: true, api: true);

    expect($result['files'])->toContain(
        'app/Http/Controllers/Admin/ApiSampleController.php',
        'app/Http/Controllers/Api/V1/ApiSampleController.php',
        'app/Http/Resources/V1/ApiSampleResource.php',
        'tests/Feature/Api/Generated/ApiSampleApiTest.php',
    );

    expect(File::get("{$this->root}/routes/api/generated.php"))
        ->toContain('// crud:api_samples')
        ->toContain("Route::apiResource('api-samples', App\\Http\\Controllers\\Api\\V1\\ApiSampleController::class);");

    $manifest = $this->generator->manifest('api_samples');
    expect($manifest['web'])->toBeTrue()
        ->and($manifest['api'])->toBeTrue()
        ->and($manifest['api_url'])->toBe('/api/v1/api-samples')
        ->and($manifest['api_files'])->toHaveCount(3);

    // Izin API = permission modul yang sama dengan web, lewat Policy & Request.
    expect(File::get("{$this->root}/app/Http/Controllers/Api/V1/ApiSampleController.php"))
        ->toContain("Gate::authorize('viewAny', ApiSample::class);")
        ->toContain("Gate::authorize('view', \$apiSample);")
        ->toContain("Gate::authorize('delete', \$apiSample);")
        ->toContain('public function store(SaveApiSampleRequest $request): JsonResponse')
        ->toContain('api_samples.create');

    // Resource: tanggal diformat, tidak ada kolom yang bocor otomatis.
    expect(File::get("{$this->root}/app/Http/Resources/V1/ApiSampleResource.php"))
        ->toContain("'joined_on' => \$this->joined_on?->format('Y-m-d'),")
        ->toContain("'met_at' => \$this->met_at->format('Y-m-d H:i'),")
        ->toContain("'created_at' => \$this->created_at?->toIso8601String(),");

    expectValidPhp($this->root, [...$result['files'], 'routes/api/generated.php']);
});

it('--api-only: tanpa halaman admin, route web & menu', function () {
    $result = $this->generator->generate(CrudBlueprint::fromInput('Api samples', apiSampleFields()), web: false, api: true);

    expect($result['files'])
        ->toContain('app/Models/ApiSample.php', 'app/Policies/ApiSamplePolicy.php', 'app/Http/Requests/Admin/SaveApiSampleRequest.php')
        ->toContain('app/Http/Controllers/Api/V1/ApiSampleController.php')
        ->not->toContain('app/Http/Controllers/Admin/ApiSampleController.php')
        ->not->toContain('resources/js/pages/admin/api-samples/index.tsx');

    expect(File::get("{$this->root}/routes/generated.php"))->not->toContain('crud:api_samples')
        ->and(File::get("{$this->root}/app/Enums/PermissionName.php"))->toContain("case ApiSamplesView = 'api_samples.view';")
        ->and($this->generator->manifest('api_samples')['url'])->toBeNull()
        // Tidak muncul sebagai halaman (dialog Delete menu).
        ->and($this->generator->urls())->toBe([]);

    expectValidPhp($this->root, $result['files']);
});

it('menolak generate tanpa web maupun API', function () {
    $this->generator->generate(CrudBlueprint::fromInput('Api samples', apiSampleFields()), web: false, api: false);
})->throws(RuntimeException::class, 'Choose at least one');

it('crud:api: menambah API ke modul yang sudah ada dari manifest, lalu menghapusnya lagi', function () {
    $this->generator->generate(CrudBlueprint::fromInput('Api samples', apiSampleFields()));
    expect($this->generator->manifest('api_samples')['api'] ?? false)->toBeFalse();

    $added = $this->generator->addApi('api_samples');

    expect($added['api_url'])->toBe('/api/v1/api-samples')
        ->and($added['files'])->toContain('app/Http/Controllers/Api/V1/ApiSampleController.php')
        ->and($this->generator->manifest('api_samples')['api'])->toBeTrue()
        ->and(File::get("{$this->root}/routes/api/generated.php"))->toContain('crud:api_samples');

    expectValidPhp($this->root, $added['files']);

    // Kedua kalinya ditolak.
    expect(fn () => $this->generator->addApi('api_samples'))->toThrow(RuntimeException::class, 'already has a REST API');

    $removed = $this->generator->removeApi('api_samples');

    expect($removed['deleted'])->toHaveCount(3)
        ->and(is_file("{$this->root}/app/Http/Controllers/Api/V1/ApiSampleController.php"))->toBeFalse()
        // Halaman admin tetap ada.
        ->and(is_file("{$this->root}/app/Http/Controllers/Admin/ApiSampleController.php"))->toBeTrue()
        ->and(File::get("{$this->root}/routes/api/generated.php"))->not->toContain('crud:api_samples')
        ->and($this->generator->manifest('api_samples')['api'])->toBeFalse();
});

it('removeApi tidak menghapus file API yang sudah Anda ubah', function () {
    $this->generator->generate(CrudBlueprint::fromInput('Api samples', apiSampleFields()), web: true, api: true);
    File::append("{$this->root}/app/Http/Resources/V1/ApiSampleResource.php", "\n// diubah manual\n");

    $removed = $this->generator->removeApi('api_samples');

    expect($removed['kept'])->toBe(['app/Http/Resources/V1/ApiSampleResource.php'])
        ->and(is_file("{$this->root}/app/Http/Resources/V1/ApiSampleResource.php"))->toBeTrue();
});

it('menolak API untuk modul buatan tangan / tanpa manifest', function () {
    $this->generator->addApi('products');
})->throws(RuntimeException::class, 'Only modules made by the CRUD generator');

it('menolak bila controller API dengan nama yang sama sudah ada', function () {
    File::ensureDirectoryExists("{$this->root}/app/Http/Controllers/Api/V1");
    File::put("{$this->root}/app/Http/Controllers/Api/V1/ApiSampleController.php", '<?php');

    expect($this->generator->problems(CrudBlueprint::fromInput('Api samples', apiSampleFields())))
        ->toHaveCount(1)
        ->and($this->generator->problems(CrudBlueprint::fromInput('Api samples', apiSampleFields()))[0])->toContain('already exists');
});

it('crud:remove juga membuang route & file API', function () {
    $this->generator->generate(CrudBlueprint::fromInput('Api samples', apiSampleFields()), web: true, api: true);

    $this->generator->remove('api_samples', dropTable: false);

    expect(File::get("{$this->root}/routes/api/generated.php"))->not->toContain('crud:api_samples')
        ->and(is_file("{$this->root}/app/Http/Controllers/Api/V1/ApiSampleController.php"))->toBeFalse()
        ->and(is_dir("{$this->root}/tests/Feature/Api/Generated"))->toBeFalse();
});

it('master–detail: API menyimpan header + baris detail dalam satu transaksi', function () {
    $bp = CrudBlueprint::fromInput('Api orders', [
        ['name' => 'order_no', 'type' => 'string', 'required' => '1'],
    ], details: [
        ['name' => 'items', 'fields' => [
            ['name' => 'sku', 'type' => 'string', 'required' => '1'],
            ['name' => 'qty', 'type' => 'integer', 'required' => '1'],
        ]],
    ]);

    $result = $this->generator->generate($bp, web: false, api: true);

    expect($result['files'])->toContain('app/Http/Resources/V1/ApiOrderItemResource.php');

    expect(File::get("{$this->root}/app/Http/Controllers/Api/V1/ApiOrderController.php"))
        ->toContain('DB::connection()->transaction(function () use ($request) {')
        ->toContain("foreach (\$request->detailRows('items') as \$row) {")
        ->toContain("->load(['items'])");

    expect(File::get("{$this->root}/app/Http/Resources/V1/ApiOrderResource.php"))
        ->toContain("'items' => ApiOrderItemResource::collection(\$this->whenLoaded('items')),");

    expect(File::get("{$this->root}/app/Http/Resources/V1/ApiOrderItemResource.php"))
        ->toContain("'api_order_id' => \$this->getAttribute('api_order_id'),");

    expectValidPhp($this->root, $result['files']);
});

it('crud:api menyesuaikan diri dengan request master–detail versi lama (detailRows() tanpa parameter)', function () {
    $bp = CrudBlueprint::fromInput('Api orders', [
        ['name' => 'order_no', 'type' => 'string', 'required' => '1'],
    ], details: [
        ['name' => 'details', 'fields' => [['name' => 'sku', 'type' => 'string', 'required' => '1']]],
    ]);
    $this->generator->generate($bp);

    // Tiru request generator versi awal (mis. Messages).
    $request = "{$this->root}/app/Http/Requests/Admin/SaveApiOrderRequest.php";
    File::put($request, preg_replace('/function detailRows\(string \$key\)/', 'function detailRows()', File::get($request)));

    $this->generator->addApi('api_orders');

    expect(File::get("{$this->root}/app/Http/Controllers/Api/V1/ApiOrderController.php"))
        ->toContain('foreach ($request->detailRows() as $row) {')
        ->not->toContain("detailRows('details')");
});

it('blueprint dari manifest sama dengan blueprint aslinya', function () {
    $bp = CrudBlueprint::fromInput('Api samples', apiSampleFields());
    $this->generator->generate($bp);

    expect(CrudBlueprint::fromManifest($this->generator->manifest('api_samples'))->toArray())->toBe($bp->toArray());
});

it('manifest modul lama (Messages, master–detail) bisa dibaca ulang', function () {
    $manifest = json_decode(File::get(storage_path('app/crud/messages.json')), true);

    $bp = CrudBlueprint::fromManifest($manifest);

    expect($bp->model)->toBe('Message')
        ->and($bp->dbTable())->toBe('msg')
        ->and($bp->connection)->toBe('wmsreports')
        ->and($bp->details)->toHaveCount(1)
        ->and($bp->details[0]->model)->toBe('MessageDetail')
        ->and($bp->details[0]->foreignKey)->toBe('msg_id')
        ->and($bp->details[0]->relation())->toBe('details');
})->skip(fn () => ! is_file(storage_path('app/crud/messages.json')), 'No messages module in this project');
