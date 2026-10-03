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
        Schema::create('booking_doctor_delegations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignUlid('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->string('role', 20);
            $table->foreignUlid('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('service_name', 200);
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('status', 20)->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_doctor_delegations');
    }
};
