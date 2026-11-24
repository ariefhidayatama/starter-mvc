<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dibuat oleh CRUD generator (Kwitansis).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kwitansis', function (Blueprint $table) {
            $table->id();
            $table->string('kwitansi_no', 50);
            $table->string('customer', 100);
            $table->string('ship_to', 100);
            $table->string('ttf_no', 50);
            $table->timestamps();
        });

        // Kwitansi dets: baris milik satu kwitansi.
        Schema::create('kwitansi_kwitansi_dets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kwitansi_id')->constrained('kwitansis');
            $table->string('customer_po', 100);
            $table->string('si_no', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kwitansi_kwitansi_dets');
        Schema::dropIfExists('kwitansis');
    }
};
