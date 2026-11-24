<?php

namespace App\Actions\Category;

use App\Models\Category;
use Illuminate\Support\Str;

/**
 * Membuat slug unik dari nama: "Kopi Susu" → "kopi-susu", dan jika sudah
 * dipakai → "kopi-susu-2", "kopi-susu-3", dst.
 */
trait GeneratesUniqueSlug
{
    protected function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'category';
        $slug = $base;
        $i = 2;

        while (Category::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
