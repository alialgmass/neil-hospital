<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema support for aligning the ledger with الدليل المحاسبي v2.0:
 *
 * - `payable_account_id` on doctors / suppliers / employees links each party
 *   to its own sub-ledger account (2201–2299 / 2301–2399 / 2401–2499).
 * - `needs_review` / `review_note` on journal_entries flag historical lines
 *   whose owner could not be traced and were parked on an "unidentified"
 *   sub-ledger for manual review.
 * - `accounting_alignment_log` records every change the data migrations
 *   make (old → new value, or a created row) so each one can be undone
 *   exactly on rollback.
 */
return new class extends Migration
{
    /** @var array<string, string> table => FK constraint name (explicit: MySQL 64-char limit) */
    private const PARTY_TABLES = [
        'doctors' => 'doctors_payable_account_fk',
        'suppliers' => 'suppliers_payable_account_fk',
        'employees' => 'employees_payable_account_fk',
    ];

    public function up(): void
    {
        foreach (self::PARTY_TABLES as $table => $foreignKey) {
            Schema::table($table, function (Blueprint $blueprint) use ($foreignKey) {
                $blueprint->char('payable_account_id', 26)->nullable()->unique();
                $blueprint->foreign('payable_account_id', $foreignKey)->references('id')->on('accounts')->nullOnDelete();
            });
        }

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->boolean('needs_review')->default(false)->index();
            $table->string('review_note', 255)->nullable();
        });

        Schema::create('accounting_alignment_log', function (Blueprint $table) {
            $table->id();
            $table->string('batch', 60)->index();
            $table->string('table_name', 60);
            $table->string('record_id', 60);
            $table->string('column_name', 60)->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            // 'update' = column changed, 'insert' = row created by the migration
            $table->string('operation', 10)->default('update');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_alignment_log');

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex(['needs_review']);
            $table->dropColumn(['needs_review', 'review_note']);
        });

        foreach (self::PARTY_TABLES as $table => $foreignKey) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $foreignKey) {
                $blueprint->dropForeign($foreignKey);
                $blueprint->dropUnique("{$table}_payable_account_id_unique");
                $blueprint->dropColumn('payable_account_id');
            });
        }
    }
};
