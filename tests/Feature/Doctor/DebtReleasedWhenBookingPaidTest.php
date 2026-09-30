<?php

namespace Tests\Feature\Doctor;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Enums\PayMethod;
use Modules\Booking\Enums\PayStatus;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Doctor\Enums\DebtEntryType;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Models\DoctorDebtSettlement;
use Modules\Doctor\Services\DoctorClaimsService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Debt is booked when a case is created unpaid, because the hospital expected
 * to collect and didn't. When the patient then pays that same case in full the
 * premise is gone — nothing is owed back — so the debt must be released, not
 * netted off the doctor's share. Netting left a fully-paid case still showing
 * a "خصم مديونية الطبيب" line, which reads as the doctor being charged for a
 * case that was collected in full.
 *
 * Debt belonging to *other* cases is untouched: that is a genuine outstanding
 * liability and is still settled out of this share.
 */
class DebtReleasedWhenBookingPaidTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'booking.pay', 'guard_name' => 'web']));
        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->doctor = Doctor::create([
            'name' => 'د. اختبار', 'fee_type' => 'fixed', 'fee_value' => 500, 'is_active' => true,
        ]);
    }

    private function makeBooking(float $price, float $paid = 0.0): Booking
    {
        $service = Service::create(['name' => 'عملية', 'dept' => 'surgery', 'price' => $price]);

        return Booking::create([
            'file_no' => 'P-RL-'.Booking::count() + 1,
            'patient_name' => 'مريض',
            'dept' => 'surgery',
            'doctor_id' => $this->doctor->id,
            'visit_date' => '2026-09-30',
            'service_id' => $service->id,
            'service_name' => $service->name,
            'eye_side' => 'OD',
            'price' => $price,
            'discount' => 0,
            'ins_amount' => 0,
            'paid_amount' => $paid,
            'pay_method' => PayMethod::Cash,
            'pay_status' => $paid > 0 ? PayStatus::Partial : PayStatus::Unpaid,
            'status' => 'confirmed',
        ]);
    }

    private function pay(Booking $booking, float $amount): void
    {
        $this->actingAs($this->user)
            ->patch(route('booking.pay', $booking->id), [
                'paid_amount' => $amount,
                'pay_method' => 'cash',
            ])
            ->assertRedirect();
    }

    public function test_paying_a_debt_bearing_booking_in_full_clears_the_doctors_debt(): void
    {
        $booking = $this->makeBooking(10000);
        $this->doctor->incurDebtForBooking($booking->id, 1085);
        $this->assertEquals(1085.0, (float) $this->doctor->fresh()->doctor_debt_balance);

        $this->pay($booking, 10000);

        $this->assertEquals(0.0, (float) $this->doctor->fresh()->doctor_debt_balance);
        $this->assertSame(0, DoctorDebtSettlement::where('booking_id', $booking->id)->count());
    }

    public function test_a_fully_paid_case_reports_no_debt_deduction(): void
    {
        $booking = $this->makeBooking(10000);
        $this->doctor->incurDebtForBooking($booking->id, 1085);

        $this->pay($booking, 10000);

        $row = collect(app(DoctorClaimsService::class)
            ->calculateClaims($this->doctor->id, '2026-01-01', '2026-12-31')['rows'])
            ->firstWhere('booking_id', $booking->id);

        // The receipt must not carry a "خصم مديونية الطبيب" line at all.
        $this->assertEquals(0.0, $row['debt_settled']);
        $this->assertEquals($row['gross_dr_share'], $row['dr_share']);
    }

    public function test_the_doctor_still_earns_the_whole_share_on_the_paid_booking(): void
    {
        $booking = $this->makeBooking(10000);
        $this->doctor->incurDebtForBooking($booking->id, 1085);

        $this->pay($booking, 10000);

        $row = collect(app(DoctorClaimsService::class)
            ->calculateClaims($this->doctor->id, '2026-01-01', '2026-12-31')['rows'])
            ->firstWhere('booking_id', $booking->id);

        $this->assertGreaterThan(0.0, $row['dr_share']);
    }

    public function test_a_partial_payment_does_not_release_the_debt_early(): void
    {
        $booking = $this->makeBooking(10000);
        $this->doctor->incurDebtForBooking($booking->id, 1085);

        $this->pay($booking, 4000);

        $this->assertSame(PayStatus::Partial, $booking->refresh()->pay_status);

        // Still only partly collected, so the debt was settled against the
        // share — never released. The 'incurred' row is what distinguishes the
        // two: a release deletes it, a settlement leaves it in place.
        $this->assertNotNull(
            DoctorDebtSettlement::where('booking_id', $booking->id)
                ->where('type', DebtEntryType::Incurred->value)
                ->first()
        );
    }

    public function test_debt_from_other_unpaid_cases_is_still_settled_and_kept(): void
    {
        $paid = $this->makeBooking(10000);
        $unpaid = $this->makeBooking(5000);

        // 3,000 of debt raised by a *different* case.
        $this->doctor->incurDebtForBooking($unpaid->id, 3000);

        $this->pay($paid, 10000);

        $this->assertEquals(0.0, (float) $this->doctor->fresh()->doctor_debt_balance);

        // The other case's debt row survives — it is a real liability that was
        // cleared by this collection, not voided.
        $row = DoctorDebtSettlement::where('booking_id', $unpaid->id)
            ->where('type', DebtEntryType::Incurred->value)
            ->first();
        $this->assertNotNull($row);
    }

    public function test_release_never_pushes_the_balance_negative(): void
    {
        $booking = $this->makeBooking(10000);
        $this->doctor->incurDebtForBooking($booking->id, 1000);
        // Balance manually drained elsewhere; the release must clamp.
        $this->doctor->update(['doctor_debt_balance' => 400]);

        $this->assertEquals(400.0, $this->doctor->releaseDebtForBooking($booking->id));
        $this->assertEquals(0.0, (float) $this->doctor->fresh()->doctor_debt_balance);
    }
}
