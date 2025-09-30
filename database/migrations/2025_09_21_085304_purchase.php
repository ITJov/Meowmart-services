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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branches_id')->constrained('branches');
            
            // DIUBAH: Tidak lagi memerlukan tabel 'suppliers'.
            // Cukup simpan nama supplier sebagai teks biasa.
            $table->string('supplier')->nullable(); 

            $table->string('invoice_number')->unique();
            $table->date('purchase_date');
            $table->string('purchase_status'); // Contoh: Dipesan, Dikirim, Selesai
            $table->decimal('total_amount', 15, 2);
            $table->string('payment_status'); // Contoh: Paid, Unpaid
            $table->text('notes')->nullable();
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
