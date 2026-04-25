<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountsSeeder extends Seeder
{
    /** Default chart of accounts from spec §5.9. */
    private const ACCOUNTS = [
        // code  => [name, group, nature]
        '1000' => ['الصندوق النقدي',         'assets',      'debit'],
        '1100' => ['البنك',                   'assets',      'debit'],
        '1200' => ['ذمم التأمين',             'assets',      'debit'],
        '2000' => ['إيرادات العيادة',          'revenues',    'credit'],
        '2100' => ['إيرادات الفحوصات',         'revenues',    'credit'],
        '2200' => ['إيرادات العمليات',         'revenues',    'credit'],
        '2300' => ['إيرادات الليزك',           'revenues',    'credit'],
        '2400' => ['إيرادات الليزر',           'revenues',    'credit'],
        '3000' => ['مستلزمات العمليات',        'expenses',    'debit'],
        '3100' => ['مستحقات الأطباء',          'expenses',    'debit'],
        '3200' => ['رواتب الموظفين',           'expenses',    'debit'],
        '3300' => ['مصروفات تشغيلية',          'expenses',    'debit'],
        '4000' => ['رأس المال',               'equity',      'credit'],
    ];

    public function run(): void
    {
        foreach (self::ACCOUNTS as $code => [$name, $group, $nature]) {
            DB::table('accounts')->updateOrInsert(
                ['code' => $code],
                [
                    'id' => (string) Str::ulid(),
                    'code' => $code,
                    'name' => $name,
                    'group' => $group,
                    'nature' => $nature,
                    'parent_id' => null,
                    'balance' => 0,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }
}
