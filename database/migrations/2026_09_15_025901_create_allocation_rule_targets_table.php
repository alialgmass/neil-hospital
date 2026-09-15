<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allocation_rule_targets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('allocation_rule_id')->constrained('allocation_rules')->cascadeOnDelete();
            $table->string('cost_center', 20); // Modules\Accounting\Enums\CostCenter
            $table->decimal('percentage', 5, 2);
            $table->timestamps();

            $table->unique(['allocation_rule_id', 'cost_center']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allocation_rule_targets');
    }
};
