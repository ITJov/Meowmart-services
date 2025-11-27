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
        Schema::create('transfer_stocks', function (Blueprint $table) {
        $table->id();
        $table->string('reference_number')->unique(); // Nomor referensi transfer (misal: ST/2025/001)
        
        // Relasi ke GUDANG ASAL
        $table->foreignId('from_warehouse_id')->constrained('warehouses');
        
        // Relasi ke GUDANG TUJUAN
        $table->foreignId('to_warehouse_id')->constrained('warehouses');

        $table->date('transfer_date');
        $table->string('status'); // Misal: Pending, Completed
        $table->text('notes')->nullable();
        $table->foreignId('user_id')->comment('Staf yang mencatat')->constrained('users');
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
