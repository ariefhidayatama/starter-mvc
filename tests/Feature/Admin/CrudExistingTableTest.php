<?php

use App\Enums\RoleName;
use App\Models\Menu;
use App\Models\User;
use App\Services\Crud\CrudBlueprint;
use App\Services\Crud\CrudField;
use App\Services\Crud\CrudGenerator;
use App\Services\Crud\CrudPostProcessor;
use App\Services\Crud\CrudRenderer;
use App\Services\Crud\CrudTableInspector;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/*
 * CRUD generator → "Use an existing table".
 *
 * Tabel "lama" dibuat di database SQLite di memori (koneksi crud_legacy),
 * bukan di database test MySQL, agar tidak mengganggu test lain.
 */

beforeEach(function () {
    config([
        'crud.enabled' => true,
        'database.connections.crud_legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true],
    ]);

    $schema = Schema::connection('crud_legacy');
    $schema->create('regions', function (Blueprint $t) {
        $t->id();
        $t->string('name');
    });
    // Nama tabel tunggal & tanpa timestamps: tidak mengikuti konvensi Laravel.
    $schema->create('supplier', function (Blueprint $t) {
        $t->id();
        $t->string('code', 10);
        $t->string('name', 100);
        $t->string('phone')->nullable();
        $t->integer('rating')->default(0);
        $t->boolean('is_active');
        $t->dateTime('joined_at')->nullable();
        $t->foreignId('region_id')->constrained('regions');
    });
    // Tanpa primary key "id" → tidak bisa dipakai.
    $schema->create('pivot_like', function (Blueprint $t) {
        $t->unsignedBigInteger('a_id');
        $t->unsignedBigInteger('b_id');
        $t->primary(['a_id', 'b_id']);
    });

    $this->root = storage_path('framework/testing/crud-'.uniqid());
    File::ensureDirectoryExists($this->root.'/app/Enums');
    File::ensureDirectoryExists($this->root.'/routes');
    File::copy(app_path('Enums/PermissionName.php'), $this->root.'/app/Enums/PermissionName.php');
    File::copy(base_path('routes/generated.php'), $this->root.'/routes/generated.php');

    $this->post = new class implements CrudPostProcessor
    {
        /** @var list<string|null> */
        public array $migrations = [];

        public function format(string $root, array $paths): array
        {
            return [];
        }

        public function afterGenerate(string $root, ?string $migrationPath): array
        {
            $this->migrations[] = $migrationPath;

            return [];
        }

        public function afterRemove(string $root): array
        {
            return [];
        }
    };

    $this->generator = new CrudGenerator(
        new CrudRenderer(base_path('stubs/crud')),
        $this->post,
        $this->root,
        new CrudTableInspector('crud_legacy'),
    );
    app()->instance(CrudGenerator::class, $this->generator);

    $this->admin = User::factory()->create()->assignRole(RoleName::Admin);
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

it('memetakan tipe kolom MySQL ke field', function (array $column, string|array $expected) {
    $result = (new CrudTableInspector)->mapColumn($column);

    if (is_string($expected)) {
        expect($result)->toBeString()->toContain($expected);

        return;
    }

    expect($result)->toBeInstanceOf(CrudField::class)
        ->and(array_intersect_key($result->toArray(), $expected))->toBe($expected);
})->with([
    'varchar(50) NOT NULL' => [
        ['name' => 'code', 'type_name' => 'varchar', 'type' => 'varchar(50)', 'nullable' => false, 'default' => null],
        ['type' => 'string', 'required' => true, 'max' => 50],
    ],
    'int unsigned default 0' => [
        ['name' => 'qty', 'type_name' => 'int', 'type' => 'int unsigned', 'nullable' => false, 'default' => '0'],
        ['type' => 'integer', 'required' => false, 'max' => 4294967295, 'unsigned' => true],
    ],
    'tinyint(1)' => [
        ['name' => 'ok', 'type_name' => 'tinyint', 'type' => 'tinyint(1)', 'nullable' => false, 'default' => null],
        ['type' => 'boolean', 'required' => false],
    ],
    'tinyint biasa' => [
        ['name' => 'level', 'type_name' => 'tinyint', 'type' => 'tinyint', 'nullable' => true, 'default' => null],
        ['type' => 'integer', 'max' => 127],
    ],
    'enum' => [
        ['name' => 'status', 'type_name' => 'enum', 'type' => "enum('draft','active')", 'nullable' => false, 'default' => null],
        ['type' => 'select', 'options' => ['draft', 'active']],
    ],
    'datetime' => [
        ['name' => 'seen_at', 'type_name' => 'datetime', 'type' => 'datetime', 'nullable' => true, 'default' => null],
        ['type' => 'datetime', 'required' => false],
    ],
    'decimal' => [
        ['name' => 'price', 'type_name' => 'decimal', 'type' => 'decimal(10,2)', 'nullable' => true, 'default' => null],
        ['type' => 'decimal'],
    ],
    'json dilewati' => [
        ['name' => 'meta', 'type_name' => 'json', 'type' => 'json', 'nullable' => true, 'default' => null],
        'not supported',
    ],
    'enum dengan karakter aneh dilewati' => [
        ['name' => 'kind', 'type_name' => 'enum', 'type' => "enum('a''b','c')", 'nullable' => false, 'default' => null],
        'not supported',
    ],
    'nama kolom bukan snake_case' => [
        ['name' => 'FirstName', 'type_name' => 'varchar', 'type' => 'varchar(20)', 'nullable' => true, 'default' => null],
        'snake_case',
    ],
]);

it('membaca struktur tabel: timestamps, foreign key, primary key', function () {
    $inspector = new CrudTableInspector('crud_legacy');
    $info = $inspector->describe('supplier');
    $fields = collect($info['fields'])->keyBy('name');

    expect($info['timestamps'])->toBeFalse()
        ->and($info['problem'])->toBeNull()
        ->and($fields->keys()->all())->toBe(['code', 'name', 'phone', 'rating', 'is_active', 'joined_at', 'region_id'])
        ->and($fields['code']->required)->toBeTrue()
        ->and($fields['phone']->required)->toBeFalse()
        ->and($fields['rating']->required)->toBeFalse()   // punya default
        ->and($fields['is_active']->type)->toBe('boolean')
        ->and($fields['region_id']->references)->toBe('regions');

    expect($inspector->describe('pivot_like')['problem'])->toContain('primary key named "id"');
});

it('hanya menawarkan tabel yang boleh dipakai', function () {
    expect($this->generator->candidateTables())->toBe(['pivot_like', 'regions', 'supplier']);
});

it('generate dari tabel lama: tanpa migration, model menyesuaikan tabel', function () {
    $result = $this->generator->generate($this->generator->blueprintForTable('Legacy suppliers', 'supplier'));

    expect($result['files'])->toHaveCount(10)
        ->and(collect($result['files'])->filter(fn ($p) => str_contains($p, 'migrations')))->toBeEmpty()
        ->and($this->post->migrations)->toBe([null]);

    expect(File::get("{$this->root}/app/Models/LegacySupplier.php"))
        ->toContain("protected \$table = 'supplier';")
        ->toContain('public $timestamps = false;')
        ->not->toContain('$created_at');

    expect(File::get("{$this->root}/app/Http/Controllers/Admin/LegacySupplierController.php"))
        ->toContain("'-id'")
        ->not->toContain('created_at');

    expect(File::get("{$this->root}/app/Http/Requests/Admin/SaveLegacySupplierRequest.php"))
        // (SQLite tidak menyimpan panjang varchar; batas max:N dari MySQL diuji di atas.)
        ->toContain("'code' => ['required', 'string', 'max:255']")
        ->toContain("'region_id' => ['required', 'integer'")
        ->toContain("'joined_at' => ['nullable', 'date']");

    // Test hasil generate dilewati bila tabelnya tidak ada di database test,
    // dan CRUD test dilewati karena ada foreign key.
    expect(File::get("{$this->root}/tests/Feature/Admin/Generated/LegacySupplierCrudTest.php"))
        ->toContain("Schema::hasTable('supplier')")
        ->toContain("->skip('Foreign key column(s) region_id");

    expect(File::get("{$this->root}/resources/js/components/admin/legacy-supplier-form.tsx"))
        ->toContain('type="datetime-local"')
        ->toContain('ID of a row in <code>regions</code>')
        ->not->toContain('created_at');

    expect($this->generator->manifest('legacy_suppliers'))
        ->toMatchArray(['db_table' => 'supplier', 'existing_table' => true, 'migration' => null])
        ->and($this->generator->generated()['/admin/legacy-suppliers'])
        ->toBe(['table' => 'legacy_suppliers', 'db_table' => 'supplier', 'can_drop' => false])
        // Tabel yang sudah dipakai tidak ditawarkan lagi.
        ->and($this->generator->candidateTables())->not->toContain('supplier');
});

it('tabel lama TIDAK pernah di-drop saat modul dihapus', function () {
    $this->generator->generate($this->generator->blueprintForTable('Legacy suppliers', 'supplier'));

    $result = $this->generator->remove('legacy_suppliers', dropTable: true);

    expect($result['table_dropped'])->toBeFalse()
        ->and(Schema::connection('crud_legacy')->hasTable('supplier'))->toBeTrue()
        ->and(is_file("{$this->root}/app/Models/LegacySupplier.php"))->toBeFalse();
});

it('menolak tabel tanpa primary key id', function () {
    expect($this->generator->problems(new CrudBlueprint('Pivots', [], 'pivot_like')))
        ->toBe(['"pivot_like" needs an auto-increment primary key named "id".']);
});

it('mode tabel baru menyarankan "Use an existing table" bila tabelnya sudah ada', function () {
    expect($this->generator->problems(new CrudBlueprint('Regions', [])))
        ->toHaveCount(1)
        ->{0}->toContain('Use an existing table');
});

it('daftar tabel & struktur kolom dimuat lewat endpoint JSON', function () {
    $this->actingAs($this->admin)
        ->getJson('/admin/menus/generator/tables')
        ->assertOk()
        ->assertJson(['tables' => ['pivot_like', 'regions', 'supplier']]);

    $this->actingAs($this->admin)
        ->getJson('/admin/menus/generator/table?table=supplier')
        ->assertOk()
        ->assertJsonPath('timestamps', false)
        ->assertJsonPath('fields.0.name', 'code')
        ->assertJsonPath('problem', null);

    // regions punya tabel detail: supplier.region_id → regions.id
    $this->actingAs($this->admin)
        ->getJson('/admin/menus/generator/table?table=regions')
        ->assertJsonPath('children.0.table', 'supplier')
        ->assertJsonPath('children.0.column', 'region_id')
        // Kolom penghubung tidak ikut kolom form detail.
        ->assertJsonMissingPath('children.0.fields.6');

    $this->actingAs($this->admin)->getJson('/admin/menus/generator/table?table=users')->assertNotFound();
    $this->actingAs($this->admin)->getJson('/admin/menus/generator/tables?connection=mysql')->assertNotFound();
});

it('endpoint generator tertutup untuk user tanpa izin & saat generator mati', function () {
    $staff = User::factory()->create()->assignRole(RoleName::Staff);
    $this->actingAs($staff)->getJson('/admin/menus/generator/tables')->assertForbidden();

    config(['crud.enabled' => false]);
    $this->actingAs($this->admin)->getJson('/admin/menus/generator/tables')->assertNotFound();
});

it('master–detail: kode induk & detail dibuat bersama', function () {
    $bp = $this->generator->blueprintForTable('Areas', 'regions', null, null, [['table' => 'supplier']]);

    expect($bp->model)->toBe('Area')
        ->and($bp->details[0]->model)->toBe('AreaDetail')
        ->and($bp->details[0]->detailKey)->toBe('details')
        ->and($bp->details[0]->foreignKey)->toBe('region_id')
        ->and(collect($bp->details[0]->fields)->pluck('name'))->not->toContain('region_id');

    $result = $this->generator->generate($bp);
    $file = fn (string $path) => File::get("{$this->root}/{$path}");

    expect($result['files'])->toContain(
        'app/Models/AreaDetail.php',
        'app/Http/Controllers/Admin/AreaDetailController.php',
        'app/Http/Requests/Admin/SaveAreaDetailRequest.php',
        'resources/js/components/admin/area-detail-section.tsx',
        'resources/js/components/admin/area-detail-rows.tsx',
        'resources/js/pages/admin/areas/details/create.tsx',
        'resources/js/pages/admin/areas/details/edit.tsx',
    );

    expect($file('app/Models/Area.php'))
        ->toContain('public function details(): HasMany')
        ->toContain("return \$this->hasMany(AreaDetail::class, 'region_id');");

    expect($file('app/Models/AreaDetail.php'))
        ->toContain("protected \$table = 'supplier';")
        ->toContain("return \$this->belongsTo(Area::class, 'region_id');");

    // Hapus induk = hapus detail dalam satu transaksi.
    expect($file('app/Http/Controllers/Admin/AreaController.php'))
        ->toContain('DB::connection()->transaction(function () use ($area) {')
        ->toContain('$area->details()->delete();')
        ->toContain("'details' => \$area->details()->orderByDesc('id')->paginate(10, ['*'], 'details_page')");

    expect($file('routes/generated.php'))
        ->toContain("Route::resource('areas.details', App\\Http\\Controllers\\Admin\\AreaDetailController::class)->except(['index', 'show'])->scoped();");

    expect($file('resources/js/pages/admin/areas/edit.tsx'))->toContain('<AreaDetailSection');
    expect($file('resources/js/components/admin/area-detail-form.tsx'))->toContain('region_id: number;');

    foreach (array_filter($result['files'], fn ($p) => str_ends_with($p, '.php')) as $path) {
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg("{$this->root}/{$path}").' 2>&1', $output, $code);
        expect($code)->toBe(0, $path);
    }

    // Dihapus bersih, termasuk file detail.
    $this->generator->remove('areas', dropTable: false);
    expect(File::exists("{$this->root}/app/Models/AreaDetail.php"))->toBeFalse()
        ->and(File::isDirectory("{$this->root}/resources/js/pages/admin/areas"))->toBeFalse()
        ->and($file('routes/generated.php'))->not->toContain('Area');
});

it('menolak tabel detail yang tidak punya foreign key ke induk', function () {
    $this->actingAs($this->admin)
        ->post('/admin/menus', [
            'location' => 'admin',
            'title' => 'Areas',
            'crud' => ['enabled' => '1', 'table' => 'regions', 'details' => [['table' => 'pivot_like']], 'columns' => ['name']],
        ])
        ->assertSessionHasErrors('crud.details.0.table');
});

it('tabel di koneksi lain: model memakai koneksi itu & izin akun database ikut dicek', function () {
    config(['crud.connections' => ['crud_legacy' => 'Legacy DB']]);
    // Koneksi default generator = MySQL test; tabel diambil dari koneksi crud_legacy.
    $generator = new CrudGenerator(new CrudRenderer(base_path('stubs/crud')), $this->post, $this->root);

    expect($generator->connections())->toBe(['crud_legacy' => 'Legacy DB'])
        ->and($generator->candidateTables('crud_legacy'))->toContain('supplier');

    $generator->generate($generator->blueprintForTable('Legacy suppliers', 'supplier', null, 'crud_legacy'));

    expect(File::get("{$this->root}/app/Models/LegacySupplier.php"))->toContain("protected \$connection = 'crud_legacy';")
        ->and(File::get("{$this->root}/app/Policies/LegacySupplierPolicy.php"))->toContain("TableAccess::allows('crud_legacy', 'supplier', 'INSERT')")
        // Test hasil generate tidak dijalankan otomatis (tidak menulis ke database lain).
        ->and(File::get("{$this->root}/tests/Feature/Admin/Generated/LegacySupplierCrudTest.php"))->toContain('markTestSkipped');
});

it('tabel BARU di database lain: migration & model memakai koneksi itu', function () {
    config(['crud.connections' => ['crud_legacy' => 'Legacy DB']]);
    $generator = new CrudGenerator(new CrudRenderer(base_path('stubs/crud')), $this->post, $this->root);

    $bp = CrudBlueprint::fromInput('Gadgets', [['name' => 'title', 'type' => 'string', 'required' => '1', 'length' => '80']], 'crud_legacy');
    expect($generator->problems($bp))->toBe([]);

    $result = $generator->generate($bp);
    $migration = collect($result['files'])->first(fn ($p) => str_contains($p, 'migrations'));

    expect(File::get("{$this->root}/{$migration}"))
        ->toContain("Schema::connection('crud_legacy')->create('gadgets'")
        ->toContain("Schema::connection('crud_legacy')->dropIfExists('gadgets')")
        ->and(File::get("{$this->root}/app/Models/Gadget.php"))->toContain("protected \$connection = 'crud_legacy';");

    // Jalankan migration-nya: tabel muncul di database TUJUAN, bukan database aplikasi.
    (require "{$this->root}/{$migration}")->up();
    expect(Schema::connection('crud_legacy')->hasTable('gadgets'))->toBeTrue()
        ->and(Schema::hasTable('gadgets'))->toBeFalse();

    // Hapus + drop table: di-drop dari database tujuan.
    $removed = $generator->remove('gadgets', dropTable: true);
    expect($removed['table_dropped'])->toBeTrue()
        ->and(Schema::connection('crud_legacy')->hasTable('gadgets'))->toBeFalse();
});

it('tabel baru: cek "sudah ada" di database tujuan', function () {
    config(['crud.connections' => ['crud_legacy' => 'Legacy DB']]);
    $generator = new CrudGenerator(new CrudRenderer(base_path('stubs/crud')), $this->post, $this->root);

    // "regions" ada di crud_legacy, tidak ada di database aplikasi.
    expect($generator->problems(new CrudBlueprint('Regions', [], connection: 'crud_legacy'))[0])->toContain('already exists')
        ->and($generator->problems(new CrudBlueprint('Regions', [])))->toBe([]);
});

it('koneksi tanpa driver PHP atau tanpa host tidak ditawarkan', function () {
    config([
        'crud.connections' => ['crud_legacy' => 'Legacy', 'oracle_x' => 'Oracle', 'no_host' => 'Empty'],
        'database.connections.oracle_x' => ['driver' => 'oci', 'host' => 'db.example.com'],
        'database.connections.no_host' => ['driver' => 'mysql', 'host' => null],
    ]);

    expect(array_keys($this->generator->connections()))->toBe(['crud_legacy']);

    $this->actingAs($this->admin)
        ->post('/admin/menus', [
            'location' => 'admin',
            'title' => 'Gadgets',
            'crud' => ['enabled' => '1', 'connection' => 'oracle_x', 'fields' => [['name' => 'title', 'type' => 'string']]],
        ])
        ->assertSessionHasErrors('crud.connection');
});

it('kolom audit dikenali: tidak di form, model memakai nama kolom asli', function () {
    Schema::connection('crud_legacy')->create('note', function (Blueprint $t) {
        $t->id();
        $t->string('body');
        $t->string('created_by', 30);
        $t->dateTime('created_date');
        $t->string('updated_by', 30);
        $t->dateTime('updated_date');
    });

    $info = (new CrudTableInspector('crud_legacy'))->describe('note');

    expect(collect($info['fields'])->pluck('name')->all())->toBe(['body'])
        ->and($info['timestamps'])->toBeTrue()
        // SQLite tidak menyimpan panjang varchar → 255. (SQL Server: 30, lihat SqlServerIntegrationTest.)
        ->and($info['audit'])->toBe(['created_at' => 'created_date', 'updated_at' => 'updated_date', 'created_by' => 'created_by', 'updated_by' => 'updated_by', 'user_max' => 255]);

    $this->generator->generate($this->generator->blueprintForTable('Notes', 'note'));
    $model = File::get("{$this->root}/app/Models/Note.php");

    expect($model)
        ->toContain("const CREATED_AT = 'created_date';")
        ->toContain("const UPDATED_AT = 'updated_date';")
        ->toContain('#[ObservedBy([FillAuditUser::class])]')
        ->toContain('class Note extends Model implements HasAuditUser')
        ->toContain("return ['created_by' => 'created_by', 'updated_by' => 'updated_by', 'max' => 255];");

    expect(File::get("{$this->root}/app/Http/Controllers/Admin/NoteController.php"))->toContain("'-created_date'");
});

it('kolom wajib tidak boleh ditinggalkan', function () {
    $this->actingAs($this->admin)
        ->post('/admin/menus', [
            'location' => 'admin',
            'title' => 'Legacy suppliers',
            'crud' => ['enabled' => '1', 'table' => 'supplier', 'columns' => ['phone']],
        ])
        ->assertSessionHasErrors(['crud.columns' => 'These columns are required by the table and must stay selected: code, name, region_id.']);

    expect(Menu::where('title', 'Legacy suppliers')->exists())->toBeFalse();
});

it('menolak tabel aplikasi atau kolom yang tidak ada', function (array $crud, string $errorKey) {
    $this->actingAs($this->admin)
        ->post('/admin/menus', ['location' => 'admin', 'title' => 'X stuff', 'crud' => ['enabled' => '1', ...$crud]])
        ->assertSessionHasErrors($errorKey);
})->with([
    'tabel aplikasi' => [['table' => 'users', 'columns' => ['name']], 'crud.table'],
    'kolom tidak ada' => [['table' => 'supplier', 'columns' => ['code', 'name', 'nope']], 'crud.columns.2'],
]);

it('Add menu dari tabel lama membuat kode & menu', function () {
    $this->actingAs($this->admin)
        ->post('/admin/menus', [
            'location' => 'admin',
            'title' => 'Legacy suppliers',
            'crud' => ['enabled' => '1', 'name' => '', 'table' => 'supplier', 'columns' => ['code', 'name', 'region_id']],
        ])
        ->assertSessionHasNoErrors();

    $menu = Menu::firstWhere('title', 'Legacy suppliers');

    expect($menu->url)->toBe('/admin/legacy-suppliers')
        ->and($menu->permission)->toBe('legacy_suppliers.view')
        ->and(File::get("{$this->root}/app/Models/LegacySupplier.php"))
        ->toContain("#[Fillable(['code', 'name', 'region_id'])]");
});

it('make:crud --table dari terminal', function () {
    $this->artisan('make:crud', ['name' => 'Legacy suppliers', '--table' => 'supplier'])->assertSuccessful();

    expect(File::get("{$this->root}/app/Models/LegacySupplier.php"))
        ->toContain("'code', 'name', 'phone', 'rating', 'is_active', 'joined_at', 'region_id'");

    $this->artisan('crud:remove', ['table' => 'legacy_suppliers', '--drop-table' => true, '--force' => true])->assertSuccessful();

    expect(Schema::connection('crud_legacy')->hasTable('supplier'))->toBeTrue();
});

/*
 * Master–detail dengan BEBERAPA tabel detail (satu tingkat): header → detail.
 */
function createPurchaseOrderTables(): void
{
    $schema = Schema::connection('crud_legacy');
    $schema->create('po_hdr', function (Blueprint $t) {
        $t->id();
        $t->string('code', 20);
    });
    $schema->create('po_det', function (Blueprint $t) {
        $t->id();
        $t->foreignId('po_id')->constrained('po_hdr');
        $t->string('sku', 30);
        $t->integer('qty')->nullable();
    });
    $schema->create('po_note', function (Blueprint $t) {
        $t->id();
        $t->foreignId('po_id')->constrained('po_hdr');
        $t->string('body');
        $t->boolean('is_internal');
    });
}

it('master–detail: nama detail default & dari input', function () {
    expect(CrudGenerator::detailKey(null, 'msg_det', 1))->toBe('details')
        ->and(CrudGenerator::detailKey(null, 'po_attach', 2))->toBe('po_attaches')
        ->and(CrudGenerator::detailKey('Line items', 'po_det', 2))->toBe('line_items')
        ->and(CrudGenerator::detailKey('item', 'po_det', 2))->toBe('items')
        ->and(CrudGenerator::parseDetailSpec('po_det:items'))->toBe(['table' => 'po_det', 'name' => 'items'])
        ->and(CrudGenerator::parseDetailSpec('po_det'))->toBe(['table' => 'po_det', 'name' => null]);
});

it('master–detail: BEBERAPA tabel detail dibuat bersama', function () {
    createPurchaseOrderTables();

    $bp = $this->generator->blueprintForTable('Purchase orders', 'po_hdr', null, null, [
        ['table' => 'po_det', 'name' => 'items'],
        ['table' => 'po_note', 'name' => 'notes'],
    ]);

    expect($this->generator->problems($bp))->toBe([])
        ->and(collect($bp->details)->pluck('model')->all())->toBe(['PurchaseOrderItem', 'PurchaseOrderNote'])
        ->and($bp->details[0]->relation())->toBe('items')
        ->and($bp->details[0]->detailParam())->toBe('item');

    $result = $this->generator->generate($bp);
    $file = fn (string $path) => File::get("{$this->root}/{$path}");

    expect($result['files'])->toContain(
        'app/Models/PurchaseOrderItem.php',
        'app/Models/PurchaseOrderNote.php',
        'app/Http/Controllers/Admin/PurchaseOrderItemController.php',
        'app/Http/Controllers/Admin/PurchaseOrderNoteController.php',
        'resources/js/components/admin/purchase-order-item-rows.tsx',
        'resources/js/components/admin/purchase-order-note-section.tsx',
        'resources/js/pages/admin/purchase-orders/items/create.tsx',
        'resources/js/pages/admin/purchase-orders/notes/edit.tsx',
    );

    expect($file('app/Models/PurchaseOrder.php'))
        ->toContain('public function items(): HasMany')
        ->toContain("return \$this->hasMany(PurchaseOrderItem::class, 'po_id');")
        ->toContain('public function notes(): HasMany')
        ->toContain("return \$this->hasMany(PurchaseOrderNote::class, 'po_id');");

    // Header + semua baris detail disimpan dalam SATU transaksi.
    expect($file('app/Http/Controllers/Admin/PurchaseOrderController.php'))
        ->toContain('DB::connection()->transaction(function () use ($request) {')
        ->toContain('$purchaseOrder = PurchaseOrder::create($request->headerData());')
        ->toContain("foreach (\$request->detailRows('items') as \$row) {")
        ->toContain('$purchaseOrder->items()->create($row);')
        ->toContain('$purchaseOrder->notes()->create($row);')
        ->toContain("'maxDetails' => SavePurchaseOrderRequest::MAX_DETAILS,")
        ->toContain("'items' => \$purchaseOrder->items()->orderByDesc('id')->paginate(10, ['*'], 'items_page')")
        ->toContain('$purchaseOrder->items()->delete();')
        ->toContain('$purchaseOrder->notes()->delete();');

    // Aturan baris detail diambil dari request detail (satu sumber).
    expect($file('app/Http/Requests/Admin/SavePurchaseOrderRequest.php'))
        ->toContain("private const DETAIL_KEYS = ['items', 'notes'];")
        ->toContain("...\$this->rowRules('items', SavePurchaseOrderItemRequest::fieldRules()),")
        ->toContain("...\$this->rowRules('notes', SavePurchaseOrderNoteRequest::fieldRules()),")
        ->toContain("return ! \$this->route('purchase_order') instanceof PurchaseOrder;");

    expect($file('app/Http/Requests/Admin/SavePurchaseOrderItemRequest.php'))
        ->toContain('public static function fieldRules(): array')
        ->toContain("'sku' => ['required', 'string'");

    // Controller detail: parameter route {item} → relasi items() (scoped binding).
    expect($file('app/Http/Controllers/Admin/PurchaseOrderItemController.php'))
        ->toContain('public function edit(PurchaseOrder $purchaseOrder, PurchaseOrderItem $item): Response')
        ->toContain('$purchaseOrder->items()->create($request->validated());')
        ->toContain("Inertia::render('admin/purchase-orders/items/create'");

    expect($file('routes/generated.php'))
        ->toContain("Route::resource('purchase-orders.items', App\\Http\\Controllers\\Admin\\PurchaseOrderItemController::class)->except(['index', 'show'])->scoped();")
        ->toContain("Route::resource('purchase-orders.notes', App\\Http\\Controllers\\Admin\\PurchaseOrderNoteController::class)->except(['index', 'show'])->scoped();");

    expect($file('resources/js/components/admin/purchase-order-form.tsx'))
        ->toContain('<PurchaseOrderItemRows errors={errors} max={maxDetails} />')
        ->toContain('<PurchaseOrderNoteRows errors={errors} max={maxDetails} />');
    expect($file('resources/js/components/admin/purchase-order-item-rows.tsx'))
        ->toContain("const KEY = 'items';")
        ->toContain("{ name: 'sku', label: 'Sku', type: 'string', required: true, maxLength: 255 },")
        ->toContain("{ name: 'qty', label: 'Qty', type: 'integer' },");
    expect($file('resources/js/components/admin/purchase-order-note-rows.tsx'))
        ->toContain("{ name: 'is_internal', label: 'Is internal', type: 'boolean' },");
    expect($file('resources/js/pages/admin/purchase-orders/edit.tsx'))
        ->toContain('details={items}')
        ->toContain('details={notes}');
    expect($file('resources/js/pages/admin/purchase-orders/create.tsx'))->toContain('maxDetails={maxDetails}');
    expect($file('resources/js/components/admin/purchase-order-item-section.tsx'))
        ->toContain('item: routeKey(item) };')
        ->toContain('<CardTitle>Items</CardTitle>');

    foreach (array_filter($result['files'], fn ($p) => str_ends_with($p, '.php')) as $path) {
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg("{$this->root}/{$path}").' 2>&1', $output, $code);
        expect($code)->toBe(0, $path);
    }

    // Kedua tabel detail tidak ditawarkan lagi; modul dihapus bersih.
    expect($this->generator->candidateTables())->not->toContain('po_det')->not->toContain('po_note');

    $this->generator->remove('purchase_orders', dropTable: false);
    expect(File::exists("{$this->root}/app/Models/PurchaseOrderNote.php"))->toBeFalse()
        ->and(File::isDirectory("{$this->root}/resources/js/pages/admin/purchase-orders"))->toBeFalse()
        ->and($file('routes/generated.php'))->not->toContain('PurchaseOrder');
});

