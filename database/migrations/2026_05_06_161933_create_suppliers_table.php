<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add FK from suppliers.open_settlement_id → supplier_settlements
        // (suppliers table exists from previous migration, settlements table now exists)
        Schema::table('suppliers', function (Blueprint $table) {
            $table->foreign('open_settlement_id')
                  ->references('id')
                  ->on('supplier_settlements')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropForeign(['open_settlement_id']);
        });
    }
};
