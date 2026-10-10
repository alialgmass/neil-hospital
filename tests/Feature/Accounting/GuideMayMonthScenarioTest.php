<?php

namespace Tests\Feature\Accounting;

use App\Enums\Department;
use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Actions\AutoPostDoctorDuesAction;
use Modules\Accounting\Actions\AutoPostInsuranceClaimAction;
use Modules\Accounting\Actions\AutoPostInsuranceDoctorCashPaymentAction;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\BalanceSheetService;
use Modules\Accounting\Services\GuideConformanceService;
use Modules\Accounting\Services\IncomeStatementService;
use Modules\Accounting\Services\JournalService;
use Modules\Accounting\Services\LedgerService;
use Modules\Accounting\Services\OpeningBalanceService;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Doctor\Actions\RecordDoctorPaymentAction;
use Modules\Doctor\Models\Doctor;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\InsuranceCompany;
use Modules\Inventory\Actions\ReceivePurchaseInvoiceAction;
use Modules\Inventory\Actions\RecordSupplierPaymentAction;
use Modules\Inventory\Actions\StockTakeAdjustmentAction;
use Modules\Inventory\Enums\ItemCategory;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Models\SupplyBundle;
use Modules\Surgery\Actions\ProcessBundleSupplyAction;
use Modules\Surgery\Models\Surgery;
use Tests\TestCase;

/**
 * Golden scenario — الدليل المحاسبي v2.0, part 3: the full month of May 2026
 * replayed through the real posting paths (opening balances, doctor dues on
 * each doctor's sub-ledger, consumables charged at selling price to 4070,
 * the insurance case's three service-day entries, a credit purchase from
 * "عمار مستلزمات" (2313) and its settlement, month-end salaries, utilities
 * and the 500 stock-count shortage). The resulting trial balance, income
 * statement and balance sheet must equal the guide's §3.4–3.6.
 *
 * The lab doctor's 162 is paid the same day (as in the guide's §2.7 worked
 * entries) — that is what the guide's own trial balance (cash 11,833 and
 * 2010 = 0) implies.
 */
class GuideMayMonthScenarioTest extends TestCase
{
    use RefreshDatabase;

