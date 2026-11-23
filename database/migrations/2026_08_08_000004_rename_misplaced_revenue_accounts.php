<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * 2500/2600/2700 were inserted (by 2026_04_25_192359_add_revenue_account_to_services_table)
     * as revenue accounts numbered inside the 2xxx liabilities block. Renumber
     * them into the 4xxx revenue range without touching their row ids, so any
     * existing services.revenue_account_id FK stays intact.
     */
    private const RENAMES = [
        '2500' => '4060', // إيرادات الشبكية
        '2600' => '4070', // إيرادات التأمين الصحي (تخصيص خدمة)
        '2700' => '4080', // إيرادات الأدوية والصيدلية
    ];

    public function up(): void
    {
        foreach (self::RENAMES as $old => $new) {
            DB::table('accounts')->where('code', $old)->update(['code' => $new, 'updated_at' => now()]);
        }

        // Doctor supply-cost recovery account — used by ProcessBundleSupplyAction
        // instead of sharing 4210 (Supplies Sales Revenue) with genuine patient sales.
        if (! DB::table('accounts')->where('code', '4230')->exists()) {
            $otherRevenueParentId = DB::table('accounts')->where('code', '4200')->value('id');

            DB::table('accounts')->insert([
                'id' => (string) Str::ulid(),
                'code' => '4230',
                'name' => 'استرداد تكلفة مستلزمات من الطبيب',
                'group' => 'revenues',
                'nature' => 'credit',
                'parent_id' => $otherRevenueParentId,
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
        foreach (self::RENAMES as $old => $new) {
            DB::table('accounts')->where('code', $new)->update(['code' => $old, 'updated_at' => now()]);
        }

        DB::table('accounts')->where('code', '4230')->delete();
    }
};
