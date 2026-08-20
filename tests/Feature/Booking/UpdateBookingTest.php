<?php

namespace Tests\Feature\Booking;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Enums\AccountGroup;
use Modules\Accounting\Enums\AccountNature;
use Modules\Accounting\Models\Account;
use Modules\Booking\Models\Booking;
use Modules\Surgery\Models\OrBed;
use Modules\Surgery\Models\OrRoom;
use Modules\Surgery\Models\Surgery;
use Modules\Surgery\States\ScheduledState;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UpdateBookingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $editPermission = Permission::firstOrCreate(['name' => 'booking.edit', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo($editPermission);
        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        Account::create([
            'code' => '1010', 'name' => 'الخزنة الرئيسية',
            'group' => AccountGroup::Assets, 'nature' => AccountNature::Debit,
            'balance' => 0, 'is_active' => true,
        ]);
        Account::create([
            'code' => '4010', 'name' => 'إيرادات العيادة الخارجية (كشف)',
            'group' => AccountGroup::Revenues, 'nature' => AccountNature::Credit,
            'balance' => 0, 'is_active' => true,
        ]);
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

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'patient_name' => 'محمد علي',
            'dept' => 'clinic',
            'visit_date' => '2026-04-20',
            'price' => 150,
            'discount' => 0,
            'ins_amount' => 0,
            'paid_amount' => 0,
            'pay_method' => 'cash',
            'pay_status' => 'unpaid',
            'status' => 'waiting',
        ], $overrides);
    }

    public function test_update_persists_status_change(): void
    {
        $booking = $this->createBooking(['status' => 'waiting']);

        $this->actingAs($this->user)
            ->put("/booking/{$booking->id}", $this->basePayload(['status' => 'confirmed']))
            ->assertRedirect();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_update_sets_pay_status_to_paid_when_paid_amount_equals_net_due(): void
    {
        $booking = $this->createBooking();

        $this->actingAs($this->user)
            ->put("/booking/{$booking->id}", $this->basePayload([
                'price' => 150,
                'discount' => 0,
                'ins_amount' => 0,
                'paid_amount' => 150,
                'pay_status' => 'unpaid',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'pay_status' => 'paid',
        ]);
    }

    public function test_update_sets_pay_status_to_unpaid_when_paid_amount_is_zero(): void
    {
        $booking = $this->createBooking(['paid_amount' => 100, 'pay_status' => 'partial']);

        $this->actingAs($this->user)
            ->put("/booking/{$booking->id}", $this->basePayload([
                'paid_amount' => 0,
                'pay_status' => 'partial',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'pay_status' => 'unpaid',
        ]);
    }

    public function test_update_sets_pay_status_to_partial_when_paid_amount_is_between_zero_and_net_due(): void
    {
        $booking = $this->createBooking();

        $this->actingAs($this->user)
            ->put("/booking/{$booking->id}", $this->basePayload([
                'price' => 150,
                'discount' => 0,
                'ins_amount' => 0,
                'paid_amount' => 75,
                'pay_status' => 'unpaid',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'pay_status' => 'partial',
        ]);
    }

    public function test_update_allows_resubmitting_bed_already_assigned_to_the_booking(): void
    {
        $room = OrRoom::create(['name' => 'Room 1']);
        $bed = OrBed::create(['room_id' => $room->id, 'bed_number' => 1]);
        $booking = $this->createBooking(['dept' => 'surgery']);

        Surgery::create([
            'booking_id' => $booking->id,
            'or_bed_id' => $bed->id,
            'dept' => 'surgery',
            'status' => ScheduledState::$name,
        ]);

        $this->actingAs($this->user)
            ->put("/booking/{$booking->id}", $this->basePayload([
                'dept' => 'surgery',
                'bed_id' => (string) $bed->id,
            ]))
            ->assertSessionDoesntHaveErrors('bed_id')
            ->assertRedirect();
    }

    public function test_update_is_blocked_when_booking_is_completed(): void
    {
        $booking = $this->createBooking(['status' => 'completed']);

        $this->actingAs($this->user)
            ->put("/booking/{$booking->id}", $this->basePayload(['patient_name' => 'اسم آخر']))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'patient_name' => 'محمد علي',
        ]);
    }
}