it('CRUD tanpa detail tidak berubah: tidak ada kode master–detail', function () {
    $this->generator->generate($this->generator->blueprintForTable('Legacy suppliers', 'supplier'));

    expect(File::get("{$this->root}/app/Http/Requests/Admin/SaveLegacySupplierRequest.php"))
        ->not->toContain('detailRules')
        ->toContain("        ];\n    }\n}");
    expect(File::get("{$this->root}/app/Http/Controllers/Admin/LegacySupplierController.php"))
        ->toContain('LegacySupplier::create($request->validated());')
        ->toContain("Inertia::render('admin/legacy-suppliers/create');");
    expect(File::get("{$this->root}/resources/js/components/admin/legacy-supplier-form.tsx"))
        ->toContain('className="max-w-3xl space-y-6"')
        ->not->toContain('maxDetails');
});

it('master–detail: menolak nama detail ganda, nama terlarang & tabel ganda', function (array $details, string $error) {
    createPurchaseOrderTables();

    $this->actingAs($this->admin)
        ->post('/admin/menus', [
            'location' => 'admin',
            'title' => 'Purchase orders',
            'crud' => ['enabled' => '1', 'table' => 'po_hdr', 'columns' => ['code'], 'details' => $details],
        ])
        ->assertSessionHasErrors($error);

    expect(Menu::where('title', 'Purchase orders')->exists())->toBeFalse();
})->with([
    'nama sama' => [[['table' => 'po_det', 'name' => 'lines'], ['table' => 'po_note', 'name' => 'line']], 'crud.details'],
    'nama terlarang' => [[['table' => 'po_det', 'name' => 'abilities']], 'crud.details'],
    'sama dengan nama resource' => [[['table' => 'po_det', 'name' => 'purchase orders']], 'crud.details'],
    'tabel ganda' => [[['table' => 'po_det'], ['table' => 'po_det', 'name' => 'x']], 'crud.details.1.table'],
    'nama tidak sah' => [[['table' => 'po_det', 'name' => '1 bad!']], 'crud.details.0.name'],
]);

