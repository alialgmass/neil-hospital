<?php

namespace Tests\Feature\Booking;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\InsuranceCompany;
use Modules\Booking\Models\Service;
use Modules\Doctor\Models\Doctor;
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

    public function test_booking_index_returns_or_rooms_in_props(): void
    {
        $response = $this->actingAs($this->user)->get('/booking');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('orRooms'));
    }
}
