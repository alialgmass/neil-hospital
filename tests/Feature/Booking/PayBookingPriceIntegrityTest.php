<?php

namespace Tests\Feature\Booking;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Enums\PayMethod;
use Modules\Booking\Enums\PayStatus;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Doctor\Models\Doctor;
use Modules\Surgery\Models\Surgery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A payment records cash coming in. It must never re-price the booking.
 *
 * PayBookingController used to set price = paid_amount and then compare the
 * running paid total against netDue derived from that same payment amount.
 * Two consequences, both fixed here:
 *
 *  - paying 3,000 on a 10,000 booking compared 3,000 >= 3,000 and stamped
 *    the booking 'paid' while 7,000 was still outstanding;
 *  - the rewritten price then became the basis for debtForZeroPayment()'s
 *    outstanding cap, so a later 0 write-off was capped against a price the
 *    booking was never agreed at.
 */
class PayBookingPriceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private Doctor $doctor;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'booking.pay', 'guard_name' => 'web']));

        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->doctor = Doctor::create([
            'name' => 'د. اختبار',
            'fee_type' => 'fixed',
            'fee_value' => 416,
            'is_active' => true,
        ]);
    }

    private function makeBooking(array $overrides = []): Booking
    {
        $service = Service::create([
            'name' => 'عملية تجريبية',
            'dept' => 'surgery',
            'price' => 10000,
        ]);

        return Booking::create(array_merge([
            'file_no' => 'P-T-'.Booking::count() + 1,
            'patient_name' => 'مريض',
            'dept' => 'surgery',
            'doctor_id' => $this->doctor->id,
            'visit_date' => '2026-09-30',
            'service_id' => $service->id,
            'service_name' => $service->name,
            'eye_side' => 'OD',
            'price' => 10000,
            'discount' => 0,
            'ins_amount' => 0,
            'paid_amount' => 0,
            'pay_method' => PayMethod::Cash,
            'pay_status' => PayStatus::Unpaid,
            'status' => 'confirmed',
        ], $overrides));
    }

    public function test_a_partial_payment_does_not_mark_the_booking_paid(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->user)->patchJson(route('booking.pay', $booking->id), [
            'paid_amount' => 3000,
            'pay_method' => 'cash',
        ])->assertRedirect();

        $booking->refresh();

        $this->assertSame(PayStatus::Partial, $booking->pay_status);
        $this->assertEquals(3000.0, (float) $booking->paid_amount);
    }

    public function test_the_agreed_price_survives_a_partial_payment(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->user)->patchJson(route('booking.pay', $booking->id), [
            'paid_amount' => 3000,
            'pay_method' => 'cash',
        ])->assertRedirect();

        $this->assertEquals(10000.0, (float) $booking->refresh()->price);
    }

    public function test_installments_accumulate_until_the_real_price_is_reached(): void
    {
        $booking = $this->makeBooking();
        $route = route('booking.pay', $booking->id);

        $this->actingAs($this->user)->patchJson($route, ['paid_amount' => 3000, 'pay_method' => 'cash'])->assertRedirect();
        $this->assertSame(PayStatus::Partial, $booking->refresh()->pay_status);

        $this->actingAs($this->user)->patchJson($route, ['paid_amount' => 3000, 'pay_method' => 'cash'])->assertRedirect();
        $this->assertSame(PayStatus::Partial, $booking->refresh()->pay_status);

        $this->actingAs($this->user)->patchJson($route, ['paid_amount' => 4000, 'pay_method' => 'cash'])->assertRedirect();
        $booking->refresh();

        $this->assertSame(PayStatus::Paid, $booking->pay_status);
        $this->assertEquals(10000.0, (float) $booking->paid_amount);
        $this->assertEquals(10000.0, (float) $booking->price);
    }

    public function test_discount_and_insurance_reduce_the_amount_that_counts_as_settled(): void
    {
        // 10,000 price, 1,000 discount, 2,000 insurance => 7,000 owed.
        $booking = $this->makeBooking(['discount' => 1000, 'ins_amount' => 2000]);

        $this->actingAs($this->user)->patchJson(route('booking.pay', $booking->id), [
            'paid_amount' => 6000,
            'pay_method' => 'cash',
        ])->assertRedirect();

        $this->assertSame(PayStatus::Partial, $booking->refresh()->pay_status);
    }

    public function test_paying_more_than_the_remaining_balance_is_rejected(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->user)->patchJson(route('booking.pay', $booking->id), [
            'paid_amount' => 20000,
            'pay_method' => 'cash',
        ])->assertStatus(422)->assertJsonValidationErrors('paid_amount');

        $booking->refresh();

        $this->assertEquals(10000.0, (float) $booking->price);
        $this->assertEquals(0.0, (float) $booking->paid_amount);
        $this->assertSame(PayStatus::Unpaid, $booking->pay_status);
    }

    public function test_the_cap_is_measured_against_what_is_left_after_earlier_payments(): void
    {
        $booking = $this->makeBooking();
        $route = route('booking.pay', $booking->id);

        $this->actingAs($this->user)->patchJson($route, ['paid_amount' => 8000, 'pay_method' => 'cash'])
            ->assertRedirect();

        // Only 2,000 is left — a further 5,000 is an overpayment.
        $this->actingAs($this->user)->patchJson($route, ['paid_amount' => 5000, 'pay_method' => 'cash'])
            ->assertStatus(422)->assertJsonValidationErrors('paid_amount');

        $this->assertEquals(8000.0, (float) $booking->refresh()->paid_amount);
        $this->assertSame(PayStatus::Partial, $booking->pay_status);
    }

    public function test_the_cap_still_allows_a_zero_write_off(): void
    {
        // 0 is the write-off signal, not an overpayment — it must never be
        // rejected by the remaining-balance cap.
        $booking = $this->makeBooking(['doctor_id' => null]);

        $this->actingAs($this->user)->patchJson(route('booking.pay', $booking->id), [
            'paid_amount' => 0,
            'pay_method' => 'cash',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(PayStatus::Paid, $booking->refresh()->pay_status);
    }

    public function test_a_zero_write_off_caps_against_the_real_price_not_a_rewritten_one(): void
    {
        $booking = $this->makeBooking();
        Surgery::create([
            'booking_id' => $booking->id,
            'dept' => 'surgery',
            'supply_total' => 0,
            'supplies_used' => json_encode([]),
        ]);

        // Collect part of the price, so a naive reader might expect the
        // remaining 7,000 to become debt.
        $this->actingAs($this->user)->patchJson(route('booking.pay', $booking->id), [
            'paid_amount' => 3000,
            'pay_method' => 'cash',
        ])->assertRedirect();
        $this->assertEquals(10000.0, (float) $booking->refresh()->price);

        $this->actingAs($this->user)->patchJson(route('booking.pay', $booking->id), [
            'paid_amount' => 0,
            'pay_method' => 'cash',
        ])->assertRedirect();

        $booking->refresh();

        $this->assertSame(PayStatus::Paid, $booking->pay_status);
        $this->assertEquals(10000.0, (float) $booking->price);
        $this->assertEquals(3000.0, (float) $booking->paid_amount);
    }
}
