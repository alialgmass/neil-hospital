<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The `status` column was created as enum('draft','posted','cancelled'),
     * but Modules\Inventory\Services\PurchaseInvoiceService::create() (and
     * Modules\Inventory\Enums\InvoiceStatus) actually writes 'paid'/'partial'/
     * 'unpaid' — values the original column rejects outright on strict
     * engines (SQLite CHECK constraint failure; MySQL enum truncation in
     * strict mode). Widen the column to accept every InvoiceStatus value
     * instead of narrowing the business logic.
     */
    public function up(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->string('status', 20)->default('draft')->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->enum('status', ['draft', 'posted', 'cancelled'])->default('draft')->change();
        });
    }
};
