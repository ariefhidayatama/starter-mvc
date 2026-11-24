<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menu & submenu (maksimal 2 tingkat) untuk sidebar dashboard dan
     * navbar website. Satu tabel, dibedakan oleh kolom `location`.
     */
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('location', 20);                       // admin | website
            // Submenu menunjuk ke menu induknya. RESTRICT: induk yang masih
            // punya submenu tidak bisa dihapus (dicek juga di DeleteMenuAction).
            $table->foreignId('parent_id')->nullable()->constrained('menus')->restrictOnDelete();
            $table->string('title', 100);
            $table->string('url', 500)->nullable();               // null = induk tanpa link (hanya pembuka submenu)
            $table->string('icon', 50)->nullable();               // nama ikon lucide, lihat App\Support\MenuIcons
            $table->string('permission', 100)->nullable();        // khusus sidebar: hanya tampil bagi yang punya izin
            $table->boolean('open_in_new_tab')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);         // menu bawaan fitur: tidak bisa dihapus
            $table->timestamps();

            // Query utama: semua menu satu lokasi, urut per induk & sort_order.
            $table->index(['location', 'parent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
