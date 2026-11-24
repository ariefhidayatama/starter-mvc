<?php

namespace App\Actions\Product;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class BulkDeleteProductAction
{
    public function __construct(private DeleteProductAction $deleteProduct) {}

    /**
     * Setiap produk tetap dihapus lewat DeleteProductAction, sehingga aturan
     * hapus di masa depan (mis. produk yang sudah dipesan) ikut berlaku.
     * Semua-atau-tidak-sama-sekali: jika satu gagal, semuanya dibatalkan.
     *
     * @param  list<string>  $ulids
     * @return int jumlah produk yang dihapus
     */
    public function execute(array $ulids): int
    {
        return DB::transaction(function () use ($ulids): int {
            $products = Product::query()->whereIn('ulid', $ulids)->get();

            foreach ($products as $product) {
                $this->deleteProduct->execute($product);
            }

            return $products->count();
        });
    }
}
