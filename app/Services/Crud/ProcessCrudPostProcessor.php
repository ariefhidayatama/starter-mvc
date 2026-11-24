<?php

namespace App\Services\Crud;

use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Menjalankan artisan / formatter di PROSES TERPISAH.
 *
 * Kenapa tidak Artisan::call()? Proses PHP yang sedang berjalan sudah
 * memuat enum PermissionName & daftar route yang LAMA. Seeder & Wayfinder
 * harus membaca file yang baru ditulis, jadi dijalankan sebagai proses baru.
 *
 * Supaya cepat: langkah yang tidak saling bergantung dijalankan PARALEL,
 * Xdebug dimatikan untuk proses anak, dan wayfinder:generate dilewati bila
 * Vite dev server hidup (plugin wayfinder di vite.config.ts sudah menjalankannya
 * sendiri setiap kali routes/ atau controller berubah).
 */
class ProcessCrudPostProcessor implements CrudPostProcessor
{
    public function format(string $root, array $paths): array
    {
        $php = array_values(array_filter($paths, fn (string $p) => str_ends_with($p, '.php')));
        $tsx = array_values(array_filter($paths, fn (string $p) => str_ends_with($p, '.tsx')));
        $node = config('crud.node_binary');

        return $this->runParallel($root, array_filter([
            'Pint (PHP formatting)' => $php === [] ? null : [...$this->php(), 'vendor/bin/pint', ...$php],
            'vp fmt (TSX formatting)' => $tsx === [] ? null : [is_string($node) ? $node : 'node', 'node_modules/vite-plus/bin/vp', 'fmt', ...$tsx],
        ]));
    }

    public function afterGenerate(string $root, ?string $migrationPath): array
    {
        // Seeder hanya membaca enum PermissionName (bukan tabel baru), jadi
        // tidak perlu menunggu migrate.
        return $this->runParallel($root, array_filter([
            'migrate' => $migrationPath === null ? null : [...$this->php(), 'artisan', 'migrate', '--path='.$migrationPath, '--force'],
            'permission seeder' => [...$this->php(), 'artisan', 'db:seed', '--class=RolePermissionSeeder', '--force'],
            'wayfinder:generate' => $this->wayfinder($root),
        ]));
    }

    public function afterRemove(string $root): array
    {
        return $this->runParallel($root, array_filter([
            'permission seeder' => [...$this->php(), 'artisan', 'db:seed', '--class=RolePermissionSeeder', '--force'],
            'wayfinder:generate' => $this->wayfinder($root),
        ]));
    }

    /**
     * @param  array<string, list<string>>  $commands  label → perintah
     * @return list<string> pesan peringatan untuk perintah yang gagal
     */
    private function runParallel(string $root, array $commands): array
    {
        if ($commands === []) {
            return [];
        }

        $results = Process::pool(function (Pool $pool) use ($root, $commands): void {
            foreach ($commands as $label => $command) {
                $pool->as($label)->path($root)->env($this->windowsEnv())->timeout(180)->command($command);
            }
        })->start()->wait();

        $warnings = [];

        foreach ($commands as $label => $command) {
            $result = $results[$label];

            if ($result->successful()) {
                continue;
            }

            Log::warning("CRUD generator: {$label} failed", [
                'command' => implode(' ', $command),
                'output' => $result->output(),
                'error' => $result->errorOutput(),
            ]);

            $warnings[] = "{$label} failed. Run it manually (see storage/logs/laravel.log).";
        }

        return $warnings;
    }

    /**
     * Perintah wayfinder:generate, atau null bila Vite dev server hidup
     * (plugin wayfinder-nya yang akan membuat ulang file actions/routes).
     *
     * @return list<string>|null
     */
    private function wayfinder(string $root): ?array
    {
        return $this->viteIsRunning($root)
            ? null
            : [...$this->php(), 'artisan', 'wayfinder:generate', '--with-form'];
    }

    /**
     * public/hot ada DAN alamatnya benar-benar menerima koneksi. File hot bisa
     * tertinggal bila Vite dimatikan paksa; tanpa cek koneksi, wayfinder tidak
     * akan pernah dijalankan.
     */
    private function viteIsRunning(string $root): bool
    {
        $hot = $root.'/public/hot';

        if (! is_file($hot)) {
            return false;
        }

        $url = parse_url(trim((string) file_get_contents($hot)));
        $host = $url['host'] ?? null;

        if (! is_string($host) || $host === '') {
            return false;
        }

        $port = $url['port'] ?? (($url['scheme'] ?? 'http') === 'https' ? 443 : 80);
        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $error, 0.5);

        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    }

    /**
     * Windows: `php artisan serve` hanya meneruskan sebagian environment
     * variable ke web server. Tanpa SystemRoot, proses anak (PHP, Node)
     * gagal membuka koneksi jaringan ("Unknown error while connecting" ke
     * MySQL), jadi variabel dasar Windows dikirim ulang di sini.
     *
     * @return array<string, string>
     */
    private function windowsEnv(): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return [];
        }

        $root = getenv('SystemRoot') ?: (getenv('WINDIR') ?: 'C:\\Windows');
        $path = (string) getenv('PATH');
        $system32 = $root.'\\System32';

        return [
            'SystemRoot' => $root,
            'WINDIR' => $root,
            'TEMP' => getenv('TEMP') ?: sys_get_temp_dir(),
            'TMP' => getenv('TMP') ?: sys_get_temp_dir(),
            'PATH' => str_contains(strtolower($path), strtolower($system32)) ? $path : $system32.';'.$path,
        ];
    }

    /**
     * Binary PHP + opsi untuk proses anak. Xdebug dimatikan (tidak berpengaruh
     * bila Xdebug tidak terpasang): proses ini tidak perlu di-debug dan
     * Xdebug memperlambat setiap boot Laravel.
     *
     * @return list<string>
     */
    private function php(): array
    {
        $configured = config('crud.php_binary');
        $binary = is_string($configured) && $configured !== ''
            ? $configured
            : ((new PhpExecutableFinder)->find(false) ?: 'php');

        return [$binary, '-d', 'xdebug.mode=off'];
    }
}
