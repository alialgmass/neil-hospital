<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 200);
            $table->enum('dept', ['clinic', 'labs', 'surgery', 'lasik', 'laser']);
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('ins_price', 10, 2)->default(0);
            $table->enum('center_type', ['pct', 'fixed'])->default('pct');
            $table->decimal('center_val', 10, 2)->default(0);
            $table->decimal('center_share', 10, 2)->default(0);
            $table->decimal('dr_share', 10, 2)->default(0);
            $table->unsignedSmallInteger('duration_mins')->default(30);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
