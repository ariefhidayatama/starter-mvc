<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;

/**
 * Pembungkus response SUKSES API agar formatnya selalu sama.
 *
 * Satu data:
 *   { "success": true, "message": "...", "data": {...} }
 *
 * Daftar ber-halaman:
 *   { "success": true, "message": "...", "data": [...], "meta": {...}, "links": {...} }
 *
 * Response ERROR dibuat oleh App\Exceptions\ApiExceptionRenderer.
 */
class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        if ($data instanceof JsonResource) {
            $data = $data->resolve(request());
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    public static function created(mixed $data = null, string $message = 'Created successfully.'): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    public static function noContent(): Response
    {
        return response()->noContent();
    }

    /**
     * Daftar ber-halaman. Mendukung paginate() (dengan total halaman) dan
     * cursorPaginate() (tanpa COUNT, untuk infinite scroll mobile).
     *
     * @template TValue
     *
     * @param  LengthAwarePaginator<int, TValue>|CursorPaginator<int, TValue>  $paginator
     * @param  class-string<JsonResource>  $resource  Resource untuk SATU item
     */
    public static function paginated(
        LengthAwarePaginator|CursorPaginator $paginator,
        string $resource,
        string $message = 'OK',
    ): JsonResponse {
        $items = $resource::collection($paginator->items())->resolve(request());

        if ($paginator instanceof LengthAwarePaginator) {
            $meta = [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ];
            $links = [
                'first' => $paginator->url(1),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
                'last' => $paginator->url($paginator->lastPage()),
            ];
        } else {
            $meta = [
                'per_page' => $paginator->perPage(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
            ];
            $links = [
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ];
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $items,
            'meta' => $meta,
            'links' => $links,
        ]);
    }
}
