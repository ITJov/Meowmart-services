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
        Schema::table('product_details', function (Blueprint $table) {
            $table->renameColumn('warehouse_id', 'branches_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_details', function (Blueprint $table) {
            // Logika untuk mengembalikan jika perlu
            $table->renameColumn('branches_id', 'warehouse_id');
        });
    }
};
