<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->string('idempotency_key', 150)->nullable()->unique()->after('reference');
            $table->ulid('reversal_of_id')->nullable()->after('idempotency_key');
            $table->timestamp('reversed_at')->nullable()->after('reversal_of_id');

            $table->foreign('reversal_of_id')->references('id')->on('journal_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropForeign(['reversal_of_id']);
            $table->dropColumn(['idempotency_key', 'reversal_of_id', 'reversed_at']);
        });
    }
};
