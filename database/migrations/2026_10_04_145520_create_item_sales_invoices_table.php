<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_sales_invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('invoice_no', 50)->unique();
            $table->date('invoice_date');
            $table->string('customer_name', 200);
            $table->string('customer_phone', 30)->nullable();
            $table->string('file_no', 50)->nullable();
            $table->string('department', 80)->nullable();
            $table->string('pay_method', 20);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('cost_total', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('invoice_date');
        });

        Schema::create('item_sales_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->ulid('invoice_id');
            $table->ulid('item_id')->nullable();
            $table->string('item_name', 200);
            $table->decimal('qty', 10, 2);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();

            $table->foreign('invoice_id')->references('id')->on('item_sales_invoices')->cascadeOnDelete();
            $table->foreign('item_id')->references('id')->on('inventory')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_sales_invoice_items');
        Schema::dropIfExists('item_sales_invoices');
    }
};
