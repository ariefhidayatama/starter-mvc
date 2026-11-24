<?php

use App\Enums\RoleName;
use App\Models\Menu;
use App\Models\User;
use App\Services\Crud\CrudBlueprint;
use App\Services\Crud\CrudGenerator;
use App\Services\Crud\CrudPostProcessor;
use App\Services\Crud\CrudRenderer;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * CRUD generator. Semua file ditulis ke folder SEMENTARA (bukan project asli)
 * dan langkah lanjutan (migrate, seeder, Wayfinder, formatter) dipalsukan.
 */

class FakeCrudPostProcessor implements CrudPostProcessor
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

/** Field contoh yang mencakup semua tipe. */
function sampleFields(): array
{
    return [
        ['name' => 'name', 'type' => 'string', 'required' => '1'],
        ['name' => 'notes', 'type' => 'text', 'required' => '0'],
        ['name' => 'rating', 'type' => 'integer', 'required' => '0'],
        ['name' => 'balance', 'type' => 'decimal', 'required' => '1'],
        ['name' => 'is_active', 'type' => 'boolean'],
        ['name' => 'joined_on', 'type' => 'date', 'required' => '0'],
        ['name' => 'status', 'type' => 'select', 'required' => '1', 'options' => 'active, blocked'],
    ];
}

