<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_collection_approvals', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('claim_id')->constrained('insurance_claims')->cascadeOnDelete();
            $table->foreignId('recorded_by')->constrained('users');
            $table->dateTime('recorded_at');
            $table->decimal('amount', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('net', 12, 2);
            $table->text('reason')->nullable();
            $table->string('why', 100);
            $table->string('status', 20)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamps();
            $table->index(['claim_id', 'status']);
        });

        Schema::create('insurance_collection_closures', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->date('collection_date')->unique();
            $table->foreignId('closed_by')->constrained('users');
            $table->dateTime('closed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_collection_closures');
        Schema::dropIfExists('insurance_collection_approvals');
    }
};
