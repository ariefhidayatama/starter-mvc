<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memaksa semua request API dianggap "meminta JSON".
 *
 * Tanpa middleware ini, jika klien lupa mengirim header
 * "Accept: application/json", Laravel bisa menjawab error validasi
 * dengan redirect (302) atau error 500 dengan halaman HTML.
 * Klien mobile tidak bisa membaca keduanya.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
