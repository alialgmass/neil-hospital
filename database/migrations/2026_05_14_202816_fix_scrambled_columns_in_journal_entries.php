<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The previous fix migration (fix_source_column_in_treasury_and_journal_entries)
        // rebuilt the journal_entries table via CREATE TABLE + INSERT INTO ... SELECT *.
        // SQLite ignores ->after() on addColumn, so cost_center was at the END of the
        // original table, not after 'source'. The INSERT by position misaligned 4 columns:
        //   old column 9  (created_by)  → landed in new column 9  (cost_center)
        //   old column 10 (created_at)  → landed in new column 10 (created_by)
        //   old column 11 (updated_at)  → landed in new column 11 (created_at)
        //   old column 12 (cost_center) → landed in new column 12 (updated_at)
        // This UPDATE reverses that shift; all right-hand sides read original values.
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("
                UPDATE journal_entries SET
                    cost_center = updated_at,
                    created_by  = CAST(cost_center AS INTEGER),
                    updated_at  = created_at,
                    created_at  = created_by
            ");
        }
    }

    public function down(): void
    {
        // Re-apply the shift if rolling back
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("
                UPDATE journal_entries SET
                    updated_at  = cost_center,
                    cost_center = CAST(created_by AS TEXT),
                    created_by  = CAST(created_at AS INTEGER),
                    created_at  = updated_at
            ");
        }
    }
};
