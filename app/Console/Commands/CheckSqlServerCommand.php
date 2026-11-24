<?php

namespace App\Console\Commands;

use App\Support\TableAccess;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Cek koneksi SQL Server ERP: bisa terhubung? versi server? akun punya
 * izin apa? HANYA membaca (tidak ada data yang diubah).
 *
 *   php artisan wmsreports:check
 *   php artisan wmsreports:check --table=msg --table=msg_det
 */
class CheckSqlServerCommand extends Command
{
    protected $signature = 'wmsreports:check
        {--connection=wmsreports : Connection name in config/database.php}
        {--table=* : Also show the account permissions on these tables}';

    protected $description = 'Check the SQL Server (ERP) connection, server version and account permissions (read-only)';

    public function handle(): int
    {
        $name = (string) $this->option('connection');

        if (! is_array(config("database.connections.{$name}"))) {
            $this->components->error("Connection \"{$name}\" is not defined in config/database.php.");

            return self::FAILURE;
        }

        if (! in_array('sqlsrv', \PDO::getAvailableDrivers(), true)) {
            $this->components->error('The pdo_sqlsrv PHP extension is not loaded. See docs/fitur/08e-integrasi-erp-sql-server.md.');

            return self::FAILURE;
        }

        if (blank(config("database.connections.{$name}.host"))) {
            $this->components->error('The host is empty. Fill WMSREPORTS_DB_* in .env.');

            return self::FAILURE;
        }

        $started = microtime(true);

        try {
            $db = DB::connection($name);
            $info = $db->selectOne(
                "SELECT CAST(SERVERPROPERTY('ProductVersion') AS nvarchar(64)) AS version,
                        CAST(SERVERPROPERTY('Edition') AS nvarchar(128)) AS edition,
                        DB_NAME() AS db, SUSER_SNAME() AS login, IS_SRVROLEMEMBER('sysadmin') AS sysadmin",
            );
        } catch (Throwable $e) {
            $this->components->error('Cannot connect: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('Connected to "%s" in %.2f s', $name, microtime(true) - $started));

        $roles = array_map(fn (object $r) => (string) ($r->name ?? ''), $db->select(
            'SELECT r.name FROM sys.database_role_members m JOIN sys.database_principals r ON r.principal_id = m.role_principal_id WHERE m.member_principal_id = USER_ID()',
        ));
        $permissions = array_map(fn (object $r) => (string) ($r->permission_name ?? ''), $db->select(
            "SELECT permission_name FROM fn_my_permissions(NULL, 'DATABASE') ORDER BY permission_name",
        ));
        $major = (int) explode('.', (string) ($info->version ?? '0'))[0];

        $this->components->twoColumnDetail('Server', "SQL Server {$info->version} ({$info->edition})");
        $this->components->twoColumnDetail('Database', (string) $info->db);
        $this->components->twoColumnDetail('Login', (string) $info->login.((int) $info->sysadmin === 1 ? ' <fg=red>(sysadmin!)</>' : ''));
        $this->components->twoColumnDetail('Database roles', implode(', ', $roles) ?: '-');
        $this->components->twoColumnDetail('Database permissions', implode(', ', $permissions));

        /** @var list<string> $tables */
        $tables = $this->option('table');

        foreach ($tables as $table) {
            $this->components->twoColumnDetail("Table {$table}", implode(', ', TableAccess::permissions($name, $table)) ?: '<fg=red>no access / not found</>');
        }

        if ((int) $info->sysadmin === 1) {
            $this->components->warn('This login is sysadmin. Use a login with only the permissions the app needs.');
        }

        // SQL Server 2014 = versi 12; STRING_AGG (dipakai sebagian fitur Laravel) baru ada di 2017 (versi 14).
        if ($major > 0 && $major < 14) {
            $this->components->warn('SQL Server older than 2017: Laravel\'s Schema::getIndexes()/getForeignKeys() do not work here. The CRUD generator uses its own queries.');
        }

        return self::SUCCESS;
    }
}
