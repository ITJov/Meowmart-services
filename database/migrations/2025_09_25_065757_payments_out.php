<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments_out', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branches_id')->constrained('branches');
            $table->foreignId('user_id')->comment('User/staf yang mencatat pembayaran')->constrained('users');
            
            $table->foreignId('purchase_id')->nullable()->constrained('purchases');
            
            $table->string('transaction_number')->unique(); // TXNS NO, e.g., PAY-OUT-XXXX
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // DIUBAH: Sesuaikan dengan nama tabel yang baru
        Schema::dropIfExists('payments_out');
    }
};

