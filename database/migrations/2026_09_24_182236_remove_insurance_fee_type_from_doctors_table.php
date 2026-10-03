<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The FeeType::Insurance doctor fee type is removed — it behaved
     * exactly like FeeType::Fixed (flat per-case fee_value on cash
     * bookings), so existing doctors are migrated to 'fixed' before the
     * enum column stops allowing 'insurance' at all.
     */
    public function up(): void
    {
        DB::table('doctors')->where('fee_type', 'insurance')->update(['fee_type' => 'fixed']);

        Schema::table('doctors', function (Blueprint $table) {
            $table->enum('fee_type', ['percentage', 'fixed'])->default('percentage')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->enum('fee_type', ['percentage', 'fixed', 'insurance'])->default('percentage')->change();
        });
    }
};
