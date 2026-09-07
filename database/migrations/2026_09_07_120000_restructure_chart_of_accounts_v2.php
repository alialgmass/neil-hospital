<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Renumber the chart of accounts to الدليل المحاسبي v2.0 (الإصدار 2.0 — أغسطس 2026).
 *
 * `journal_entries` reference accounts by ULID id, not by code, so relabelling
 * `accounts.code` in place preserves every historical entry and balance — no
 * amount is touched. `services.revenue_account_id` is likewise an id and is
 * unaffected by a code change.
 *
 * Order matters: codes whose numbers are being reused by a different account
 * are moved out of the way first (VAT 2030 → 2035, retina 4060 → 4065, the
 * fixed-asset shuffle) before the accounts that claim those numbers are moved.
 * This migration only relabels existing rows; run `db:seed --class=AccountsSeeder`
 * afterwards (deploy step) to upsert every account to its final name, nature,
 * parent and is_postable flag and to insert the accounts new in v2.0.
 */
return new class extends Migration
{
    /** @var array<int, array{0:string,1:string}> ordered [from, to] relabels */
    private const RELABELS = [
        ['2030', '2035'], // retire VAT payable (VAT automation stays out of scope)
        ['4060', '4065'], // retire standalone retina revenue (no equivalent in v2.0)
        ['1140', '1150'], // computers
        ['1130', '1140'], // furniture
        ['1110', '1130'], // medical equipment
        ['1120', '1131'], // accumulated depreciation — medical equipment
        ['1040', '1048'], // patient receivable
        ['1050', '1051'], // medical-supplies inventory (1050 becomes a non-postable group)
        ['2040', '2030'], // employee payable → net-salary payable (master)
        ['4090', '4060'], // pentacam revenue
        ['4230', '5115'], // doctor supply-cost recovery → contra-expense
    ];

    public function up(): void
    {
        foreach (self::RELABELS as [$from, $to]) {
            $this->relabel($from, $to);
        }

        // 4230 changes meaning from a revenue account to an expense contra-account.
        DB::table('accounts')->where('code', '5115')->update([
            'group' => 'expenses',
            'nature' => 'credit',
            'name' => '(-) استرداد تكلفة من الطبيب',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        foreach (array_reverse(self::RELABELS) as [$from, $to]) {
            $this->relabel($to, $from);
        }
    }

    private function relabel(string $from, string $to): void
    {
        $source = DB::table('accounts')->where('code', $from)->first();
        $targetExists = DB::table('accounts')->where('code', $to)->exists();

        if ($source && ! $targetExists) {
            DB::table('accounts')->where('code', $from)->update(['code' => $to, 'updated_at' => now()]);
        }
    }
};
