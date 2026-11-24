<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveKwitansiRequest;
use App\Models\Kwitansi;
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
 * Admin → Kwitansis. Dibuat oleh CRUD generator.
 *
 * Sengaja sederhana: query langsung di controller. Bila logikanya
 * bertambah, pindahkan ke Action/Service seperti modul Products.
 */
class KwitansiController extends Controller
{
    /** Kunci ?sort= → kolom (whitelist, lihat App\Support\QuerySort). */
    private const SORTS = [
        'kwitansi_no' => 'kwitansi_no',
        'customer' => 'customer',
        'ship_to' => 'ship_to',
        'ttf_no' => 'ttf_no',
        'created_at' => 'created_at',
    ];

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Kwitansi::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(QuerySort::options(array_keys(self::SORTS)))],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ]);

        $search = is_string($filters['search'] ?? null) ? trim($filters['search']) : '';
        $sort = is_string($filters['sort'] ?? null) ? $filters['sort'] : '-created_at';
        $perPage = is_numeric($filters['per_page'] ?? null) ? (int) $filters['per_page'] : 10;

        $query = Kwitansi::query()
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('kwitansi_no', 'like', "%{$search}%")
                ->orWhere('customer', 'like', "%{$search}%")
                ->orWhere('ship_to', 'like', "%{$search}%")
                ->orWhere('ttf_no', 'like', "%{$search}%")));

        return Inertia::render('admin/kwitansis/index', [
            'items' => QuerySort::apply($query, $sort, self::SORTS, '-created_at')
                ->paginate($perPage)
                ->withQueryString(),
            'filters' => ['search' => $search, 'sort' => $sort, 'per_page' => $perPage],
            'abilities' => $this->abilities(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Kwitansi::class);

        return Inertia::render('admin/kwitansis/create', [
            'maxDetails' => SaveKwitansiRequest::MAX_DETAILS,
        ]);
    }

    /**
     * Kwitansi + baris kwitansi dets disimpan dalam SATU transaksi:
     * bila satu baris gagal, header & baris lain ikut dibatalkan.
     * Validasi semua baris sudah selesai sebelum transaksi dimulai.
     */
    public function store(SaveKwitansiRequest $request): RedirectResponse
    {
        DB::connection()->transaction(function () use ($request) {
            $kwitansi = Kwitansi::create($request->headerData());

            // kwitansi_kwitansi_dets.kwitansi_id diisi otomatis lewat relasi kwitansiDets().
            foreach ($request->detailRows('kwitansi_dets') as $row) {
                $kwitansi->kwitansiDets()->create($row);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kwitansi created.']);

        return to_route('admin.kwitansis.index');
    }

    public function edit(Kwitansi $kwitansi): Response
    {
        Gate::authorize('update', $kwitansi);

        return Inertia::render('admin/kwitansis/edit', [
            'item' => $kwitansi,
            'abilities' => $this->abilities(),
            // Kwitansi dets milik kwitansi ini, 10 per halaman (?kwitansi_dets_page=2).
            'kwitansiDets' => $kwitansi->kwitansiDets()->orderByDesc('id')->paginate(10, ['*'], 'kwitansi_dets_page')->withQueryString(),
        ]);
    }

    public function update(SaveKwitansiRequest $request, Kwitansi $kwitansi): RedirectResponse
    {
        $kwitansi->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kwitansi updated.']);

        return to_route('admin.kwitansis.index');
    }

    public function destroy(Kwitansi $kwitansi): RedirectResponse
    {
        Gate::authorize('delete', $kwitansi);

        // Hapus semua detail lalu induknya dalam SATU transaksi: berhasil semua atau batal semua.
        DB::connection()->transaction(function () use ($kwitansi) {
            $kwitansi->kwitansiDets()->delete();
            $kwitansi->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kwitansi deleted.']);

        return to_route('admin.kwitansis.index');
    }

    /**
     * Tombol yang boleh tampil: permission aplikasi DAN izin akun database.
     *
     * @return array{create: bool, update: bool, delete: bool}
     */
    private function abilities(): array
    {
        return [
            'create' => Gate::allows('create', Kwitansi::class),
            'update' => Gate::allows('update', new Kwitansi),
            'delete' => Gate::allows('delete', new Kwitansi),
        ];
    }
}
