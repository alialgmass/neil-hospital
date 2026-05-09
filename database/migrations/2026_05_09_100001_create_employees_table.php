<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('employee_no', 20)->unique();
            $table->string('name', 150);
            $table->string('national_id', 20)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('dept', 50);
            $table->string('position', 100);
            $table->date('hire_date');
            $table->decimal('base_salary', 10, 2)->default(0);
            $table->decimal('allowances', 10, 2)->default(0);
            $table->string('contract_type', 20)->default('full_time');
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
