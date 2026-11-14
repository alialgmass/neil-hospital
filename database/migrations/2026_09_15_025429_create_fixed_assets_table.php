<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 150);
            $table->string('asset_class', 30); // Modules\Accounting\Enums\AssetClass
            $table->decimal('cost', 12, 2); // original cost — never modified by depreciation
            $table->unsignedInteger('useful_life_months');
            $table->decimal('accumulated_depreciation', 12, 2)->default(0);
            $table->date('acquired_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
