<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_cash_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->string('patient_id', 26)->nullable();
            $table->foreignUlid('insurance_company_id')->nullable()->constrained('insurance_companies')->nullOnDelete();
            $table->string('description', 200);
            $table->string('department', 50);
            $table->decimal('amount', 12, 2);
            $table->date('entry_date');
            $table->string('status', 20)->default('due');
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('collected_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_cash_entries');
    }
};
