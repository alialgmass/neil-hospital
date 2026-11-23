<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allocation rules let a shared expense (rent, admin salaries, electricity,
 * ...) be split across cost centers by administrator-set percentages.
 *
 * This migration only creates the structure — no rule/target rows are
 * seeded, and no posting action reads from these tables yet. The manual
 * explicitly says: if no percentages exist in the system, add the
 * structure only, don't invent them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allocation_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 150);
            $table->foreignUlid('source_account_id')->constrained('accounts')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allocation_rules');
    }
};
