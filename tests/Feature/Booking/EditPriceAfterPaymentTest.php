<?php

namespace Tests\Feature\Booking;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Actions\ReverseBookingPaymentAction;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Enums\TreasuryType;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\TreasuryEntry;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Doctor\Models\Doctor;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EditPriceAfterPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        foreach (['booking.pay', 'booking.edit'] as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }

        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->doctor = Doctor::create(['name' => 'د. أحمد', 'fee_type' => 'percentage', 'fee_value' => 50]);
    }

    private function createBooking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'file_no' => 'MRN-'.uniqid(),
            'patient_name' => 'محمد علي',
            'dept' => 'clinic',
            'eye_side' => 'OD',
            'doctor_id' => $this->doctor->id,
            'visit_date' => '2026-04-20',
            'price' => 500,
            'discount' => 0,
            'ins_amount' => 0,
            'paid_amount' => 0,
            'pay_method' => 'cash',
            'pay_status' => 'unpaid',
            'status' => 'waiting',
            'created_by' => $this->user->id,
        ], $overrides));
    }

    private function payload(Booking $booking, array $overrides = []): array
    {
        return array_merge([
            'patient_name' => $booking->patient_name,
            'dept' => 'clinic',
            'eye_side' => 'OD',
            'doctor_id' => $booking->doctor_id,
            'service_id' => $booking->service_id,
            'visit_date' => '2026-04-20',
            'price' => 500,
            'discount' => 0,
            'ins_amount' => 0,
            'paid_amount' => 500,
            'pay_method' => 'cash',
            'pay_status' => 'paid',
            'status' => 'waiting',
        ], $overrides);
    }

    private function payInFull(Booking $booking, float $amount = 500): void
    {
        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'paid_amount' => $amount,
            'pay_method' => 'cash',
        ])->assertRedirect();
    }

    private function liveRevenue(Booking $booking): float
    {
        return (float) JournalEntry::where('reference', $booking->file_no)
            ->whereIn('source', [JournalSource::BOOKING->value, JournalSource::AUTO_BOOKING->value])
            ->whereNull('reversed_at')
            ->sum('amount');
    }

    private function liveDoctorDues(Booking $booking): float
    {
        return (float) JournalEntry::where('reference', $booking->file_no)
            ->where('source', JournalSource::DOCTOR_SHIFT->value)
            ->where('idempotency_key', 'like', 'doctor_dues:%')
            ->whereNull('reversed_at')
            ->whereNull('reversal_of_id')
            ->sum('amount');
    }

    private function netTreasury(Booking $booking): float
    {
        $in = (float) TreasuryEntry::where('booking_id', $booking->id)->where('type', TreasuryType::In->value)->sum('amount');
        $out = (float) TreasuryEntry::where('booking_id', $booking->id)->where('type', TreasuryType::Out->value)->sum('amount');

        return round($in - $out, 2);
    }

    public function test_lowering_price_after_payment_refunds_and_reduces_revenue_and_doctor_dues(): void
    {
        $booking = $this->createBooking();
        $this->payInFull($booking);

        $this->assertEquals(500, $this->liveRevenue($booking));
        $this->assertEquals(250, $this->liveDoctorDues($booking));

        $this->actingAs($this->user)
            ->put("/booking/{$booking->id}", $this->payload($booking, ['price' => 400, 'paid_amount' => 400]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $booking->refresh();
        $this->assertEquals(400, (float) $booking->price);
        $this->assertEquals(400, (float) $booking->paid_amount);
        $this->assertEquals(400, $this->liveRevenue($booking));
        $this->assertEquals(400, $this->netTreasury($booking));
        $this->assertEquals(200, $this->liveDoctorDues($booking));
    }

    public function test_raising_price_after_payment_posts_extra_revenue_and_doctor_dues(): void
    {
        $booking = $this->createBooking();
        $this->payInFull($booking);

        $this->actingAs($this->user)
            ->put("/booking/{$booking->id}", $this->payload($booking, ['price' => 600, 'paid_amount' => 600]))
            ->assertSessionHasNoErrors();

        $this->assertEquals(600, $this->liveRevenue($booking));
        $this->assertEquals(600, $this->netTreasury($booking));
        $this->assertEquals(300, $this->liveDoctorDues($booking));
    }

    public function test_repeated_price_edits_keep_ledger_in_sync(): void
    {
        $booking = $this->createBooking();
        $this->payInFull($booking);

        foreach ([400, 500, 400, 700] as $price) {
            $this->actingAs($this->user)
                ->put("/booking/{$booking->id}", $this->payload($booking, ['price' => $price, 'paid_amount' => $price]))
                ->assertSessionHasNoErrors();
        }

        $this->assertEquals(700, $this->liveRevenue($booking));
        $this->assertEquals(700, $this->netTreasury($booking));
        $this->assertEquals(350, $this->liveDoctorDues($booking));
    }

    public function test_resubmitting_the_same_amounts_posts_nothing_new(): void
    {
        $booking = $this->createBooking();
        $this->payInFull($booking);
        $entriesBefore = JournalEntry::count();

        $this->actingAs($this->user)
            ->put("/booking/{$booking->id}", $this->payload($booking, ['visit_note' => 'ملاحظة']))
            ->assertSessionHasNoErrors();

        $this->assertSame($entriesBefore, JournalEntry::count());
    }

    public function test_typed_price_overrides_service_price_after_payment(): void
    {
        $service = Service::create(['name' => 'كشف', 'dept' => 'clinic', 'price' => 500, 'one_eye_price' => 500]);
        $booking = $this->createBooking(['service_id' => $service->id]);
        $this->payInFull($booking);

        $this->actingAs($this->user)
            ->put("/booking/{$booking->id}", $this->payload($booking, ['price' => 450, 'paid_amount' => 450]))
            ->assertSessionHasNoErrors();

        $this->assertEquals(450, (float) $booking->fresh()->price);
    }

    public function test_unpaid_booking_still_uses_service_price(): void
    {
        $service = Service::create(['name' => 'كشف', 'dept' => 'clinic', 'price' => 500, 'one_eye_price' => 500]);
        $booking = $this->createBooking(['service_id' => $service->id]);

        $this->actingAs($this->user)
            ->put("/booking/{$booking->id}", $this->payload($booking, [
                'price' => 100, 'paid_amount' => 0, 'pay_status' => 'unpaid',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertEquals(500, (float) $booking->fresh()->price);
    }

    public function test_paid_booking_without_posted_revenue_is_not_back_posted(): void
    {
        $booking = $this->createBooking(['paid_amount' => 500, 'pay_status' => 'paid']);

        $this->actingAs($this->user)
            ->put("/booking/{$booking->id}", $this->payload($booking, ['price' => 400, 'paid_amount' => 400]))
            ->assertSessionHasNoErrors();

        $this->assertEquals(400, (float) $booking->fresh()->price);
        $this->assertSame(0, JournalEntry::where('reference', $booking->file_no)->count());
        $this->assertSame(0, TreasuryEntry::where('booking_id', $booking->id)->count());
    }

    public function test_cancelling_after_price_edit_refunds_only_what_remains(): void
    {
        $booking = $this->createBooking();
        $this->payInFull($booking);

        $this->actingAs($this->user)
            ->put("/booking/{$booking->id}", $this->payload($booking, ['price' => 400, 'paid_amount' => 400]))
            ->assertSessionHasNoErrors();

        app(ReverseBookingPaymentAction::class)->execute($booking->fresh());

        $this->assertEquals(0, $this->liveRevenue($booking));
        $this->assertEquals(0, $this->liveDoctorDues($booking));
        $this->assertEquals(0, $this->netTreasury($booking));
    }
}
