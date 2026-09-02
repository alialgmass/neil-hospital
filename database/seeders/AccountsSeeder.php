<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Accounting\Enums\AccountCode;

class AccountsSeeder extends Seeder
{
    /**
     * Full chart of accounts per the Al-Nour accounting guide.
     * Format: code => [name, group, nature, parentCode|null]
     */
    private const ACCOUNTS = [
        // ── ASSETS ─────────────────────────────────────────────────
        '1000' => ['الأصول المتداولة',                       'assets',      'debit',  null],
        '1010' => ['الخزنة الرئيسية',                        'assets',      'debit',  '1000'],
        '1020' => ['البنك — الحساب الجاري',                   'assets',      'debit',  '1000'],
        '1030' => ['ذمم مدينة — تأمين صحي',                  'assets',      'debit',  '1000'],
        '1040' => ['ذمم مدينة — مرضى (آجل)',                 'assets',      'debit',  '1000'],
        '1050' => ['المخزون (المستلزمات الطبية)',              'assets',      'debit',  '1000'],
        '1060' => ['مصروفات مقدمة',                          'assets',      'debit',  '1000'],
        '1100' => ['الأصول الثابتة',                         'assets',      'debit',  null],
        '1110' => ['الأجهزة والمعدات الطبية',                 'assets',      'debit',  '1100'],
        '1120' => ['م. مجمعة — الأجهزة الطبية',              'assets',      'credit', '1100'],
        '1130' => ['التجهيزات والأثاث',                      'assets',      'debit',  '1100'],
        '1140' => ['أجهزة حاسوب وأنظمة',                    'assets',      'debit',  '1100'],

        // ── LIABILITIES ────────────────────────────────────────────
        '2000' => ['الخصوم المتداولة',                       'liabilities', 'credit', null],
        '2010' => ['مستحقات الأطباء',                        'liabilities', 'credit', '2000'],
        '2020' => ['الموردون (مستحقات مشتريات)',              'liabilities', 'credit', '2000'],
        '2030' => ['ضريبة القيمة المضافة المستحقة',          'liabilities', 'credit', '2000'],
        '2040' => ['مستحقات موظفين (رواتب)',                  'liabilities', 'credit', '2000'],
        '2050' => ['أمانات — دفعات تأمين مُحصَّلة',           'liabilities', 'credit', '2000'],
        '2100' => ['خصوم طويلة الأجل',                       'liabilities', 'credit', null],
        '2110' => ['قروض بنكية طويلة الأجل',                 'liabilities', 'credit', '2100'],

        // ── EQUITY ─────────────────────────────────────────────────
        '3010' => ['رأس المال',                              'equity',      'credit', null],
        '3020' => ['الأرباح المُبقاة',                        'equity',      'credit', null],
        '3030' => ['صافي الربح السنوي',                      'equity',      'credit', null],

        // ── REVENUES ───────────────────────────────────────────────
        '4000' => ['الإيرادات التشغيلية الرئيسية',            'revenues',    'credit', null],
        '4010' => ['إيرادات العيادة الخارجية (كشف)',          'revenues',    'credit', '4000'],
        '4020' => ['إيرادات الفحوصات والمختبر',               'revenues',    'credit', '4000'],
        '4030' => ['إيرادات غرفة العمليات الجراحية',          'revenues',    'credit', '4000'],
        '4040' => ['إيرادات وحدة الليزك',                    'revenues',    'credit', '4000'],
        '4050' => ['إيرادات الليزر التشخيصي/العلاجي',        'revenues',    'credit', '4000'],
        '4100' => ['إيرادات التأمين',                        'revenues',    'credit', null],
        '4110' => ['إيرادات التأمين الصحي — حصة المستشفى',   'revenues',    'credit', '4100'],
        '4120' => ['إيرادات التأمين المحصلة فعلياً',          'revenues',    'credit', '4100'],
        '4060' => ['إيرادات الشبكية',                        'revenues',    'credit', '4000'],
        '4070' => ['إيرادات التأمين الصحي (تخصيص خدمة)',    'revenues',    'credit', '4000'],
        '4080' => ['إيرادات الأدوية والصيدلية',               'revenues',    'credit', '4000'],
        '4090' => ['إيرادات وحدة البنتكام',                  'revenues',    'credit', '4000'],
        '4200' => ['إيرادات أخرى',                           'revenues',    'credit', null],
        '4210' => ['إيرادات بيع مستلزمات للمرضى',            'revenues',    'credit', '4200'],
        '4220' => ['إيرادات متنوعة',                         'revenues',    'credit', '4200'],
        '4230' => ['استرداد تكلفة مستلزمات من الطبيب',        'revenues',    'credit', '4200'],

        // ── EXPENSES ───────────────────────────────────────────────
        '5000' => ['تكاليف الخدمات الطبية المباشرة',          'expenses',    'debit',  null],
        '5010' => ['تكلفة مستلزمات العمليات الجراحية',        'expenses',    'debit',  '5000'],
        '5020' => ['تكلفة مستلزمات وحدة الليزك',              'expenses',    'debit',  '5000'],
        '5030' => ['تكلفة الأدوية والقطرات',                  'expenses',    'debit',  '5000'],
        '5100' => ['مستحقات الأطباء (مصروفات)',               'expenses',    'debit',  null],
        '5110' => ['نصيب الطبيب — العيادة والكشف',            'expenses',    'debit',  '5100'],
        '5120' => ['نصيب الطبيب — العمليات الجراحية',         'expenses',    'debit',  '5100'],
        '5130' => ['أتعاب طبيب التأمين (مبلغ ثابت/حالة)',    'expenses',    'debit',  '5100'],
        '5200' => ['المصروفات التشغيلية',                     'expenses',    'debit',  null],
        '5210' => ['رواتب وأجور الموظفين',                    'expenses',    'debit',  '5200'],
        '5220' => ['الإيجار',                                'expenses',    'debit',  '5200'],
        '5230' => ['الكهرباء والمياه والاتصالات',             'expenses',    'debit',  '5200'],
        '5240' => ['مصروفات الصيانة',                        'expenses',    'debit',  '5200'],
        '5250' => ['مصروفات إدارية وتسويقية',                 'expenses',    'debit',  '5200'],
        '5260' => ['استهلاك الأصول الثابتة',                  'expenses',    'debit',  '5200'],
        '5270' => ['مصروفات المشتريات الآجلة',                'expenses',    'debit',  '5200'],
        '5300' => ['مصروفات ديون معدومة',                     'expenses',    'debit',  null],
    ];

