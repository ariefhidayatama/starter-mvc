<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveMessageRequest;
use App\Http\Resources\V1\MessageResource;
use App\Models\Message;
use App\Support\ApiResponse;
use App\Support\QuerySort;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * REST API /api/v1/messages. Dibuat oleh CRUD generator.
 *
 * Siapa boleh apa diatur di Admin → Permissions (permission yang SAMA
 * dengan halaman web):
 *
 *   GET    /api/v1/messages            messages.view
 *   GET    /api/v1/messages/{id}       messages.view
 *   POST   /api/v1/messages            messages.create
 *   PUT    /api/v1/messages/{id}       messages.update
 *   DELETE /api/v1/messages/{id}       messages.delete
 *
 * Semua endpoint wajib token + role ber-"api.access" (routes/api/v1.php).
 * Validasi & izin tulis memakai SaveMessageRequest yang sama dengan web.
 */
class MessageController extends Controller
{
    /** Kunci ?sort= → kolom (whitelist, lihat App\Support\QuerySort). */
    private const SORTS = [
        'whs_code' => 'whs_code',
        'owner_code' => 'owner_code',
        'msg_group' => 'msg_group',
        'photo1' => 'photo1',
        'photo2' => 'photo2',
        'photo3' => 'photo3',
        'photo4' => 'photo4',
        'photo5' => 'photo5',
        'document1' => 'document1',
        'document2' => 'document2',
        'document3' => 'document3',
        'created_date' => 'created_date',
    ];

    /**
     * Daftar ber-halaman: ?search=, ?sort= (awali "-" untuk menurun), ?per_page= (maks. 100).
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Message::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(QuerySort::options(array_keys(self::SORTS)))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = is_string($filters['search'] ?? null) ? trim($filters['search']) : '';
        $sort = is_string($filters['sort'] ?? null) ? $filters['sort'] : '-created_date';
        $perPage = is_numeric($filters['per_page'] ?? null) ? (int) $filters['per_page'] : 15;

        $query = Message::query()
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('whs_code', 'like', "%{$search}%")
                ->orWhere('owner_code', 'like', "%{$search}%")
                ->orWhere('msg_group', 'like', "%{$search}%")
                ->orWhere('photo1', 'like', "%{$search}%")
                ->orWhere('photo2', 'like', "%{$search}%")
                ->orWhere('photo3', 'like', "%{$search}%")
                ->orWhere('photo4', 'like', "%{$search}%")
                ->orWhere('photo5', 'like', "%{$search}%")
                ->orWhere('document1', 'like', "%{$search}%")
                ->orWhere('document2', 'like', "%{$search}%")
                ->orWhere('document3', 'like', "%{$search}%")));

        return ApiResponse::paginated(
            QuerySort::apply($query, $sort, self::SORTS, '-created_date')->paginate($perPage)->withQueryString(),
            MessageResource::class,
        );
    }

    public function show(Message $message): JsonResponse
    {
        Gate::authorize('view', $message);

        return ApiResponse::success(MessageResource::make($message->load(['details'])));
    }

    /**
     * Message + baris details disimpan dalam SATU transaksi:
     * bila satu baris gagal, header & baris lain ikut dibatalkan.
     * Validasi semua baris sudah selesai sebelum transaksi dimulai.
     */
    public function store(SaveMessageRequest $request): JsonResponse
    {
        $message = DB::connection('wmsreports')->transaction(function () use ($request) {
            $message = Message::create($request->headerData());

            foreach ($request->detailRows() as $row) {
                $message->details()->create($row);
            }

            return $message;
        });

        return ApiResponse::created(MessageResource::make($message->load(['details'])), 'Message created.');
    }

    public function update(SaveMessageRequest $request, Message $message): JsonResponse
    {
        $message->update($request->validated());

        return ApiResponse::success(MessageResource::make($message->load(['details'])), 'Message updated.');
    }

    public function destroy(Message $message): Response
    {
        Gate::authorize('delete', $message);

        // Hapus semua detail lalu induknya dalam SATU transaksi: berhasil semua atau batal semua.
        DB::connection('wmsreports')->transaction(function () use ($message) {
            $message->details()->delete();
            $message->delete();
        });

        return ApiResponse::noContent();
    }
}
