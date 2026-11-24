<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Health check sederhana untuk klien API.
 *
 * Single-action controller (__invoke): dipakai bila controller hanya punya
 * satu tugas, sehingga route cukup menyebut nama class-nya.
 */
class PingController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'pong',
            'data' => [
                'version' => 'v1',
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }
}
