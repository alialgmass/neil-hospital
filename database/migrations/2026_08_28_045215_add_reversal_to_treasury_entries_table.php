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
        Schema::table('treasury_entries', function (Blueprint $table) {
            $table->ulid('reversal_of_id')->nullable()->after('source');
            $table->timestamp('reversed_at')->nullable()->after('reversal_of_id');

            $table->foreign('reversal_of_id')->references('id')->on('treasury_entries')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('treasury_entries', function (Blueprint $table) {
            $table->dropForeign(['reversal_of_id']);
            $table->dropColumn(['reversal_of_id', 'reversed_at']);
        });
    }
};
