<?php

namespace App\Actions\Category;

use App\Exceptions\BusinessException;
use App\Models\Category;

class DeleteCategoryAction
{
    public function execute(Category $category): void
    {
        // Cek di aplikasi agar pesan errornya jelas (409). Database juga
        // menolak lewat foreign key restrictOnDelete sebagai pengaman kedua.
        $productsCount = $category->products()->count();

        if ($productsCount > 0) {
            throw new BusinessException("This category still has {$productsCount} product(s). Move or delete them first.");
        }

        $category->delete();
    }
}
