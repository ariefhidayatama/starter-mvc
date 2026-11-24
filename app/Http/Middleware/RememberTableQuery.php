<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Filter tabel (search, filter, sort, per_page, page) disimpan di SESSION,
 * sehingga address bar tetap bersih:
 *
 *   sebelum  /admin/products?per_page=25&sort=-created_at&search=kopi
 *   sesudah  /admin/products
 *
 * Cara kerja:
 *  1. React (useTableQuery) tetap mengirim filter sebagai query string lewat
 *     XHR Inertia + header X-Table-Query. Request ini tidak tampil di address bar.
 *  2. Middleware ini menyimpan filter ke session SETELAH response sukses
 *     (artinya sudah lolos validasi Form Request, jadi nilai rusak tidak
 *     pernah tersimpan).
 *  3. URL halaman Inertia diganti dengan path saja (tanpa query), jadi itulah
 *     yang ditulis browser ke address bar & riwayat.
 *  4. Request tanpa filter (refresh, klik menu sidebar, redirect setelah
 *     simpan/hapus) → filter terakhir diambil dari session.
 *
 * Link lama yang membawa query (mis. bookmark ?search=kopi) tetap berfungsi:
 * filternya dipakai, disimpan, lalu URL-nya dibersihkan.
 *
 * Pemakaian di route (hanya halaman daftar admin, bukan API):
 *   Route::get(...)->middleware('remember.table')
 *   Route::resource(...)->middlewareFor('index', 'remember.table')
 */
class RememberTableQuery
{
    /** Kunci query yang diingat (selain "page"). Harus sama dengan useTableQuery. */
    public const KEYS = ['search', 'filter', 'sort', 'per_page'];

    /** Dikirim useTableQuery: "filter ini pilihan user", termasuk saat dikosongkan. */
    public const HEADER = 'X-Table-Query';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') || ! $request->hasSession()) {
            return $next($request);
        }

        $sessionKey = 'table_query.'.($request->route()?->getName() ?? $request->path());
        $stored = $request->session()->get($sessionKey);
        $stored = is_array($stored) ? $stored : [];

        $query = $request->query->all();
        $fromClient = $request->hasHeader(self::HEADER) || Arr::hasAny($query, self::KEYS);

        // Filter: dari query (pilihan baru) atau dari session (dipulihkan).
        $state = Arr::only($fromClient ? $query : $stored, self::KEYS);

        // Halaman: dari query (klik nomor halaman); filter berubah → halaman 1.
        if (isset($query['page'])) {
            $state['page'] = $query['page'];
        } elseif (! $fromClient && isset($stored['page'])) {
            $state['page'] = $stored['page'];
        }

        $request->query->replace($state + Arr::except($query, [...self::KEYS, 'page']));

        // Address bar: path saja. Link nomor halaman: ?page=N saja (filter
        // tidak ikut, karena sudah ada di session).
        Inertia::resolveUrlUsing(fn (Request $request) => $request->getBaseUrl().$request->getPathInfo());
        Paginator::queryStringResolver(fn () => []);

        try {
            $response = $next($request);
        } finally {
            Inertia::resolveUrlUsing(null);
            Paginator::queryStringResolver(fn () => app('request')->query());
        }

        if ($response->isSuccessful()) {
            $request->session()->put($sessionKey, $state);
        } elseif (! $fromClient) {
            // Filter dari session ternyata tidak valid lagi (mis. whitelist
            // sort berubah) → lupakan, agar tidak terjebak redirect berulang.
            $request->session()->forget($sessionKey);
        }

        return $response;
    }
}
