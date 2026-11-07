<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Deliberately separate from doctor_service (insurance/contract fees):
     * a doctor's delegation/anesthesia price for a service is its own thing,
     * independent of — and possibly different from — their normal per-service
     * fee for that same service.
     */
    public function up(): void
    {
        Schema::create('doctor_delegation_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->foreignUlid('service_id')->constrained('services')->cascadeOnDelete();
            $table->decimal('fee', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['doctor_id', 'service_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_delegation_fees');
    }
};
