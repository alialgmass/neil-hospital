<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\Subledger;

/**
 * The approved chart of accounts — الدليل المحاسبي الكامل، الإصدار 2.0
 * (أغسطس 2026) — as data. Single source for the seeder that builds a fresh
 * environment, the alignment migrations that bring a live database onto it,
 * and the conformance check (`accounting:verify-guide`).
 *
 * The display-only group headers the system has always had (1100, 2000,
 * 2100, 4000, 4100, 4200, 4900, 5000, 5100, 5200) are kept as non-postable
 * titles; every other code and name below is copied verbatim from the guide.
 */
class GuideChart
{
    /**
     * code => [name, group, nature, parentCode|null]
     *
     * @var array<string, array{0:string, 1:string, 2:string, 3:string|null}>
     */
    private const ACCOUNTS = [
        // ── ASSETS ─────────────────────────────────────────────────
        '1000' => ['الأصول المتداولة (مجمع)',                 'assets',      'debit',  null],
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
        '1053' => ['مخزون — مستلزمات تشغيلية (قرطاسية/نظافة)', 'assets',    'debit',  '1050'],
        '1060' => ['مصروفات مقدمة',                          'assets',      'debit',  '1000'],
        '1080' => ['ضريبة دخل مخصومة من المنبع',             'assets',      'debit',  '1000'],
        '1090' => ['نقدية مركز — العيادة (CC-CLINIC)',        'assets',      'debit',  '1000'],
        '1091' => ['نقدية مركز — الفحوصات (CC-LAB)',         'assets',      'debit',  '1000'],
        '1092' => ['نقدية مركز — البنتاكام (CC-PENTA)',      'assets',      'debit',  '1000'],
        '1093' => ['نقدية مركز — الليزر (CC-LASER)',         'assets',      'debit',  '1000'],
        '1094' => ['نقدية مركز — الليزك (CC-LASIK)',         'assets',      'debit',  '1000'],
        '1095' => ['نقدية مركز — الجراحة (CC-SURG)',         'assets',      'debit',  '1000'],
        '1096' => ['نقدية مركز — التأمين (CC-INS)',          'assets',      'debit',  '1000'],
        '1100' => ['الأصول الثابتة',                         'assets',      'debit',  null],
        '1110' => ['أراضي',                                  'assets',      'debit',  '1100'],
        '1120' => ['مباني وإنشاءات',                         'assets',      'debit',  '1100'],
        '1121' => ['(-) مجمع إهلاك المباني',                 'assets',      'credit', '1100'],
        '1130' => ['أجهزة طبية (ليزك/ليزر/سكانر)',           'assets',      'debit',  '1100'],
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
        '4000' => ['إيرادات نقدية',                           'revenues',    'credit', null],
        '4010' => ['إيرادات العيادة الخارجية',                'revenues',    'credit', '4000'],
        '4020' => ['إيرادات الفحوصات والمختبر',               'revenues',    'credit', '4000'],
        '4030' => ['إيرادات العمليات الجراحية',               'revenues',    'credit', '4000'],
        '4040' => ['إيرادات وحدة الليزك',                    'revenues',    'credit', '4000'],
        '4050' => ['إيرادات الليزر التشخيصي/العلاجي',        'revenues',    'credit', '4000'],
        '4060' => ['إيرادات وحدة البنتاكام (CC-PENTA)',      'revenues',    'credit', '4000'],
        '4070' => ['إيراد بيع مستهلكات للأطباء',             'revenues',    'credit', '4000'],
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
        '5250' => ['مصروفات إدارية ومكتبية (قرطاسية)',       'expenses',    'debit',  '5200'],
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

    /**
     * The guide's named doctor sub-ledger accounts (2201–2235), verbatim.
     *
     * @var array<string, string>
     */
    public const DOCTORS = [
        '2201' => 'دكتور احمد البحار',
        '2202' => 'دكتور احمد عادل',
        '2203' => 'دكتور احمد عبداللاه',
        '2204' => 'دكتور احمد عبده',
        '2205' => 'دكتور احمد محمد حسن',
        '2206' => 'دكتور تامر كمال',
        '2207' => 'دكتور جوزيف جورج',
        '2208' => 'دكتور حسام جيره',
        '2209' => 'دكتور حسام شعبان',
        '2210' => 'دكتور حسن عبد الوهاب حسن',
        '2211' => 'دكتور خالد عبد العظيم محمد',
        '2212' => 'دكتور خالد كمال فايزي',
        '2213' => 'دكتور شريف صابر',
        '2214' => 'دكتور عبد الهادى ياسين',
        '2215' => 'دكتور عبداللطيف خميس',
        '2216' => 'دكتور عمرو الجارحى',
        '2217' => 'دكتور محمد حسن',
        '2218' => 'دكتور محمد عبد الوهاب حسن',
        '2219' => 'دكتور محمد مطاوع',
        '2220' => 'دكتور محمود فوزى الجداوى',
        '2221' => 'دكتور محمود لطفي',
        '2222' => 'دكتور مصطفى على جنيدى',
        '2223' => 'دكتور مينا مجدى',
        '2224' => 'دكتور نادر صادق',
        '2225' => 'دكتوره ايات مصطفى',
        '2226' => 'دكتوره بسمه ايهاب',
        '2227' => 'دكتوره رحمه عاطف',
        '2228' => 'دكتوره ساره جمال',
        '2229' => 'دكتوره صفاء هاشم',
        '2230' => 'دكتوره حسناء حسين',
        '2231' => 'دكتوره حسناء عبد الحميد',
        '2232' => 'دكتورة ايمان سعد',
        '2233' => 'دكتوره رضوى سامى مالك',
        '2234' => 'دكتور عمر سليمان',
        '2235' => 'دكتوره سحر نشات',
    ];

    /**
     * The guide's named supplier sub-ledger accounts (2301–2324), verbatim.
     *
     * @var array<string, string>
     */
    public const SUPPLIERS = [
        '2301' => 'محمد عيون',
        '2302' => 'مجدي صبحي',
        '2303' => 'عفت',
        '2304' => 'وليد صميده',
        '2305' => 'جوهر',
        '2306' => 'وليد عويس',
        '2307' => 'امجد تخدير',
        '2308' => 'اوركيديا',
        '2309' => 'مخزن الفتح',
        '2310' => 'سامر',
        '2311' => 'صيدلية',
        '2312' => 'فارما للأدوية',
        '2313' => 'عمار مستلزمات',
        '2314' => 'insight',
        '2315' => 'ايجل',
        '2316' => 'الصفا والمروة',
        '2317' => 'مورد نقدي',
        '2318' => 'هاي تك ايجبت (عدسات تورك)',
        '2319' => 'جلوبال مابي',
        '2320' => 'الفاطمية',
        '2321' => 'فاركو للأدوية',
        '2322' => 'ايبكو',
        '2323' => 'انفستا',
        '2324' => 'الأدوية الدولية',
    ];

    /**
     * The guide's named employee sub-ledger accounts (2401–2416), verbatim.
     *
     * @var array<string, string>
     */
    public const EMPLOYEES = [
        '2401' => 'اسامة',
        '2402' => 'اشرقت',
        '2403' => 'امانى',
        '2404' => 'عمر ربيع',
        '2405' => 'ساره',
        '2406' => 'محمد وحيد علي',
        '2407' => 'نشوي',
        '2408' => 'جمال',
        '2409' => 'حسام هاشم',
        '2410' => 'محمد الششتاوى',
        '2411' => 'جمانه',
        '2412' => 'محمد اليمنى (المحاسب الضريبى)',
        '2413' => 'مروه',
        '2414' => 'شيماء مصطفى',
        '2415' => 'منى رمضان',
        '2416' => 'مايكل',
    ];

    /**
     * Accounts that exist(ed) in the system but are not in the guide, with
     * the guide account their balance and history move to (null = no
     * successor; the account may only be retired if it never moved).
     *
     * - 4065 retina revenue: the guide forbids a separate retina revenue
     *   account — retina is surgery revenue (4030) tagged CC-SURG.
     * - 4080 pharmacy revenue: a real sale to a patient is 4210.
     * - 5115 contra-expense: consumables charged to the doctor are revenue
     *   in 4070 (guide §1.4 / §2.7, the approved model).
     * - 4125 / 2035: reserved codes left over from earlier charts.
     *
     * @var array<string, string|null>
     */
    public const RETIRED = [
        '4065' => '4030',
        '4080' => '4210',
        '5115' => '4070',
        '4125' => null,
        '2035' => null,
    ];

    /**
     * Every account of the full tree — the guide chart plus each sub-ledger
     * party and the three "unidentified — for review" buckets.
     *
     * @return array<string, array{name:string, group:string, nature:string, parent:string|null, postable:bool}>
     */
    public static function accounts(): array
    {
        $nonPostable = AccountCode::nonPostableCodes();
        $rows = [];

        foreach (self::ACCOUNTS as $code => [$name, $group, $nature, $parent]) {
            $code = (string) $code;
            $rows[$code] = [
                'name' => $name,
                'group' => $group,
                'nature' => $nature,
                'parent' => $parent,
                'postable' => ! in_array($code, $nonPostable, true),
            ];
        }

        foreach (Subledger::cases() as $subledger) {
            foreach (self::partyNames($subledger) as $code => $partyName) {
                $rows[(string) $code] = self::subledgerRow($subledger, $subledger->namePrefix().$partyName);
            }

            $rows[(string) $subledger->unassignedCode()] = self::subledgerRow($subledger, $subledger->unassignedName());
        }

        ksort($rows, SORT_STRING);

        return $rows;
    }

    /** @return array<string, string> */
    public static function partyNames(Subledger $subledger): array
    {
        return match ($subledger) {
            Subledger::Doctor => self::DOCTORS,
            Subledger::Supplier => self::SUPPLIERS,
            Subledger::Employee => self::EMPLOYEES,
        };
    }

    /** @return array<int, string> */
    public static function codes(): array
    {
        return array_map('strval', array_keys(self::accounts()));
    }

    /** @return array{name:string, group:string, nature:string, parent:string, postable:bool} */
    private static function subledgerRow(Subledger $subledger, string $name): array
    {
        return [
            'name' => $name,
            'group' => 'liabilities',
            'nature' => 'credit',
            'parent' => $subledger->masterCode()->value,
            'postable' => true,
        ];
    }
}
