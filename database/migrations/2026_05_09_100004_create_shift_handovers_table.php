<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_handovers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->foreignUlid('from_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignUlid('to_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('handover_date');
            $table->string('handover_time', 8)->nullable();
            $table->decimal('cash_amount', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_handovers');
    }
};
