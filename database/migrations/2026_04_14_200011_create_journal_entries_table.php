<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->date('date');
            $table->string('description', 300);
            $table->ulid('debit_account_id');
            $table->ulid('credit_account_id');
            $table->decimal('amount', 12, 2);
            $table->string('reference', 80)->nullable();
            $table->string('source', 30)->default('manual');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('debit_account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->foreign('credit_account_id')->references('id')->on('accounts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