beforeEach(function () {
    config(['crud.enabled' => true]);

    $this->root = storage_path('framework/testing/crud-'.uniqid());
    File::ensureDirectoryExists($this->root.'/app/Enums');
    File::ensureDirectoryExists($this->root.'/routes');
    File::copy(app_path('Enums/PermissionName.php'), $this->root.'/app/Enums/PermissionName.php');
    File::copy(base_path('routes/generated.php'), $this->root.'/routes/generated.php');

    $this->post = new FakeCrudPostProcessor;
    $this->generator = new CrudGenerator(new CrudRenderer(base_path('stubs/crud')), $this->post, $this->root);
    app()->instance(CrudGenerator::class, $this->generator);

    $this->admin = User::factory()->create()->assignRole(RoleName::Admin);
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

it('menurunkan semua nama dari satu nama resource', function () {
    $bp = new CrudBlueprint('Purchase orders', []);

    expect($bp->table)->toBe('purchase_orders')
        ->and($bp->model)->toBe('PurchaseOrder')
        ->and($bp->slug())->toBe('purchase-orders')
        ->and($bp->url())->toBe('/admin/purchase-orders')
        ->and($bp->routeParameter())->toBe('purchase_order')
        ->and($bp->permission('view'))->toBe('purchase_orders.view')
        ->and((new CrudBlueprint('Crud sample', []))->table)->toBe('crud_samples');
});

it('membuat semua file, permission, route & manifest', function () {
    $result = $this->generator->generate(CrudBlueprint::fromInput('Crud samples', sampleFields()));

    expect($result['files'])->toHaveCount(11);

    foreach ($result['files'] as $path) {
        expect(is_file("{$this->root}/{$path}"))->toBeTrue($path);
    }

    $enum = File::get("{$this->root}/app/Enums/PermissionName.php");
    expect($enum)->toContain("case CrudSamplesView = 'crud_samples.view';")
        ->toContain("self::CrudSamplesDelete => 'Delete crud samples',")
        ->toContain("'crud_samples' => 'Crud samples',");

    expect(File::get("{$this->root}/routes/generated.php"))
        ->toContain("Route::resource('crud-samples', App\\Http\\Controllers\\Admin\\CrudSampleController::class)");

    expect($this->generator->manifest('crud_samples'))->not->toBeNull()
        ->and($this->generator->urls())->toBe(['/admin/crud-samples' => 'crud_samples'])
        ->and($this->post->calls)->toBe(['format', 'afterGenerate']);

    // Opsi select & tipe kolom sampai ke kode.
    expect(File::get("{$this->root}/app/Http/Requests/Admin/SaveCrudSampleRequest.php"))
        ->toContain("'status' => ['required', 'string', 'in:active,blocked']")
        ->toContain("'notes' => ['nullable', 'string', 'max:5000']");
});

it('menghasilkan file PHP yang valid secara sintaks', function () {
    $result = $this->generator->generate(CrudBlueprint::fromInput('Crud samples', sampleFields()));

    $php = [...array_filter($result['files'], fn ($p) => str_ends_with($p, '.php')), 'app/Enums/PermissionName.php', 'routes/generated.php'];

    foreach ($php as $path) {
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg("{$this->root}/{$path}").' 2>&1', $output, $code);
        expect($code)->toBe(0, $path.': '.implode("\n", $output));
    }
});

it('panjang Short text bisa diatur (migration, validasi, input form)', function () {
    $this->generator->generate(CrudBlueprint::fromInput('Crud samples', [
        ['name' => 'code', 'type' => 'string', 'required' => '1', 'length' => '20'],
        ['name' => 'title', 'type' => 'string', 'required' => '1', 'length' => ''],
    ]));

    expect(File::get(File::glob("{$this->root}/database/migrations/*_create_crud_samples_table.php")[0]))
        ->toContain("\$table->string('code', 20);")
        ->toContain("\$table->string('title');")
        ->and(File::get("{$this->root}/app/Http/Requests/Admin/SaveCrudSampleRequest.php"))
        ->toContain("'code' => ['required', 'string', 'max:20']")
        ->toContain("'title' => ['required', 'string', 'max:255']")
        ->and(File::get("{$this->root}/resources/js/components/admin/crud-sample-form.tsx"))
        ->toContain('maxLength={20}');
});

it('menolak panjang Short text di luar 1–1000', function (string $length) {
    $this->actingAs($this->admin)
        ->post('/admin/menus', [
            'location' => 'admin',
            'title' => 'Crud samples',
            'crud' => ['enabled' => '1', 'fields' => [['name' => 'code', 'type' => 'string', 'length' => $length]]],
        ])
        ->assertSessionHasErrors('crud.fields.0.length');
})->with(['0', '1001', 'abc']);

it('make:crud menerima string(N)', function () {
    $this->artisan('make:crud', ['name' => 'Crud samples', '--field' => ['code:string(15):required']])->assertSuccessful();

    expect(File::get("{$this->root}/app/Http/Requests/Admin/SaveCrudSampleRequest.php"))->toContain("'max:15'");
});

it('remove menghapus file yang belum diubah dan menyimpan yang sudah diubah', function () {
    $original = File::get("{$this->root}/app/Enums/PermissionName.php");
    $this->generator->generate(CrudBlueprint::fromInput('Crud samples', sampleFields()));

    File::append("{$this->root}/app/Policies/CrudSamplePolicy.php", "// my change\n");

    $result = $this->generator->remove('crud_samples', dropTable: false);

    expect($result['kept'])->toBe(['app/Policies/CrudSamplePolicy.php'])
        ->and($result['deleted'])->toContain('app/Http/Controllers/Admin/CrudSampleController.php')
        ->and($result['table_dropped'])->toBeFalse()
        ->and(is_file("{$this->root}/app/Models/CrudSample.php"))->toBeFalse()
        ->and(is_dir("{$this->root}/resources/js/pages/admin/crud-samples"))->toBeFalse()
        // Migration tetap ada karena tabelnya tidak di-drop.
        ->and(File::glob("{$this->root}/database/migrations/*_create_crud_samples_table.php"))->toHaveCount(1)
        // Enum kembali persis seperti semula.
        ->and(File::get("{$this->root}/app/Enums/PermissionName.php"))->toBe($original)
        ->and(File::get("{$this->root}/routes/generated.php"))->not->toContain('crud_samples')
        ->and($this->generator->manifest('crud_samples'))->toBeNull()
        ->and($this->post->calls)->toContain('afterRemove');
});

it('remove tetap menghapus file yang hanya diformat ulang', function () {
    $this->generator->generate(CrudBlueprint::fromInput('Crud samples', sampleFields()));

    // Meniru format ulang seluruh proyek: baris dipecah, indentasi & trailing comma berubah.
    $form = "{$this->root}/resources/js/components/admin/crud-sample-form.tsx";
    File::put($form, str_replace(
        ["}) {\n", "    action,\n"],
        ["},) {\n\n", "        action ,\n"],
        File::get($form),
    ));

    $result = $this->generator->remove('crud_samples', dropTable: false);

    expect($result['kept'])->toBe([])
        ->and(is_file($form))->toBeFalse();
});

it('remove juga membersihkan import controller yang dipindah Pint', function () {
    $this->generator->generate(CrudBlueprint::fromInput('Crud samples', sampleFields()));

    // Meniru hasil Pint: FQCN → baris "use" di atas file.
    $routes = File::get("{$this->root}/routes/generated.php");
    $routes = str_replace(
        ['use Illuminate\\Support\\Facades\\Route;', 'App\\Http\\Controllers\\Admin\\CrudSampleController::class'],
        ["use App\\Http\\Controllers\\Admin\\CrudSampleController;\nuse Illuminate\\Support\\Facades\\Route;", 'CrudSampleController::class'],
        $routes,
    );
    File::put("{$this->root}/routes/generated.php", $routes);

    $this->generator->remove('crud_samples', dropTable: false);

    expect(File::get("{$this->root}/routes/generated.php"))->not->toContain('CrudSample');
});

it('remove dengan drop table ikut menghapus migration', function () {
    $this->generator->generate(CrudBlueprint::fromInput('Crud samples', sampleFields()));

    $result = $this->generator->remove('crud_samples', dropTable: true);

    expect($result['table_dropped'])->toBeTrue()
        ->and(File::glob("{$this->root}/database/migrations/*"))->toBeEmpty();
});

it('remove dengan drop table ikut menghapus migration add_ulid', function () {
    $this->generator->generate(CrudBlueprint::fromInput('Crud samples', sampleFields()));

    // Meniru: php artisan make:ulid-migration crud_samples && php artisan migrate.
    $name = '2030_01_01_000000_add_ulid_to_crud_samples_table';
    File::put("{$this->root}/database/migrations/{$name}.php", '<?php return new class {};');
    DB::table('migrations')->insert(['migration' => $name, 'batch' => 99]);

    $result = $this->generator->remove('crud_samples', dropTable: true);

    expect($result['deleted'])->toContain("database/migrations/{$name}.php")
        ->and(File::glob("{$this->root}/database/migrations/*"))->toBeEmpty()
        ->and(DB::table('migrations')->where('migration', $name)->exists())->toBeFalse();
});

it('menolak nama yang bentrok atau dicadangkan', function (string $name) {
    expect($this->generator->problems(new CrudBlueprint($name, [])))->not->toBeEmpty();
})->with(['Users', 'Products', 'Orders', 'Class', 'Menus']);

it('menolak generate dua kali dengan nama yang sama', function () {
    $this->generator->generate(CrudBlueprint::fromInput('Crud samples', sampleFields()));

    expect($this->generator->problems(new CrudBlueprint('Crud sample', [])))
        ->toBe(['"Crud samples" was already generated.']);
});

it('mati di production apa pun isi config', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(CrudGenerator::enabled())->toBeFalse();
    $this->generator->generate(CrudBlueprint::fromInput('Crud samples', sampleFields()));
})->throws(RuntimeException::class, 'only runs in a local development environment');

