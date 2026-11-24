<?php

use App\Enums\RoleName;
use App\Models\Menu;
use App\Models\User;
use App\Services\Crud\CrudBlueprint;
use App\Services\Crud\CrudGenerator;
use App\Services\Crud\CrudPostProcessor;
use App\Services\Crud\CrudRenderer;
use App\Services\Crud\CrudTableInspector;
use Database\Seeders\MenuSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Halaman Admin → API Generator. Tabel "lama" dibuat di SQLite memori
 * (koneksi api_legacy), file ditulis ke folder SEMENTARA, dan langkah
 * lanjutan (seeder, Wayfinder, formatter) dipalsukan.
 */

beforeEach(function () {
    config([
        'crud.enabled' => true,
        'database.connections.api_legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true],
    ]);

    $schema = Schema::connection('api_legacy');
    $schema->create('vendor', function (Blueprint $t) {
        $t->id();
        $t->string('code', 10);
        $t->string('name', 100);
        $t->string('phone')->nullable();
    });
    $schema->create('vendor_contact', function (Blueprint $t) {
        $t->id();
        $t->foreignId('vendor_id')->constrained('vendor');
        $t->string('email', 100);
    });

    $this->root = storage_path('framework/testing/api-page-'.uniqid());
    File::ensureDirectoryExists($this->root.'/app/Enums');
    File::ensureDirectoryExists($this->root.'/routes/api');
    File::copy(app_path('Enums/PermissionName.php'), $this->root.'/app/Enums/PermissionName.php');
    File::copy(base_path('routes/generated.php'), $this->root.'/routes/generated.php');
    File::copy(base_path('routes/api/generated.php'), $this->root.'/routes/api/generated.php');

    $post = new class implements CrudPostProcessor
    {
        public function format(string $root, array $paths): array
        {
            return [];
        }

        public function afterGenerate(string $root, ?string $migrationPath): array
        {
            return [];
        }

        public function afterRemove(string $root): array
        {
            return [];
        }
    };

    $this->generator = new CrudGenerator(new CrudRenderer(base_path('stubs/crud')), $post, $this->root, new CrudTableInspector('api_legacy'));
    app()->instance(CrudGenerator::class, $this->generator);

    $this->admin = User::factory()->create()->assignRole(RoleName::Admin);
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

it('hanya untuk yang boleh membuat menu', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::Staff))
        ->get('/admin/api-generator')
        ->assertForbidden();
});

it('menampilkan modul hasil generator beserta status API-nya', function () {
    $this->generator->generate(CrudBlueprint::fromInput('Suppliers', [['name' => 'name', 'type' => 'string', 'required' => '1']]));

    $this->actingAs($this->admin)
        ->get('/admin/api-generator')
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/api-generator/index')
            ->where('enabled', true)
            ->where('modules.0.table', 'suppliers')
            ->where('modules.0.web', true)
            ->where('modules.0.api', false)
            ->has('connections'));
});

it('di luar development hanya menampilkan pemberitahuan', function () {
    config(['crud.enabled' => false]);

    $this->actingAs($this->admin)
        ->get('/admin/api-generator')
        ->assertInertia(fn (Assert $page) => $page->where('enabled', false)->where('modules', []));

    $this->actingAs($this->admin)->post('/admin/api-generator/suppliers/api')->assertNotFound();
});

it('membuat modul API saja dari tabel yang sudah ada (+ tabel detail)', function () {
    $this->actingAs($this->admin)
        ->post('/admin/api-generator', ['crud' => [
            'name' => 'Vendors',
            'table' => 'vendor',
            'columns' => ['code', 'name', 'phone'],
            'details' => [['table' => 'vendor_contact', 'name' => 'contacts']],
            'token' => 'test-token-123',
        ]])
        ->assertRedirect('/admin/api-generator');

    $manifest = $this->generator->manifest('vendors');

    expect($manifest['web'])->toBeFalse()
        ->and($manifest['api_url'])->toBe('/api/v1/vendors')
        ->and($manifest['db_table'])->toBe('vendor')
        ->and(is_file("{$this->root}/app/Http/Controllers/Api/V1/VendorController.php"))->toBeTrue()
        ->and(is_file("{$this->root}/app/Http/Resources/V1/VendorContactResource.php"))->toBeTrue()
        // Tanpa halaman admin.
        ->and(is_file("{$this->root}/app/Http/Controllers/Admin/VendorController.php"))->toBeFalse()
        ->and(File::get("{$this->root}/routes/api/generated.php"))->toContain("Route::apiResource('vendors'");

    // Status untuk halaman yang ter-reload Vite.
    expect($this->generator->status('test-token-123'))
        ->toMatchArray(['state' => 'done', 'redirect' => '/admin/api-generator']);
});

it('tabel wajib dipilih', function () {
    $this->actingAs($this->admin)
        ->post('/admin/api-generator', ['crud' => ['name' => 'Vendors', 'table' => '']])
        ->assertSessionHasErrors('crud.table');
});

it('menambah lalu menghapus API modul yang sudah ada', function () {
    $this->generator->generate(CrudBlueprint::fromInput('Suppliers', [['name' => 'name', 'type' => 'string', 'required' => '1']]));

    $this->actingAs($this->admin)->post('/admin/api-generator/suppliers/api')->assertRedirect('/admin/api-generator');
    expect($this->generator->manifest('suppliers')['api'])->toBeTrue();

    $this->actingAs($this->admin)->delete('/admin/api-generator/suppliers/api')->assertRedirect('/admin/api-generator');
    expect($this->generator->manifest('suppliers')['api'])->toBeFalse()
        ->and(is_file("{$this->root}/app/Http/Controllers/Admin/SupplierController.php"))->toBeTrue();
});

it('menghapus modul API saja, tabelnya tetap ada', function () {
    $this->generator->generate($this->generator->blueprintForTable('Vendors', 'vendor', null, null), web: false, api: true);

    $this->actingAs($this->admin)->delete('/admin/api-generator/vendors')->assertRedirect('/admin/api-generator');

    expect($this->generator->manifest('vendors'))->toBeNull()
        ->and(is_file("{$this->root}/app/Models/Vendor.php"))->toBeFalse()
        ->and(Schema::connection('api_legacy')->hasTable('vendor'))->toBeTrue();
});

it('modul dengan halaman admin tidak bisa dihapus dari sini', function () {
    $this->generator->generate(CrudBlueprint::fromInput('Suppliers', [['name' => 'name', 'type' => 'string', 'required' => '1']]));

    $this->actingAs($this->admin)
        ->delete('/admin/api-generator/suppliers')
        ->assertSessionHasErrors('module');

    expect($this->generator->manifest('suppliers'))->not->toBeNull();
});

it('menu API Generator ada di sidebar admin', function () {
    $this->seed(MenuSeeder::class);

    expect(Menu::query()->where('url', '/admin/api-generator')->value('permission'))->toBe('menus.create');
});
