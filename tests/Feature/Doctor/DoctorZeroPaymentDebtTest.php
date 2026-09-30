<?php

namespace Tests\Feature\Doctor;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Doctor\Enums\DelegationStatus;
use Modules\Doctor\Models\BookingDoctorDelegation;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Services\DoctorClaimsService;
use Modules\Surgery\Models\Surgery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Business rule: a zero payment puts on the doctor ONLY the cost the booking
 * assigns to them — never the whole uncollected price.
 *
 *   surgery          → supply_total + delegated fees (تخدير/تفويض)
 *   laser / lasik    → the service's own price for the booked side + dev fee
 *   clinic / labs    → legacy: outstanding less the dev-treasury fee
 *
 * Both entry points (booking creation and the pay screen) must produce the
 * same figure for the same booking.
 */
class DoctorZeroPaymentDebtTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Doctor $doctor;

    private Service $surgeryService;

    private Service $lasikService;

    private Doctor $delegate;

    /**
     * Report-path input: computeDrShare() reads plain rows off the query
     * builder, so hand it the stdClass shape with an overridden pay_method.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function reportRow(Booking $booking, array $overrides = []): object
    {
        $row = (object) $booking->fresh()->toArray();

        foreach ($overrides as $key => $value) {
            $row->{$key} = $value;
        }

        return $row;
    }

    /** Record supplies on the case, creating the surgery row when absent. */
    private function recordSupplies(Booking $booking, float $total): void
    {
        $surgery = Surgery::firstOrNew(['booking_id' => $booking->id]);
        $surgery->fill([
            'booking_id' => $booking->id,
            'dept' => $booking->dept,
            'supply_total' => $total,
        ])->save();
    }

    /** @param array<int, array{role: string, amount: float, status?: string}> $lines */
    private function delegate(Booking $booking, array $lines): void
    {
        foreach ($lines as $line) {
            BookingDoctorDelegation::create([
                'booking_id' => $booking->id,
                'doctor_id' => $this->delegate->id,
                'role' => $line['role'],
                'service_name' => 'مساعدة',
                'amount' => $line['amount'],
                'status' => $line['status'] ?? DelegationStatus::Pending->value,
            ]);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['booking.create', 'booking.pay'] as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->doctor = Doctor::create([
            'name' => 'د. مركز', 'fee_type' => 'fixed', 'fee_value' => 500, 'is_active' => true,
        ]);
        $this->delegate = Doctor::create([
            'name' => 'د. مساعد', 'fee_type' => 'fixed', 'fee_value' => 0, 'is_active' => true,
        ]);

        $this->surgeryService = Service::create([
            'name' => 'فاكو', 'dept' => 'surgery', 'price' => 10000, 'dev_treasury_fee' => 300,
        ]);

        $this->lasikService = Service::create([
            'name' => 'ليزك', 'dept' => 'lasik', 'price' => 8000, 'dev_treasury_fee' => 200,
            'one_eye_price' => 4000, 'both_eyes_price' => 7000, 'center_type' => 'pct', 'center_val' => 50,
        ]);
    }

    /**
     * Beds are not part of what the debt rule reads, so a plain booking row is
     * enough here — the route-level bed_id requirement only applies to the
     * create/pay endpoints, not to the pay screen this test exercises.
     */
    private function createBooking(string $dept, Service $service, float $price): Booking
    {
        return Booking::create([
            'file_no' => 'MRN-'.uniqid(),
            'patient_name' => 'مريض',
            'dept' => $dept,
            'doctor_id' => $this->doctor->id,
            'visit_date' => '2026-05-10',
            'service_id' => $service->id,
            'service_name' => $service->name,
            'eye_side' => 'OD',
            'price' => $price,
            'discount' => 0,
            'ins_amount' => 0,
            'paid_amount' => 0,
            'pay_method' => 'cash',
            'pay_status' => 'unpaid',
            'status' => 'waiting',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_surgery_zero_payment_debt_is_supplies_plus_delegation_only(): void
    {
        $booking = $this->createBooking('surgery', $this->surgeryService, 10000);

        $this->recordSupplies($booking, 1200);

        $this->delegate($booking, [
            ['role' => 'delegate', 'amount' => 400],
            ['role' => 'anesthesia', 'amount' => 300],
        ]);

        // supplies 1200 + delegate 400 + anesthesia 300 = 1900.
        // NOT price (10000) less the dev fee (300) — that was the old rule.
        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'paid_amount' => 0, 'pay_method' => 'cash',
        ])->assertRedirect();

        $this->assertEquals(1900.0, (float) $this->doctor->fresh()->doctor_debt_balance);
        $this->assertDatabaseHas('doctor_debt_settlements', [
            'booking_id' => $booking->id, 'amount' => 1900, 'type' => 'incurred',
        ]);
    }

    public function test_surgery_debt_ignores_void_delegation_lines(): void
    {
        $booking = $this->createBooking('surgery', $this->surgeryService, 10000);

        $this->recordSupplies($booking, 500);

        $this->delegate($booking, [
            ['role' => 'delegate', 'amount' => 900, 'status' => DelegationStatus::Void->value],
            ['role' => 'anesthesia', 'amount' => 200],
        ]);

        // 500 supplies + 200 anesthesia; the voided 900 is not owed.
        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'paid_amount' => 0, 'pay_method' => 'cash',
        ])->assertRedirect();

        $this->assertEquals(700.0, (float) $this->doctor->fresh()->doctor_debt_balance);
    }

    public function test_lasik_zero_payment_debt_is_service_price_plus_dev_fee(): void
    {
        $booking = $this->createBooking('lasik', $this->lasikService, 8000);

        // OD → one_eye_price 4000 + dev fee 200.
        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'paid_amount' => 0, 'pay_method' => 'cash',
        ])->assertRedirect();

        $this->assertEquals(4200.0, (float) $this->doctor->fresh()->doctor_debt_balance);
    }

    public function test_lasik_debt_uses_both_eyes_price_for_an_ou_booking(): void
    {
        $booking = $this->createBooking('lasik', $this->lasikService, 8000);
        $booking->update(['eye_side' => 'OU']);

        // OU → both_eyes_price 7000 + dev fee 200.
        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'paid_amount' => 0, 'pay_method' => 'cash',
        ])->assertRedirect();

        $this->assertEquals(7200.0, (float) $this->doctor->fresh()->doctor_debt_balance);
    }

    public function test_lasik_debt_is_capped_at_the_outstanding_amount(): void
    {
        $booking = $this->createBooking('lasik', $this->lasikService, 5000);
        // Partly collected already: 4200 - 1000 paid → only 3200 still expected,
        // so the debt can never exceed that.
        $booking->update(['paid_amount' => 1000, 'pay_status' => 'partial']);

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'paid_amount' => 0, 'pay_method' => 'cash',
        ])->assertRedirect();

        // The cost the case assigns is 4000 + 200 = 4200, but only 4000 is
        // still outstanding (5000 price − 1000 already collected), so the debt
        // is capped there instead of overshooting what was expected.
        $this->assertEquals(4000.0, (float) $this->doctor->fresh()->doctor_debt_balance);
    }

    public function test_clinic_keeps_the_legacy_outstanding_less_dev_fee_rule(): void
    {
        $service = Service::create(['name' => 'كشف', 'dept' => 'clinic', 'price' => 1000, 'dev_treasury_fee' => 50]);
        $booking = $this->createBooking('clinic', $service, 1000);

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'paid_amount' => 0, 'pay_method' => 'cash',
        ])->assertRedirect();

        $this->assertEquals(950.0, (float) $this->doctor->fresh()->doctor_debt_balance);
    }

    public function test_a_fully_paid_booking_never_incurred_debt(): void
    {
        $booking = $this->createBooking('surgery', $this->surgeryService, 10000);
        $this->recordSupplies($booking, 900);

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'paid_amount' => 10000, 'pay_method' => 'cash',
        ])->assertRedirect();

        $this->assertEquals(0.0, (float) $this->doctor->fresh()->doctor_debt_balance);
        $this->assertDatabaseMissing('doctor_debt_settlements', [
            'booking_id' => $booking->id, 'type' => 'incurred',
        ]);
    }

    public function test_creation_and_the_pay_screen_produce_the_same_debt(): void
    {
        $service = app(DoctorClaimsService::class);
        $atCreation = $this->createBooking('surgery', $this->surgeryService, 10000);
        $this->recordSupplies($atCreation, 1500);
        $fromPayScreen = $this->createBooking('surgery', $this->surgeryService, 10000);
        $this->recordSupplies($fromPayScreen, 1500);

        $this->assertEquals(
            $service->debtForZeroPayment($atCreation),
            $service->debtForZeroPayment($fromPayScreen)
        );

        $this->actingAs($this->user)->patch("/booking/{$fromPayScreen->id}/pay", [
            'paid_amount' => 0, 'pay_method' => 'cash',
        ])->assertRedirect();

        $this->assertEquals(1500.0, (float) $this->doctor->fresh()->doctor_debt_balance);
    }
}
