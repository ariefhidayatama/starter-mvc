<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid pengenal acak untuk URL admin (diisi otomatis saat dibuat)
 * @property int $category_id
 * @property string $sku
 * @property string $name
 * @property string|null $description
 * @property string $price decimal → string, agar tidak ada galat pembulatan float
 * @property int $stock
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Category $category
 */
#[Fillable(['category_id', 'sku', 'name', 'description', 'price', 'stock', 'is_active'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /** ULID untuk URL admin (route {product:ulid}); primary key tetap "id". */
    use HasPublicUlid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Scope: Product::query()->active()
     *
     * @param  Builder<Product>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
