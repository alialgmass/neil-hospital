<?php

namespace Tests\Feature\Surgery;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\Booking;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SurgeryIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        foreach (['surgery.view', 'lasik.view', 'laser.view'] as $perm) {
            $permission = Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
            $role->givePermissionTo($permission);
        }

        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    private function createBooking(string $dept, string $status = 'waiting'): Booking
    {
        static $counter = 0;
        $counter++;

        return Booking::create([
            'file_no' => "MRN-{$counter}",
            'patient_name' => 'مريض تجريبي',
            'dept' => $dept,
            'visit_date' => '2026-04-20',
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

    public function test_surgery_index_returns_only_surgery_dept_bookings(): void
    {
        $this->createBooking('surgery');
        $this->createBooking('lasik');

        $response = $this->actingAs($this->user)->get('/surgery');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('dept', 'surgery')
        );
    }

    public function test_lasik_index_returns_only_lasik_dept_bookings(): void
    {
        $this->createBooking('lasik');
        $this->createBooking('surgery');

        $response = $this->actingAs($this->user)->get('/lasik');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('dept', 'lasik')
        );
    }

    public function test_laser_index_returns_only_laser_dept_bookings(): void
    {
        $this->createBooking('laser');
        $this->createBooking('surgery');

        $response = $this->actingAs($this->user)->get('/laser');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('dept', 'laser')
        );
    }

    public function test_lasik_status_filter_stays_on_lasik_route(): void
    {
        $response = $this->actingAs($this->user)->get('/lasik?status=scheduled');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('dept', 'lasik')
        );
    }

    public function test_laser_index_returns_bookings_prop_with_laser_waiting_or_confirmed(): void
    {
        $this->createBooking('laser', 'waiting');
        $this->createBooking('laser', 'confirmed');
        $this->createBooking('laser', 'completed');
        $this->createBooking('surgery', 'waiting');

        $response = $this->actingAs($this->user)->get('/laser');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('bookings', 2)
        );
    }
}
