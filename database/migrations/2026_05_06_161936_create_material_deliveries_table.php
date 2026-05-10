<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_deliveries', function (Blueprint $table) {
            $table->id();
            $table->enum('delivery_type', ['cash', 'debit']);
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('material_type_id')->constrained('material_types')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('quantity', 10, 3);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->date('delivery_date');
            $table->foreignId('cash_entry_id')->nullable()->constrained('cash_entries')->restrictOnDelete();
            $table->foreignId('settlement_id')->nullable()->constrained('supplier_settlements')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_deliveries');
    }
};
