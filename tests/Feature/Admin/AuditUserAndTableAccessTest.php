<?php

use App\Contracts\HasAuditUser;
use App\Models\User;
use App\Observers\FillAuditUser;
use App\Support\TableAccess;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Kolom "dibuat/diubah oleh" (FillAuditUser) & izin akun database (TableAccess).
 * Tabel contoh di SQLite memori, bukan database test MySQL.
 */

#[ObservedBy([FillAuditUser::class])]
class AuditedNote extends Model implements HasAuditUser
{
    protected $connection = 'audit_test';

    protected $table = 'note';

    protected $guarded = [];

    const CREATED_AT = 'created_date';

    const UPDATED_AT = 'updated_date';

    public function auditUserColumns(): array
    {
        return ['created_by' => 'created_by', 'updated_by' => 'updated_by', 'max' => 10];
    }
}

beforeEach(function () {
    config(['database.connections.audit_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);

    Schema::connection('audit_test')->create('note', function (Blueprint $t) {
        $t->id();
        $t->string('body');
        $t->string('created_by', 10);
        $t->dateTime('created_date');
        $t->string('updated_by', 10);
        $t->dateTime('updated_date');
    });
});

it('mengisi created_by & updated_by dengan nama user yang login (dipotong sesuai panjang kolom)', function () {
    $alice = User::factory()->create(['name' => 'Alice Wonderland']);
    $bob = User::factory()->create(['name' => 'Bob']);

    $this->actingAs($alice);
    $note = AuditedNote::create(['body' => 'Hello']);

    expect($note->created_by)->toBe('Alice Wond')   // max 10 karakter
        ->and($note->updated_by)->toBe('Alice Wond')
        ->and($note->created_date)->not->toBeNull();

    $this->actingAs($bob);
    $note->update(['body' => 'Changed']);

    expect($note->fresh()->created_by)->toBe('Alice Wond')
        ->and($note->fresh()->updated_by)->toBe('Bob');
});

it('tanpa user login diisi "system"', function () {
    expect(AuditedNote::create(['body' => 'Job'])->created_by)->toBe('system');
});

it('TableAccess: koneksi selain SQL Server dianggap boleh semua', function () {
    expect(TableAccess::permissions('audit_test', 'note'))->toBe(['SELECT', 'INSERT', 'UPDATE', 'DELETE'])
        ->and(TableAccess::allows(null, 'users', 'DELETE'))->toBeTrue();
});
