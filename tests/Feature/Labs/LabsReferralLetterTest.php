<?php

namespace Tests\Feature\Labs;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\Booking;
use Modules\Labs\Models\DiagnosticResult;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LabsReferralLetterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'labs.view']);
        Permission::firstOrCreate(['name' => 'labs.write']);
        $role = Role::firstOrCreate(['name' => 'admin']);
        $role->givePermissionTo(['labs.view', 'labs.write']);

        $this->user = User::factory()->create();
        $this->user->assignRole('admin');

        $this->booking = Booking::create([
            'file_no' => 'MRN-LABS-1',
            'patient_name' => 'مريض الفحوصات',
            'dept' => 'labs',
            'visit_date' => today()->toDateString(),
            'price' => 200.00,
            'discount' => 0.00,
            'ins_amount' => 0.00,
            'paid_amount' => 200.00,
            'pay_method' => 'cash',
            'pay_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_b_scan_result_can_be_recorded(): void
    {
        $response = $this->actingAs($this->user)->post("/labs/{$this->booking->id}/results", [
            'test_name' => 'B-Scan',
            'eye' => 'OS',
            'result_text' => 'Normal posterior segment.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('diagnostic_results', [
            'booking_id' => $this->booking->id,
            'test_name' => 'B-Scan',
            'eye' => 'OS',
        ]);
    }

    public function test_referral_letter_page_renders_with_result_data(): void
    {
        $result = DiagnosticResult::create([
            'booking_id' => $this->booking->id,
            'test_name' => 'B-Scan',
            'eye' => 'OS',
            'result_text' => 'Normal posterior segment.',
            'technician_id' => $this->user->id,
            'recorded_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->get("/labs/results/{$result->id}/letter");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('result.test_name', 'B-Scan')
            ->where('result.eye', 'OS')
            ->where('result.booking.patient_name', 'مريض الفحوصات')
        );
    }
}
