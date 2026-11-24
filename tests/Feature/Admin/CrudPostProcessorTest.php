<?php

use App\Services\Crud\ProcessCrudPostProcessor;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->root = storage_path('framework/testing/crud-post-'.uniqid());
    File::ensureDirectoryExists("{$this->root}/public");
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

/** Perintah yang dijalankan, digabung jadi string. */
function ranCommands(): array
{
    $commands = [];
    // assertNotRan memeriksa SEMUA proses yang tercatat (assertRan berhenti di yang pertama cocok).
    Process::assertNotRan(function (PendingProcess $process) use (&$commands) {
        $commands[] = is_array($process->command) ? implode(' ', $process->command) : $process->command;

        return false;
    });

    return $commands;
}

it('afterGenerate menjalankan migrate, seeder & wayfinder tanpa Xdebug', function () {
    Process::fake();

    $warnings = (new ProcessCrudPostProcessor)->afterGenerate($this->root, 'database/migrations/x_create_foos_table.php');

    $commands = ranCommands();

    expect($warnings)->toBe([])
        ->and($commands)->toHaveCount(3)
        ->and(collect($commands)->every(fn (string $c) => str_contains($c, '-d xdebug.mode=off')))->toBeTrue()
        ->and(implode("\n", $commands))
        ->toContain('migrate --path=database/migrations/x_create_foos_table.php')
        ->toContain('db:seed --class=RolePermissionSeeder')
        ->toContain('wayfinder:generate --with-form');
});

it('wayfinder dilewati bila Vite dev server hidup', function () {
    Process::fake();
    $server = stream_socket_server('tcp://127.0.0.1:0');
    $address = stream_socket_get_name($server, false);
    File::put("{$this->root}/public/hot", "http://{$address}");

    (new ProcessCrudPostProcessor)->afterRemove($this->root);
    fclose($server);

    expect(implode("\n", ranCommands()))
        ->toContain('db:seed')
        ->not->toContain('wayfinder:generate');
});

it('wayfinder tetap jalan bila file hot tertinggal tapi Vite mati', function () {
    Process::fake();
    $server = stream_socket_server('tcp://127.0.0.1:0');
    $address = stream_socket_get_name($server, false);
    fclose($server);
    File::put("{$this->root}/public/hot", "http://{$address}");

    (new ProcessCrudPostProcessor)->afterRemove($this->root);

    expect(implode("\n", ranCommands()))->toContain('wayfinder:generate');
});

it('format menjalankan Pint & vp fmt, dan melaporkan yang gagal', function () {
    Process::fake([
        '*pint*' => Process::result(exitCode: 1, errorOutput: 'boom'),
        '*' => Process::result(),
    ]);

    $warnings = (new ProcessCrudPostProcessor)->format($this->root, ['app/Models/Foo.php', 'resources/js/foo.tsx']);

    expect($warnings)->toBe(['Pint (PHP formatting) failed. Run it manually (see storage/logs/laravel.log).'])
        ->and(ranCommands())->toHaveCount(2);
});
