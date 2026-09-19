<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The original `status` column is a DB-level enum, which rejects any
     * value outside its fixed list — including the new system-only
     * "completed_electronic" state. Widen it to a plain string; validity is
     * already enforced by the Spatie ModelStates machinery and FormRequests.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('status', 30)->default('waiting')->change();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->enum('status', ['waiting', 'confirmed', 'in_progress', 'completed', 'cancelled'])
                ->default('waiting')
                ->change();
        });
    }
};
