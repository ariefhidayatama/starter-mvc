<?php

use App\Services\Crud\CrudTableInspector;
use App\Support\TableAccess;
use Illuminate\Support\Facades\DB;

/*
 * Uji BACA-SAJA ke SQL Server sungguhan (koneksi "wmsreports", database
 * training). Tidak ada data yang ditulis.
 *
 * Dilewati otomatis bila: ekstensi pdo_sqlsrv tidak ada, WMSREPORTS_DB_HOST
 * kosong, atau server tidak bisa dihubungi (mis. di CI).
 *
 *   php artisan test --group=sqlsrv
 */

beforeEach(function () {
    if (! in_array('sqlsrv', PDO::getAvailableDrivers(), true) || blank(config('database.connections.wmsreports.host'))) {
        $this->markTestSkipped('SQL Server "wmsreports" is not configured.');
    }

    try {
        DB::connection('wmsreports')->select('SELECT 1 AS ok');
    } catch (Throwable $e) {
        $this->markTestSkipped('SQL Server "wmsreports" is not reachable: '.$e->getMessage());
    }

    if (DB::connection('wmsreports')->selectOne("SELECT OBJECT_ID('dbo.msg') AS id")->id === null) {
        $this->markTestSkipped('Table dbo.msg does not exist on this server.');
    }
});

it('membaca struktur msg: kolom, kolom audit & tabel detail', function () {
    $info = (new CrudTableInspector('wmsreports'))->describe('msg');
    $fields = collect($info['fields'])->keyBy('name');

    expect($info['problem'])->toBeNull()
        ->and($info['timestamps'])->toBeTrue()
        ->and($info['audit'])->toMatchArray(['created_at' => 'created_date', 'updated_at' => 'updated_date', 'created_by' => 'created_by', 'updated_by' => 'updated_by', 'user_max' => 30])
        ->and($info['children'])->toContain(['table' => 'msg_det', 'column' => 'msg_id'])
        ->and($fields['whs_code']->max)->toBe(10)
        ->and($fields['whs_code']->required)->toBeTrue()
        ->and($fields['msg_description']->type)->toBe('text')
        ->and($fields->keys())->not->toContain('id', 'created_by', 'created_date');
})->group('sqlsrv');

it('membaca foreign key msg_det.msg_id tanpa STRING_AGG (SQL Server 2014)', function () {
    $fields = collect((new CrudTableInspector('wmsreports'))->describe('msg_det')['fields'])->keyBy('name');

    expect($fields['msg_id']->references)->toBe('msg');
})->group('sqlsrv');

it('mengecek izin CREATE TABLE sebelum membuat tabel baru', function () {
    $canCreate = (int) DB::connection('wmsreports')
        ->selectOne("SELECT HAS_PERMS_BY_NAME(NULL, 'DATABASE', 'CREATE TABLE') AS x")->x === 1;
    $problem = (new CrudTableInspector('wmsreports'))->createTableProblem();

    expect($problem === null)->toBe($canCreate);
})->group('sqlsrv');

it('membaca izin akun pada tabel', function () {
    expect(TableAccess::permissions('wmsreports', 'msg'))->toContain('SELECT');
})->group('sqlsrv');
