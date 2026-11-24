<?php

use App\Enums\RoleName;
use App\Models\Concerns\HasOptionalPublicUlid;
use App\Models\User;
use App\Services\Crud\CrudBlueprint;
use App\Services\Crud\CrudRenderer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/*
 * ULID untuk modul CRUD generator: opsi saat generate, deteksi kolom saat
 * runtime (HasOptionalPublicUlid), dan make:ulid-migration untuk tabel lama.
 * Tabel contoh dibuat di SQLite in-memory agar tidak mengganggu database test.
 */

class UlidTestGizmo extends Model
{
    use HasOptionalPublicUlid;

    protected $connection = 'ulid_test';

    protected $table = 'gizmos';

    protected $guarded = [];
}

class UlidTestWidget extends Model
{
    use HasOptionalPublicUlid;

    protected $connection = 'ulid_test';

    protected $table = 'widgets';

    protected $guarded = [];
}

beforeEach(function () {
    config(['database.connections.ulid_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);

    $schema = Schema::connection('ulid_test');
    $schema->create('gizmos', function (Blueprint $t) {
        $t->id();
        $t->ulid()->unique();
        $t->string('name');
        $t->timestamps();
    });
    $schema->create('widgets', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->timestamps();
    });

    UlidTestGizmo::forgetPublicUlidColumns();
});

it('tabel dengan kolom ulid: ulid terisi otomatis & dipakai sebagai route key', function () {
    $gizmo = UlidTestGizmo::create(['name' => 'A']);

    expect($gizmo->ulid)->toMatch('/^[0-9a-z]{26}$/')
        ->and($gizmo->getRouteKeyName())->toBe('ulid')
        ->and($gizmo->getRouteKey())->toBe($gizmo->ulid)
        // Primary key tetap id integer.
        ->and($gizmo->getKeyType())->toBe('int')
        ->and($gizmo->getIncrementing())->toBeTrue()
        ->and((new UlidTestGizmo)->resolveRouteBinding($gizmo->ulid)?->is($gizmo))->toBeTrue();
});

it('tabel tanpa kolom ulid: tetap memakai id', function () {
    $widget = UlidTestWidget::create(['name' => 'A']);

    expect($widget->getRouteKeyName())->toBe('id')
        ->and($widget->getRouteKey())->toBe($widget->id)
        ->and($widget->getAttributes())->not->toHaveKey('ulid');
});

it('make:ulid-migration menambah kolom ulid & mengisi baris lama', function () {
    UlidTestWidget::create(['name' => 'lama 1']);
    UlidTestWidget::create(['name' => 'lama 2']);
    $before = File::glob(database_path('migrations/*_add_ulid_to_widgets_table.php'));

    $this->artisan('make:ulid-migration', ['table' => 'widgets', '--connection' => 'ulid_test'])->assertSuccessful();

    $created = array_values(array_diff(File::glob(database_path('migrations/*_add_ulid_to_widgets_table.php')), $before));

    try {
        expect($created)->toHaveCount(1);

        $migration = require $created[0];
        $migration->up();
        // Aman dijalankan ulang (kolom sudah ada → dilewati).
        $migration->up();
        UlidTestWidget::forgetPublicUlidColumns();

        $ulids = UlidTestWidget::query()->pluck('ulid');
        $new = UlidTestWidget::create(['name' => 'baru']);

        expect($ulids)->toHaveCount(2)
            ->and($ulids->every(fn ($u) => preg_match('/^[0-9a-z]{26}$/', (string) $u) === 1))->toBeTrue()
            ->and($ulids->unique())->toHaveCount(2)
            ->and($new->ulid)->toMatch('/^[0-9a-z]{26}$/')
            ->and($new->getRouteKeyName())->toBe('ulid');

        $migration->down();
        expect(Schema::connection('ulid_test')->hasColumn('widgets', 'ulid'))->toBeFalse();
    } finally {
        File::delete($created);
    }
});

it('make:ulid-migration: tabel yang sudah punya ulid tidak dibuatkan migration', function () {
    $before = File::glob(database_path('migrations/*_add_ulid_to_gizmos_table.php'));

    $this->artisan('make:ulid-migration', ['table' => 'gizmos', '--connection' => 'ulid_test'])
        ->expectsOutputToContain('already has a "ulid" column')
        ->assertSuccessful();

    expect(File::glob(database_path('migrations/*_add_ulid_to_gizmos_table.php')))->toBe($before);
});

it('make:ulid-migration menolak tabel yang tidak ada', function () {
    $this->artisan('make:ulid-migration', ['table' => 'nope', '--connection' => 'ulid_test'])->assertFailed();
});

it('generator: opsi ulid menambah kolom ulid di migration induk & detail', function () {
    $fields = [['name' => 'name', 'type' => 'string', 'required' => '1']];
    $details = [['name' => 'items', 'fields' => [['name' => 'sku', 'type' => 'string', 'required' => '1']]]];
    $renderer = new CrudRenderer(base_path('stubs/crud'));
    $migration = fn (CrudBlueprint $bp) => $renderer->files($bp, '2026_01_01_000000')['database/migrations/2026_01_01_000000_create_purchase_orders_table.php'];

    $with = $renderer->files($bp = CrudBlueprint::fromInput('Purchase orders', $fields, details: $details, ulid: true), '2026_01_01_000000');
    $without = $migration(CrudBlueprint::fromInput('Purchase orders', $fields, details: $details));

    expect(substr_count($migration($bp), '$table->ulid()->unique();'))->toBe(2)
        ->and($without)->not->toContain('ulid')
        // Model selalu memakai trait: kolom yang ditambah belakangan langsung terpakai.
        ->and($with['app/Models/PurchaseOrder.php'])->toContain('use HasOptionalPublicUlid;')
        ->and($with['app/Models/PurchaseOrderItem.php'])->toContain('use HasOptionalPublicUlid;')
        ->and($with['resources/js/pages/admin/purchase-orders/index.tsx'])->toContain('PurchaseOrderController.edit(routeKey(item))')
        ->and($with['resources/js/pages/admin/purchase-orders/edit.tsx'])->toContain('parentKey={routeKey(item)}');
});

it('generator: "ulid" tidak boleh dipakai sebagai nama field', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::Admin));
    config(['crud.enabled' => true]);

    $this->post('/admin/menus', [
        'location' => 'admin',
        'title' => 'Gadgets',
        'crud' => ['enabled' => '1', 'name' => 'Gadgets', 'fields' => [['name' => 'ulid', 'type' => 'string']]],
    ])->assertSessionHasErrors('crud.fields.0.name');
});
