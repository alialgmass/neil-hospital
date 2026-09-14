<?php

namespace Tests\Feature\Insurance;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Insurance\Actions\UpdateInsuranceClaimAction;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\InsuranceCompany;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InsuranceClaimAccountingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    private function makeClaim(float $insuranceShare = 800, string $dept = 'clinic', ?InsuranceCompany $company = null): InsuranceClaim
    {
        $company ??= InsuranceCompany::create(['name' => 'شركة تأمين', 'status' => 'active']);
        $service = Service::create([
            'name' => 'كشف', 'dept' => $dept, 'price' => 1000,
            'center_type' => 'pct', 'center_val' => 40, 'center_share' => 400, 'dr_share' => 600,
        ]);
        $booking = Booking::create([
            'file_no' => 'MRN-'.uniqid(), 'patient_name' => 'مريض', 'dept' => $dept,
            'visit_date' => now()->toDateString(), 'price' => 1000, 'paid_amount' => 0,
            'pay_method' => 'insurance', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ]);

        return InsuranceClaim::create([
            'booking_id' => $booking->id,
            'insurance_company_id' => $company->id,
            'service_id' => $service->id,
            'patient_name' => 'مريض',
            'service_name' => 'كشف',
            'invoice_amount' => 1000,
            'insurance_share' => $insuranceShare,
            'patient_share' => 1000 - $insuranceShare,
            'status' => 'draft',
            'service_date' => now()->toDateString(),
            'claim_date' => now()->toDateString(),
            'claim_reference' => 'CLM-'.uniqid(),
        ]);
    }

    public function test_submission_debits_receivable_credits_insurance_revenue(): void
    {
        $claim = $this->makeClaim(800);

        app(UpdateInsuranceClaimAction::class)->execute($claim, ['status' => 'submitted']);

        $receivable = Account::where('code', '1031')->firstOrFail();
        $revenue = Account::where('code', '4110')->firstOrFail();

        $entry = JournalEntry::where('source', 'insurance_claim')->sole();
        $this->assertSame($receivable->id, $entry->debit_account_id);
        $this->assertSame($revenue->id, $entry->credit_account_id);
        $this->assertEquals(800.00, (float) $entry->amount);
    }

    public function test_full_collection_debits_cash_credits_receivable(): void
    {
        $claim = $this->makeClaim(800);
        app(UpdateInsuranceClaimAction::class)->execute($claim, ['status' => 'submitted']);
        $claim->refresh();
        app(UpdateInsuranceClaimAction::class)->execute($claim, ['status' => 'approved', 'approved_amount' => 800]);
        $claim->refresh();

        app(UpdateInsuranceClaimAction::class)->execute($claim, ['status' => 'paid', 'paid_amount' => 800]);

        $cash = Account::where('code', '1010')->firstOrFail();
        $receivable = Account::where('code', '1031')->firstOrFail();

        $entry = JournalEntry::where('source', 'insurance_collect')->sole();
        $this->assertSame($cash->id, $entry->debit_account_id);
        $this->assertSame($receivable->id, $entry->credit_account_id);
        $this->assertEquals(800.00, (float) $entry->amount);

        // Fully collected: no bad-debt write-off
        $this->assertEquals(0.00, (float) Account::where('code', '5300')->firstOrFail()->balance);
    }

    public function test_partial_collection_writes_off_shortfall_to_bad_debt(): void
    {
        $claim = $this->makeClaim(800);
        app(UpdateInsuranceClaimAction::class)->execute($claim, ['status' => 'submitted']);
        $claim->refresh();
        app(UpdateInsuranceClaimAction::class)->execute($claim, ['status' => 'approved', 'approved_amount' => 600]);
        $claim->refresh();

        // Only 600 actually collected against an 800 receivable — 200 shortfall
        app(UpdateInsuranceClaimAction::class)->execute($claim, ['status' => 'paid', 'paid_amount' => 600]);

        $badDebt = Account::where('code', '5300')->firstOrFail();
        $receivable = Account::where('code', '1031')->firstOrFail();

        $writeOff = JournalEntry::where('debit_account_id', $badDebt->id)->sole();
        $this->assertSame($receivable->id, $writeOff->credit_account_id);
        $this->assertEquals(200.00, (float) $writeOff->amount);

        // Receivable fully cleared: 800 (submit) - 600 (collect) - 200 (write-off) = 0
        $this->assertEquals(0.00, (float) $receivable->fresh()->balance);
    }

    public function test_rejection_reverses_the_submitted_entry(): void
    {
        $claim = $this->makeClaim(800);
        app(UpdateInsuranceClaimAction::class)->execute($claim, ['status' => 'submitted']);
        $claim->refresh();

        app(UpdateInsuranceClaimAction::class)->execute($claim, ['status' => 'rejected']);

        $receivable = Account::where('code', '1031')->firstOrFail();
        $revenue = Account::where('code', '4110')->firstOrFail();

        $reversal = JournalEntry::where('source', 'reversal')->sole();
        $this->assertSame($revenue->id, $reversal->debit_account_id);
        $this->assertSame($receivable->id, $reversal->credit_account_id);
        $this->assertEquals(800.00, (float) $reversal->amount);

        $this->assertEquals(0.00, (float) $receivable->fresh()->balance);
        $this->assertEquals(0.00, (float) $revenue->fresh()->balance);
    }

    public static function deptRevenueCodeProvider(): array
    {
        return [
            'clinic' => ['clinic', '4110'],
            'labs' => ['labs', '4120'],
            'surgery' => ['surgery', '4130'],
            'lasik' => ['lasik', '4140'],
            'laser' => ['laser', '4150'],
        ];
    }

    #[DataProvider('deptRevenueCodeProvider')]
    public function test_submission_routes_revenue_by_department(string $dept, string $expectedCode): void
    {
        $claim = $this->makeClaim(800, $dept);

        app(UpdateInsuranceClaimAction::class)->execute($claim, ['status' => 'submitted']);

        $revenue = Account::where('code', $expectedCode)->firstOrFail();
        $entry = JournalEntry::where('source', 'insurance_claim')->sole();

        $this->assertSame($revenue->id, $entry->credit_account_id);
    }

    public function test_submission_routes_receivable_by_company_and_each_company_keeps_its_own_balance(): void
    {
        $companyA = InsuranceCompany::create(['name' => 'شركة أ', 'status' => 'active']);
        $companyB = InsuranceCompany::create(['name' => 'شركة ب', 'status' => 'active']);

        $claimA = $this->makeClaim(800, 'clinic', $companyA);
        $claimB = $this->makeClaim(500, 'clinic', $companyB);

        app(UpdateInsuranceClaimAction::class)->execute($claimA, ['status' => 'submitted']);
        app(UpdateInsuranceClaimAction::class)->execute($claimB, ['status' => 'submitted']);

        $companyA->refresh();
        $companyB->refresh();

        $this->assertNotNull($companyA->receivable_account_id);
        $this->assertNotNull($companyB->receivable_account_id);
        $this->assertNotSame($companyA->receivable_account_id, $companyB->receivable_account_id);

        $this->assertEquals(800.00, (float) Account::find($companyA->receivable_account_id)->balance);
        $this->assertEquals(500.00, (float) Account::find($companyB->receivable_account_id)->balance);

        // Neither claim touched the shared, non-postable 1030 roll-up.
        $aggregate = Account::where('code', '1030')->firstOrFail();
        $this->assertEquals(0.00, (float) $aggregate->balance);
    }
}
