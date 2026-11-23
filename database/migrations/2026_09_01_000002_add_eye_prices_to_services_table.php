<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->decimal('one_eye_price', 10, 2)->nullable()->after('price');
            $table->decimal('both_eyes_price', 10, 2)->nullable()->after('one_eye_price');
        });

        // Backfill: one-eye defaults from existing price, both-eyes defaults to double.
        DB::table('services')->whereNull('one_eye_price')->update([
            'one_eye_price' => DB::raw('price'),
        ]);
        DB::table('services')->whereNull('both_eyes_price')->update([
            'both_eyes_price' => DB::raw('price * 2'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['one_eye_price', 'both_eyes_price']);
        });
    }
};
