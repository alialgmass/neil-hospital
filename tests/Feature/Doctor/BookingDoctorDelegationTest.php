<?php

namespace Tests\Feature\Doctor;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\JournalEntry;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\InsuranceCompany;
use Modules\Booking\Models\Service;
use Modules\Doctor\Enums\DelegationStatus;
use Modules\Doctor\Models\BookingDoctorDelegation;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Models\DoctorEntitlement;
use Modules\Doctor\Services\DoctorClaimsService;
use Modules\Surgery\Models\OrBed;
use Modules\Surgery\Models\OrRoom;
use Modules\Surgery\Models\Surgery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingDoctorDelegationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Service $service;

    private Service $delegationService;

    private Doctor $primaryDoctor;

    private Doctor $delegateDoctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['booking.create', 'booking.pay', 'surgery.write', 'surgery.view'] as $perm) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']));
        }
        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->service = Service::create(['name' => 'إعتام عدسة', 'dept' => 'surgery', 'price' => 5000, 'ins_price' => 5000]);
        $this->delegationService = Service::create(['name' => 'مساعدة جراحية', 'dept' => 'surgery', 'price' => 0, 'ins_price' => 0]);

        $this->primaryDoctor = Doctor::create(['name' => 'د. أساسي', 'fee_type' => 'fixed', 'fee_value' => 1000]);
        $this->delegateDoctor = Doctor::create(['name' => 'د. مفوَّض', 'fee_type' => 'fixed', 'fee_value' => 0]);
    }

    private function bookAndSchedule(string $payMethod, float $price): Booking
    {
        $room = OrRoom::create(['name' => 'Room 1']);
        $bed = OrBed::create(['room_id' => $room->id, 'bed_number' => 1]);

        $insCompanyId = null;
        if ($payMethod === 'insurance') {
            $insCompanyId = InsuranceCompany::create(['name' => 'شركة تأمين', 'code' => 'INS1'])->id;
        }

        $this->actingAs($this->user)->post('/booking', [
            'patient_name' => 'مريض', 'dept' => 'surgery', 'eye_side' => 'OD', 'visit_date' => '2026-05-10',
            'service_id' => $this->service->id, 'service_name' => $this->service->name,
            'doctor_id' => $this->primaryDoctor->id, 'price' => $price, 'bed_id' => $bed->id,
            'ins_company_id' => $insCompanyId,
            'pay_method' => $payMethod, 'pay_status' => 'unpaid', 'status' => 'waiting',
        ])->assertRedirect();

        $booking = Booking::latest('id')->first();

        $this->actingAs($this->user)->post('/surgery', [
            'booking_id' => $booking->id,
            'dept' => 'surgery',
            'surgeon_id' => $this->primaryDoctor->id,
            'delegations' => [[
                'doctor_id' => $this->delegateDoctor->id,
                'role' => 'delegate',
                'service_id' => $this->delegationService->id,
                'service_name' => $this->delegationService->name,
                'amount' => 300,
            ]],
        ])->assertRedirect();

        return $booking->fresh();
    }

    public function test_scheduling_a_surgery_with_a_delegation_line_creates_a_pending_delegation_row(): void
    {
        $booking = $this->bookAndSchedule('cash', 5000);

        $this->assertDatabaseHas('booking_doctor_delegations', [
            'booking_id' => $booking->id,
            'doctor_id' => $this->delegateDoctor->id,
            'role' => 'delegate',
            'amount' => 300,
            'status' => DelegationStatus::Pending->value,
        ]);
    }

    public function test_cash_payment_deducts_the_delegated_amount_from_the_primary_doctor_and_pays_the_delegate(): void
    {
        $booking = $this->bookAndSchedule('cash', 5000);

        // The booking was created unpaid, so CreateBookingAction::recordDoctorDebtIfUnpaid()
        // already put the full 5000 on the primary doctor as debt.
        $this->assertEquals(5000.0, (float) $this->primaryDoctor->fresh()->doctor_debt_balance);

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'paid_amount' => 5000,
            'pay_method' => 'cash',
        ])->assertRedirect();

        // Surgery share (paid - supplies = 5000) minus the delegated amount
        // (300) = 4700, settled entirely out of the pre-existing 5000 debt —
        // leaving 300 of debt still outstanding, and no dues journal for the
        // primary doctor since none of it was actually payable this time.
        $this->assertEquals(300.0, (float) $this->primaryDoctor->fresh()->doctor_debt_balance);

        $delegation = BookingDoctorDelegation::where('booking_id', $booking->id)->firstOrFail();
        $this->assertEquals(DelegationStatus::Settled, $delegation->status);

        $this->assertTrue(
            JournalEntry::where('reference', $booking->fresh()->file_no)
                ->where('description', 'like', "%{$this->delegateDoctor->name}%")
                ->exists(),
        );
    }

    public function test_claims_report_nets_out_the_amount_that_was_diverted_to_settle_the_primary_doctors_debt(): void
    {
        $booking = $this->bookAndSchedule('cash', 5000);

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'paid_amount' => 5000,
            'pay_method' => 'cash',
        ])->assertRedirect();

        // The 4700 share (5000 surgery share − 300 delegated) was consumed
        // entirely settling the doctor's pre-existing 5000 debt (see the
        // cash-payment test above) — none of it is newly payable, so the
        // claims report must not still show it as "مستحق".
        $result = app(DoctorClaimsService::class)
            ->calculateClaims($this->primaryDoctor->id, '2026-01-01', '2026-12-31');

        $this->assertEquals(0.0, $result['total_claims']);
        $this->assertEquals(0.0, $result['rows'][0]['dr_share']);

        // The row still surfaces the gross share and how much of it went to
        // debt, so the doctor-dues screen can show *why* this case is 0.
        $this->assertEquals(4700.0, $result['rows'][0]['gross_dr_share']);
        $this->assertEquals(4700.0, $result['rows'][0]['debt_settled']);
    }

    public function test_insurance_booking_accrues_the_delegate_immediately_and_nets_the_primary_entitlement(): void
    {
        $this->primaryDoctor->services()->attach($this->service->id, ['fee' => 1000]);

        $booking = $this->bookAndSchedule('insurance', 5000);

        $entitlement = DoctorEntitlement::where('booking_id', $booking->id)->first();
        $this->assertNotNull($entitlement);
        $this->assertEquals(700.0, (float) $entitlement->amount);

        $delegation = BookingDoctorDelegation::where('booking_id', $booking->id)->firstOrFail();
        $this->assertEquals(DelegationStatus::Settled, $delegation->status);
    }

    public function test_claims_service_surfaces_the_delegated_doctors_dues_even_though_they_are_not_the_bookings_doctor(): void
    {
        $booking = $this->bookAndSchedule('cash', 5000);

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'paid_amount' => 5000,
            'pay_method' => 'cash',
        ])->assertRedirect();

        $result = app(DoctorClaimsService::class)
            ->calculateClaims($this->delegateDoctor->id, '2026-01-01', '2026-12-31');

        $this->assertEquals(300.0, $result['total_claims']);
        $this->assertCount(1, $result['rows']);
        $this->assertEquals('delegate', $result['rows'][0]['role']);
    }

    public function test_the_dedicated_delegations_endpoint_adds_a_line_without_touching_the_surgerys_own_fields(): void
    {
        $room = OrRoom::create(['name' => 'Room 1']);
        $bed = OrBed::create(['room_id' => $room->id, 'bed_number' => 1]);

        $this->actingAs($this->user)->post('/booking', [
            'patient_name' => 'مريض', 'dept' => 'surgery', 'eye_side' => 'OD', 'visit_date' => '2026-05-10',
            'service_id' => $this->service->id, 'service_name' => $this->service->name,
            'doctor_id' => $this->primaryDoctor->id, 'price' => 5000, 'bed_id' => $bed->id,
            'pay_method' => 'cash', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ])->assertRedirect();

        $booking = Booking::latest('id')->first();

        // Scheduled with a procedure name and no delegations at all yet.
        $this->actingAs($this->user)->post('/surgery', [
            'booking_id' => $booking->id,
            'dept' => 'surgery',
            'surgeon_id' => $this->primaryDoctor->id,
            'procedure' => 'استئصال المياه البيضاء',
        ])->assertRedirect();

        $surgery = Surgery::where('booking_id', $booking->id)->firstOrFail();
        $orBedIdBefore = $surgery->or_bed_id;

        // Now add a delegation purely through the dedicated case-overlay endpoint.
        $this->actingAs($this->user)->post("/surgery/{$surgery->id}/delegations", [
            'delegations' => [[
                'doctor_id' => $this->delegateDoctor->id,
                'role' => 'anesthesia',
                'service_id' => $this->delegationService->id,
                'service_name' => $this->delegationService->name,
                'amount' => 250,
            ]],
        ])->assertRedirect();

        $this->assertDatabaseHas('booking_doctor_delegations', [
            'booking_id' => $booking->id,
            'doctor_id' => $this->delegateDoctor->id,
            'role' => 'anesthesia',
            'amount' => 250,
        ]);

        // The surgery's own fields (procedure, bed, dept) must be untouched
        // by the dedicated delegations endpoint.
        $surgery->refresh();
        $this->assertEquals('استئصال المياه البيضاء', $surgery->procedure);
        $this->assertEquals($orBedIdBefore, $surgery->or_bed_id);
        $this->assertEquals('surgery', $surgery->dept->value);
    }
}
