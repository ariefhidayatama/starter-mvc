<?php

namespace App\Http\Resources\V1;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Pengenal acak untuk URL admin (/admin/products/{ulid}/edit).
            'ulid' => $this->ulid,
            'sku' => $this->sku,
            'name' => $this->name,
            // whenHas: daftar produk tidak mengambil kolom description (teks
            // panjang) agar response ringan; detail produk mengambilnya.
            'description' => $this->whenHas('description'),
            // Harga dikirim sebagai STRING "85000.00", bukan float, agar
            // tidak ada galat pembulatan di klien. Klien memformat sendiri.
            'price' => $this->price,
            'stock' => $this->stock,
            'in_stock' => $this->stock > 0,
            'is_active' => $this->is_active,
            // Resource bersarang di-resolve menjadi array biasa. Jika objek
            // CategoryResource dibiarkan apa adanya, Inertia merendernya
            // sebagai response tersendiri dan membungkusnya { "data": ... }.
            'category' => $this->whenLoaded(
                'category',
                fn () => CategoryResource::make($this->category)->resolve($request),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
