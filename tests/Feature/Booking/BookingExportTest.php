<?php

namespace Tests\Feature\Booking;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\Booking;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'booking.view']);
        $role = Role::firstOrCreate(['name' => 'admin']);
        $role->givePermissionTo('booking.view');

        $this->user = User::factory()->create();
        $this->user->assignRole('admin');
    }

    public function test_export_route_returns_xlsx_download(): void
    {
        Booking::create([
            'file_no' => 'EXP-001',
            'patient_name' => 'مريض اختبار',
            'dept' => 'clinic',
            'visit_date' => '2026-01-15',
            'price' => 100,
            'paid_amount' => 0,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)->get('/booking/export');

        $response->assertStatus(200);
        $this->assertStringStartsWith(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type'),
        );
        $this->assertStringContainsString('reservations.xlsx', $response->headers->get('Content-Disposition'));
    }

    public function test_export_respects_filters(): void
    {
        Booking::create(['file_no' => 'F1', 'patient_name' => 'أ', 'dept' => 'clinic', 'visit_date' => now(), 'price' => 0, 'paid_amount' => 0, 'status' => 'completed']);
        Booking::create(['file_no' => 'F2', 'patient_name' => 'ب', 'dept' => 'labs', 'visit_date' => now(), 'price' => 0, 'paid_amount' => 0, 'status' => 'completed']);

        $response = $this->actingAs($this->user)->get('/booking/export?dept=clinic');

        $response->assertStatus(200);
    }

    public function test_export_is_blocked_without_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/booking/export')->assertForbidden();
    }
}
