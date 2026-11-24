<?php

namespace App\Http\Middleware;

use App\Enums\PermissionName;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lapis pertama RBAC API: role user harus punya permission "api.access".
 *
 * Dipasang SETELAH auth:sanctum. Izin per endpoint (mis. messages.view)
 * tetap dicek Policy / Form Request masing-masing, memakai permission yang
 * sama dengan halaman web. Admin lolos lewat Gate::before.
 *
 * Akun yang dinonaktifkan tidak perlu dicek di sini: semua tokennya sudah
 * dicabut saat akun dinonaktifkan (lihat TokenService / UserObserver).
 */
class EnsureApiAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->can(PermissionName::ApiAccess)) {
            abort(403, 'Your role is not allowed to use the API.');
        }

        return $next($request);
    }
}
