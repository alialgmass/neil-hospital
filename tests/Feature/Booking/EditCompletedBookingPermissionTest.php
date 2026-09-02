<?php

namespace Tests\Feature\Booking;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Enums\AccountGroup;
use Modules\Accounting\Enums\AccountNature;
use Modules\Accounting\Models\Account;
use Modules\Admin\Models\ActivityLog;
use Modules\Booking\Models\Booking;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EditCompletedBookingPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

    private function userWithPermissions(array $permissions): User
    {
        $role = Role::firstOrCreate(['name' => 'reception_'.uniqid(), 'guard_name' => 'web']);
        $role->givePermissionTo(collect($permissions)->map(
            fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'])
        )->all());

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
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
            'status' => 'completed',
            'created_by' => null,
        ], $attributes));
    }

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'patient_name' => 'اسم محدث',
            'dept' => 'clinic',
            'visit_date' => '2026-04-20',
            'price' => 150,
            'discount' => 0,
            'ins_amount' => 0,
            'paid_amount' => 0,
            'pay_method' => 'cash',
            'pay_status' => 'unpaid',
            'status' => 'completed',
        ], $overrides);
    }

    public function test_user_without_edit_completed_permission_is_still_blocked(): void
    {
        $user = $this->userWithPermissions(['booking.edit']);
        $booking = $this->createBooking();

        $this->actingAs($user)
            ->put("/booking/{$booking->id}", $this->basePayload())
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'patient_name' => 'محمد علي',
        ]);
    }

    public function test_user_with_edit_completed_permission_can_update_a_completed_booking(): void
    {
        $user = $this->userWithPermissions(['booking.edit', 'booking.edit_completed']);
        $booking = $this->createBooking();

        $this->actingAs($user)
            ->put("/booking/{$booking->id}", $this->basePayload(['patient_name' => 'اسم محدث']))
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'patient_name' => 'اسم محدث',
        ]);
    }

    public function test_non_completed_bookings_remain_editable_regardless_of_new_permission(): void
    {
        $user = $this->userWithPermissions(['booking.edit']);
        $booking = $this->createBooking(['status' => 'waiting']);

        $this->actingAs($user)
            ->put("/booking/{$booking->id}", $this->basePayload(['status' => 'confirmed']))
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_editing_a_completed_booking_writes_an_activity_log_entry(): void
    {
        $user = $this->userWithPermissions(['booking.edit', 'booking.edit_completed']);
        $booking = $this->createBooking();

        $this->actingAs($user)
            ->put("/booking/{$booking->id}", $this->basePayload(['patient_name' => 'اسم محدث']))
            ->assertRedirect();

        $this->assertDatabaseHas((new ActivityLog)->getTable(), [
            'action' => 'edited_while_completed',
            'module' => 'booking',
            'record_id' => $booking->id,
        ]);
    }
}
