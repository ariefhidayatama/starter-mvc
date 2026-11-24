<?php

namespace App\Actions\Product;

use App\Models\Product;

class DeleteProductAction
{
    /**
     * Catatan: setelah modul Pesanan dibuat (Tahap 5), produk yang sudah
     * pernah dipesan tidak boleh dihapus permanen. Aturan itu ditambahkan
     * di sini, sehingga Web & API otomatis ikut.
     */
    public function execute(Product $product): void
    {
        $product->delete();
    }
}
