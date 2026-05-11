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
        Schema::create('product_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('branches_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_batch_id')->nullable()->constrained()->onDelete('set null');
            $table->string('unique_qr_code')->unique(); // Kode unik per biji barang
            $table->enum('status', ['Tersedia', 'Terjual', 'Rusak', 'Retur'])->default('Tersedia');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_items');
    }
};
