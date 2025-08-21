<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // File: ..._add_foreign_keys_to_users_table.php (File baru Anda)
    public function up(): void
    {
        // Gunakan Schema::table() untuk MEMODIFIKASI, bukan Schema::create()
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->constrained('roles')->after('password');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->after('role_id');
            $table->foreignId('company_id')->constrained('companies')->after('warehouse_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
