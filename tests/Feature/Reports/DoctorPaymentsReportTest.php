<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Models\DoctorPayment;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DoctorPaymentsReportTest extends TestCase
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

    public function test_report_exposes_payment_method_and_notes(): void
    {
        $doctor = Doctor::create(['name' => 'د. تجريبي', 'fee_type' => 'percentage', 'fee_value' => 40]);

        DoctorPayment::create([
            'doctor_id' => $doctor->id,
            'amount' => 500,
            'period_from' => '2026-06-01',
            'period_to' => '2026-06-30',
            'paid_at' => '2026-06-20',
            'method' => 'transfer',
            'notes' => 'دفعة جزئية',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get('/reports/doctor-payments?from=2026-06-01&to=2026-06-30');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('data.rows.0.method', 'transfer')
            ->where('data.rows.0.notes', 'دفعة جزئية')
            ->where('data.rows.0.doctor_id', $doctor->id));
    }

    public function test_export_route_returns_a_downloadable_spreadsheet(): void
    {
        $doctor = Doctor::create(['name' => 'د. تجريبي', 'fee_type' => 'percentage', 'fee_value' => 40]);

        DoctorPayment::create([
            'doctor_id' => $doctor->id,
            'amount' => 500,
            'period_from' => '2026-06-01',
            'period_to' => '2026-06-30',
            'paid_at' => '2026-06-20',
            'method' => 'cash',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get('/reports/doctor-payments/export?from=2026-06-01&to=2026-06-30');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
