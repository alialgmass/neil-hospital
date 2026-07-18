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
        Schema::table('dr_payments', function (Blueprint $table) {
            $table->date('period_from')->nullable()->change();
            $table->date('period_to')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('dr_payments', function (Blueprint $table) {
            $table->date('period_from')->nullable(false)->change();
            $table->date('period_to')->nullable(false)->change();
        });
    }
};
