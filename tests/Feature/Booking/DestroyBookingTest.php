<?php

namespace Tests\Feature\Booking;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\Booking;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DestroyBookingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $permission = Permission::firstOrCreate(['name' => 'booking.delete', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    private function createBooking(array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'file_no' => 'MRN-001',
            'patient_name' => 'محمد علي',
            'dept' => 'clinic',
            'visit_date' => '2026-04-20',
            'price' => 150.00,
            'discount' => 0.00,
            'ins_amount' => 0.00,
            'paid_amount' => 0.00,
            'pay_method' => 'cash',
            'pay_status' => 'unpaid',
            'status' => 'waiting',
            'created_by' => $this->user->id,
        ], $attributes));
    }

    public function test_destroy_deletes_a_non_completed_booking(): void
    {
        $booking = $this->createBooking(['status' => 'waiting']);

        $this->actingAs($this->user)
            ->delete("/booking/{$booking->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    }

    public function test_destroy_is_blocked_when_booking_is_completed(): void
    {
        $booking = $this->createBooking(['status' => 'completed']);

        $this->actingAs($this->user)
            ->delete("/booking/{$booking->id}")
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
    }
}
