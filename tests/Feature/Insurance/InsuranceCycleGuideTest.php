<?php

namespace Tests\Feature\Insurance;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Actions\AutoPostInsuranceDoctorCashPaymentAction;
use Modules\Accounting\Exceptions\AccountingException;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\GuideConformanceService;
use Modules\Accounting\Services\JournalService;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\InsuranceCompany;
use Modules\Booking\Models\Service;
use Modules\Doctor\Models\Doctor;
use Modules\Insurance\Actions\UpdateInsuranceClaimAction;
use Modules\Insurance\Models\InsuranceClaim;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Guide §2.2–2.4 — the 6,500 ج insurance case. On the service day the
 * booking alone produces the claim's revenue + receivable (1أ) and the
 * doctor's same-day cash fee (1ج); collection splits bank + withholding tax
 * (3); a rejection reverses only the revenue. 2010 never moves.
 */
class InsuranceCycleGuideTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Doctor $doctor;

    private Service $service;

    private InsuranceCompany $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['booking.create', 'booking.edit'] as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->service = Service::create(['name' => 'فحص مجال', 'dept' => 'labs', 'price' => 6500, 'ins_price' => 6500]);
        $this->doctor = Doctor::create(['name' => 'د. تأمين', 'fee_type' => 'fixed', 'fee_value' => 0]);
        $this->doctor->services()->attach($this->service->id, ['fee' => 500]);
        // 500 / 6,500 — the guide's withholding on this case.
        $this->company = InsuranceCompany::create(['name' => 'نفقة الدولة', 'coverage_pct' => 100, 'withholding_pct' => 7.6923077]);
    }

    public function test_service_day_posts_revenue_receivable_and_doctor_cash_without_touching_2010(): void
    {
        $booking = $this->bookInsuranceCase();

        $this->assertEquals(6500.0, $this->balance('1031'));
        $this->assertEquals(6500.0, $this->balance('4120'));
        $this->assertEquals(500.0, $this->balance('5130'));
        $this->assertEquals(-500.0, $this->balance('1010'));
        $this->assertSame(0, JournalEntry::whereIn('credit_account_id', $this->doctorSubledgerIds())->orWhereIn('debit_account_id', $this->doctorSubledgerIds())->count());

        $recognition = JournalEntry::where('source', 'insurance_claim')->sole();
        $this->assertSame('2026-05-08', $recognition->date->toDateString());
        $this->assertSame($booking->file_no, $recognition->reference);
    }

    public function test_collection_closes_the_receivable_into_bank_and_withholding_tax(): void
    {
        $booking = $this->bookInsuranceCase();
        $claim = InsuranceClaim::where('booking_id', $booking->id)->sole();
        $action = app(UpdateInsuranceClaimAction::class);

        $action->execute($claim, ['status' => 'submitted']);
        $action->execute($claim->refresh(), ['status' => 'approved']);
        $action->execute($claim->refresh(), ['status' => 'paid', 'payment_date' => '2026-06-30']);

        $this->assertSame(1, JournalEntry::where('source', 'insurance_claim')->count(), 'submission must not re-recognize');
        $this->assertEquals(0.0, $this->balance('1031'));
        $this->assertEquals(6000.0, $this->balance('1020'));
        $this->assertEquals(500.0, $this->balance('1080'));
        $this->assertEquals(6500.0, $this->balance('4120'));

        $checks = app(GuideConformanceService::class)->run();
        $this->assertTrue($checks['insurance']['passed'], implode("\n", $checks['insurance']['details']));
        $this->assertTrue($checks['trial_balance']['passed'], implode("\n", $checks['trial_balance']['details']));
    }

    public function test_rejection_reverses_revenue_but_not_the_doctors_cash_fee(): void
    {
        $booking = $this->bookInsuranceCase();
        $claim = InsuranceClaim::where('booking_id', $booking->id)->sole();
        $action = app(UpdateInsuranceClaimAction::class);

        $action->execute($claim, ['status' => 'submitted']);
        $action->execute($claim->refresh(), ['status' => 'rejected', 'rejection_reason' => 'طبي']);

        $this->assertEquals(0.0, $this->balance('1031'));
        $this->assertEquals(0.0, $this->balance('4120'));
        $this->assertEquals(500.0, $this->balance('5130'));
    }

    public function test_cancelling_the_booking_reverses_the_claim_recognition(): void
    {
        $booking = $this->bookInsuranceCase();

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/status", ['status' => 'cancelled', 'cancel_reason' => 'x'])->assertRedirect();

        $this->assertEquals(0.0, $this->balance('1031'));
        $this->assertEquals(0.0, $this->balance('4120'));
    }

    public function test_editing_the_insured_amount_re_recognizes_at_the_new_amount(): void
    {
        $booking = $this->bookInsuranceCase();

        $this->actingAs($this->user)->put("/booking/{$booking->id}", $this->bookingPayload(['price' => 7000, 'ins_amount' => 7000]))->assertRedirect();

        $this->assertEquals(7000.0, $this->balance('1031'));
        $this->assertEquals(7000.0, $this->balance('4120'));
        $this->assertSame(1, JournalEntry::where('source', 'insurance_claim')->whereNull('reversed_at')->whereNull('reversal_of_id')->count());
    }

    public function test_insurance_doctor_fee_is_refused_without_a_matching_insurance_revenue(): void
    {
        $booking = Booking::create([
            'file_no' => 'P-99-001', 'patient_name' => 'مريض', 'dept' => 'labs', 'visit_date' => '2026-05-08',
            'price' => 6500, 'paid_amount' => 0, 'pay_method' => 'insurance', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ]);

        $this->expectException(AccountingException::class);

        app(AutoPostInsuranceDoctorCashPaymentAction::class)->execute(500, 'د. تأمين', $booking->file_no);
    }

    public function test_5130_cannot_be_posted_outside_the_insurance_cycle(): void
    {
        $this->expectException(AccountingException::class);

        app(JournalService::class)->record([
            'date' => '2026-05-08',
            'description' => 'manual',
            'debit_account_id' => Account::where('code', '5130')->value('id'),
            'credit_account_id' => Account::where('code', '1010')->value('id'),
            'amount' => 500,
        ]);
    }

    private function bookInsuranceCase(): Booking
    {
        $this->actingAs($this->user)->post('/booking', $this->bookingPayload())->assertRedirect()->assertSessionHasNoErrors();

        return Booking::latest('id')->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function bookingPayload(array $overrides = []): array
    {
        return [
            'patient_name' => 'مريض تأمين', 'dept' => 'labs', 'eye_side' => 'OD', 'visit_date' => '2026-05-08',
            'service_id' => $this->service->id, 'service_name' => $this->service->name,
            'doctor_id' => $this->doctor->id, 'price' => 6500, 'ins_amount' => 6500, 'ins_company_id' => $this->company->id,
            'pay_method' => 'insurance', 'pay_status' => 'unpaid', 'status' => 'waiting',
            ...$overrides,
        ];
    }

    private function balance(string $code): float
    {
        return (float) Account::where('code', $code)->value('balance');
    }

    /** @return array<int, string> */
    private function doctorSubledgerIds(): array
    {
        return Account::where('parent_id', Account::where('code', '2010')->value('id'))->pluck('id')->all();
    }
}
