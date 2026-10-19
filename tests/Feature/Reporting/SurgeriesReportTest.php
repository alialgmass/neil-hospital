<?php

namespace Tests\Feature\Reporting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Booking\Models\Booking;
use Modules\Surgery\Models\Surgery;
use Modules\Surgery\States\ScheduledState;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SurgeriesReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'reports.clinical', 'guard_name' => 'web']));
    }

    private function makeSurgery(string $visitDate, ?string $scheduledAt): Surgery
    {
        $booking = Booking::create([
            'file_no' => 'MRN-'.uniqid(),
            'patient_name' => 'مريض '.uniqid(),
            'dept' => 'surgery',
            'visit_date' => $visitDate,
            'price' => 100,
            'paid_amount' => 100,
            'pay_method' => 'cash',
            'pay_status' => 'paid',
            'status' => 'waiting',
            'created_by' => $this->user->id,
        ]);

        return Surgery::create([
            'booking_id' => $booking->id,
            'dept' => 'surgery',
            'status' => ScheduledState::$name,
            'scheduled_at' => $scheduledAt,
        ]);
    }

    public function test_unscheduled_surgery_is_listed_by_booking_visit_date(): void
    {
        $this->makeSurgery(today()->toDateString(), null);
        $this->makeSurgery(today()->subYear()->toDateString(), null);

        $this->actingAs($this->user)
            ->get('/reports/surgeries?from='.today()->subDay()->toDateString().'&to='.today()->toDateString())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reports/SurgeriesReport')
                ->where('data.total_count', 1)
            );
    }

    public function test_scheduled_surgery_is_filtered_by_scheduled_date(): void
    {
        $this->makeSurgery(today()->subMonths(3)->toDateString(), today()->toDateTimeString());

        $this->actingAs($this->user)
            ->get('/reports/surgeries?from='.today()->toDateString().'&to='.today()->toDateString())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('data.total_count', 1));
    }
}
