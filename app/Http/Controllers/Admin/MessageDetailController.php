<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveMessageDetailRequest;
use App\Models\Message;
use App\Models\MessageDetail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Baris DETAIL milik satu message (master–detail). Dibuat oleh CRUD generator.
 *
 * Route bertingkat & "scoped": /admin/messages/{message}/details/{detail}
 * Laravel memastikan detail itu memang milik induk di URL (404 bila bukan).
 * Hak akses mengikuti induk: boleh mengubah message = boleh mengelola detailnya.
 */
class MessageDetailController extends Controller
{
    public function create(Message $message): Response
    {
        Gate::authorize('update', $message);

        return Inertia::render('admin/messages/details/create', [
            'parent' => $message->only(['id', 'whs_code']),
        ]);
    }

    public function store(SaveMessageDetailRequest $request, Message $message): RedirectResponse
    {
        // Kolom penghubung (msg_id) diisi otomatis lewat relasi.
        $message->details()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Detail added.']);

        return to_route('admin.messages.edit', $message);
    }

    public function edit(Message $message, MessageDetail $detail): Response
    {
        Gate::authorize('update', $message);

        return Inertia::render('admin/messages/details/edit', [
            'parent' => $message->only(['id', 'whs_code']),
            'item' => $detail,
        ]);
    }

    public function update(SaveMessageDetailRequest $request, Message $message, MessageDetail $detail): RedirectResponse
    {
        $detail->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Detail updated.']);

        return to_route('admin.messages.edit', $message);
    }

    public function destroy(Message $message, MessageDetail $detail): RedirectResponse
    {
        Gate::authorize('update', $message);

        $detail->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Detail deleted.']);

        return to_route('admin.messages.edit', $message);
    }
}
