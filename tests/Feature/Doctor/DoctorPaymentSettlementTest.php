<?php

namespace Tests\Feature\Doctor;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\JournalEntry;
use Modules\Booking\Models\Booking;
use Modules\Doctor\Actions\RecordDoctorPaymentAction;
use Modules\Doctor\Actions\SettleDoctorEntitlementsAction;
use Modules\Doctor\Actions\SyncBookingDoctorDuesAction;
use Modules\Doctor\Actions\SyncDoctorEntitlementAction;
use Modules\Doctor\Enums\EntitlementSource;
use Modules\Doctor\Enums\EntitlementStatus;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Models\DoctorEntitlement;
use Modules\Doctor\Models\DoctorPayment;
use Tests\TestCase;

class DoctorPaymentSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    public function test_payment_settles_a_fully_covered_entitlement(): void
    {
        $doctor = $this->makeDoctor();
        $this->makeEntitlement($doctor, 100, '2026-01-01');

        app(RecordDoctorPaymentAction::class)->execute($this->paymentData($doctor, 100));

        $this->assertDatabaseHas('doctor_entitlements', [
            'doctor_id' => $doctor->id,
            'status' => EntitlementStatus::Settled->value,
        ]);
    }

    public function test_payment_settles_the_two_oldest_of_three_entitlements(): void
    {
        $doctor = $this->makeDoctor();
        $oldest = $this->makeEntitlement($doctor, 100, '2026-01-01');
        $middle = $this->makeEntitlement($doctor, 100, '2026-01-02');
        $newest = $this->makeEntitlement($doctor, 100, '2026-01-03');

        app(RecordDoctorPaymentAction::class)->execute($this->paymentData($doctor, 200));

        $this->assertSame(EntitlementStatus::Settled, $oldest->fresh()->status);
        $this->assertSame(EntitlementStatus::Settled, $middle->fresh()->status);
        $this->assertSame(EntitlementStatus::Pending, $newest->fresh()->status);
    }

    public function test_partial_coverage_does_not_settle_the_next_entitlement(): void
    {
        $doctor = $this->makeDoctor();
        $first = $this->makeEntitlement($doctor, 100, '2026-01-01');
        $second = $this->makeEntitlement($doctor, 100, '2026-01-02');

        app(RecordDoctorPaymentAction::class)->execute($this->paymentData($doctor, 150));

        $this->assertSame(EntitlementStatus::Settled, $first->fresh()->status);
        $this->assertSame(EntitlementStatus::Pending, $second->fresh()->status);
    }

    public function test_settlement_action_is_idempotent_for_the_same_payment(): void
    {
        $doctor = $this->makeDoctor();
        $entitlement = $this->makeEntitlement($doctor, 100, '2026-01-01');
        $payment = app(RecordDoctorPaymentAction::class)->execute($this->paymentData($doctor, 100));

        app(SettleDoctorEntitlementsAction::class)->execute($payment);

        $this->assertSame(EntitlementStatus::Settled, $entitlement->fresh()->status);
        $this->assertSame(1, DoctorPayment::where('doctor_id', $doctor->id)->count());
    }

    public function test_settled_entitlement_is_untouched_by_booking_resync(): void
    {
        $doctor = $this->makeDoctor();
        $booking = $this->makeBooking($doctor, '2026-01-01');
        $entitlement = DoctorEntitlement::create([
            'booking_id' => $booking->id,
            'doctor_id' => $doctor->id,
            'amount' => 100,
            'source' => EntitlementSource::Contract,
            'status' => EntitlementStatus::Settled,
        ]);
        $journalCount = JournalEntry::count();

        app(SyncDoctorEntitlementAction::class)->execute($booking->fresh());
        app(SyncBookingDoctorDuesAction::class)->execute($booking->fresh());

        $this->assertSame(100.0, (float) $entitlement->fresh()->amount);
        $this->assertSame(EntitlementStatus::Settled, $entitlement->fresh()->status);
        $this->assertSame($journalCount, JournalEntry::count());
    }

    public function test_doctor_without_entitlements_is_unaffected(): void
    {
        $doctor = $this->makeDoctor();

        app(RecordDoctorPaymentAction::class)->execute($this->paymentData($doctor, 100));

        $this->assertDatabaseCount('doctor_entitlements', 0);
    }

    private function makeDoctor(): Doctor
    {
        return Doctor::create([
            'name' => 'Dr Settlement',
            'fee_type' => 'fixed',
            'fee_value' => 100,
            'is_active' => true,
        ]);
    }

    private function makeBooking(Doctor $doctor, string $visitDate): Booking
    {
        return Booking::create([
            'file_no' => 'SET-'.uniqid(),
            'patient_name' => 'Settlement patient',
            'dept' => 'clinic',
            'doctor_id' => $doctor->id,
            'visit_date' => $visitDate,
            'price' => 1000,
            'pay_method' => 'contract',
            'pay_status' => 'unpaid',
            'status' => 'completed',
        ]);
    }

    private function makeEntitlement(Doctor $doctor, float $amount, string $visitDate): DoctorEntitlement
    {
        return DoctorEntitlement::create([
            'booking_id' => $this->makeBooking($doctor, $visitDate)->id,
            'doctor_id' => $doctor->id,
            'amount' => $amount,
            'source' => EntitlementSource::Contract,
            'status' => EntitlementStatus::Pending,
        ]);
    }

    /** @return array<string, mixed> */
    private function paymentData(Doctor $doctor, float $amount): array
    {
        return [
            'doctor_id' => $doctor->id,
            'amount' => $amount,
            'method' => 'cash',
            'paid_at' => '2026-02-01',
        ];
    }
}
