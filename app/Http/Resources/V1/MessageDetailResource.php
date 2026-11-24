<?php

namespace App\Http\Resources\V1;

use App\Models\MessageDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bentuk JSON satu message detail di REST API. Dibuat oleh CRUD generator.
 *
 * Hanya kolom yang ditulis di sini yang dikirim ke klien, jadi kolom baru
 * di tabel TIDAK otomatis bocor ke API. Tambah / hapus sesuai kebutuhan.
 *
 * @mixin MessageDetail
 */
class MessageDetailResource extends JsonResource
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
            'msg_id' => $this->getAttribute('msg_id'),
            'trans_code' => $this->trans_code,
            'trans_no' => $this->trans_no,
            'ref_no' => $this->ref_no,
            'po_no' => $this->po_no,
            'prod_code' => $this->prod_code,
            'loc_code' => $this->loc_code,
            'pallet_no' => $this->pallet_no,
            'lot_no' => $this->lot_no,
            'inv_status' => $this->inv_status,
            'qty' => $this->qty,
            'created_date' => $this->created_date?->toIso8601String(),
            'updated_date' => $this->updated_date?->toIso8601String(),
        ];
    }
}
