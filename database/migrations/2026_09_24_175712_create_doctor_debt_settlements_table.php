<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A ledger of Doctor::settleDebt() calls — doctor_debt_balance is a
     * single running scalar with no history, so without this the claims
     * report (DoctorClaimsService) has no way to know that part of a
     * booking's freshly computed dr_share was silently diverted to pay
     * down old debt rather than being newly payable, and would keep
     * showing that diverted amount as still "مستحق" forever.
     */
    public function up(): void
    {
        Schema::create('doctor_debt_settlements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->foreignUlid('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_debt_settlements');
    }
};
