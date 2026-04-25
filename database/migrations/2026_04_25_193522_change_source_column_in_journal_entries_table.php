<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Convert the source column from a restrictive enum to a plain string
        // so that all JournalSource enum values are accepted.
        // SQLite (test env) rebuilds from migrations so no change is needed there.
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE journal_entries MODIFY source VARCHAR(30) NOT NULL DEFAULT 'manual'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE journal_entries MODIFY source ENUM('manual','auto_booking','auto_payment') NOT NULL DEFAULT 'manual'");
        }
    }
};
