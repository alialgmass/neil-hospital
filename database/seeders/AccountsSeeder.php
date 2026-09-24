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
        // Numbering follows الدليل المحاسبي v2.0 (الإصدار 2.0 — أغسطس 2026).
        '1000' => ['الأصول المتداولة',                       'assets',      'debit',  null],
        '1010' => ['الخزنة الرئيسية',                        'assets',      'debit',  '1000'],
        '1011' => ['خزنة التطوير — الاستقبال',               'assets',      'debit',  '1000'],
        '1020' => ['البنك — الحساب الجاري',                   'assets',      'debit',  '1000'],
        '1030' => ['ذمم مدينة — تأمين وجهات التعاقد (مجمع)', 'assets',      'debit',  '1000'],
        '1031' => ['ذمم تأمين — نفقة الدولة',                'assets',      'debit',  '1030'],
        '1032' => ['ذمم تأمين — التأمين الصحي',              'assets',      'debit',  '1030'],
        '1033' => ['ذمم تأمين — بنك الشفاء المصري',          'assets',      'debit',  '1030'],
        '1034' => ['ذمم تأمين — الأهلي للخدمات الطبية',      'assets',      'debit',  '1030'],
        '1035' => ['ذمم تأمين — جلوب ميد',                   'assets',      'debit',  '1030'],
        '1036' => ['ذمم تأمين — ايجي كير',                   'assets',      'debit',  '1030'],
        '1037' => ['ذمم تأمين — نقابة المحامين',              'assets',      'debit',  '1030'],
        '1038' => ['ذمم تأمين — ميد شور',                    'assets',      'debit',  '1030'],
        '1039' => ['ذمم تأمين — ميد نت',                     'assets',      'debit',  '1030'],
        '1040' => ['ذمم تأمين — ميد رايت',                   'assets',      'debit',  '1030'],
        '1041' => ['ذمم تأمين — صحة وان',                    'assets',      'debit',  '1030'],
        '1042' => ['ذمم تأمين — نقابة المعلمين',              'assets',      'debit',  '1030'],
        '1043' => ['ذمم تأمين — مصر للتأمين',                'assets',      'debit',  '1030'],
        '1044' => ['ذمم تأمين — ميد جولد',                   'assets',      'debit',  '1030'],
        '1045' => ['ذمم تأمين — نقابة الأطباء',               'assets',      'debit',  '1030'],
        '1046' => ['ذمم تأمين — وادي النيل',                 'assets',      'debit',  '1030'],
        '1047' => ['ذمم تأمين — جمعية رضى الخير',             'assets',      'debit',  '1030'],
        '1048' => ['ذمم مدينة — مرضى (آجل)',                 'assets',      'debit',  '1000'],
        '1049' => ['ذمم مدينة — سلف موظفين',                 'assets',      'debit',  '1000'],
        '1050' => ['المخزون (مجمع)',                         'assets',      'debit',  '1000'],
        '1051' => ['مخزون — مستلزمات طبية',                  'assets',      'debit',  '1050'],
        '1052' => ['مخزون — أدوية وقطرات',                   'assets',      'debit',  '1050'],
        '1053' => ['مخزون — مستلزمات تشغيلية',               'assets',      'debit',  '1050'],
        '1060' => ['مصروفات مقدمة',                          'assets',      'debit',  '1000'],
        '1080' => ['ضريبة دخل مخصومة من المنبع',             'assets',      'debit',  '1000'],
        '1090' => ['نقدية مركز — العيادة',                   'assets',      'debit',  '1000'],
        '1091' => ['نقدية مركز — الفحوصات',                  'assets',      'debit',  '1000'],
        '1092' => ['نقدية مركز — البنتاكام',                 'assets',      'debit',  '1000'],
        '1093' => ['نقدية مركز — الليزر',                    'assets',      'debit',  '1000'],
        '1094' => ['نقدية مركز — الليزك',                    'assets',      'debit',  '1000'],
        '1095' => ['نقدية مركز — الجراحة',                   'assets',      'debit',  '1000'],
        '1096' => ['نقدية مركز — التأمين',                   'assets',      'debit',  '1000'],
        '1100' => ['الأصول الثابتة',                         'assets',      'debit',  null],
        '1110' => ['أراضي',                                  'assets',      'debit',  '1100'],
        '1120' => ['مباني وإنشاءات',                         'assets',      'debit',  '1100'],
        '1121' => ['(-) مجمع إهلاك المباني',                 'assets',      'credit', '1100'],
        '1130' => ['أجهزة طبية',                             'assets',      'debit',  '1100'],
        '1131' => ['(-) مجمع إهلاك أجهزة طبية',              'assets',      'credit', '1100'],
        '1140' => ['أثاث وتجهيزات',                          'assets',      'debit',  '1100'],
        '1141' => ['(-) مجمع إهلاك أثاث',                    'assets',      'credit', '1100'],
        '1150' => ['حاسبات وأجهزة مكتبية',                   'assets',      'debit',  '1100'],
        '1151' => ['(-) مجمع إهلاك حاسبات',                  'assets',      'credit', '1100'],
        '1160' => ['سيارات',                                 'assets',      'debit',  '1100'],
        '1161' => ['(-) مجمع إهلاك سيارات',                  'assets',      'credit', '1100'],

        // ── LIABILITIES ────────────────────────────────────────────
        '2000' => ['الخصوم المتداولة',                       'liabilities', 'credit', null],
        '2010' => ['دائنون — أطباء (رئيسي)',                 'liabilities', 'credit', '2000'],
        '2020' => ['دائنون — موردون (رئيسي)',                'liabilities', 'credit', '2000'],
        '2030' => ['مستحقات موظفين — رواتب صافية (رئيسي)',   'liabilities', 'credit', '2000'],
        '2041' => ['التأمينات الاجتماعية المستحقة',          'liabilities', 'credit', '2000'],
        '2050' => ['أمانات — دفعات مقدمة من المرضى',         'liabilities', 'credit', '2000'],
        '2060' => ['مصروفات مستحقة الدفع',                   'liabilities', 'credit', '2000'],
        '2070' => ['ضريبة الدخل المستحقة (نهاية السنة)',      'liabilities', 'credit', '2000'],
        '2100' => ['خصوم طويلة الأجل',                       'liabilities', 'credit', null],
        '2110' => ['قروض بنكية طويلة الأجل',                 'liabilities', 'credit', '2100'],

        // ── EQUITY ─────────────────────────────────────────────────
        '3010' => ['رأس المال',                              'equity',      'credit', null],
        '3020' => ['الأرباح المُبقاة',                        'equity',      'credit', null],
        '3030' => ['صافي الربح/الخسارة — السنة الحالية',     'equity',      'credit', null],
        '3040' => ['مسحوبات الملاك',                          'equity',      'debit',  null],

        // ── REVENUES ───────────────────────────────────────────────
        '4000' => ['الإيرادات التشغيلية الرئيسية',            'revenues',    'credit', null],
        '4010' => ['إيرادات العيادة الخارجية',                'revenues',    'credit', '4000'],
        '4020' => ['إيرادات الفحوصات والمختبر',               'revenues',    'credit', '4000'],
        '4030' => ['إيرادات العمليات الجراحية',               'revenues',    'credit', '4000'],
        '4040' => ['إيرادات وحدة الليزك',                    'revenues',    'credit', '4000'],
        '4050' => ['إيرادات الليزر التشخيصي/العلاجي',        'revenues',    'credit', '4000'],
        '4060' => ['إيرادات وحدة البنتاكام',                 'revenues',    'credit', '4000'],
        '4070' => ['إيراد بيع مستهلكات للأطباء',             'revenues',    'credit', '4000'],
        '4080' => ['إيرادات الأدوية والصيدلية',               'revenues',    'credit', '4000'],
        '4100' => ['إيرادات التأمين',                        'revenues',    'credit', null],
        '4110' => ['إيرادات تأمين — عيادة',                  'revenues',    'credit', '4100'],
        '4120' => ['إيرادات تأمين — فحوصات ومختبر',          'revenues',    'credit', '4100'],
        '4130' => ['إيرادات تأمين — جراحة',                  'revenues',    'credit', '4100'],
        '4140' => ['إيرادات تأمين — ليزك',                   'revenues',    'credit', '4100'],
        '4150' => ['إيرادات تأمين — ليزر',                   'revenues',    'credit', '4100'],
        '4200' => ['إيرادات أخرى',                           'revenues',    'credit', null],
        '4210' => ['إيرادات بيع مستلزمات للمرضى',            'revenues',    'credit', '4200'],
        '4220' => ['إيرادات متنوعة',                         'revenues',    'credit', '4200'],
        '4250' => ['أرباح بيع أصول ثابتة',                   'revenues',    'credit', '4200'],
        '4900' => ['تخفيضات الإيراد (مجمع)',                 'revenues',    'debit',  null],
        '4910' => ['خصومات ممنوحة للمرضى',                   'revenues',    'debit',  '4900'],
        '4920' => ['مرتجعات إيرادات',                        'revenues',    'debit',  '4900'],

        // ── EXPENSES ───────────────────────────────────────────────
        '5000' => ['تكلفة الخدمات المباشرة',                 'expenses',    'debit',  null],
        '5010' => ['تكلفة مستلزمات العمليات الجراحية',        'expenses',    'debit',  '5000'],
        '5020' => ['تكلفة مستلزمات وحدة الليزك',              'expenses',    'debit',  '5000'],
        '5030' => ['تكلفة أدوية وقطرات',                     'expenses',    'debit',  '5000'],
        '5040' => ['تكلفة مستلزمات فحوصات ومختبر',           'expenses',    'debit',  '5000'],
        '5100' => ['أتعاب الأطباء (مجمع)',                   'expenses',    'debit',  null],
        '5110' => ['أتعاب أطباء — عيادة/مختبر/ليزر',         'expenses',    'debit',  '5100'],
        '5115' => ['(-) استرداد تكلفة من الطبيب',            'expenses',    'credit', '5100'],
        '5120' => ['أتعاب أطباء — جراحة/ليزك',               'expenses',    'debit',  '5100'],
        '5130' => ['أتعاب أطباء — حالات التأمين',            'expenses',    'debit',  '5100'],
        '5140' => ['أتعاب أطباء زائرين/استشاريين',           'expenses',    'debit',  '5100'],
        '5200' => ['المصروفات التشغيلية',                     'expenses',    'debit',  null],
        '5210' => ['رواتب وأجور (Gross)',                    'expenses',    'debit',  '5200'],
        '5211' => ['حصة المستشفى في التأمينات الاجتماعية',   'expenses',    'debit',  '5200'],
        '5212' => ['مكافآت وحوافز',                          'expenses',    'debit',  '5200'],
        '5213' => ['بدلات (انتقال/إعاشة)',                   'expenses',    'debit',  '5200'],
        '5214' => ['تدريب وتطوير',                           'expenses',    'debit',  '5200'],
        '5220' => ['إيجار',                                  'expenses',    'debit',  '5200'],
        '5225' => ['صيانة مباني',                            'expenses',    'debit',  '5200'],
        '5230' => ['كهرباء ومياه',                           'expenses',    'debit',  '5200'],
        '5231' => ['اتصالات وإنترنت',                        'expenses',    'debit',  '5200'],
        '5240' => ['صيانة أجهزة طبية',                       'expenses',    'debit',  '5200'],
        '5241' => ['صيانة أجهزة مكتبية',                     'expenses',    'debit',  '5200'],
        '5250' => ['مصروفات إدارية ومكتبية',                 'expenses',    'debit',  '5200'],
        '5251' => ['مصروفات نظافة',                          'expenses',    'debit',  '5200'],
        '5252' => ['مصروفات تسويق وإعلان',                   'expenses',    'debit',  '5200'],
        '5260' => ['استهلاك أصول ثابتة (مجمع)',              'expenses',    'debit',  '5200'],
        '5261' => ['استهلاك مباني',                          'expenses',    'debit',  '5260'],
        '5262' => ['استهلاك أجهزة طبية',                     'expenses',    'debit',  '5260'],
        '5263' => ['استهلاك أثاث',                           'expenses',    'debit',  '5260'],
        '5264' => ['استهلاك حاسبات',                         'expenses',    'debit',  '5260'],
        '5265' => ['استهلاك سيارات',                         'expenses',    'debit',  '5260'],
        '5270' => ['مصروفات نقل ومواصلات',                   'expenses',    'debit',  '5200'],
        '5300' => ['ديون معدومة',                            'expenses',    'debit',  null],
        '5310' => ['خسائر بيع أصول ثابتة',                   'expenses',    'debit',  null],
        '5330' => ['مصروفات قانونية واستشارية',              'expenses',    'debit',  null],
        '5340' => ['اشتراكات ورخص',                          'expenses',    'debit',  null],
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
