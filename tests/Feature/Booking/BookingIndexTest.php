<?php

namespace Tests\Feature\Booking;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\InsuranceCompany;
use Modules\Booking\Models\Service;
use Modules\Doctor\Models\Doctor;
use Modules\Surgery\Models\OrBed;
use Modules\Surgery\Models\OrRoom;
use Modules\Surgery\Models\Surgery;
use Modules\Surgery\States\ScheduledState;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingIndexTest extends TestCase
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

    public function test_booking_index_returns_services_in_props(): void
    {
        Service::create([
            'name' => 'فحص عام',
            'dept' => 'clinic',
            'price' => 150.00,
            'ins_price' => 100.00,
            'status' => 'active',
        ]);

        Service::create([
            'name' => 'خدمة معطلة',
            'dept' => 'clinic',
            'price' => 50.00,
            'ins_price' => 50.00,
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($this->user)->get('/booking');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('services', 1)
            ->where('services.0.name', 'فحص عام')
            ->where('services.0.dept', 'clinic')
        );
    }

    public function test_booking_index_returns_active_doctors_only(): void
    {
        Doctor::create(['name' => 'د. أحمد', 'is_active' => true, 'fee_type' => 'fixed', 'fee_value' => 0]);
        Doctor::create(['name' => 'د. غير نشط', 'is_active' => false, 'fee_type' => 'fixed', 'fee_value' => 0]);

        $response = $this->actingAs($this->user)->get('/booking');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('doctors', 1)
            ->where('doctors.0.name', 'د. أحمد')
        );
    }

    public function test_booking_index_returns_insurance_companies(): void
    {
        InsuranceCompany::create(['name' => 'التأمين الوطني']);

        $response = $this->actingAs($this->user)->get('/booking');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('insuranceCompanies', 1)
            ->where('insuranceCompanies.0.name', 'التأمين الوطني')
        );
    }

    public function test_booking_index_exposes_coverage_pct_for_client_side_insurance_fallback(): void
    {
        // Regression test: the booking form's insurance amount only auto-calculated
        // when a dedicated price list existed for the chosen company; otherwise it
        // silently stayed 0 (readonly) even though the company has a general
        // coverage_pct that should be used as a fallback.
        InsuranceCompany::create(['name' => 'التأمين الوطني', 'coverage_pct' => 80]);

        $response = $this->actingAs($this->user)->get('/booking');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('insuranceCompanies.0.coverage_pct', '80.00')
        );
    }

    public function test_booking_index_returns_or_rooms_in_props(): void
    {
        $response = $this->actingAs($this->user)->get('/booking');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('orRooms'));
    }

    public function test_booking_index_exposes_assigned_bed_via_surgery_relation(): void
    {
        $room = OrRoom::create(['name' => 'Room 1']);
        $bed = OrBed::create(['room_id' => $room->id, 'bed_number' => 1]);

        $booking = Booking::create([
            'file_no' => 'MRN-001',
            'patient_name' => 'محمد علي',
            'dept' => 'surgery',
            'visit_date' => '2026-04-20',
            'price' => 150.00,
            'discount' => 0.00,
            'ins_amount' => 0.00,
            'paid_amount' => 0.00,
            'pay_method' => 'cash',
            'pay_status' => 'unpaid',
            'status' => 'waiting',
            'created_by' => $this->user->id,
        ]);

        Surgery::create([
            'booking_id' => $booking->id,
            'or_bed_id' => $bed->id,
            'dept' => 'surgery',
            'status' => ScheduledState::$name,
        ]);

        $response = $this->actingAs($this->user)->get('/booking');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('bookings.data.0.surgery.or_bed_id', $bed->id)
        );
    }

    public function test_pagination_stays_stable_across_pages_when_many_rows_share_the_same_visit_date(): void
    {
        // Regression test: sorting by visit_date alone with no tiebreaker means
        // rows sharing a date have no deterministic order, so updating any one
        // of them (e.g. a status change) can shift its position between pages
        // and make it appear to vanish from the list even though it still
        // matches every filter. `created_at` breaks the tie deterministically.
        $bookingIds = [];
        foreach (range(1, 25) as $i) {
            $booking = Booking::create([
                'file_no' => 'MRN-TIE-'.$i,
                'patient_name' => 'مريض '.$i,
                'dept' => 'surgery',
                'visit_date' => '2026-06-20',
                'price' => 100.00,
                'discount' => 0.00,
                'ins_amount' => 0.00,
                'paid_amount' => 0.00,
                'pay_method' => 'cash',
                'pay_status' => 'unpaid',
                'status' => 'waiting',
                'created_by' => $this->user->id,
            ]);
            $booking->forceFill(['created_at' => now()->addSeconds($i)])->save();
            $bookingIds[] = $booking->id;
        }

        // Update the oldest (last-page) booking's status, as a completion would.
        $oldest = Booking::find($bookingIds[0]);
        $oldest->update(['status' => 'confirmed']);

        $page1 = $this->actingAs($this->user)->get('/booking?per_page=20&page=1');
        $page2 = $this->actingAs($this->user)->get('/booking?per_page=20&page=2');

        $page1Response = $page1->getOriginalContent()->getData()['page']['props']['bookings']['data'];
        $page2Response = $page2->getOriginalContent()->getData()['page']['props']['bookings']['data'];

        $allIds = collect($page1Response)->pluck('id')->merge(collect($page2Response)->pluck('id'));

        $this->assertEqualsCanonicalizing($bookingIds, $allIds->intersect($bookingIds)->values()->all());
        $this->assertContains($oldest->id, collect($page2Response)->pluck('id')->all());
    }
}
