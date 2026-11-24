<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\DeviceResource;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Daftar & pencabutan device login milik user yang sedang login.
 */
class DeviceController extends Controller
{
    public function __construct(private readonly TokenService $tokens) {}

    /**
     * GET /api/v1/auth/devices
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            DeviceResource::collection($this->tokens->devices($user)),
        );
    }

    /**
     * DELETE /api/v1/auth/devices/{tokenId}
     *
     * Hanya bisa mencabut token MILIK SENDIRI. Token milik user lain
     * dijawab 404 (bukan 403), supaya keberadaannya tidak bocor.
     */
    public function destroy(Request $request, int $tokenId): Response
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($this->tokens->revoke($user, $tokenId), 404, 'Device not found.');

        return ApiResponse::noContent();
    }
}
