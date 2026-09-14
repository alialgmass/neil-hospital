<?php

namespace Tests\Feature\Clinic;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\Booking;
use Modules\Clinic\Models\ClinicSheet;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReferPatientTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web']);
        $role->givePermissionTo([
            Permission::firstOrCreate(['name' => 'clinic.view', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'clinic.write', 'guard_name' => 'web']),
        ]);
        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    private function makeBooking(array $attrs = []): Booking
    {
        return Booking::create(array_merge([
            'file_no' => 'P-100-123',
            'patient_name' => 'مريض تجريبي',
            'patient_phone' => '01000000000',
            'national_id' => '29012345678901',
            'dept' => 'clinic',
            'visit_date' => today()->toDateString(),
            'price' => 100, 'discount' => 0, 'ins_amount' => 0, 'paid_amount' => 0,
            'pay_method' => 'cash', 'pay_status' => 'unpaid', 'status' => 'confirmed',
            'created_by' => $this->user->id,
        ], $attrs));
    }

    public function test_referring_sets_referral_to_on_the_clinic_sheet(): void
    {
        $booking = $this->makeBooking();
        ClinicSheet::create(['booking_id' => $booking->id, 'diagnosis' => 'إعتام عدسة', 'recorded_at' => now()]);

        $this->actingAs($this->user)
            ->post("/clinic/{$booking->id}/refer", ['referral_to' => 'surgery'])
            ->assertRedirect();

        $this->assertDatabaseHas((new ClinicSheet)->getTable(), [
            'booking_id' => $booking->id,
            'referral_to' => 'surgery',
        ]);
    }

    public function test_referring_creates_a_clinic_sheet_when_none_exists_yet(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->user)
            ->post("/clinic/{$booking->id}/refer", ['referral_to' => 'labs'])
            ->assertRedirect();

        $this->assertDatabaseHas((new ClinicSheet)->getTable(), [
            'booking_id' => $booking->id,
            'referral_to' => 'labs',
        ]);
    }

    public function test_create_follow_up_creates_a_same_day_booking_in_the_target_dept(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->user)
            ->post("/clinic/{$booking->id}/refer", ['referral_to' => 'labs', 'create_follow_up' => true])
            ->assertRedirect();

        $followUp = Booking::where('dept', 'labs')->where('id', '!=', $booking->id)->sole();
        $this->assertSame('مريض تجريبي', $followUp->patient_name);
        $this->assertSame(today()->toDateString(), $followUp->visit_date->toDateString());
    }

    public function test_follow_up_booking_references_the_original_visit_in_its_note(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->user)
            ->post("/clinic/{$booking->id}/refer", ['referral_to' => 'surgery', 'create_follow_up' => true])
            ->assertRedirect();

        $followUp = Booking::where('dept', 'surgery')->where('id', '!=', $booking->id)->sole();
        $this->assertStringContainsString($booking->file_no, $followUp->visit_note);
    }

    public function test_without_create_follow_up_no_new_booking_is_created(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->user)
            ->post("/clinic/{$booking->id}/refer", ['referral_to' => 'labs'])
            ->assertRedirect();

        $this->assertSame(1, Booking::count());
    }

    public function test_referring_to_the_same_current_department_is_rejected(): void
    {
        $booking = $this->makeBooking(['dept' => 'labs']);

        $this->actingAs($this->user)
            ->post("/clinic/{$booking->id}/refer", ['referral_to' => 'labs'])
            ->assertSessionHasErrors('referral_to');
    }

    public function test_unauthorized_user_cannot_refer(): void
    {
        $booking = $this->makeBooking();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post("/clinic/{$booking->id}/refer", ['referral_to' => 'labs'])
            ->assertForbidden();
    }

    public function test_invalid_referral_target_is_rejected(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->user)
            ->post("/clinic/{$booking->id}/refer", ['referral_to' => 'not-a-dept'])
            ->assertSessionHasErrors('referral_to');
    }
}
