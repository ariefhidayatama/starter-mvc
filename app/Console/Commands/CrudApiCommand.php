<?php

namespace App\Console\Commands;

use App\Services\Crud\CrudGenerator;
use Illuminate\Console\Command;
use RuntimeException;

use function Laravel\Prompts\confirm;

/**
 * REST API untuk modul yang SUDAH dibuat CRUD generator.
 *
 * Contoh:
 *   php artisan crud:api                     (daftar modul & status API-nya)
 *   php artisan crud:api messages            (buat /api/v1/messages)
 *   php artisan crud:api messages --remove   (hapus API saja, halaman admin tetap)
 *
 * Field, tabel, koneksi database & tabel detail dibaca dari manifest
 * (storage/app/crud/<tabel>.json), jadi tidak ditanyakan lagi. Model, policy,
 * request & permission yang sudah ada dipakai ulang.
 *
 * Modul buatan tangan (products, categories, ...) TIDAK bisa: API-nya harus
 * ditulis manual agar aturan bisnisnya (Action/Service) tetap dipakai.
 */
class CrudApiCommand extends Command
{
    protected $signature = 'crud:api
        {table? : Table name of a generated module, e.g. messages}
        {--remove : Remove the generated REST API of this module (admin pages are kept)}
        {--force : Do not ask for confirmation}';

    protected $description = 'Add or remove the REST API of a module made by make:crud (local only)';

    public function handle(CrudGenerator $generator): int
    {
        if (! CrudGenerator::enabled()) {
            $this->error('The CRUD generator is disabled. It only runs locally.');

            return self::FAILURE;
        }

        $table = $this->argument('table');

        if (! is_string($table) || $table === '') {
            return $this->list($generator);
        }

        try {
            return $this->option('remove') ? $this->remove($generator, $table) : $this->add($generator, $table);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function add(CrudGenerator $generator, string $table): int
    {
        $this->components->info("Generating the REST API for \"{$table}\" …");
        $result = $generator->addApi($table);

        foreach ($result['files'] as $path) {
            $this->components->twoColumnDetail($path, '<fg=green>created</>');
        }

        $this->components->twoColumnDetail('routes/api/generated.php', '<fg=yellow>updated</>');

        foreach ($result['warnings'] as $warning) {
            $this->components->warn($warning);
        }

        $this->components->info("REST API ready at {$result['api_url']}. Access follows the {$table}.* permissions on the Permissions page (plus api.access).");

        return self::SUCCESS;
    }

    private function remove(CrudGenerator $generator, string $table): int
    {
        if (! $this->option('force') && ! confirm("Remove the generated REST API of \"{$table}\"? (admin pages are kept)", default: false)) {
            return self::FAILURE;
        }

        $result = $generator->removeApi($table);

        foreach ($result['deleted'] as $path) {
            $this->components->twoColumnDetail($path, '<fg=red>deleted</>');
        }

        foreach ($result['kept'] as $path) {
            $this->components->twoColumnDetail($path, '<fg=yellow>kept (you changed it)</>');
        }

        foreach ($result['warnings'] as $warning) {
            $this->components->warn($warning);
        }

        return self::SUCCESS;
    }

    private function list(CrudGenerator $generator): int
    {
        $modules = $generator->modules();

        if ($modules === []) {
            $this->components->info('No generated modules yet. Create one with php artisan make:crud.');

            return self::SUCCESS;
        }

        $this->table(
            ['Table', 'Name', 'Admin pages', 'REST API'],
            array_map(fn (array $m) => [
                $m['table'],
                $m['name'],
                $m['web'] ? 'yes' : '-',
                $m['api_url'] ?? '- (php artisan crud:api '.$m['table'].')',
            ], $modules),
        );

        return self::SUCCESS;
    }
}