    public function run(): void
    {
        $codeToId = [];
        $nonPostable = AccountCode::nonPostableCodes();

        foreach (self::ACCOUNTS as $code => [$name, $group, $nature, $parentCode]) {
            // PHP silently casts all-digit string array keys (e.g. '1000') to
            // integers — cast back before comparing against AccountCode's
            // string values, or the strict in_array() below always misses.
            $code = (string) $code;

            $parentId = $parentCode
                ? ($codeToId[$parentCode] ?? DB::table('accounts')->where('code', $parentCode)->value('id'))
                : null;

            $isPostable = ! in_array($code, $nonPostable, true);

            $existing = DB::table('accounts')->where('code', $code)->first();

            if ($existing) {
                DB::table('accounts')->where('code', $code)->update([
                    'name' => $name,
                    'group' => $group,
                    'nature' => $nature,
                    'parent_id' => $parentId,
                    'is_active' => true,
                    'is_postable' => $isPostable,
                    'updated_at' => now(),
                ]);
                $codeToId[$code] = $existing->id;
            } else {
                $id = (string) Str::ulid();
                DB::table('accounts')->insert([
                    'id' => $id,
                    'code' => $code,
                    'name' => $name,
                    'group' => $group,
                    'nature' => $nature,
                    'parent_id' => $parentId,
                    'balance' => 0,
                    'is_active' => true,
                    'is_postable' => $isPostable,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $codeToId[$code] = $id;
            }
        }
    }
}