it('form Add menu hanya menawarkan generator saat aktif', function () {
    $this->actingAs($this->admin)
        ->get('/admin/menus/create?location=admin')
        ->assertInertia(fn (Assert $page) => $page->where('crudGenerator.maxFields', 20));

    $this->actingAs($this->admin)
        ->get('/admin/menus/create?location=website')
        ->assertInertia(fn (Assert $page) => $page->where('crudGenerator', null));

    config(['crud.enabled' => false]);

    $this->actingAs($this->admin)
        ->get('/admin/menus/create?location=admin')
        ->assertInertia(fn (Assert $page) => $page->where('crudGenerator', null));
});

it('Add menu + Generate CRUD page membuat kode dan menghubungkan menu', function () {
    $this->actingAs($this->admin)
        ->post('/admin/menus', [
            'location' => 'admin',
            'title' => 'Crud samples',
            'url' => '/ignored',
            'audience' => 'everyone',
            'crud' => ['enabled' => '1', 'name' => '', 'token' => 'test-token-123', 'fields' => sampleFields()],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/admin/menus?location=admin');

    $menu = Menu::firstWhere('title', 'Crud samples');

    expect($menu->url)->toBe('/admin/crud-samples')
        ->and($menu->permission)->toBe('crud_samples.view')
        ->and($menu->icon)->toBe('FolderOpen')
        ->and(is_file("{$this->root}/app/Http/Controllers/Admin/CrudSampleController.php"))->toBeTrue();

    // Halaman yang ter-reload Vite membaca hasilnya lewat token.
    $this->actingAs($this->admin)
        ->getJson('/admin/menus/generator-status/test-token-123')
        ->assertOk()
        ->assertJson(['state' => 'done', 'type' => 'success', 'redirect' => '/admin/menus?location=admin']);

    $this->actingAs($this->admin)
        ->getJson('/admin/menus/generator-status/unknown-token')
        ->assertJson(['state' => 'unknown']);

    // Halaman Menus menandai menu ini sebagai hasil generator.
    $this->actingAs($this->admin)
        ->get('/admin/menus?location=admin')
        ->assertInertia(fn (Assert $page) => $page->where(
            'rows',
            fn ($rows) => collect($rows)->firstWhere('id', $menu->id)['generated'] === [
                'table' => 'crud_samples', 'db_table' => 'crud_samples', 'can_drop' => true,
            ],
        ));
});

it('memvalidasi input generator (termasuk upaya menyisipkan kode)', function (array $crud, string $errorKey) {
    $this->actingAs($this->admin)
        ->post('/admin/menus', [
            'location' => 'admin',
            'title' => 'Crud samples',
            'crud' => ['enabled' => '1', ...$crud],
        ])
        ->assertSessionHasErrors($errorKey);

    expect(Menu::where('title', 'Crud samples')->exists())->toBeFalse()
        ->and(File::exists("{$this->root}/app/Models/CrudSample.php"))->toBeFalse();
})->with([
    'tanpa field' => [['name' => 'Crud samples', 'fields' => []], 'crud.fields'],
    'nama field tidak valid' => [['name' => 'Crud samples', 'fields' => [['name' => 'Bad Name', 'type' => 'string']]], 'crud.fields.0.name'],
    'nama field dicadangkan' => [['name' => 'Crud samples', 'fields' => [['name' => 'id', 'type' => 'string']]], 'crud.fields.0.name'],
    'nama field ganda' => [['name' => 'Crud samples', 'fields' => [['name' => 'a', 'type' => 'string'], ['name' => 'a', 'type' => 'text']]], 'crud.fields.0.name'],
    'tipe tidak dikenal' => [['name' => 'Crud samples', 'fields' => [['name' => 'a', 'type' => 'json']]], 'crud.fields.0.type'],
    'select tanpa pilihan' => [['name' => 'Crud samples', 'fields' => [['name' => 'a', 'type' => 'select']]], 'crud.fields.0.options'],
    'kode di pilihan' => [['name' => 'Crud samples', 'fields' => [['name' => 'a', 'type' => 'select', 'options' => "x'); system('calc"]]], 'crud.fields.0.options'],
    'kode di nama' => [['name' => "S'; exit;", 'fields' => [['name' => 'a', 'type' => 'string']]], 'crud.name'],
    'nama bentrok' => [['name' => 'Products', 'fields' => [['name' => 'a', 'type' => 'string']]], 'crud.name'],
]);

it('generator mati → input crud diabaikan, menu biasa tetap dibuat', function () {
    config(['crud.enabled' => false]);

    $this->actingAs($this->admin)
        ->post('/admin/menus', [
            'location' => 'admin',
            'title' => 'Plain',
            'url' => '/admin/plain',
            'audience' => 'everyone',
            'crud' => ['enabled' => '1', 'name' => 'Plain', 'fields' => sampleFields()],
        ])
        ->assertSessionHasNoErrors();

    expect(Menu::firstWhere('title', 'Plain')->url)->toBe('/admin/plain')
        ->and(File::exists("{$this->root}/app/Models/Plain.php"))->toBeFalse();
});

it('hapus menu hasil generator bisa ikut menghapus kodenya', function () {
    $this->generator->generate(CrudBlueprint::fromInput('Crud samples', sampleFields()));
    $menu = Menu::create(['location' => 'admin', 'title' => 'Crud samples', 'url' => '/admin/crud-samples', 'sort_order' => 99, 'is_active' => true]);

    $this->actingAs($this->admin)
        ->delete("/admin/menus/{$menu->ulid}", ['remove_code' => 1, 'drop_table' => 0])
        ->assertRedirect('/admin/menus?location=admin');

    expect(Menu::find($menu->id))->toBeNull()
        ->and(is_file("{$this->root}/app/Models/CrudSample.php"))->toBeFalse()
        ->and($this->generator->manifest('crud_samples'))->toBeNull();
});

it('hapus menu tanpa centang tidak menyentuh kode', function () {
    $this->generator->generate(CrudBlueprint::fromInput('Crud samples', sampleFields()));
    $menu = Menu::create(['location' => 'admin', 'title' => 'Crud samples', 'url' => '/admin/crud-samples', 'sort_order' => 99, 'is_active' => true]);

    $this->actingAs($this->admin)->delete("/admin/menus/{$menu->ulid}");

    expect(is_file("{$this->root}/app/Models/CrudSample.php"))->toBeTrue();
});

it('make:crud dari terminal, termasuk menu sidebar', function () {
    $this->artisan('make:crud', [
        'name' => 'Crud samples',
        '--field' => ['name:string:required', 'status:select:required:active|blocked'],
        '--menu' => true,
    ])->assertSuccessful();

    expect(File::get("{$this->root}/app/Http/Requests/Admin/SaveCrudSampleRequest.php"))
        ->toContain("'in:active,blocked'")
        ->and(Menu::firstWhere('url', '/admin/crud-samples')->permission)->toBe('crud_samples.view');

    $this->artisan('crud:remove', ['table' => 'crud_samples', '--force' => true])->assertSuccessful();

    expect(Menu::where('url', '/admin/crud-samples')->exists())->toBeFalse();
});

/*
 * Master–detail untuk TABEL BARU ("Create a new table" + tabel detail baru).
 */

/** @return list<array{name: string, fields: list<array<string, string>>}> */
function newDetails(): array
{
    return [
        ['name' => 'items', 'fields' => [
            ['name' => 'sku', 'type' => 'string', 'required' => '1', 'length' => '30'],
            ['name' => 'qty', 'type' => 'integer', 'required' => '1'],
        ]],
        ['name' => 'notes', 'fields' => [
            // Nama kolom sama dengan detail lain: boleh (tabelnya berbeda).
            ['name' => 'sku', 'type' => 'string', 'required' => '0'],
            ['name' => 'body', 'type' => 'text', 'required' => '1'],
        ]],
    ];
}

it('tabel baru + detail baru: nama turunan & semua file', function () {
    $bp = CrudBlueprint::fromInput('Purchase orders', [['name' => 'po_no', 'type' => 'string', 'required' => '1']], null, newDetails());

    expect($bp->details)->toHaveCount(2)
        ->and($bp->details[0]->table)->toBe('purchase_order_items')
        ->and($bp->details[0]->model)->toBe('PurchaseOrderItem')
        ->and($bp->details[0]->foreignKey)->toBe('purchase_order_id')
        ->and($bp->details[0]->usesExistingTable())->toBeFalse()
        ->and($bp->details[1]->table)->toBe('purchase_order_notes');

    $result = $this->generator->generate($bp);

    // 11 file induk + 9 file per detail.
    expect($result['files'])->toHaveCount(11 + 9 * 2);

    $migration = File::get(File::glob("{$this->root}/database/migrations/*_create_purchase_orders_table.php")[0]);

    // Induk dibuat dulu, detail menunjuk ke induk; di-drop dalam urutan terbalik.
    expect(strpos($migration, "create('purchase_orders'"))->toBeLessThan(strpos($migration, "create('purchase_order_items'"))
        ->and($migration)->toContain("\$table->foreignId('purchase_order_id')->constrained('purchase_orders');")
        ->toContain("\$table->string('sku', 30);")
        ->toContain("\$table->text('body');")
        ->and(strpos($migration, "dropIfExists('purchase_order_notes')"))->toBeLessThan(strpos($migration, "dropIfExists('purchase_order_items')"))
        ->and(strpos($migration, "dropIfExists('purchase_order_items')"))->toBeLessThan(strpos($migration, "dropIfExists('purchase_orders')"));

    expect(File::get("{$this->root}/app/Models/PurchaseOrder.php"))
        ->toContain('public function items(): HasMany')
        ->toContain("return \$this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');")
        ->and(File::get("{$this->root}/app/Http/Controllers/Admin/PurchaseOrderController.php"))
        ->toContain('DB::connection()->transaction(')
        ->toContain("foreach (\$request->detailRows('notes') as \$row)")
        ->and(File::get("{$this->root}/routes/generated.php"))
        ->toContain("Route::resource('purchase-orders.items', App\\Http\\Controllers\\Admin\\PurchaseOrderItemController::class)")
        ->and(File::get("{$this->root}/tests/Feature/Admin/Generated/PurchaseOrderCrudTest.php"))
        ->toContain('use App\\Models\\PurchaseOrderItem;')
        ->toContain("'items' => [Arr::only(PurchaseOrderItem::factory()->make()->toArray(), ['sku', 'qty'])],")
        ->toContain('expect($item->notes()->count())->toBe(1);');

    $manifest = $this->generator->manifest('purchase_orders');
    expect(array_column($manifest['details'], 'db_table'))->toBe(['purchase_order_items', 'purchase_order_notes']);

    $php = [...array_filter($result['files'], fn ($p) => str_ends_with($p, '.php')), 'routes/generated.php'];

    foreach ($php as $path) {
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg("{$this->root}/{$path}").' 2>&1', $output, $code);
        expect($code)->toBe(0, $path.': '.implode("\n", $output));
    }

    // Hapus modul: folder halaman detail ikut hilang.
    $this->generator->remove('purchase_orders', dropTable: false);

    expect(is_dir("{$this->root}/resources/js/pages/admin/purchase-orders"))->toBeFalse()
        ->and(is_file("{$this->root}/app/Models/PurchaseOrderItem.php"))->toBeFalse();
});

it('tabel baru tanpa detail: migration sama seperti sebelumnya', function () {
    $this->generator->generate(CrudBlueprint::fromInput('Crud samples', sampleFields()));

    $migration = File::get(File::glob("{$this->root}/database/migrations/*_create_crud_samples_table.php")[0]);

    expect($migration)->not->toContain('foreignId')
        ->toContain("            \$table->timestamps();\n        });\n    }\n")
        ->toContain("    public function down(): void\n    {\n        Schema::dropIfExists('crud_samples');");
});

it('Add menu: tabel baru + detail baru', function () {
    $this->actingAs($this->admin)
        ->post('/admin/menus', [
            'location' => 'admin',
            'title' => 'Purchase orders',
            'crud' => [
                'enabled' => '1',
                'fields' => [['name' => 'po_no', 'type' => 'string', 'required' => '1']],
                'details' => newDetails(),
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/admin/menus?location=admin');

    expect(is_file("{$this->root}/app/Models/PurchaseOrderItem.php"))->toBeTrue()
        ->and(is_file("{$this->root}/resources/js/components/admin/purchase-order-note-rows.tsx"))->toBeTrue()
        ->and(Menu::firstWhere('title', 'Purchase orders')->url)->toBe('/admin/purchase-orders');
});

it('Add menu: memvalidasi tabel detail baru', function (array $details, string $errorKey) {
    $this->actingAs($this->admin)
        ->post('/admin/menus', [
            'location' => 'admin',
            'title' => 'Purchase orders',
            'crud' => [
                'enabled' => '1',
                'fields' => [['name' => 'po_no', 'type' => 'string', 'required' => '1']],
                'details' => $details,
            ],
        ])
        ->assertSessionHasErrors($errorKey);

    expect(File::exists("{$this->root}/app/Models/PurchaseOrder.php"))->toBeFalse();
})->with([
    'tanpa nama' => [[['name' => '', 'fields' => [['name' => 'a', 'type' => 'string']]]], 'crud.details.0.name'],
    'tanpa kolom' => [[['name' => 'items', 'fields' => []]], 'crud.details.0.fields'],
    'kolom ganda' => [[['name' => 'items', 'fields' => [['name' => 'a', 'type' => 'string'], ['name' => 'a', 'type' => 'text']]]], 'crud.details.0.fields'],
    'kolom penghubung' => [[['name' => 'items', 'fields' => [['name' => 'purchase_order_id', 'type' => 'integer']]]], 'crud.details.0.fields'],
    'kolom tidak valid' => [[['name' => 'items', 'fields' => [['name' => 'Bad Name', 'type' => 'string']]]], 'crud.details.0.fields.0.name'],
    'select tanpa pilihan' => [[['name' => 'items', 'fields' => [['name' => 'a', 'type' => 'select']]]], 'crud.details.0.fields.0.options'],
    'nama sama' => [[
        ['name' => 'items', 'fields' => [['name' => 'a', 'type' => 'string']]],
        ['name' => 'Items', 'fields' => [['name' => 'b', 'type' => 'string']]],
    ], 'crud.details'],
    'nama dicadangkan' => [[['name' => 'abilities', 'fields' => [['name' => 'a', 'type' => 'string']]]], 'crud.details'],
    'sama dengan resource' => [[['name' => 'purchase order', 'fields' => [['name' => 'a', 'type' => 'string']]]], 'crud.details'],
    'kode di nama' => [[['name' => "x'); exit;", 'fields' => [['name' => 'a', 'type' => 'string']]]], 'crud.details.0.name'],
]);

it('make:crud: tabel baru + detail baru dari terminal', function () {
    $this->artisan('make:crud', [
        'name' => 'Purchase orders',
        '--field' => ['po_no:string:required', 'items.sku:string(30):required', 'items.qty:integer'],
        '--detail' => ['items'],
    ])->assertSuccessful();

    expect(File::get(File::glob("{$this->root}/database/migrations/*_create_purchase_orders_table.php")[0]))
        ->toContain("create('purchase_order_items'")
        ->toContain("\$table->string('sku', 30);")
        ->and(File::get("{$this->root}/app/Http/Requests/Admin/SavePurchaseOrderItemRequest.php"))
        ->toContain("'qty' => ['nullable', 'integer'");
});
