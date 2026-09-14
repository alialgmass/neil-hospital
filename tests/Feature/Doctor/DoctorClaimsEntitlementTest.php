<?php

namespace Tests\Feature\Doctor;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Services\ClaimCalculator;
use Modules\Doctor\Services\DoctorClaimsService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DoctorClaimsEntitlementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Service $service;

    private Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'booking.create', 'guard_name' => 'web']));
        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->service = Service::create(['name' => 'مياه بيضاء', 'dept' => 'clinic', 'price' => 5000, 'ins_price' => 5000]);
        $this->doctor = Doctor::create(['name' => 'د. عمر', 'fee_type' => 'fixed', 'fee_value' => 100]);
        $this->doctor->services()->attach($this->service->id, ['fee' => 750]);
    }

    private function book(string $payMethod, float $price): Booking
    {
        $this->actingAs($this->user)->post('/booking', [
            'patient_name' => 'مريض', 'dept' => 'clinic', 'visit_date' => '2026-05-10',
            'service_id' => $this->service->id, 'service_name' => $this->service->name,
            'doctor_id' => $this->doctor->id, 'price' => $price,
            'pay_method' => $payMethod, 'pay_status' => 'unpaid', 'status' => 'waiting',
        ])->assertRedirect();

        return Booking::latest('id')->first();
    }

    public function test_claims_service_counts_each_booking_once_across_computed_and_entitlement_sources(): void
    {
        $this->book('cash', 5000);       // computed: fixed fee = 100
        $this->book('insurance', 5000);  // entitlement: 750 (not the fixed 100)

        $result = app(DoctorClaimsService::class)
            ->calculateClaims($this->doctor->id, '2026-01-01', '2026-12-31');

        $this->assertEquals(850.0, $result['total_claims']);

        $shares = collect($result['rows'])->pluck('dr_share')->sort()->values()->all();
        $this->assertEquals([100.0, 750.0], $shares);
    }

    public function test_claim_calculator_uses_the_entitlement_amount_for_insurance_bookings(): void
    {
        $this->book('contract', 5000);

        // ClaimCalculator only counts confirmed/in_progress/completed bookings.
        Booking::latest('id')->first()->update(['status' => 'confirmed']);

        $calc = app(ClaimCalculator::class)->calculate(
            $this->doctor->fresh(),
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-12-31'),
        );

        $this->assertEquals(750.0, $calc['stats']['total_claim']);
    }
}
