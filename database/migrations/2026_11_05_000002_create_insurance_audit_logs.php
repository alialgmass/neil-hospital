<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_audit_logs', function (Blueprint $table): void {
            $table->bigIncrements('seq');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name', 150);
            $table->string('action', 100);
            $table->text('detail');
            $table->foreignUlid('claim_id')->nullable()->constrained('insurance_claims')->nullOnDelete();
            $table->char('prev_hash', 64)->default('');
            $table->char('hash', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->index('claim_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_audit_logs');
    }
};
