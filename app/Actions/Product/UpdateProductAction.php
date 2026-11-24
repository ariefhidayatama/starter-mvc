<?php

namespace App\Actions\Product;

use App\Models\Product;

class UpdateProductAction
{
    /**
     * Mendukung update sebagian (PATCH): hanya field yang dikirim yang diubah.
     *
     * @param  array{category_id?: int, sku?: string, name?: string, description?: string|null, price?: numeric-string|int|float, stock?: int, is_active?: bool}  $data
     */
    public function execute(Product $product, array $data): Product
    {
        if (isset($data['sku'])) {
            $data['sku'] = strtoupper($data['sku']);
        }

        $product->update($data);

        return $product->load('category:id,name,slug');
    }
}
