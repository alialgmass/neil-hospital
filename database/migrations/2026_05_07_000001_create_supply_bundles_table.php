<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supply_bundles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 200);
            $table->string('code', 40)->nullable()->unique();
            $table->string('dept', 20)->nullable(); // surgery|lasik|laser|null=all
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('supply_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('bundle_id')->constrained('supply_bundles')->cascadeOnDelete();
            $table->foreignUlid('inventory_item_id')->nullable()->constrained('inventory')->nullOnDelete();
            $table->string('item_name', 200);
            $table->decimal('qty', 10, 2);
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supply_bundle_items');
        Schema::dropIfExists('supply_bundles');
    }
};