    private JournalService $journal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);
        $this->actingAs(User::factory()->create());
        $this->journal = app(JournalService::class);

        $this->replayMay();
    }

    public function test_trial_balance_matches_the_guide(): void
    {
        $expected = [
            '1010' => 11833, '1011' => 500, '1031' => 6500, '1051' => 125970, '3010' => 150000,
            '4010' => 1500, '4020' => 650, '4030' => 10000, '4040' => 15000, '4050' => 1000, '4060' => 500,
            '4070' => 4720, '4130' => 6500, '5010' => 3265, '5020' => 765, '5110' => 1137, '5120' => 24900,
            '5130' => 500, '5210' => 10000, '5230' => 4500,
        ];

        $rows = collect(app(LedgerService::class)->trialBalance())->keyBy('code');

        foreach ($expected as $code => $balance) {
            $this->assertEquals($balance, $rows[$code]['balance'], "balance of {$code}");
        }

        foreach (['2010', '2020', '1020', '3020'] as $code) {
            $this->assertEquals(0.0, $rows[$code]['balance'] ?? 0.0, "{$code} must be zero");
        }

        [$debitSide, $creditSide] = collect($rows)->reject(fn ($row) => $row['is_group'])->reduce(function ($carry, $row) {
            $net = $row['debits'] - $row['credits'];

            return [$carry[0] + max($net, 0), $carry[1] + max(-$net, 0)];
        }, [0.0, 0.0]);

        $this->assertEquals(189870.0, round($debitSide, 2));
        $this->assertEquals(189870.0, round($creditSide, 2));
    }

    public function test_income_statement_matches_the_guide(): void
    {
        $statement = app(IncomeStatementService::class)->get();

        $this->assertEquals(39870.0, $statement['totalRevenue']);
        $this->assertEquals(-5197.0, $statement['netBeforeTax']);
    }

    public function test_balance_sheet_matches_the_guide(): void
    {
        $sheet = app(BalanceSheetService::class)->get();

        $this->assertEquals(144803.0, $sheet['totalAssets']);
        $this->assertEquals(144803.0, $sheet['totalLiabilitiesAndEquity']);
        $this->assertTrue($sheet['isBalanced']);
    }

    public function test_the_month_conforms_to_every_guide_rule(): void
    {
        foreach (app(GuideConformanceService::class)->run() as $check) {
            $this->assertTrue($check['passed'], $check['label']."\n".implode("\n", $check['details']));
        }

        // The supplier's own sub-ledger carried the purchase and its settlement.
        $this->assertSame(0.0, (float) Account::where('code', '2313')->value('balance'));
        $this->assertSame(1, Supplier::whereNotNull('payable_account_id')->count());
    }

    private function replayMay(): void
    {
        app(OpeningBalanceService::class)->post('2026-05-01', ['1010' => 50000, '1051' => 100000, '3010' => 150000]);

        // 3 May — 5 clinic cases × 300, doctor 50% of (1,500 − 250).
        $clinicDoctor = $this->doctor('د. عيادة');
        $this->cashService('4010', 1500, 250, 'CC-CLINIC', '2026-05-03');
        $this->duesAndSameDayPayment(Department::Clinic, $clinicDoctor, 625, '2026-05-03');

        // 5 May — cash cataract 10,000; consumables 2,360 at sale / 765 at cost.
        $surgeon = $this->doctor('د. جراح');
        $this->cashService('4030', 10000, 50, 'CC-SURG', '2026-05-05');
        app(AutoPostDoctorDuesAction::class)->execute(Department::Surgery, 9950, $surgeon->id, $surgeon->name, 'P-SURG', '2026-05-05', 'may:surgery:dues');
        $this->consumables('surgery', $surgeon);
        $this->payDoctor($surgeon, 7590, '2026-05-05');

        // 8 May — the 6,500 insurance cataract case (guide §2.2).
        $this->insuranceCase();

        // 10 May — 30,000 credit purchase from عمار مستلزمات.
        $supplier = Supplier::create(['name' => 'عمار مستلزمات', 'is_active' => true, 'balance' => 0]);
        app(ReceivePurchaseInvoiceAction::class)->execute(
            ['invoice_no' => 'PI-MAY-1', 'supplier_id' => $supplier->id, 'invoice_date' => '2026-05-10', 'paid_amount' => 0],
            [['item_name' => 'مستلزمات طبية', 'qty' => 1, 'unit_cost' => 30000]],
        );

        // 12 May — cash lasik 15,000; same consumables, costed to lasik.
        $lasikDoctor = $this->doctor('د. ليزك');
        $this->cashService('4040', 15000, 50, 'CC-LASIK', '2026-05-12');
        app(AutoPostDoctorDuesAction::class)->execute(Department::Lasik, 14950, $lasikDoctor->id, $lasikDoctor->name, 'P-LASIK', '2026-05-12', 'may:lasik:dues');
        $this->consumables('lasik', $lasikDoctor);
        $this->payDoctor($lasikDoctor, 12590, '2026-05-12');

        // 22 May — labs 650 with د. حسناء حسين (27% → 162).
        $labDoctor = $this->doctor('د. حسناء حسين');
        $this->cashService('4020', 650, 50, 'CC-LAB', '2026-05-22');
        $this->duesAndSameDayPayment(Department::Labs, $labDoctor, 162, '2026-05-22');

        // 25 May — supplier settled.
        app(RecordSupplierPaymentAction::class)->execute(['supplier_id' => $supplier->id, 'amount' => 30000, 'method' => 'cash', 'paid_at' => '2026-05-25']);

        // 27 May — laser 1,000; center keeps 600, doctor 350 after the 50.
        $laserDoctor = $this->doctor('د. ليزر');
        $this->cashService('4050', 1000, 50, 'CC-LASER', '2026-05-27');
        $this->duesAndSameDayPayment(Department::Laser, $laserDoctor, 350, '2026-05-27');

        // 29 May — pentacam 500, no doctor share.
        $this->cashService('4060', 500, 50, 'CC-PENTACAM', '2026-05-29');

        // 31 May — salaries, utilities, stock count shortage 500.
        $this->manual('5210', '1010', 10000, 'CC-ADMIN', '2026-05-31');
        $this->manual('5230', '1010', 4500, 'CC-ADMIN', '2026-05-31');
        $counted = InventoryItem::create(['name' => 'مستلزم جرد', 'code' => 'CNT-1', 'category' => ItemCategory::Medical, 'unit' => 'piece', 'quantity' => 10, 'min_quantity' => 0, 'unit_cost' => 50, 'sell_price' => 50]);
        app(StockTakeAdjustmentAction::class)->execute([['item_id' => $counted->id, 'physical_qty' => 0]]);
    }

    private function cashService(string $revenueCode, float $amount, float $developmentFund, string $costCenter, string $date): void
    {
        $this->journal->record($this->line('1010', $revenueCode, $amount, $costCenter, $date, JournalSource::BOOKING));
        $this->journal->record($this->line('1011', '1010', $developmentFund, $costCenter, $date, JournalSource::BOOKING));
    }

    private function duesAndSameDayPayment(Department $dept, Doctor $doctor, float $amount, string $date): void
    {
        app(AutoPostDoctorDuesAction::class)->execute($dept, $amount, $doctor->id, $doctor->name, 'P-'.$dept->value, $date, "may:{$dept->value}:dues");
        $this->payDoctor($doctor, $amount, $date);
    }

    private function payDoctor(Doctor $doctor, float $amount, string $date): void
    {
        app(RecordDoctorPaymentAction::class)->execute(['doctor_id' => $doctor->id, 'amount' => $amount, 'method' => 'cash', 'paid_at' => $date]);
    }

    /** فتح غرفة 1,000/45 · ميثيل 500/300 · عدسة لينة 700/350 · كيراتوم 160/70 = 2,360 sale / 765 cost. */
    private function consumables(string $dept, Doctor $doctor): void
    {
        $bundle = SupplyBundle::create(['name' => 'مستهلكات '.$dept, 'code' => 'B-'.$dept, 'price' => 2360, 'is_active' => true]);

        foreach (['فتح غرفة العمليات' => 45, 'ميثيل' => 300, 'عدسة لينة' => 350, 'كيراتوم' => 70] as $name => $cost) {
            $item = InventoryItem::create(['name' => $name.' '.$dept, 'code' => 'I-'.uniqid(), 'category' => ItemCategory::Medical, 'unit' => 'piece', 'quantity' => 10, 'min_quantity' => 0, 'unit_cost' => $cost, 'sell_price' => $cost]);
            $bundle->items()->create(['inventory_item_id' => $item->id, 'item_name' => $name, 'qty' => 1, 'unit_cost' => $cost]);
        }

        $booking = Booking::create(['file_no' => 'P-'.$dept, 'patient_name' => 'مريض', 'dept' => $dept, 'visit_date' => '2026-05-05', 'price' => 10000, 'paid_amount' => 10000, 'doctor_id' => $doctor->id, 'pay_method' => 'cash', 'pay_status' => 'paid', 'status' => 'waiting']);
        $surgery = Surgery::create(['booking_id' => $booking->id, 'surgeon_id' => $doctor->id, 'dept' => $dept, 'status' => 'in_progress']);

        app(ProcessBundleSupplyAction::class)->process($bundle->id, 1, $dept, [], $surgery->id);
    }

    private function insuranceCase(): void
    {
        $doctor = $this->doctor('د. تأمين');
        $company = InsuranceCompany::create(['name' => 'نفقة الدولة', 'status' => 'active']);
        $service = Service::create(['name' => 'مياه بيضاء', 'dept' => 'surgery', 'price' => 6500]);
        $booking = Booking::create(['file_no' => 'P-INS', 'patient_name' => 'مريض تأمين', 'dept' => 'surgery', 'visit_date' => '2026-05-08', 'price' => 6500, 'paid_amount' => 0, 'doctor_id' => $doctor->id, 'pay_method' => 'insurance', 'pay_status' => 'unpaid', 'status' => 'waiting']);
        $claim = InsuranceClaim::create([
            'booking_id' => $booking->id, 'insurance_company_id' => $company->id, 'service_id' => $service->id, 'patient_name' => 'مريض تأمين',
            'file_no' => 'P-INS', 'service_name' => 'مياه بيضاء', 'invoice_amount' => 6500, 'insurance_share' => 6500, 'patient_share' => 0,
            'status' => 'draft', 'service_date' => '2026-05-08', 'claim_date' => '2026-05-08',
        ]);

        app(AutoPostInsuranceClaimAction::class)->recognize($claim);                                            // 1أ
        $this->journal->record($this->line('5010', '1051', 2000, 'CC-INS', '2026-05-08', JournalSource::SUPPLIES_USED)); // 1ب
        app(AutoPostInsuranceDoctorCashPaymentAction::class)->execute(500, $doctor->name, 'P-INS', '2026-05-08', 'may:insurance:doctor'); // 1ج
    }

    private function manual(string $debit, string $credit, float $amount, string $costCenter, string $date): void
    {
        $this->journal->record($this->line($debit, $credit, $amount, $costCenter, $date, JournalSource::EXPENSE));
    }

    /** @return array<string, mixed> */
    private function line(string $debit, string $credit, float $amount, string $costCenter, string $date, JournalSource $source): array
    {
        return [
            'date' => $date,
            'description' => "{$debit}/{$credit}",
            'debit_account_id' => Account::where('code', $debit)->value('id'),
            'credit_account_id' => Account::where('code', $credit)->value('id'),
            'amount' => $amount,
            'source' => $source,
            'cost_center' => $costCenter,
        ];
    }

    private function doctor(string $name): Doctor
    {
        return Doctor::create(['name' => $name, 'fee_type' => 'percentage', 'fee_value' => 50]);
    }
}