it('Add menu: dua tabel detail tanpa nama memakai nama tabelnya', function () {
    createPurchaseOrderTables();

    $this->actingAs($this->admin)
        ->post('/admin/menus', [
            'location' => 'admin',
            'title' => 'Purchase orders',
            'crud' => ['enabled' => '1', 'table' => 'po_hdr', 'columns' => ['code'], 'details' => [['table' => 'po_det'], ['table' => 'po_note', 'name' => '']]],
        ])
        ->assertSessionHasNoErrors();

    expect(File::get("{$this->root}/app/Models/PurchaseOrder.php"))
        ->toContain('public function poDets(): HasMany')
        ->toContain('public function poNotes(): HasMany');
});

it('make:crud dengan beberapa --detail dari terminal', function () {
    createPurchaseOrderTables();

    $this->artisan('make:crud', ['name' => 'Purchase orders', '--table' => 'po_hdr', '--detail' => ['po_det:items', 'po_note:notes']])
        ->assertSuccessful();

    expect(File::get("{$this->root}/app/Models/PurchaseOrder.php"))
        ->toContain('public function items(): HasMany')
        ->toContain('public function notes(): HasMany');

    $this->artisan('make:crud', ['name' => 'Other orders', '--table' => 'po_hdr', '--detail' => ['nope']])
        ->assertFailed();
});
