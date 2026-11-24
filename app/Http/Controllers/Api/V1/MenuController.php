<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MenuLocation;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Menu\MenuTreeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/menus/website — menu navbar website (publik) untuk aplikasi
 * mobile / frontend lain. Memakai MenuTreeService yang SAMA dengan web,
 * jadi menu yang diatur admin otomatis sama di semua tampilan.
 *
 * Menu sidebar dashboard sengaja tidak dibuka lewat API (khusus panel web).
 */
class MenuController extends Controller
{
    public function __invoke(Request $request, MenuTreeService $menus): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user('sanctum');

        return ApiResponse::success($menus->forUser(MenuLocation::Website, $user));
    }
}
