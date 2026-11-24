<?php

namespace App\Actions\Category;

use App\Models\Category;

class UpdateCategoryAction
{
    use GeneratesUniqueSlug;

    /**
     * @param  array{name: string, slug?: string|null, description?: string|null}  $data
     */
    public function execute(Category $category, array $data): Category
    {
        $category->update([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug(
                filled($data['slug'] ?? null) ? (string) $data['slug'] : $data['name'],
                $category->id,
            ),
            'description' => $data['description'] ?? null,
        ]);

        return $category;
    }
}
