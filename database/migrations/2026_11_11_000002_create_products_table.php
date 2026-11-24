<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // restrictOnDelete: kategori yang masih punya produk TIDAK bisa
            // dihapus di level database (lapisan pengaman kedua setelah Action).
            $table->foreignId('category_id')->constrained()->restrictOnDelete();

            // SKU (Stock Keeping Unit): kode unik produk, mis. "KOP-001".
            $table->string('sku', 50)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();

            // Uang SELALU DECIMAL, bukan FLOAT (FLOAT punya galat pembulatan:
            // 0.1 + 0.2 = 0.30000000000000004).
            $table->decimal('price', 15, 2);
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Index dasar. Optimasi index untuk filter/sort/search dibahas
            // & diukur di Tahap 5.
            $table->index(['is_active', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
