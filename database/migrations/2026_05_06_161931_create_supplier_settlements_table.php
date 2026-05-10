<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create suppliers first (without open_settlement_id — added after settlements table exists)
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('phone', 20)->nullable()->unique();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('open_settlement_id')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->date('period_from');
            $table->date('period_to')->nullable();
            $table->date('settlement_date')->nullable();
            $table->decimal('snapshot_total_delivered', 10, 2)->nullable();
            $table->decimal('snapshot_total_paid', 10, 2)->nullable();
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->foreignId('cash_entry_id')->nullable()->constrained('cash_entries')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_settlements');
        Schema::dropIfExists('suppliers');
    }
};
