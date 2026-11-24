<?php

namespace App\Actions\Product;

use App\Models\Product;

class CreateProductAction
{
    /**
     * @param  array{category_id: int, sku: string, name: string, description?: string|null, price: numeric-string|int|float, stock: int, is_active?: bool}  $data
     */
    public function execute(array $data): Product
    {
        $product = Product::create([
            ...$data,
            // SKU disimpan huruf besar agar "kop-001" dan "KOP-001" tidak dianggap berbeda.
            'sku' => strtoupper($data['sku']),
            'is_active' => $data['is_active'] ?? true,
        ]);

        return $product->load('category:id,name,slug');
    }
}
