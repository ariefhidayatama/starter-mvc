<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Dilempar oleh Action saat aturan bisnis dilanggar, misalnya
 * "admin tidak boleh menghapus akunnya sendiri" atau "stok tidak cukup".
 *
 * Pesannya AMAN ditampilkan ke user (bukan error teknis).
 *
 * Laravel otomatis memanggil render() saat exception ini tidak ditangkap:
 * - API  → JSON 409 Conflict
 * - Web  → kembali ke halaman sebelumnya + toast error
 */
class BusinessException extends Exception
{
    public function __construct(string $message, private readonly int $status = 409)
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'success' => false,
                'message' => $this->getMessage(),
                'code' => 'CONFLICT',
                'errors' => null,
            ], $this->status);
        }

        Inertia::flash('toast', ['type' => 'error', 'message' => $this->getMessage()]);

        return back();
    }
}
