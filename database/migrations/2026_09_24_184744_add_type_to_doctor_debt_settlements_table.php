<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The ledger now records both directions against a booking: 'settled'
     * (existing rows — a share diverted to pay down old debt) and
     * 'incurred' (a 0-payment write-off, see
     * PayBookingController::writeOffAsDoctorDebt()) — so the claims report
     * can tell a written-off booking apart from a normal one instead of
     * still computing it a full share.
     */
    public function up(): void
    {
        Schema::table('doctor_debt_settlements', function (Blueprint $table) {
            $table->string('type', 20)->default('settled')->after('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctor_debt_settlements', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
