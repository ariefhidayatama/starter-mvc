<?php

namespace App\Console\Commands;

use App\Actions\Menu\CreateMenuAction;
use App\Enums\MenuLocation;
use App\Services\Crud\CrudBlueprint;
use App\Services\Crud\CrudField;
use App\Services\Crud\CrudGenerator;
use App\Services\Crud\CrudInput;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Contoh:
 *   php artisan make:crud Suppliers \
 *       --field=name:string:required \
 *       --field=phone:string \
 *       --field=status:select:required:active|inactive \
 *       --menu
 *
 * Format --field: nama:tipe[:required][:opsi1|opsi2]
 * Tanpa --field, field ditanyakan satu per satu.
 *
 * Tabel yang SUDAH ADA (tanpa migration, kolom dibaca dari tabel):
 *   php artisan make:crud Suppliers --table=supplier --menu
 *   php artisan make:crud Suppliers --table=supplier --column=name --column=phone
 *
 * Tabel di SQL Server ERP + tabel detailnya (master–detail, satu tingkat):
 *   php artisan make:crud Messages --connection=wmsreports --table=msg --detail=msg_det --menu
 *
 * Lebih dari satu tabel detail, masing-masing boleh diberi nama (tabel:nama):
 *   php artisan make:crud "Purchase orders" --table=po_hdr --detail=po_det:items --detail=po_attach:attachments
 *
 * REST API (/api/v1/<nama>, permission sama dengan halaman admin):
 *   php artisan make:crud Suppliers --field=name:string:required --api        (halaman admin + API)
 *   php artisan make:crud Vendors --connection=wmsreports --table=vendor --api-only   (API saja)
 * API untuk modul yang SUDAH di-generate: php artisan crud:api <tabel>
 *
 * Tabel BARU + tabel detail BARU: --detail=<nama>, kolom detail ditulis
 * <nama>.<kolom>:<tipe>... (tabel purchase_order_items + kolom purchase_order_id dibuat otomatis):
 *   php artisan make:crud "Purchase orders" --field=po_no:string:required \
 *       --detail=items --field=items.sku:string:required --field=items.qty:integer:required
 */
class MakeCrudCommand extends Command
{
    protected $signature = 'make:crud
        {name : Resource name, e.g. "Suppliers" or "Purchase orders"}
        {--field=* : name:type[:required][:a|b|c] — types: string, string(N), text, integer, decimal, boolean, date, datetime, select}
        {--table= : Use this EXISTING table (no migration; columns are read from the table)}
        {--column=* : With --table: only these columns (default: all supported columns)}
        {--connection= : Another database connection from config/crud.php, e.g. wmsreports (SQL Server) or postgres}
        {--detail=* : With --table: an existing DETAIL table, as table or table:name (e.g. po_det:items). Without --table: a NEW detail table name (e.g. items) whose fields are --field=items.sku:string}
        {--ulid : New table: add a "ulid" column so admin URLs use it instead of the id}
        {--menu : Also add a sidebar menu that follows the new view permission}
        {--api : Also generate a REST API at /api/v1/<name> (same permissions as the admin pages)}
        {--api-only : Generate ONLY the REST API (model, policy, request + API), no admin pages or menu}';

    protected $description = 'Generate migration, model, factory, policy, request, controller, React pages, REST API & tests (local only)';

