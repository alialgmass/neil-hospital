<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admin\Models\Setting;
use Modules\Booking\Models\Booking;
use Modules\Booking\States\BookingStatus;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingStatusVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'booking.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'booking.edit', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'settings.manage', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo(['booking.view', 'booking.edit', 'settings.manage']);

        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    private function makeBooking(string $status): Booking
    {
        return Booking::create([
            'file_no' => 'MRN-'.strtoupper($status).'-'.uniqid(),
            'patient_name' => 'مريض '.$status,
            'dept' => 'clinic',
            'visit_date' => today()->toDateString(),
            'price' => 100.00,
            'discount' => 0.00,
            'ins_amount' => 0.00,
            'paid_amount' => 0.00,
            'pay_method' => 'cash',
            'pay_status' => 'unpaid',
            'status' => $status,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_all_statuses_are_visible_by_default(): void
    {
        foreach (BookingStatus::options() as $option) {
            $this->assertTrue(BookingStatus::isVisible($option['value']));
        }

        $this->assertEqualsCanonicalizing(
            ['waiting', 'confirmed', 'in_progress', 'completed', 'completed_electronic', 'cancelled'],
            BookingStatus::visibleStatusNames(),
        );
    }

    public function test_hidden_status_bookings_disappear_from_the_booking_list(): void
    {
        $this->makeBooking('waiting');
        $this->makeBooking('cancelled');
        $this->makeBooking('completed');

        Setting::setValue(BookingStatus::settingKey('cancelled'), 'false', 'booking_statuses');

        $response = $this->actingAs($this->user)->get('/booking');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('bookings.data', fn ($rows) => ! collect($rows)->pluck('status')->contains('cancelled')
                && collect($rows)->pluck('status')->contains('waiting')
                && collect($rows)->pluck('status')->contains('completed'))
        );
    }

    public function test_hidden_status_filter_returns_no_rows_even_when_filtered_explicitly(): void
    {
        $this->makeBooking('cancelled');

        Setting::setValue(BookingStatus::settingKey('cancelled'), 'false', 'booking_statuses');

        $response = $this->actingAs($this->user)->get('/booking?status=cancelled');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('bookings.data', fn ($rows) => count($rows) === 0));
    }

    public function test_transition_to_a_hidden_status_is_rejected(): void
    {
        $booking = $this->makeBooking('waiting');

        Setting::setValue(BookingStatus::settingKey('confirmed'), 'false', 'booking_statuses');

        $response = $this->actingAs($this->user)->patch("/booking/{$booking->id}/status", [
            'status' => 'confirmed',
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertSame('waiting', (string) $booking->fresh()->status);
    }

    public function test_transition_still_works_when_the_target_status_stays_visible(): void
    {
        $booking = $this->makeBooking('waiting');

        Setting::setValue(BookingStatus::settingKey('cancelled'), 'false', 'booking_statuses');

        $response = $this->actingAs($this->user)->patch("/booking/{$booking->id}/status", [
            'status' => 'confirmed',
        ]);

        $response->assertSessionDoesntHaveErrors('status');
        $this->assertSame('confirmed', (string) $booking->fresh()->status);
    }

    public function test_settings_page_exposes_booking_status_toggles(): void
    {
        $response = $this->actingAs($this->user)->get('/settings');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('bookingStatuses', 6)
            ->where('bookingStatuses.0.value', 'waiting')
            ->where('bookingStatuses.0.visible', true)
        );
    }

    public function test_settings_update_persists_booking_status_toggle(): void
    {
        $response = $this->actingAs($this->user)->post('/settings', [
            'settings' => [
                ['key' => BookingStatus::settingKey('cancelled'), 'value' => 'false'],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('settings', [
            'key' => BookingStatus::settingKey('cancelled'),
            'value' => 'false',
        ]);
        $this->assertFalse(BookingStatus::isVisible('cancelled'));
    }

    public function test_visibility_map_is_shared_via_inertia(): void
    {
        Setting::setValue(BookingStatus::settingKey('cancelled'), 'false', 'booking_statuses');

        $response = $this->actingAs($this->user)->get('/booking');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('bookingStatusVisibility.waiting', true)
            ->where('bookingStatusVisibility.cancelled', false)
        );
    }
}
