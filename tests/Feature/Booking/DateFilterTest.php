<?php

namespace Tests\Feature\Booking;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\Booking;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DateFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $permission = Permission::firstOrCreate(['name' => 'booking.view', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    private function createBooking(string $visitDate): Booking
    {
        static $counter = 0;
        $counter++;

        return Booking::create([
            'file_no' => "MRN-{$counter}",
            'patient_name' => 'مريض تجريبي',
            'dept' => 'clinic',
            'visit_date' => $visitDate,
            'price' => 100.00,
            'discount' => 0.00,
            'ins_amount' => 0.00,
            'paid_amount' => 0.00,
            'pay_method' => 'cash',
            'pay_status' => 'unpaid',
            'status' => 'waiting',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_date_from_and_date_to_are_echoed_in_filters_prop(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/booking?date_from=2026-04-01&date_to=2026-04-15');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('filters.date_from', '2026-04-01')
            ->where('filters.date_to', '2026-04-15')
        );
    }

    public function test_single_date_param_is_echoed_in_filters(): void
    {
        $response = $this->actingAs($this->user)->get('/booking?date=2026-04-10');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('filters.date', '2026-04-10')
        );
    }

    public function test_date_range_scopes_results(): void
    {
        $this->createBooking('2026-04-05');
        $this->createBooking('2026-04-10');
        $this->createBooking('2026-04-20');

        $response = $this->actingAs($this->user)
            ->get('/booking?date_from=2026-04-01&date_to=2026-04-15');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('bookings.total', 2)
        );
    }
}