    public function handle(CrudGenerator $generator, CreateMenuAction $createMenu): int
    {
        if (! CrudGenerator::enabled()) {
            $this->error('The CRUD generator is disabled. It only runs locally (APP_ENV=local or CRUD_GENERATOR=true).');

            return self::FAILURE;
        }

        $apiOnly = (bool) $this->option('api-only');
        $api = $apiOnly || (bool) $this->option('api');

        if ($apiOnly && $this->option('menu')) {
            $this->error('--menu needs admin pages. Remove --menu or use --api instead of --api-only.');

            return self::FAILURE;
        }

        $name = (string) $this->argument('name');
        $table = $this->option('table');
        $table = is_string($table) && $table !== '' ? $table : null;
        $connection = $this->option('connection');
        $connection = is_string($connection) && $connection !== '' ? $connection : null;
        /** @var list<string> $detailOptions */
        $detailOptions = $this->option('detail');
        $details = array_map(CrudGenerator::parseDetailSpec(...), array_values(array_filter($detailOptions, fn (string $d) => trim($d) !== '')));

        $columns = [];
        $fields = [];

        if ($table !== null) {
            // Tabel yang sudah ada: kolom dibaca dari database.
            /** @var list<string> $columns */
            $columns = $this->option('column');
            $columns = $columns !== [] || ! in_array($table, $generator->candidateTables($connection), true)
                ? $columns
                : array_map(fn (CrudField $f) => $f->name, $generator->inspector($connection)->describe($table)['fields']);
            $input = ['name' => $name, 'table' => $table, 'connection' => $connection, 'details' => $details, 'columns' => $columns];
        } else {
            /** @var list<string> $specs */
            $specs = $this->option('field');
            // Tabel baru + detail baru: --detail=items, kolomnya --field=items.sku:string.
            $newDetails = [];

            foreach ($details as $detail) {
                $newDetails[$detail['table']] = ['name' => $detail['table'], 'fields' => []];
            }

            foreach ($specs as $i => $spec) {
                [$head] = explode(':', $spec, 2);

                if (str_contains($head, '.')) {
                    [$detailName, $rest] = explode('.', $spec, 2);
                    $newDetails[$detailName] ??= ['name' => $detailName, 'fields' => []];
                    $newDetails[$detailName]['fields'][] = $this->parseField($rest);
                    unset($specs[$i]);
                }
            }

            $fields = $specs === [] ? $this->askFields() : array_map($this->parseField(...), array_values($specs));
            $details = array_values($newDetails);
            $input = ['name' => $name, 'connection' => $connection, 'fields' => $fields, 'details' => $details];
        }

        $validator = Validator::make($input, CrudInput::rules($generator, '', $input), CrudInput::messages(), CrudInput::attributes());

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $blueprint = $table !== null
            ? $generator->blueprintForTable($name, $table, $columns, $connection, $details)
            : CrudBlueprint::fromInput($name, $fields, $connection, CrudInput::newDetailSpecs($input), (bool) $this->option('ulid'));

        $apiUrl = '/api/v1/'.$blueprint->slug();
        $targets = array_filter([$apiOnly ? null : $blueprint->url(), $api ? $apiUrl : null]);

        $this->components->info("Generating {$blueprint->plural()} (".implode(' + ', $targets).') …');
        $result = $generator->generate($blueprint, web: ! $apiOnly, api: $api);

        foreach ($result['files'] as $path) {
            $this->components->twoColumnDetail($path, '<fg=green>created</>');
        }

        $this->components->twoColumnDetail('app/Enums/PermissionName.php', '<fg=yellow>updated</>');

        if (! $apiOnly) {
            $this->components->twoColumnDetail('routes/generated.php', '<fg=yellow>updated</>');
        }

        if ($api) {
            $this->components->twoColumnDetail('routes/api/generated.php', '<fg=yellow>updated</>');
        }

        if ($this->option('menu')) {
            $createMenu->execute([
                'location' => MenuLocation::Admin->value,
                'title' => $blueprint->plural(),
                'url' => $blueprint->url(),
                'icon' => 'FolderOpen',
                'audience' => 'permission',
                'permission' => $blueprint->permission('view'),
            ]);
            $this->components->twoColumnDetail('Sidebar menu', '<fg=green>created</>');
        }

        foreach ($result['warnings'] as $warning) {
            $this->components->warn($warning);
        }

        if ($api) {
            $this->components->info("REST API ready at {$apiUrl}. Grant {$blueprint->table}.view/create/update/delete (and api.access) to roles on the Permissions page.");
        }

        if (! $apiOnly) {
            $this->components->info("Done. Open {$blueprint->url()} — run `npm run build` before deploying.");
        }

        return self::SUCCESS;
    }

    /**
     * "code:string(20):required" → panjang 20.
     *
     * @return array{name: string, type: string, required: bool, options: string, length: string}
     */
    private function parseField(string $spec): array
    {
        $parts = explode(':', $spec);
        $required = false;
        $options = '';
        $type = trim($parts[1] ?? 'string');
        $length = '';

        if (preg_match('/^string\((\d+)\)$/', $type, $m)) {
            $type = 'string';
            $length = $m[1];
        }

        foreach (array_slice($parts, 2) as $part) {
            if ($part === 'required') {
                $required = true;
            } else {
                $options = str_replace('|', ',', $part);
            }
        }

        return ['name' => trim($parts[0]), 'type' => $type, 'required' => $required, 'options' => $options, 'length' => $length];
    }

    /**
     * @return list<array{name: string, type: string, required: bool, options: string, length: string}>
     */
    private function askFields(): array
    {
        $fields = [];

        do {
            $name = text('Field name (e.g. name, phone_number)', required: true);
            $type = select('Type', CrudField::TYPES);
            $options = $type === 'select' ? text('Choices, separated by commas', required: true) : '';
            $length = $type === 'string' ? text('Max length', default: '255', required: true) : '';
            $required = $type !== 'boolean' && confirm('Required?', default: true);

            $fields[] = ['name' => $name, 'type' => (string) $type, 'required' => $required, 'options' => $options, 'length' => $length];
        } while (count($fields) < CrudInput::MAX_FIELDS && confirm('Add another field?', default: true));

        return $fields;
    }
}
