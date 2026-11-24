<?php

namespace App\Console\Commands;

use App\Models\Menu;
use App\Services\Crud\CrudGenerator;
use App\Services\Menu\MenuTreeService;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

/**
 * Contoh:
 *   php artisan crud:remove suppliers               (kode saja, tabel & data tetap)
 *   php artisan crud:remove suppliers --drop-table  (tabel & datanya ikut dihapus)
 *
 * File yang sudah Anda ubah sejak di-generate TIDAK dihapus.
 */
class RemoveCrudCommand extends Command
{
    protected $signature = 'crud:remove
        {table : Table name of the generated CRUD, e.g. suppliers}
        {--drop-table : Also drop the database table and its data}
        {--force : Do not ask for confirmation}';

    protected $description = 'Remove a CRUD made by make:crud (unchanged files only)';

    public function handle(CrudGenerator $generator, MenuTreeService $menus): int
    {
        if (! CrudGenerator::enabled()) {
            $this->error('The CRUD generator is disabled. It only runs locally.');

            return self::FAILURE;
        }

        $table = (string) $this->argument('table');
        $manifest = $generator->manifest($table);

        if ($manifest === null) {
            $this->error("No generated CRUD named \"{$table}\". Generated: ".(implode(', ', $generator->urls()) ?: 'none').'.');

            return self::FAILURE;
        }

        $drop = (bool) $this->option('drop-table');

        if (! $this->option('force') && ! confirm(
            $drop ? "Remove the code AND drop the \"{$table}\" table with all its data?" : "Remove the generated code for \"{$table}\"? (the table is kept)",
            default: false,
        )) {
            return self::FAILURE;
        }

        $result = $generator->remove($table, $drop);

        // Menu yang menunjuk ke halaman ini sudah tidak berguna.
        $url = is_string($manifest['url'] ?? null) ? $manifest['url'] : null;
        // Ambil id dulu: MySQL menolak DELETE dengan subquery ke tabel yang sama (error 1093).
        $menuIds = $url
            ? Menu::query()->where('url', $url)->where('is_system', false)->whereDoesntHave('children')->pluck('id')->all()
            : [];
        $removedMenus = $menuIds === [] ? 0 : Menu::query()->whereKey($menuIds)->delete();

        if ($removedMenus > 0) {
            $menus->flush();
        }

        foreach ($result['deleted'] as $path) {
            $this->components->twoColumnDetail($path, '<fg=red>deleted</>');
        }

        foreach ($result['kept'] as $path) {
            $this->components->twoColumnDetail($path, '<fg=yellow>kept (you changed it)</>');
        }

        $this->components->twoColumnDetail('Database table', $result['table_dropped'] ? '<fg=red>dropped</>' : 'kept');
        $this->components->twoColumnDetail('Sidebar menus removed', (string) $removedMenus);

        foreach ($result['warnings'] as $warning) {
            $this->components->warn($warning);
        }

        return self::SUCCESS;
    }
}
