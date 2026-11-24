<?php

namespace App\Http\Resources\V1;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Pengenal acak untuk URL admin (/admin/categories/{ulid}/edit).
            // whenHas: relasi product.category hanya mengambil id,name,slug.
            'ulid' => $this->whenHas('ulid'),
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->whenHas('description'),
            // Hanya muncul jika query memakai withCount('products').
            'products_count' => $this->whenCounted('products'),
            'created_at' => $this->whenHas('created_at', fn () => $this->created_at?->toIso8601String()),
            'updated_at' => $this->whenHas('updated_at', fn () => $this->updated_at?->toIso8601String()),
        ];
    }
}
