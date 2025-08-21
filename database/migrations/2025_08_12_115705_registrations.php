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
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->string('booking_id')->unique(); // Kode booking unik
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('pet_id')->constrained('pets');
            $table->foreignId('slot_id')->nullable()->constrained('slots'); // Untuk booking terjadwal
            $table->string('registration_type'); // Grooming, Pet Hotel, Clinic
            $table->string('status'); // Terjadwal, Selesai, Batal
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
