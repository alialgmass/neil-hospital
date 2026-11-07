<?php

namespace Tests\Feature\Doctor;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Booking\Enums\PayMethod;
use Modules\Booking\Enums\PayStatus;
use Modules\Booking\Models\Booking;
use Modules\Doctor\Actions\SyncBookingDoctorDelegationsAction;
use Modules\Doctor\Enums\DelegationRole;
use Modules\Doctor\Enums\DelegationStatus;
use Modules\Doctor\Models\BookingDoctorDelegation;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Services\DoctorClaimsService;
use Tests\TestCase;

/**
 * The booking's own doctor can never be its delegate/anesthetist.
 *
 * Self-delegation is a no-op that still costs real money in the books: the
 * fee is withheld from the primary doctor's share and then re-credited to the
 * same person, while the zero-payment debt rule adds every delegated fee to
 * what the doctor owes. Both the dropdowns and the backend now refuse it.
 */
class SelfDelegationGuardTest extends TestCase
{
    use RefreshDatabase;

    private Doctor $primary;

    private Doctor $other;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->primary = Doctor::create([
            'name' => 'د. أساسي', 'fee_type' => 'fixed', 'fee_value' => 500, 'is_active' => true,
        ]);
        $this->other = Doctor::create([
            'name' => 'د. آخر', 'fee_type' => 'fixed', 'fee_value' => 0, 'is_active' => true,
        ]);

        $this->booking = Booking::create([
            'file_no' => 'P-SD-1',
            'patient_name' => 'مريض',
            'dept' => 'surgery',
            'doctor_id' => $this->primary->id,
            'visit_date' => '2026-09-30',
            'eye_side' => 'OD',
            'price' => 10000,
            'discount' => 0,
            'ins_amount' => 0,
            'paid_amount' => 0,
            'pay_method' => PayMethod::Cash,
            'pay_status' => PayStatus::Unpaid,
            'status' => 'confirmed',
        ]);
    }

    private function line(string $doctorId, DelegationRole $role, float $amount): array
    {
        return [
            'doctor_id' => $doctorId,
            'role' => $role->value,
            'service_id' => null,
            'service_name' => 'مساعدة',
            'amount' => $amount,
        ];
    }

    public function test_delegating_to_the_primary_doctor_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        app(SyncBookingDoctorDelegationsAction::class)->execute($this->booking, [
            $this->line($this->primary->id, DelegationRole::Delegate, 500),
        ]);
    }

    public function test_recording_the_primary_doctor_as_anesthetist_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        app(SyncBookingDoctorDelegationsAction::class)->execute($this->booking, [
            $this->line($this->primary->id, DelegationRole::Anesthesia, 300),
        ]);
    }

    public function test_a_rejected_self_delegation_writes_nothing(): void
    {
        try {
            app(SyncBookingDoctorDelegationsAction::class)->execute($this->booking, [
                $this->line($this->other->id, DelegationRole::Delegate, 100),
                $this->line($this->primary->id, DelegationRole::Anesthesia, 300),
            ]);
            $this->fail('Expected the self-delegation to be rejected');
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame(0, BookingDoctorDelegation::where('booking_id', $this->booking->id)->count());
    }

    public function test_delegating_to_another_doctor_still_works(): void
    {
        app(SyncBookingDoctorDelegationsAction::class)->execute($this->booking, [
            $this->line($this->other->id, DelegationRole::Delegate, 100),
            $this->line($this->other->id, DelegationRole::Anesthesia, 300),
        ]);

        $this->assertSame(2, BookingDoctorDelegation::where('booking_id', $this->booking->id)->count());
        $this->assertEquals(400.0, app(DoctorClaimsService::class)->delegatedTotal($this->booking->id));
    }

    public function test_a_booking_without_a_primary_doctor_can_still_be_delegated(): void
    {
        $this->booking->update(['doctor_id' => null]);

        app(SyncBookingDoctorDelegationsAction::class)->execute($this->booking, [
            $this->line($this->other->id, DelegationRole::Delegate, 100),
        ]);

        $this->assertSame(1, BookingDoctorDelegation::where('booking_id', $this->booking->id)->count());
    }

    public function test_existing_settled_self_delegations_are_not_re_created_or_removed(): void
    {
        // Guards legacy rows (saved before this rule existed) from being
        // destroyed or duplicated on the next schedule save.
        BookingDoctorDelegation::create([
            'booking_id' => $this->booking->id,
            'doctor_id' => $this->primary->id,
            'role' => DelegationRole::Delegate,
            'service_name' => 'قديم',
            'amount' => 77,
            'status' => DelegationStatus::Settled,
        ]);

        app(SyncBookingDoctorDelegationsAction::class)->execute($this->booking, [
            $this->line($this->other->id, DelegationRole::Anesthesia, 300),
        ]);

        $rows = BookingDoctorDelegation::where('booking_id', $this->booking->id)->get();

        $this->assertCount(2, $rows);
        $this->assertEquals(77.0, (float) $rows->firstWhere('status', DelegationStatus::Settled->value)->amount);
    }
}
