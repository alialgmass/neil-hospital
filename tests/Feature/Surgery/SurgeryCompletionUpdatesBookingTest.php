<?php

namespace Tests\Feature\Surgery;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Enums\AccountGroup;
use Modules\Accounting\Enums\AccountNature;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Booking\Models\Booking;
use Modules\Surgery\Actions\UpdateSurgeryStatusAction;
use Modules\Surgery\Models\OrBed;
use Modules\Surgery\Models\OrRoom;
use Modules\Surgery\Models\Surgery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SurgeryCompletionUpdatesBookingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['surgery.view', 'surgery.write', 'booking.view', 'booking.edit'] as $perm) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']));
        }
        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        Account::create([
            'code' => '1010', 'name' => 'الخزنة الرئيسية',
            'group' => AccountGroup::Assets, 'nature' => AccountNature::Debit,
            'balance' => 0, 'is_active' => true,
        ]);
        Account::create([
            'code' => '4030', 'name' => 'إيرادات العمليات',
            'group' => AccountGroup::Revenues, 'nature' => AccountNature::Credit,
            'balance' => 0, 'is_active' => true,
        ]);
    }

    private function makeBookingWithSurgery(array $bookingAttrs = [], string $surgeryStatus = 'in_progress'): Surgery
    {
        $room = OrRoom::create(['name' => 'غرفة 1', 'status' => 'available', 'total_beds' => 1]);
        $bed = OrBed::create(['room_id' => $room->id, 'bed_number' => '1', 'status' => 'occupied']);

        $booking = Booking::create(array_merge([
            'file_no' => 'MRN-1', 'patient_name' => 'مريض', 'dept' => 'surgery',
            'visit_date' => today()->toDateString(), 'price' => 100, 'discount' => 0,
            'ins_amount' => 0, 'paid_amount' => 100, 'pay_method' => 'cash', 'pay_status' => 'paid',
            'status' => 'confirmed', 'created_by' => $this->user->id,
        ], $bookingAttrs));

        return Surgery::create([
            'booking_id' => $booking->id,
            'or_bed_id' => $bed->id,
            'dept' => 'surgery',
            'status' => $surgeryStatus,
            'scheduled_at' => now(),
        ]);
    }

    public function test_completing_a_surgery_transitions_its_booking_to_completed_electronic(): void
    {
        $surgery = $this->makeBookingWithSurgery();

        app(UpdateSurgeryStatusAction::class)->execute($surgery->id, 'completed');

        $this->assertSame('completed_electronic', $surgery->booking->fresh()->status->getValue());
    }

    public function test_completing_a_surgery_posts_accounting_when_booking_is_paid(): void
    {
        $surgery = $this->makeBookingWithSurgery(['pay_status' => 'paid', 'paid_amount' => 100, 'price' => 100]);

        app(UpdateSurgeryStatusAction::class)->execute($surgery->id, 'completed');

        $this->assertDatabaseHas((new JournalEntry)->getTable(), [
            'reference' => $surgery->booking->file_no,
        ]);
    }

    public function test_booking_no_longer_appears_in_default_booking_listing_after_surgery_completion(): void
    {
        $surgery = $this->makeBookingWithSurgery();

        app(UpdateSurgeryStatusAction::class)->execute($surgery->id, 'completed');

        $response = $this->actingAs($this->user)->get('/booking');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where(
            'bookings.data',
            fn ($bookings) => collect($bookings)->doesntContain(fn ($b) => $b['id'] === $surgery->booking_id)
        ));
    }

    public function test_booking_appears_when_explicitly_filtering_by_completed_electronic_status(): void
    {
        $surgery = $this->makeBookingWithSurgery();

        app(UpdateSurgeryStatusAction::class)->execute($surgery->id, 'completed');

        $response = $this->actingAs($this->user)->get('/booking?status=completed_electronic');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where(
            'bookings.data',
            fn ($bookings) => collect($bookings)->contains(fn ($b) => $b['id'] === $surgery->booking_id)
        ));
    }

    public function test_completing_a_surgery_whose_booking_is_already_cancelled_does_not_throw(): void
    {
        $surgery = $this->makeBookingWithSurgery(['status' => 'cancelled']);

        app(UpdateSurgeryStatusAction::class)->execute($surgery->id, 'completed');

        $this->assertSame('cancelled', $surgery->booking->fresh()->status->getValue());
    }

    public function test_re_running_the_completion_action_is_idempotent(): void
    {
        $surgery = $this->makeBookingWithSurgery();

        app(UpdateSurgeryStatusAction::class)->execute($surgery->id, 'completed');
        app(UpdateSurgeryStatusAction::class)->execute($surgery->id, 'completed');

        $this->assertSame('completed_electronic', $surgery->booking->fresh()->status->getValue());
    }

    public function test_manual_status_endpoint_rejects_completed_electronic_as_input(): void
    {
        $booking = Booking::create([
            'file_no' => 'MRN-2', 'patient_name' => 'مريض', 'dept' => 'clinic',
            'visit_date' => today()->toDateString(), 'price' => 100, 'discount' => 0,
            'ins_amount' => 0, 'paid_amount' => 0, 'pay_method' => 'cash', 'pay_status' => 'unpaid',
            'status' => 'waiting', 'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->patch("/booking/{$booking->id}/status", ['status' => 'completed_electronic'])
            ->assertSessionHasErrors('status');

        $this->assertSame('waiting', $booking->fresh()->status->getValue());
    }
}
