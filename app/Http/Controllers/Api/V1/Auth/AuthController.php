<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\IssueApiTokenAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Autentikasi klien API dengan Bearer token (Sanctum).
 *
 * Controller ini tipis: validasi di LoginRequest, logika login di
 * IssueApiTokenAction, pengelolaan token di TokenService.
 */
class AuthController extends Controller
{
    /**
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request, IssueApiTokenAction $issueToken): JsonResponse
    {
        /** @var array{email: string, password: string, device_name: string, code?: string|null, recovery_code?: string|null} $credentials */
        $credentials = $request->validated();

        ['user' => $user, 'token' => $token] = $issueToken->execute($credentials);

        $user->load('roles');

        return ApiResponse::success([
            'token_type' => 'Bearer',
            // Token mentah hanya dikirim SEKALI ini. Simpan di tempat aman
            // (Android Keystore / iOS Keychain), jangan di storage biasa.
            'access_token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => UserResource::make($user)->withPermissions()->resolve($request),
        ], 'Logged in successfully.');
    }

    /**
     * GET /api/v1/auth/me — profil + roles + permissions user yang login.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->load('roles');

        return ApiResponse::success(UserResource::make($user)->withPermissions());
    }

    /**
     * POST /api/v1/auth/logout — logout dari device INI saja.
     */
    public function logout(Request $request): Response
    {
        // Cari baris token dari header "Authorization: Bearer <token>"
        // (Sanctum menyimpan hash-nya), lalu hapus.
        PersonalAccessToken::findToken((string) $request->bearerToken())?->delete();

        return ApiResponse::noContent();
    }

    /**
     * POST /api/v1/auth/logout-all — logout dari SEMUA device (token).
     */
    public function logoutAll(Request $request, TokenService $tokens): Response
    {
        /** @var User $user */
        $user = $request->user();
        $tokens->revokeAll($user);

        return ApiResponse::noContent();
    }
}
