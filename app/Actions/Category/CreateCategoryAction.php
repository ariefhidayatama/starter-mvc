<?php

namespace App\Actions\Category;

use App\Models\Category;

class CreateCategoryAction
{
    use GeneratesUniqueSlug;

    /**
     * @param  array{name: string, slug?: string|null, description?: string|null}  $data
     */
    public function execute(array $data): Category
    {
        return Category::create([
            'name' => $data['name'],
            // Slug opsional: jika dikosongkan, dibuat otomatis dari nama.
            'slug' => $this->uniqueSlug(filled($data['slug'] ?? null) ? (string) $data['slug'] : $data['name']),
            'description' => $data['description'] ?? null,
        ]);
    }
}
