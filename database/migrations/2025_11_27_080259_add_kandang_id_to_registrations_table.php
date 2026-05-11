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
        Schema::table('registrations', function (Blueprint $table) {
            $table->foreignId('kandang_id')
                  ->nullable() // Kandang hanya diperlukan untuk layanan Pet Hotel
                  ->after('pet_id') // Setelah kolom pet_id (opsional, untuk kerapian)
                  ->constrained() // Membuat foreign key ke tabel 'kandangs'
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            //
        });
    }
};
