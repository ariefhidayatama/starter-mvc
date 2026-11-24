<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Mengubah SEMUA exception di route /api/* menjadi format error standar:
 *
 *   {
 *     "success": false,
 *     "message": "Pesan yang aman dibaca manusia.",
 *     "code": "VALIDATION_ERROR",
 *     "errors": { "field": ["..."] } | null
 *   }
 *
 * Didaftarkan di bootstrap/app.php (->withExceptions). Klien mobile cukup
 * membaca field "code" untuk menentukan reaksi, tanpa menebak dari teks.
 */
class ApiExceptionRenderer
{
    /** Kode error per status HTTP. */
    private const CODES = [
        400 => 'BAD_REQUEST',
        401 => 'UNAUTHENTICATED',
        403 => 'FORBIDDEN',
        404 => 'NOT_FOUND',
        405 => 'METHOD_NOT_ALLOWED',
        409 => 'CONFLICT',
        413 => 'PAYLOAD_TOO_LARGE',
        419 => 'SESSION_EXPIRED',
        422 => 'VALIDATION_ERROR',
        429 => 'TOO_MANY_REQUESTS',
        500 => 'SERVER_ERROR',
        503 => 'SERVICE_UNAVAILABLE',
    ];

    /** Pesan default bila exception tidak membawa pesan yang aman. */
    private const MESSAGES = [
        400 => 'The request could not be processed.',
        401 => 'Unauthenticated.',
        403 => 'You are not allowed to perform this action.',
        404 => 'Resource not found.',
        405 => 'Method not allowed.',
        409 => 'The request conflicts with the current state of the resource.',
        413 => 'The request is too large.',
        419 => 'Your session has expired.',
        422 => 'The given data was invalid.',
        429 => 'Too many requests. Please slow down.',
        500 => 'An unexpected error occurred. Please try again later.',
        503 => 'The service is temporarily unavailable.',
    ];

    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null; // biarkan Laravel menangani route web seperti biasa
        }

        return match (true) {
            $e instanceof ValidationException => $this->respond(
                422, $this->firstError($e) ?? self::MESSAGES[422], $e->errors(),
            ),
            $e instanceof AuthenticationException => $this->respond(401, self::MESSAGES[401]),
            $e instanceof HttpExceptionInterface => $this->fromHttpException($e),
            default => $this->serverError($e),
        };
    }

    private function fromHttpException(HttpExceptionInterface $e): JsonResponse
    {
        $status = $e->getStatusCode();
        $message = $e->getMessage();

        // 404 dari route model binding berisi "No query results for model
        // [App\Models\Product] 99" → membocorkan nama class. Ganti pesan umum.
        if ($e->getPrevious() instanceof ModelNotFoundException) {
            $message = '';
        }

        // 403 dari Policy berisi "This action is unauthorized." → pakai pesan kita.
        if ($status === 403 && $message === 'This action is unauthorized.') {
            $message = '';
        }

        return $this->respond(
            $status,
            $message !== '' ? $message : (self::MESSAGES[$status] ?? 'Request failed.'),
            null,
            $e->getHeaders(), // mis. Retry-After pada 429
        );
    }

    private function serverError(Throwable $e): JsonResponse
    {
        $response = $this->respond(500, self::MESSAGES[500]);

        // Detail teknis HANYA saat APP_DEBUG=true (lokal). Di production
        // pesan & stack trace tidak pernah dikirim ke klien.
        if (config('app.debug')) {
            $response->setData([
                ...$response->getData(true),
                'debug' => [
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile().':'.$e->getLine(),
                ],
            ]);
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>|null  $errors
     * @param  array<string, string>  $headers
     */
    private function respond(int $status, string $message, ?array $errors = null, array $headers = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'code' => self::CODES[$status] ?? 'HTTP_'.$status,
            'errors' => $errors,
        ], $status, $headers);
    }

    private function firstError(ValidationException $e): ?string
    {
        $first = collect($e->errors())->flatten()->first();

        return is_string($first) ? $first : null;
    }
}
