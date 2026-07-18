<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');

            // treasury_entries: rebuild with string source
            DB::statement('CREATE TABLE treasury_entries_new AS SELECT * FROM treasury_entries');
            DB::statement('DROP TABLE treasury_entries');
            DB::statement("
                CREATE TABLE treasury_entries (
                    id TEXT NOT NULL PRIMARY KEY,
                    type TEXT NOT NULL CHECK(type IN ('in','out')),
                    description TEXT NOT NULL,
                    amount NUMERIC(12,2) NOT NULL,
                    date TEXT NOT NULL,
                    reference_no TEXT,
                    beneficiary TEXT,
                    account_id TEXT,
                    source TEXT NOT NULL DEFAULT 'manual',
                    booking_id TEXT,
                    created_by INTEGER,
                    created_at TEXT,
                    updated_at TEXT,
                    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL,
                    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
                    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
                )
            ");
            DB::statement('INSERT INTO treasury_entries SELECT * FROM treasury_entries_new');
            DB::statement('DROP TABLE treasury_entries_new');

            // journal_entries: rebuild with string source
            DB::statement('CREATE TABLE journal_entries_new AS SELECT * FROM journal_entries');
            DB::statement('DROP TABLE journal_entries');
            DB::statement("
                CREATE TABLE journal_entries (
                    id TEXT NOT NULL PRIMARY KEY,
                    date TEXT NOT NULL,
                    description TEXT NOT NULL,
                    debit_account_id TEXT NOT NULL,
                    credit_account_id TEXT NOT NULL,
                    amount NUMERIC(12,2) NOT NULL,
                    reference TEXT,
                    source TEXT NOT NULL DEFAULT 'manual',
                    cost_center TEXT,
                    created_by INTEGER,
                    created_at TEXT,
                    updated_at TEXT,
                    FOREIGN KEY (debit_account_id) REFERENCES accounts(id) ON DELETE RESTRICT,
                    FOREIGN KEY (credit_account_id) REFERENCES accounts(id) ON DELETE RESTRICT,
                    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
                )
            ");
            DB::statement('INSERT INTO journal_entries SELECT * FROM journal_entries_new');
            DB::statement('DROP TABLE journal_entries_new');

            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            Schema::table('treasury_entries', function (Blueprint $table) {
                $table->string('source', 50)->default('manual')->change();
            });

            Schema::table('journal_entries', function (Blueprint $table) {
                $table->string('source', 50)->default('manual')->change();
            });
        }
    }

    public function down(): void
    {
        // Intentionally left empty — reverting to a narrow enum would break existing data
    }
};
