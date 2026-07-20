<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\Booking;
use Modules\Doctor\Models\Doctor;
use Modules\Surgery\Models\Surgery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DoctorClaimsReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'reports.financial', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo('reports.financial');

        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    private function makeBooking(string $dept, string $doctorId, float $price, float $insAmount = 0.0, string $visitDate = '2026-06-20', string $payMethod = 'cash'): Booking
    {
        return Booking::create([
            'file_no' => 'MRN-'.strtoupper($dept).'-'.uniqid(),
            'patient_name' => 'مريض '.$dept,
            'dept' => $dept,
            'doctor_id' => $doctorId,
            'visit_date' => $visitDate,
            'price' => $price,
            'discount' => 0.00,
            'ins_amount' => $insAmount,
            'paid_amount' => $price - $insAmount,
            'pay_method' => $payMethod,
            'pay_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_surgery_claim_deducts_supply_cost_instead_of_using_fee_percentage(): void
    {
        $doctor = Doctor::create(['name' => 'د. جراح', 'fee_type' => 'percentage', 'fee_value' => 50]);
        $booking = $this->makeBooking('surgery', $doctor->id, 1000.00);

        Surgery::create([
            'booking_id' => $booking->id,
            'dept' => 'surgery',
            'status' => 'scheduled',
            'supply_total' => 300.00,
        ]);

        $response = $this->actingAs($this->user)->get('/reports/doctor-claims?from=2026-06-01&to=2026-06-30');

        $response->assertOk();
        // Correct: price(1000) - supply_total(300) = 700, NOT 1000 * 50% = 500.
        $response->assertInertia(fn ($page) => $page->where('data.rows.0.doctor_claim', 700));
    }

    public function test_dept_fee_override_takes_priority_over_default_fee_value(): void
    {
        $doctor = Doctor::create([
            'name' => 'د. عيادة',
            'fee_type' => 'percentage',
            'fee_value' => 50,
            'dept_fees' => ['clinic' => ['fee_type' => 'fixed', 'fee_value' => 40]],
        ]);
        $this->makeBooking('clinic', $doctor->id, 1000.00);

        $response = $this->actingAs($this->user)->get('/reports/doctor-claims?from=2026-06-01&to=2026-06-30');

        $response->assertOk();
        // Dept override is a flat 40, not 1000 * 50% = 500.
        $response->assertInertia(fn ($page) => $page->where('data.rows.0.doctor_claim', 40));
    }

    public function test_insurance_fee_type_doctor_has_zero_claim(): void
    {
        $doctor = Doctor::create(['name' => 'د. تأمين', 'fee_type' => 'insurance', 'fee_value' => 0]);
        $this->makeBooking('clinic', $doctor->id, 1000.00);

        $response = $this->actingAs($this->user)->get('/reports/doctor-claims?from=2026-06-01&to=2026-06-30');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('data.rows.0.doctor_claim', 0));
    }

    public function test_rows_are_sorted_by_most_recent_activity_first(): void
    {
        $older = Doctor::create(['name' => 'د. قديم', 'fee_type' => 'percentage', 'fee_value' => 10]);
        $newer = Doctor::create(['name' => 'د. حديث', 'fee_type' => 'percentage', 'fee_value' => 10]);

        $this->makeBooking('clinic', $older->id, 100.00, 0.0, '2026-06-05');
        $this->makeBooking('clinic', $newer->id, 100.00, 0.0, '2026-06-25');

        $response = $this->actingAs($this->user)->get('/reports/doctor-claims?from=2026-06-01&to=2026-06-30');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('data.rows.0.doctor_name', 'د. حديث')
            ->where('data.rows.1.doctor_name', 'د. قديم'));
    }
}
