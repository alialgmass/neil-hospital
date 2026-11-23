<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Accounting\Enums\Subledger;
use Modules\Accounting\Services\GuideChart;

/**
 * Builds the complete chart of accounts of الدليل المحاسبي v2.0 from scratch
 * (or brings an existing one onto it): every guide account with its exact
 * code, name, nature and "لا يُرحّل" flag, the control accounts 2010 / 2020 /
 * 2030 with their named sub-ledgers (2201–2235, 2301–2324, 2401–2416), and the
 * "unidentified — for review" buckets. Idempotent — safe to re-run.
 *
 * Accounts outside the guide are deactivated when they never moved; ones
 * with history are left for the alignment migrations, which move their
 * balance to the guide successor (GuideChart::RETIRED) first.
 */
class AccountsSeeder extends Seeder
{
    public function run(): void
    {
        $codeToId = DB::table('accounts')->pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [(string) $code => $id])->all();

        foreach (GuideChart::accounts() as $code => $account) {
            $code = (string) $code;
            $parentId = $account['parent'] ? ($codeToId[$account['parent']] ?? null) : null;

            $attributes = [
                'name' => $account['name'],
                'group' => $account['group'],
                'nature' => $account['nature'],
                'parent_id' => $parentId,
                'is_active' => true,
                'is_postable' => $account['postable'],
                'updated_at' => now(),
            ];

            if (isset($codeToId[$code])) {
                DB::table('accounts')->where('id', $codeToId[$code])->update($attributes);

                continue;
            }

            $id = (string) Str::ulid();
            DB::table('accounts')->insert([...$attributes, 'id' => $id, 'code' => $code, 'balance' => 0, 'created_at' => now()]);
            $codeToId[$code] = $id;
        }

        $this->retireUnusedOutOfGuideAccounts();
    }

    /**
     * Any account outside the guide (e.g. left behind by an older chart)
     * that never moved is deactivated. Party sub-ledgers created on demand
     * (2236+, 2325+, 2417+) belong to the guide's ranges and are kept.
     */
    private function retireUnusedOutOfGuideAccounts(): void
    {
        $guideCodes = GuideChart::codes();
        $controlIds = DB::table('accounts')->whereIn('code', array_map(fn (Subledger $s) => $s->masterCode()->value, Subledger::cases()))->pluck('id')->all();

        $outside = DB::table('accounts')
            ->whereNotIn('code', $guideCodes)
            ->where(fn ($q) => $q->whereNull('parent_id')->orWhereNotIn('parent_id', $controlIds ?: ['__none__']))
            ->pluck('id');

        foreach ($outside as $id) {
            $hasHistory = DB::table('journal_entries')
                ->where(fn ($q) => $q->where('debit_account_id', $id)->orWhere('credit_account_id', $id))
                ->exists();

            if (! $hasHistory) {
                DB::table('accounts')->where('id', $id)->update(['is_active' => false, 'is_postable' => false, 'updated_at' => now()]);
            }
        }
    }
}
