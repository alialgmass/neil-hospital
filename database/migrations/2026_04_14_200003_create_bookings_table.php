<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('file_no', 20)->unique();                    // MRN-2026-00001
            $table->string('patient_name', 150);
            $table->string('patient_phone', 20)->nullable();
            $table->unsignedTinyInteger('patient_age')->nullable();
            $table->string('national_id', 20)->nullable();
            $table->string('gender', 10)->nullable();
            $table->enum('dept', ['clinic', 'labs', 'surgery', 'lasik', 'laser']);
            $table->string('service_name', 200)->nullable();            // snapshot at booking time
            $table->foreignUlid('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignUlid('doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->foreignUlid('ins_company_id')->nullable()->constrained('insurance_companies')->nullOnDelete();
            $table->date('visit_date');
            $table->time('visit_time')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('ins_amount', 10, 2)->default(0);           // amount covered by insurance
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->enum('pay_method', ['cash', 'card', 'transfer', 'insurance'])->default('cash');
            $table->enum('pay_status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            $table->enum('status', ['waiting', 'confirmed', 'in_progress', 'completed', 'cancelled'])->default('waiting');
            $table->string('cancel_reason', 300)->nullable();
            $table->text('visit_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['visit_date', 'dept']);
            $table->index(['doctor_id', 'visit_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
