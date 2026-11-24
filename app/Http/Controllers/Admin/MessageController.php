<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveMessageRequest;
use App\Models\Message;
use App\Support\QuerySort;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Messages. Dibuat oleh CRUD generator.
 *
 * Sengaja sederhana: query langsung di controller. Bila logikanya
 * bertambah, pindahkan ke Action/Service seperti modul Products.
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

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Message::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(QuerySort::options(array_keys(self::SORTS)))],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ]);

        $search = is_string($filters['search'] ?? null) ? trim($filters['search']) : '';
        $sort = is_string($filters['sort'] ?? null) ? $filters['sort'] : '-created_date';
        $perPage = is_numeric($filters['per_page'] ?? null) ? (int) $filters['per_page'] : 10;

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

        return Inertia::render('admin/messages/index', [
            'items' => QuerySort::apply($query, $sort, self::SORTS, '-created_date')
                ->paginate($perPage)
                ->withQueryString(),
            'filters' => ['search' => $search, 'sort' => $sort, 'per_page' => $perPage],
            'abilities' => $this->abilities(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Message::class);

        return Inertia::render('admin/messages/create', [
            'maxDetails' => SaveMessageRequest::MAX_DETAILS,
        ]);
    }

    /**
     * Header + baris detail disimpan dalam SATU transaksi: bila satu baris
     * gagal, header & detail lain ikut dibatalkan (tidak ada data setengah jadi).
     * Validasi semua baris sudah selesai sebelum transaksi dimulai.
     */
    public function store(SaveMessageRequest $request): RedirectResponse
    {
        $details = $request->detailRows();

        DB::connection('wmsreports')->transaction(function () use ($request, $details) {
            // INSERT msg → SQL Server membuat id (IDENTITY), Laravel mengisinya ke $message->id.
            $message = Message::create($request->headerData());

            // INSERT msg_det per baris; msg_id diisi otomatis lewat relasi details().
            foreach ($details as $detail) {
                $message->details()->create($detail);
            }
        });

        $count = count($details);
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $count > 0
                ? "Message created with {$count} detail row".($count === 1 ? '' : 's').'.'
                : 'Message created.',
        ]);

        return to_route('admin.messages.index');
    }

    public function edit(Message $message): Response
    {
        Gate::authorize('update', $message);

        return Inertia::render('admin/messages/edit', [
            'item' => $message,
            'abilities' => $this->abilities(),
            // Detail milik message ini, 10 per halaman (?details_page=2).
            'details' => $message->details()->orderByDesc('id')->paginate(10, ['*'], 'details_page')->withQueryString(),
        ]);
    }

    public function update(SaveMessageRequest $request, Message $message): RedirectResponse
    {
        $message->update($request->headerData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Message updated.']);

        return to_route('admin.messages.index');
    }

    public function destroy(Message $message): RedirectResponse
    {
        Gate::authorize('delete', $message);

        // Hapus detail lalu induknya dalam SATU transaksi: berhasil semua atau batal semua.
        DB::connection('wmsreports')->transaction(function () use ($message) {
            $message->details()->delete();
            $message->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Message deleted.']);

        return to_route('admin.messages.index');
    }

    /**
     * Tombol yang boleh tampil: permission aplikasi DAN izin akun database.
     *
     * @return array{create: bool, update: bool, delete: bool}
     */
    private function abilities(): array
    {
        return [
            'create' => Gate::allows('create', Message::class),
            'update' => Gate::allows('update', new Message),
            'delete' => Gate::allows('delete', new Message),
        ];
    }
}
