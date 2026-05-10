<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_entries', function (Blueprint $table) {
            $table->dropForeign(['entry_type_id']);
            $table->dropColumn(['entry_type_id', 'entry_date']);
        });

        Schema::table('cash_entries', function (Blueprint $table) {
            $table->string('entry_type', 50)->default('client_payment')->after('id');
        });

        DB::table('cash_entries')->update(['entry_type' => 'client_payment']);

        Schema::dropIfExists('entry_types');
    }

    public function down(): void
    {
        // Intentionally left empty — this refactor is one-way
    }
};
