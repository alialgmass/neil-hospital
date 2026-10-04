<?php

namespace Tests\Feature\Booking;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Accounting\Enums\AccountGroup;
use Modules\Accounting\Enums\AccountNature;
use Modules\Accounting\Models\Account;
use Modules\Booking\Enums\PreBookingStatus;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\CallLog;
use Modules\Booking\Models\PreBooking;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CallCenterTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private User $reception;

    protected function setUp(): void
    {
        parent::setUp();

        $agentRole = Role::firstOrCreate(['name' => 'call_center', 'guard_name' => 'web']);
        foreach (['callcenter.view', 'callcenter.write'] as $permission) {
            $agentRole->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
        $this->agent = User::factory()->create();
        $this->agent->assignRole($agentRole);

        $receptionRole = Role::firstOrCreate(['name' => 'reception', 'guard_name' => 'web']);
        foreach (['booking.view', 'booking.create'] as $permission) {
            $receptionRole->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
        $this->reception = User::factory()->create();
        $this->reception->assignRole($receptionRole);

        Account::create(['code' => '1010', 'name' => 'الخزنة الرئيسية', 'group' => AccountGroup::Assets, 'nature' => AccountNature::Debit, 'balance' => 0, 'is_active' => true]);
        Account::create(['code' => '4010', 'name' => 'إيرادات العيادة الخارجية (كشف)', 'group' => AccountGroup::Revenues, 'nature' => AccountNature::Credit, 'balance' => 0, 'is_active' => true]);
    }

    private function makeBooking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'file_no' => 'MRN-'.uniqid(), 'patient_name' => 'محمد علي', 'patient_phone' => '01000000001',
            'dept' => 'clinic', 'visit_date' => today()->addDay()->toDateString(), 'price' => 150,
            'paid_amount' => 0, 'pay_method' => 'cash', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ], $overrides));
    }

    private function preBookingPayload(array $overrides = []): array
    {
        return array_merge([
            'patient_name' => 'سارة محمود',
            'patient_phone' => '01012345678',
            'dept' => 'clinic',
            'preferred_date' => today()->addDays(2)->toDateString(),
            'preferred_time' => '10:30',
        ], $overrides);
    }

    public function test_agent_logs_a_call(): void
    {
        $this->actingAs($this->agent)
            ->post('/call-center/calls', [
                'direction' => 'incoming', 'phone' => '01012345678', 'caller_name' => 'سارة',
                'reason' => 'inquiry', 'outcome' => 'resolved', 'notes' => 'سألت عن مواعيد العيادة',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $call = CallLog::sole();
        $this->assertSame('01012345678', $call->phone);
        $this->assertSame($this->agent->id, $call->created_by);
    }

    public function test_call_rejects_invalid_enums(): void
    {
        $this->actingAs($this->agent)
            ->post('/call-center/calls', ['direction' => 'sideways', 'phone' => '010', 'reason' => 'x', 'outcome' => 'y'])
            ->assertSessionHasErrors(['direction', 'reason', 'outcome']);
    }

    public function test_follow_up_is_listed_until_a_resolving_call_is_logged(): void
    {
        $call = CallLog::create([
            'direction' => 'incoming', 'phone' => '01012345678', 'reason' => 'complaint', 'outcome' => 'call_back',
            'follow_up_at' => now()->subHour(), 'created_by' => $this->agent->id,
        ]);

        $this->actingAs($this->agent)->get('/call-center')
            ->assertInertia(fn (Assert $page) => $page->component('callcenter/Index')->has('followUps', 1));

        $this->post('/call-center/calls', [
            'direction' => 'outgoing', 'phone' => '01012345678', 'reason' => 'follow_up',
            'outcome' => 'resolved', 'resolves_call_id' => $call->id,
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($call->fresh()->follow_up_done_at);
        $this->get('/call-center')->assertInertia(fn (Assert $page) => $page->has('followUps', 0));
    }

    public function test_reminders_list_tomorrows_bookings_with_last_reminder_outcome(): void
    {
        $reminded = $this->makeBooking();
        $this->makeBooking(['patient_name' => 'مريض آخر']);
        $this->makeBooking(['status' => 'cancelled']);
        $this->makeBooking(['visit_date' => today()->addDays(3)->toDateString()]);

        $this->actingAs($this->agent)->post('/call-center/calls', [
            'direction' => 'outgoing', 'phone' => '01000000001', 'booking_id' => $reminded->id,
            'reason' => 'reminder', 'outcome' => 'confirmed',
        ])->assertSessionHasNoErrors();

        $this->assertSame($reminded->file_no, CallLog::sole()->file_no);

        $this->get('/call-center')
            ->assertInertia(fn (Assert $page) => $page
                ->has('reminders', 2)
                ->where('reminders', fn ($rows) => collect($rows)->firstWhere('id', $reminded->id)['last_reminder_outcome'] === 'confirmed')
            );
    }

    public function test_pre_booking_reaches_reception_and_is_converted_by_a_real_booking(): void
    {
        $this->actingAs($this->agent)
            ->post('/call-center/pre-bookings', $this->preBookingPayload())
            ->assertSessionHasNoErrors();

        $preBooking = PreBooking::sole();
        $this->assertSame(PreBookingStatus::Pending, $preBooking->status);

        $this->actingAs($this->reception)
            ->get('/booking')
            ->assertInertia(fn (Assert $page) => $page->has('preBookings', 1)->where('preBookings.0.id', $preBooking->id));

        $this->post('/booking', [
            'pre_booking_id' => $preBooking->id,
            'patient_name' => 'سارة محمود', 'patient_phone' => '01012345678', 'dept' => 'clinic', 'eye_side' => 'OD',
            'visit_date' => $preBooking->preferred_date->toDateString(), 'price' => 150, 'discount' => 0, 'ins_amount' => 0,
            'paid_amount' => 0, 'pay_method' => 'cash', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $preBooking->refresh();
        $this->assertSame(PreBookingStatus::Converted, $preBooking->status);
        $this->assertSame(Booking::sole()->id, $preBooking->booking_id);
        $this->assertSame($this->reception->id, $preBooking->handled_by);

        $this->get('/booking')->assertInertia(fn (Assert $page) => $page->has('preBookings', 0));
    }

    public function test_converting_an_already_converted_pre_booking_rolls_back_the_booking(): void
    {
        $preBooking = PreBooking::create([...$this->preBookingPayload(), 'status' => PreBookingStatus::Cancelled]);

        $this->actingAs($this->reception)->post('/booking', [
            'pre_booking_id' => $preBooking->id,
            'patient_name' => 'سارة محمود', 'dept' => 'clinic', 'eye_side' => 'OD',
            'visit_date' => today()->toDateString(), 'price' => 150, 'discount' => 0, 'ins_amount' => 0,
            'paid_amount' => 0, 'pay_method' => 'cash', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ])->assertSessionHasErrors('pre_booking_id');

        $this->assertSame(0, Booking::count());
    }

    public function test_agent_cancels_pending_pre_booking_only_once(): void
    {
        $preBooking = PreBooking::create([...$this->preBookingPayload(), 'status' => PreBookingStatus::Pending]);

        $this->actingAs($this->agent)
            ->patch("/call-center/pre-bookings/{$preBooking->id}/cancel")
            ->assertSessionHasNoErrors();

        $this->assertSame(PreBookingStatus::Cancelled, $preBooking->fresh()->status);

        $this->patch("/call-center/pre-bookings/{$preBooking->id}/cancel")
            ->assertSessionHasErrors('pre_booking');
    }

    public function test_pre_booking_validation(): void
    {
        $this->actingAs($this->agent)
            ->post('/call-center/pre-bookings', $this->preBookingPayload([
                'patient_name' => '', 'dept' => 'nowhere', 'preferred_date' => today()->subDay()->toDateString(),
            ]))
            ->assertSessionHasErrors(['patient_name', 'dept', 'preferred_date']);
    }

    public function test_lookup_finds_patient_by_phone_without_amounts(): void
    {
        $this->makeBooking(['patient_phone' => '01099998888', 'patient_name' => 'أحمد سمير']);

        $this->actingAs($this->agent)
            ->getJson('/call-center/lookup?q=0109999')
            ->assertOk()
            ->assertJsonPath('0.patient_name', 'أحمد سمير')
            ->assertJsonMissingPath('0.price');
    }

    public function test_call_center_is_closed_to_users_without_permission(): void
    {
        $this->actingAs($this->reception)->get('/call-center')->assertForbidden();
        $this->actingAs($this->reception)
            ->post('/call-center/pre-bookings', $this->preBookingPayload())
            ->assertForbidden();
    }
}
