<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Insurance-specific one-eye / both-eyes prices. Left null when not
     * configured — the pricing service then falls back to `ins_price`.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->decimal('ins_one_eye_price', 10, 2)->nullable()->after('ins_price');
            $table->decimal('ins_both_eyes_price', 10, 2)->nullable()->after('ins_one_eye_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['ins_one_eye_price', 'ins_both_eyes_price']);
        });
    }
};
