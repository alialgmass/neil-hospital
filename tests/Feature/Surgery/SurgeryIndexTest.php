<?php

namespace Tests\Feature\Surgery;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Doctor\Enums\DelegationStatus;
use Modules\Doctor\Models\BookingDoctorDelegation;
use Modules\Doctor\Models\Doctor;
use Modules\Surgery\Models\OrBed;
use Modules\Surgery\Models\OrRoom;
use Modules\Surgery\Models\Surgery;
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

    public function test_surgery_index_ships_each_cases_saved_delegate_and_anesthesia_lines(): void
    {
        $booking = $this->createBooking('surgery');
        $service = Service::create(['name' => 'إعتام عدسة', 'dept' => 'surgery', 'price' => 5000, 'ins_price' => 5000]);
        $booking->update(['service_id' => $service->id, 'service_name' => $service->name]);

        $room = OrRoom::create(['name' => 'Room 1']);
        $bed = OrBed::create(['room_id' => $room->id, 'bed_number' => 1]);

        $surgery = Surgery::create([
            'booking_id' => $booking->id,
            'or_bed_id' => $bed->id,
            'dept' => 'surgery',
            'status' => 'scheduled',
            'scheduled_at' => now(),
        ]);

        $anesthetist = Doctor::create(['name' => 'د. تخدير', 'fee_type' => 'fixed', 'fee_value' => 0]);
        $delegate = Doctor::create(['name' => 'د. مفوَّض', 'fee_type' => 'fixed', 'fee_value' => 0]);

        foreach ([['delegate', $delegate, 300], ['anesthesia', $anesthetist, 200]] as [$role, $doctor, $amount]) {
            BookingDoctorDelegation::create([
                'booking_id' => $booking->id,
                'doctor_id' => $doctor->id,
                'role' => $role,
                'service_id' => $booking->service_id,
                'service_name' => $booking->service_name,
                'amount' => $amount,
                'status' => DelegationStatus::Pending,
            ]);
        }

        // The overlay form is seeded from surgery.booking.doctor_delegations, so
        // without this eager load the case silently reopens with an empty form
        // even though both lines are in the database.
        $response = $this->actingAs($this->user)->get('/surgery');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('orRooms.0.beds.0.surgery.id', $surgery->id)
            // The bed card renders the service name under the patient name.
            ->where('orRooms.0.beds.0.surgery.booking.service_name', 'إعتام عدسة')
            ->has('orRooms.0.beds.0.surgery.booking.doctor_delegations', 2)
            ->where('orRooms.0.beds.0.surgery.booking.doctor_delegations.0.role', 'delegate')
            ->where('orRooms.0.beds.0.surgery.booking.doctor_delegations.0.status', 'pending')
            ->where('orRooms.0.beds.0.surgery.booking.doctor_delegations.0.doctor.name', $delegate->name)
            ->where('orRooms.0.beds.0.surgery.booking.doctor_delegations.1.role', 'anesthesia')
            ->where('orRooms.0.beds.0.surgery.booking.doctor_delegations.1.doctor.name', $anesthetist->name)
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
