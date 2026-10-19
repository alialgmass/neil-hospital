<?php

namespace Tests\Feature\Reporting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Booking\Models\Booking;
use Modules\Doctor\Models\Doctor;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DoctorCasesAndReceptionReportTest extends TestCase
{
    use RefreshDatabase;

    private function userCan(string ...$permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }

        return $user;
    }

    private function booking(?Doctor $doctor, array $overrides = []): Booking
    {
        return Booking::create([
            'file_no' => 'MRN-'.uniqid(),
            'patient_name' => 'مريض '.uniqid(),
            'dept' => 'clinic',
            'doctor_id' => $doctor?->id,
            'visit_date' => today()->toDateString(),
            'price' => 1000,
            'discount' => 100,
            'ins_amount' => 0,
            'paid_amount' => 300,
            'pay_method' => 'cash',
            'pay_status' => 'partial',
            'status' => 'waiting',
            'created_by' => User::factory()->create()->id,
            ...$overrides,
        ]);
    }

    private function doctor(string $name): Doctor
    {
        return Doctor::create(['name' => $name, 'fee_type' => 'percentage', 'fee_value' => 10]);
    }

    public function test_doctor_cases_report_groups_cases_with_paid_and_remaining(): void
    {
        $doctorA = $this->doctor('د. أحمد');
        $doctorB = $this->doctor('د. بهاء');
        $this->booking($doctorA);
        $this->booking($doctorA, ['paid_amount' => 900, 'pay_status' => 'paid']);
        $this->booking($doctorB);
        $this->booking($doctorA, ['status' => 'cancelled']);

        $this->actingAs($this->userCan('reports.financial'))
            ->get('/reports/doctor-cases')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reports/DoctorCases')
                ->has('data.doctors', 2)
                ->where('data.doctors.0.doctor_name', 'د. أحمد')
                ->where('data.doctors.0.cases_count', 2)
                ->where('data.doctors.0.net_due', 1800)
                ->where('data.doctors.0.paid', 1200)
                ->where('data.doctors.0.remaining', 600)
            );
    }

    public function test_doctor_cases_report_can_be_limited_to_one_doctor(): void
    {
        $doctorA = $this->doctor('د. أحمد');
        $doctorB = $this->doctor('د. بهاء');
        $this->booking($doctorA);
        $this->booking($doctorB);

        $this->actingAs($this->userCan('reports.financial'))
            ->get('/reports/doctor-cases?doctor_id='.$doctorB->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('data.doctors', 1)
                ->where('data.doctors.0.doctor_name', 'د. بهاء')
            );
    }

    public function test_doctor_cases_report_requires_financial_permission(): void
    {
        $this->actingAs($this->userCan('booking.view'))
            ->get('/reports/doctor-cases')
            ->assertForbidden();
    }

    public function test_doctor_cases_export_downloads_excel(): void
    {
        $this->booking($this->doctor('د. أحمد'));

        $this->actingAs($this->userCan('reports.financial'))
            ->get('/reports/doctor-cases/export')
            ->assertOk();
    }

    public function test_reception_report_defaults_to_today_with_statistics(): void
    {
        $this->booking(null, ['status' => 'waiting', 'visit_time' => '10:00']);
        $this->booking(null, ['status' => 'completed', 'dept' => 'labs', 'visit_time' => '09:00']);
        $this->booking(null, ['visit_date' => today()->subDays(3)->toDateString()]);

        $this->actingAs($this->userCan('booking.view'))
            ->get('/reports/reception')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reports/Reception')
                ->where('data.stats.total', 2)
                ->where('data.stats.by_status.waiting', 1)
                ->where('data.stats.by_dept.labs', 1)
                ->where('data.rows.0.visit_time', '09:00')
            );
    }

    public function test_reception_report_supports_a_date_range_and_export(): void
    {
        $this->booking(null);
        $this->booking(null, ['visit_date' => today()->subDays(3)->toDateString()]);
        $user = $this->userCan('booking.view');
        $range = 'from='.today()->subDays(5)->toDateString().'&to='.today()->toDateString();

        $this->actingAs($user)
            ->get("/reports/reception?{$range}")
            ->assertInertia(fn (Assert $page) => $page->where('data.stats.total', 2));

        $this->actingAs($user)->get("/reports/reception/export?{$range}")->assertOk();
    }

    public function test_reception_report_requires_booking_view_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/reports/reception')
            ->assertForbidden();
    }
}
