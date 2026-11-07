<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_deposits', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignUlid('insurance_company_id')->constrained('insurance_companies')->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->date('paid_on');
            $table->string('decision', 20)->default('waiting');
            $table->boolean('refunded')->default(false);
            $table->boolean('converted')->default(false);
            $table->foreignUlid('claim_id')->nullable()->constrained('insurance_claims')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_deposits');
    }
};
