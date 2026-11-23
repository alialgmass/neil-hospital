<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Full snapshot of `accounts` and `journal_entries` taken before the
 * الدليل المحاسبي v2.0 alignment migrations touch any live data. The copies
 * are plain tables in the same database, so a restore is a straight
 * `INSERT ... SELECT` and needs no external dump. Kept until the finance
 * team signs off; dropped only on rollback of this migration.
 */
return new class extends Migration
{
    private const TABLES = [
        'accounts' => 'accounts_backup_guide_v2',
        'journal_entries' => 'journal_entries_backup_guide_v2',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $source => $backup) {
            if (Schema::hasTable($backup)) {
                continue;
            }

            DB::statement("CREATE TABLE {$backup} AS SELECT * FROM {$source}");
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $backup) {
            Schema::dropIfExists($backup);
        }
    }
};
