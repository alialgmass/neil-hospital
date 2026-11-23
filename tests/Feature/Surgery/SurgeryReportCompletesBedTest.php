<?php

namespace Tests\Feature\Surgery;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\Booking;
use Modules\Surgery\Models\OrBed;
use Modules\Surgery\Models\OrRoom;
use Modules\Surgery\Models\Surgery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SurgeryReportCompletesBedTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private OrBed $bed;

    private Surgery $surgery;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['surgery.view', 'surgery.write'] as $perm) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']));
        }
        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $room = OrRoom::create(['name' => 'غرفة 1', 'status' => 'available', 'total_beds' => 1]);
        $this->bed = OrBed::create(['room_id' => $room->id, 'bed_number' => '1', 'status' => 'occupied']);

        $booking = Booking::create([
            'file_no' => 'MRN-1', 'patient_name' => 'مريض', 'dept' => 'surgery',
            'visit_date' => today()->toDateString(), 'price' => 100, 'discount' => 0,
            'ins_amount' => 0, 'paid_amount' => 100, 'pay_method' => 'cash', 'pay_status' => 'paid',
            'status' => 'confirmed', 'created_by' => $this->user->id,
        ]);

        $this->surgery = Surgery::create([
            'booking_id' => $booking->id,
            'or_bed_id' => $this->bed->id,
            'dept' => 'surgery',
            'status' => 'in_progress',
            'scheduled_at' => now(),
        ]);
    }

    public function test_recording_the_op_report_completes_the_surgery_without_crashing(): void
    {
        $response = $this->actingAs($this->user)->post("/surgery/{$this->surgery->id}/report", [
            'op_report' => 'تمت العملية بنجاح',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertSame('completed', $this->surgery->fresh()->status->getValue());
        $this->assertSame('available', $this->bed->fresh()->status);
    }

    public function test_a_case_completed_today_still_shows_on_the_beds_screen(): void
    {
        $this->actingAs($this->user)->post("/surgery/{$this->surgery->id}/report", [
            'op_report' => 'تمت العملية بنجاح',
        ]);

        $response = $this->actingAs($this->user)->get('/surgery');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('orRooms.0.beds.0.surgery.status', 'completed')
            ->where('orRooms.0.beds.0.surgery.id', $this->surgery->id));
    }
}
