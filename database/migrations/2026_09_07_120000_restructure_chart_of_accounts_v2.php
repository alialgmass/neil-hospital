<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Accounting\Enums\AccountGroup;
use Modules\Accounting\Enums\AccountNature;

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
 * in the same deploy so every account gets its final name, nature, parent and
 * is_postable flag and the accounts new in v2.0 are inserted. Running the seeder
 * without this migration would strand the pre-v2 `1050` inventory balance on a
 * non-postable group account.
 */
return new class extends Migration
{
    /**
     * Ordered [from, to] code relabels. Reversed on down().
     *
     * @var array<int, array{0:string, 1:string}>
     */
    private const RELABELS = [
        ['2030', '2035'], // retire VAT payable (VAT automation stays out of scope)
        ['4060', '4065'], // retire standalone retina revenue (no equivalent in v2.0)
        ['4120', '4125'], // retire reserved "insurance revenue collected" (unused)
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

        // 4230 (a revenue account) becomes 5115, a credit-nature contra-expense.
        $this->reclassify('5115', AccountGroup::Expenses, AccountNature::Credit, '(-) استرداد تكلفة من الطبيب');
    }

    public function down(): void
    {
        // Undo the reclassify first — while the row still carries the new code.
        $this->reclassify('5115', AccountGroup::Revenues, AccountNature::Credit, 'استرداد تكلفة مستلزمات من الطبيب');

        foreach (array_reverse(self::RELABELS) as [$from, $to]) {
            $this->relabel($to, $from);
        }
    }

    private function relabel(string $from, string $to): void
    {
        $sourceExists = DB::table('accounts')->where('code', $from)->exists();
        $targetExists = DB::table('accounts')->where('code', $to)->exists();

        if (! $sourceExists) {
            return;
        }

        if ($targetExists) {
            Log::warning("chart_of_accounts_v2: skipped relabel {$from} → {$to} — target code already exists.");

            return;
        }

        DB::table('accounts')->where('code', $from)->update(['code' => $to, 'updated_at' => now()]);
    }

    private function reclassify(string $code, AccountGroup $group, AccountNature $nature, string $name): void
    {
        DB::table('accounts')->where('code', $code)->update([
            'group' => $group->value,
            'nature' => $nature->value,
            'name' => $name,
            'updated_at' => now(),
        ]);
    }
};
