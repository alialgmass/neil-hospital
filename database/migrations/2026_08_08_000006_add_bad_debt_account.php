<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * 5300 (Bad Debt Expense) is part of the chart per the accounting guide
     * but was missing from AccountsSeeder — needed by
     * AutoPostInsuranceClaimAction to write off insurance-claim collection
     * shortfalls.
     */
    public function up(): void
    {
        if (! DB::table('accounts')->where('code', '5300')->exists()) {
            DB::table('accounts')->insert([
                'id' => (string) Str::ulid(),
                'code' => '5300',
                'name' => 'مصروفات ديون معدومة',
                'group' => 'expenses',
                'nature' => 'debit',
                'parent_id' => null,
                'balance' => 0,
                'is_active' => true,
                'is_postable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('accounts')->where('code', '5300')->delete();
    }
};
