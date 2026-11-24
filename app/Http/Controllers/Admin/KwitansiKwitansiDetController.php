<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveKwitansiKwitansiDetRequest;
use App\Models\Kwitansi;
use App\Models\KwitansiKwitansiDet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Baris kwitansi det milik satu kwitansi (master–detail). Dibuat oleh CRUD generator.
 *
 * Route bertingkat & "scoped": /admin/kwitansis/{kwitansi}/kwitansi-dets/{kwitansi_det}
 * Laravel memastikan baris itu memang milik induk di URL (404 bila bukan).
 * Hak akses mengikuti induk: boleh mengubah kwitansi = boleh mengelola kwitansi dets.
 */
class KwitansiKwitansiDetController extends Controller
{
    public function create(Kwitansi $kwitansi): Response
    {
        Gate::authorize('update', $kwitansi);

        return Inertia::render('admin/kwitansis/kwitansi-dets/create', [
            'parent' => $kwitansi->only(['id', 'ulid', 'kwitansi_no']),
        ]);
    }

    public function store(SaveKwitansiKwitansiDetRequest $request, Kwitansi $kwitansi): RedirectResponse
    {
        // Kolom penghubung (kwitansi_id) diisi otomatis lewat relasi.
        $kwitansi->kwitansiDets()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kwitansi det added.']);

        return to_route('admin.kwitansis.edit', $kwitansi);
    }

    public function edit(Kwitansi $kwitansi, KwitansiKwitansiDet $kwitansiDet): Response
    {
        Gate::authorize('update', $kwitansi);

        return Inertia::render('admin/kwitansis/kwitansi-dets/edit', [
            'parent' => $kwitansi->only(['id', 'ulid', 'kwitansi_no']),
            'item' => $kwitansiDet,
        ]);
    }

    public function update(SaveKwitansiKwitansiDetRequest $request, Kwitansi $kwitansi, KwitansiKwitansiDet $kwitansiDet): RedirectResponse
    {
        $kwitansiDet->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kwitansi det updated.']);

        return to_route('admin.kwitansis.edit', $kwitansi);
    }

    public function destroy(Kwitansi $kwitansi, KwitansiKwitansiDet $kwitansiDet): RedirectResponse
    {
        Gate::authorize('update', $kwitansi);

        $kwitansiDet->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kwitansi det deleted.']);

        return to_route('admin.kwitansis.edit', $kwitansi);
    }
}
