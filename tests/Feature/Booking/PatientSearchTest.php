<?php

namespace Tests\Feature\Booking;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\Booking;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PatientSearchTest extends TestCase
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

    private function makeBooking(string $fileNo, array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'file_no' => $fileNo,
            'patient_name' => 'أحمد علي',
            'national_id' => '29001010100001',
            'patient_phone' => '01000000001',
            'patient_age' => 40,
            'gender' => 'male',
            'dept' => 'clinic',
            'visit_date' => now()->toDateString(),
            'price' => 100,
            'paid_amount' => 100,
            'pay_method' => 'cash',
            'pay_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ], $overrides));
    }

    public function test_returns_empty_array_for_blank_query(): void
    {
        $response = $this->actingAs($this->user)->getJson('/booking/patients/search?q=');

        $response->assertOk()->assertJson([]);
    }

    public function test_matches_patients_by_name(): void
    {
        $this->makeBooking('MRN-1');

        $response = $this->actingAs($this->user)->getJson('/booking/patients/search?q='.urlencode('أحمد'));

        $response->assertOk();
        $response->assertJsonFragment(['patient_name' => 'أحمد علي', 'national_id' => '29001010100001']);
    }

    public function test_deduplicates_repeat_visits_by_national_id(): void
    {
        $this->makeBooking('MRN-1', ['created_at' => now()->subDay()]);
        $this->makeBooking('MRN-2', ['created_at' => now()]);

        $response = $this->actingAs($this->user)->getJson('/booking/patients/search?q='.urlencode('أحمد'));

        $response->assertOk();
        $this->assertCount(1, $response->json());
        $response->assertJsonFragment(['file_no' => 'MRN-2']);
    }
}
