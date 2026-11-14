<?php

namespace Tests\Feature\Accounting;

use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\AlignmentLog;
use Modules\Accounting\Services\GuideAlignmentService;
use Modules\Accounting\Services\GuideConformanceService;
use Modules\Booking\Models\Service;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Models\DoctorPayment;
use Tests\TestCase;

/**
 * The reversible data phases that bring a live (pre-guide) ledger onto
 * الدليل المحاسبي v2.0: control-account history moves to per-party
 * sub-ledgers, out-of-guide accounts are retired into their successors,
 * missing insurance recognitions are posted, inventory consumption is costed
 * by department — the trial balance foots after every phase, and every
 * phase reverts exactly.
 */
class GuideAlignmentMigrationTest extends TestCase
{
    use RefreshDatabase;

    private GuideAlignmentService $alignment;

    /** @var array<string, string> */
    private array $ids;

    private string $doctorId;

    private string $supplierId;

    private string $employeeId;

    private string $insuranceBookingFileNo = 'P-2-002';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);
        $this->alignment = app(GuideAlignmentService::class);

        $this->addLegacyAccount('4065', 'إيرادات الشبكية', 'revenues', 'credit', '4000');
        $this->addLegacyAccount('5115', '(-) استرداد تكلفة من الطبيب', 'expenses', 'credit', '5100');
        $this->ids = Account::pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [(string) $code => $id])->all();

        $this->buildLegacyLedger();
    }

    public function test_control_account_history_moves_to_the_owning_partys_subledger(): void
    {
        $this->runAllPhases();

        $doctorAccount = Account::where('code', '2202')->firstOrFail(); // "دكتور احمد عادل"
        $supplierAccount = Account::where('code', '2313')->firstOrFail(); // "عمار مستلزمات"
        $employeeAccount = Account::where('code', '2401')->firstOrFail(); // "اسامة"

        $this->assertSame($doctorAccount->id, DB::table('doctors')->where('id', $this->doctorId)->value('payable_account_id'));
        // 10,000 accrued − 3,000 paid; the 200 accrual and its reversal net to zero on the same account.
        $this->assertEquals(7000.0, (float) $doctorAccount->fresh()->balance);
        $this->assertEquals(0.0, (float) $supplierAccount->fresh()->balance);
        $this->assertSame(2, JournalEntry::where(fn ($q) => $q->where('debit_account_id', $supplierAccount->id)->orWhere('credit_account_id', $supplierAccount->id))->count());
        $this->assertEquals(5000.0, (float) $employeeAccount->fresh()->balance);

        foreach (['2010', '2020', '2030'] as $control) {
            $this->assertSame(0, JournalEntry::where('debit_account_id', $this->ids[$control])->orWhere('credit_account_id', $this->ids[$control])->count(), "{$control} still has direct lines");
        }
    }

    public function test_untraceable_lines_are_parked_on_the_review_bucket_and_flagged(): void
    {
        $this->runAllPhases();

        $bucket = Account::where('code', '2299')->firstOrFail();

        // 500 manual payout with no reference + the 2,360 consumables charge (bundle, no case link).
        $this->assertEquals(-2860.0, (float) $bucket->fresh()->balance);
        $this->assertSame(2, JournalEntry::where('needs_review', true)->where(fn ($q) => $q->where('debit_account_id', $bucket->id)->orWhere('credit_account_id', $bucket->id))->count());
    }

    public function test_out_of_guide_accounts_are_retired_into_their_successors(): void
    {
        $this->runAllPhases();

        $retina = Account::where('code', '4065')->firstOrFail();
        $this->assertFalse($retina->is_active);
        $this->assertFalse($retina->is_postable);
        $this->assertEquals(0.0, (float) $retina->balance);

        $this->assertEquals(47500.0, (float) Account::where('code', '4030')->value('balance'));
        $this->assertSame('CC-SURG', DB::table('journal_entries')->where('credit_account_id', $this->ids['4030'])->value('cost_center'));
        $this->assertSame($this->ids['4030'], DB::table('services')->where('name', 'حقن شبكية')->value('revenue_account_id'));

        $this->assertFalse((bool) Account::where('code', '5115')->value('is_active'));
        $this->assertEquals(2360.0, (float) Account::where('code', '4070')->value('balance'));
    }

    public function test_insurance_claims_get_their_revenue_and_receivable(): void
    {
        $this->runAllPhases();

        $this->assertEquals(6500.0, (float) Account::where('code', '1031')->value('balance')); // نفقة الدولة
        $this->assertEquals(6500.0, (float) Account::where('code', '4130')->value('balance')); // insurance — surgery

        $check = app(GuideConformanceService::class)->run()['insurance'];
        $this->assertTrue($check['passed'], implode("\n", $check['details']));
    }

    public function test_inventory_consumption_is_costed_to_the_consuming_department(): void
    {
        $this->runAllPhases();

        $this->assertEquals(0.0, (float) Account::where('code', '5010')->value('balance'));
        $this->assertEquals(765.0, (float) Account::where('code', '5020')->value('balance'));

        $check = app(GuideConformanceService::class)->run()['inventory'];
        $this->assertTrue($check['passed'], implode("\n", $check['details']));
    }

    public function test_every_phase_keeps_the_trial_balance_footed_and_reverts_exactly(): void
    {
        $before = $this->snapshot();

        $batches = $this->runAllPhases();

        $check = app(GuideConformanceService::class)->run()['trial_balance'];
        $this->assertTrue($check['passed'], implode("\n", $check['details']));

        foreach (array_reverse($batches) as $batch) {
            $this->alignment->revert($batch);
        }

        $this->assertEquals($before, $this->snapshot());
        $this->assertSame(0, DB::table('accounting_alignment_log')->count());
    }

    /** @return array<int, string> the batches, in run order */
    private function runAllPhases(): array
    {
        $this->alignment->alignChart('t_chart');
        $this->alignment->movePayableHistoryToSubledgers('t_payables');
        $this->alignment->retireOutOfGuideAccounts('t_retire');
        $this->alignment->recognizeMissingInsuranceRevenue('t_insurance');
        $this->alignment->fixInventoryConsumptionCosting('t_inventory');

        return ['t_chart', 't_payables', 't_retire', 't_insurance', 't_inventory'];
    }

    private function buildLegacyLedger(): void
    {
        $doctor = Doctor::create(['name' => 'د. احمد عادل', 'fee_type' => 'percentage', 'fee_value' => 50]);
        $this->doctorId = $doctor->id;
        $bookingId = $this->insertBooking('P-1-001', 'surgery', $doctor->id);

        $this->entry('5120', '2010', 10000, ['reference' => 'P-1-001', 'idempotency_key' => 'doctor_dues:P-1-001:10000', 'source' => 'doctor_shift']);
        $payment = DoctorPayment::create(['doctor_id' => $doctor->id, 'amount' => 3000, 'paid_at' => '2026-05-10', 'method' => 'cash']);
        $this->entry('2010', '1010', 3000, ['reference' => (string) $payment->id, 'idempotency_key' => "doctor_payment:{$payment->id}", 'source' => 'doctor_payment']);
        $accrual = $this->entry('5120', '2010', 200, ['reference' => 'P-1-001', 'idempotency_key' => 'doctor_dues:P-1-001:adjust:1:200', 'source' => 'doctor_shift', 'reversed_at' => now()]);
        $this->entry('2010', '5120', 200, ['reference' => 'P-1-001', 'source' => 'doctor_shift', 'reversal_of_id' => $accrual]);
        $this->entry('2010', '1010', 500, ['source' => 'manual']);
        $this->entry('2010', '5115', 2360, ['reference' => 'باقة مياه بيضاء', 'idempotency_key' => 'bundle_supply_charge:'.Str::ulid(), 'source' => 'supplies_used']);

        $this->supplierId = (string) Str::ulid();
        DB::table('suppliers')->insert(['id' => $this->supplierId, 'name' => 'عمار مستلزمات', 'created_at' => now(), 'updated_at' => now()]);
        $invoiceId = (string) Str::ulid();
        DB::table('purchase_invoices')->insert(['id' => $invoiceId, 'invoice_no' => 'PI-1', 'supplier_id' => $this->supplierId, 'invoice_date' => '2026-05-10', 'total' => 30000, 'paid_amount' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $this->entry('1051', '2020', 30000, ['reference' => 'PI-1', 'idempotency_key' => "purchase_invoice:{$invoiceId}", 'source' => 'purchase']);
        $supplierPaymentId = (string) Str::ulid();
        DB::table('supplier_payments')->insert(['id' => $supplierPaymentId, 'supplier_id' => $this->supplierId, 'amount' => 30000, 'method' => 'cash', 'paid_at' => '2026-05-25', 'created_at' => now(), 'updated_at' => now()]);
        $this->entry('2020', '1010', 30000, ['reference' => $supplierPaymentId, 'idempotency_key' => "supplier_payment:{$supplierPaymentId}", 'source' => 'supplier_payment']);

        $this->employeeId = (string) Str::ulid();
        DB::table('employees')->insert(['id' => $this->employeeId, 'employee_no' => 'E-1', 'name' => 'اسامة', 'dept' => 'admin', 'position' => 'محاسب', 'hire_date' => '2026-01-01', 'created_at' => now(), 'updated_at' => now()]);
        $payrollId = (string) Str::ulid();
        DB::table('payrolls')->insert(['id' => $payrollId, 'employee_id' => $this->employeeId, 'month' => 5, 'year' => 2026, 'net_salary' => 5000, 'created_at' => now(), 'updated_at' => now()]);
        $this->entry('5210', '2030', 5000, ['reference' => $payrollId, 'idempotency_key' => "payroll_accrual:{$payrollId}", 'source' => 'salary']);

        $retinaService = Service::create(['name' => 'حقن شبكية', 'dept' => 'surgery', 'price' => 47500]);
        DB::table('services')->where('id', $retinaService->id)->update(['revenue_account_id' => $this->ids['4065']]);
        $this->entry('1010', '4065', 47500, ['reference' => 'P-1-001', 'source' => 'booking', 'cost_center' => 'CC-LASER']);

        $companyId = (string) Str::ulid();
        DB::table('insurance_companies')->insert(['id' => $companyId, 'name' => 'نفقة الدولة', 'created_at' => now(), 'updated_at' => now()]);
        $insuranceBookingId = $this->insertBooking($this->insuranceBookingFileNo, 'surgery', $doctor->id);
        DB::table('insurance_claims')->insert([
            'id' => (string) Str::ulid(), 'booking_id' => $insuranceBookingId, 'insurance_company_id' => $companyId, 'service_id' => $retinaService->id,
            'patient_name' => 'مريض تأمين', 'file_no' => $this->insuranceBookingFileNo, 'service_name' => 'مياه بيضاء', 'invoice_amount' => 6500,
            'insurance_share' => 6500, 'status' => 'draft', 'service_date' => '2026-05-08', 'claim_date' => '2026-05-08', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->entry('5130', '1010', 500, ['reference' => $this->insuranceBookingFileNo, 'source' => 'insurance_doctor_payment', 'cost_center' => 'CC-INS']);

        $this->entry('5010', '1051', 765, ['reference' => 'OUT-1', 'source' => 'supplies_used', 'cost_center' => 'CC-LASIK']);

        app(AlignmentLog::class)->recomputeBalances();
        unset($bookingId);
    }

    private function insertBooking(string $fileNo, string $dept, string $doctorId): string
    {
        $id = (string) Str::ulid();
        DB::table('bookings')->insert(['id' => $id, 'file_no' => $fileNo, 'patient_name' => 'مريض', 'dept' => $dept, 'visit_date' => '2026-05-05', 'doctor_id' => $doctorId, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    /** Insert a raw legacy line (bypassing today's posting guards), returning its id. */
    private function entry(string $debitCode, string $creditCode, float $amount, array $attributes = []): string
    {
        $id = (string) Str::ulid();

        DB::table('journal_entries')->insert([
            'id' => $id,
            'date' => '2026-05-10',
            'description' => 'legacy',
            'debit_account_id' => $this->ids[$debitCode],
            'credit_account_id' => $this->ids[$creditCode],
            'amount' => $amount,
            'source' => 'manual',
            'created_at' => now()->addSeconds(DB::table('journal_entries')->count()),
            'updated_at' => now(),
            ...$attributes,
        ]);

        return $id;
    }

    /** A pre-guide account as it lives in production (older migrations may already have left the row behind). */
    private function addLegacyAccount(string $code, string $name, string $group, string $nature, string $parentCode): void
    {
        $attributes = [
            'name' => $name, 'group' => $group, 'nature' => $nature, 'parent_id' => Account::where('code', $parentCode)->value('id'),
            'balance' => 0, 'is_active' => true, 'is_postable' => true, 'updated_at' => now(),
        ];

        if (DB::table('accounts')->where('code', $code)->exists()) {
            DB::table('accounts')->where('code', $code)->update($attributes);

            return;
        }

        DB::table('accounts')->insert([...$attributes, 'id' => (string) Str::ulid(), 'code' => $code, 'created_at' => now()]);
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'accounts' => DB::table('accounts')->orderBy('code')->get(['id', 'code', 'name', 'is_active', 'is_postable', 'balance', 'parent_id'])->map(fn ($row) => (array) $row)->all(),
            'journal' => DB::table('journal_entries')->orderBy('id')->get(['id', 'debit_account_id', 'credit_account_id', 'amount', 'cost_center', 'needs_review'])->map(fn ($row) => (array) $row)->all(),
            'links' => DB::table('doctors')->pluck('payable_account_id', 'id')->all(),
            'services' => DB::table('services')->pluck('revenue_account_id', 'id')->all(),
            'companies' => DB::table('insurance_companies')->pluck('receivable_account_id', 'id')->all(),
        ];
    }
}
