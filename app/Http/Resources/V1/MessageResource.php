<?php

namespace App\Http\Resources\V1;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bentuk JSON satu message di REST API. Dibuat oleh CRUD generator.
 *
 * Hanya kolom yang ditulis di sini yang dikirim ke klien, jadi kolom baru
 * di tabel TIDAK otomatis bocor ke API. Tambah / hapus sesuai kebutuhan.
 *
 * @mixin Message
 */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Pengenal acak (bila tabel punya kolom ulid); URL API memakainya.
            'ulid' => $this->whenHas('ulid'),
            'whs_code' => $this->whs_code,
            'owner_code' => $this->owner_code,
            'msg_group' => $this->msg_group,
            'msg_description' => $this->msg_description,
            'photo1' => $this->photo1,
            'photo2' => $this->photo2,
            'photo3' => $this->photo3,
            'photo4' => $this->photo4,
            'photo5' => $this->photo5,
            'document1' => $this->document1,
            'document2' => $this->document2,
            'document3' => $this->document3,
            'created_date' => $this->created_date?->toIso8601String(),
            'updated_date' => $this->updated_date?->toIso8601String(),
            // Hanya ada di detail (show) & hasil tambah/ubah.
            'details' => MessageDetailResource::collection($this->whenLoaded('details')),
        ];
    }
}
