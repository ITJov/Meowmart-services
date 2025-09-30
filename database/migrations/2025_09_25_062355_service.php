<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            
            // Kolom untuk nama layanan, contoh: "Grooming Sehat"
            $table->string('name');
            
            // DIUBAH: Kolom ini sekarang terhubung ke tabel 'categories' yang sudah ada
            $table->foreignId('category_id')->constrained('categories');
            
            // Kolom untuk harga layanan
            $table->decimal('price', 15, 2);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};

