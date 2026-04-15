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
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedSmallInteger('bed_no')->nullable()->after('visit_note');
            $table->string('eye_side', 10)->nullable()->after('bed_no');     // OD / OS / OU
            $table->string('analysis_type', 150)->nullable()->after('eye_side');
            $table->string('analysis_notes', 500)->nullable()->after('analysis_type');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['bed_no', 'eye_side', 'analysis_type', 'analysis_notes']);
        });
    }
};
