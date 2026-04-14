<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_sheets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignUlid('doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->text('chief_complaint')->nullable();
            $table->string('visual_acuity_od', 20)->nullable();
            $table->string('visual_acuity_os', 20)->nullable();
            $table->decimal('iop_od', 4, 1)->nullable();
            $table->decimal('iop_os', 4, 1)->nullable();
            $table->text('anterior_segment')->nullable();
            $table->text('posterior_segment')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('plan')->nullable();
            $table->string('referral_to', 100)->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('recorded_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_sheets');
    }
};
