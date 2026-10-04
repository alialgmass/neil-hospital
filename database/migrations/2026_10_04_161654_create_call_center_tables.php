<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preliminary bookings taken by the call center, confirmed (converted
        // into a real booking) by reception.
        Schema::create('pre_bookings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('patient_name', 150);
            $table->string('patient_phone', 30);
            $table->string('national_id', 20)->nullable();
            $table->string('file_no', 20)->nullable();
            $table->string('dept', 20);
            $table->ulid('service_id')->nullable();
            $table->ulid('doctor_id')->nullable();
            $table->date('preferred_date');
            $table->time('preferred_time')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending');
            $table->ulid('booking_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->foreign('service_id')->references('id')->on('services')->nullOnDelete();
            $table->foreign('doctor_id')->references('id')->on('doctors')->nullOnDelete();
            $table->foreign('booking_id')->references('id')->on('bookings')->nullOnDelete();
            $table->index(['status', 'preferred_date']);
        });

        Schema::create('call_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('direction', 10);
            $table->string('caller_name', 150)->nullable();
            $table->string('phone', 30);
            $table->string('file_no', 20)->nullable();
            $table->ulid('booking_id')->nullable();
            $table->ulid('pre_booking_id')->nullable();
            $table->string('reason', 20);
            $table->string('outcome', 20);
            $table->text('notes')->nullable();
            $table->timestamp('follow_up_at')->nullable();
            $table->timestamp('follow_up_done_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('booking_id')->references('id')->on('bookings')->nullOnDelete();
            $table->foreign('pre_booking_id')->references('id')->on('pre_bookings')->nullOnDelete();
            $table->index('phone');
            $table->index(['follow_up_at', 'follow_up_done_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_logs');
        Schema::dropIfExists('pre_bookings');
    }
};
