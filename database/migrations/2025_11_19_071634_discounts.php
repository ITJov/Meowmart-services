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
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            
            // Informasi Dasar & SnK
            $table->string('name');
            $table->string('code')->unique()->nullable(); // Kode kupon (misal: HEMAT10)
            $table->text('description')->nullable(); // Syarat dan Ketentuan (SnK)

            // Nilai Diskon
            $table->enum('discount_type', ['percentage', 'fixed']); // Persentase atau Potongan
            $table->decimal('discount_value', 8, 2); // Nilai diskon (0.10 atau 50000)
            $table->decimal('min_payment_amount', 10, 2)->default(0); // Minimal pembayaran

            // Batasan Waktu & Penggunaan
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('usage_limit')->nullable(); // Batas total penggunaan kupon
            $table->unsignedInteger('user_limit')->default(1); // Batas penggunaan per user

            // Relasi (Jika diskon spesifik untuk cabang tertentu)
            $table->foreignId('branches_id')->nullable()->constrained();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
