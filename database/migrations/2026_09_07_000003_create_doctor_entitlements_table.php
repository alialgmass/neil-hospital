<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_entitlements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            // Nullable + nullOnDelete so a settled entitlement survives a hard
            // booking delete as an audit record; non-settled rows are cleaned
            // up explicitly before the booking is removed.
            $table->foreignUlid('booking_id')->nullable()->unique()->constrained('bookings')->nullOnDelete();
            $table->foreignUlid('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->foreignUlid('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('source', 20);
            $table->string('status', 20)->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_entitlements');
    }
};
