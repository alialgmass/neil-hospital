<?php

namespace Tests\Feature\Doctor;

use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Enums\PayMethod;
use Modules\Booking\Enums\PayStatus;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Doctor\Enums\DelegationRole;
use Modules\Doctor\Enums\DelegationStatus;
use Modules\Doctor\Models\BookingDoctorDelegation;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Services\DoctorClaimsService;
use Modules\Surgery\Models\Surgery;
use Tests\TestCase;

/**
 * The case receipt has to make its own arithmetic add up.
 *
 * dr_share is already net of supplies AND delegated/anesthesia fees,
 * but only supplies used to be sent to the front end. The receipt therefore
 * showed "10,000 paid − 228 supplies = 8,915 gross", leaving an unexplained
 * 857 gap. delegated_total is now part of the row payload so the receipt can
 * show the deduction it is actually applying.
 */
class DoctorClaimsReceiptBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private Doctor $doctor;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);

        $service = Service::create([
            'name' => 'زرع عدسة', 'dept' => 'surgery', 'price' => 10000, 'dev_treasury_fee' => null,
        ]);

        $this->doctor = Doctor::create([
            'name' => 'د. faculties', 'fee_type' => 'fixed', 'fee_value' => 416, 'is_active' => true,
        ]);

        $this->booking = Booking::create([
            'file_no' => 'P-19-565',
            'patient_name' => 'مريض',
            'dept' => 'surgery',
            'doctor_id' => $this->doctor->id,
            'visit_date' => '2026-09-30',
            'service_id' => $service->id,
            'service_name' => $service->name,
            'eye_side' => 'OD',
            'price' => 10000,
            'discount' => 0,
            'ins_amount' => 0,
            'paid_amount' => 10000,
            'pay_method' => PayMethod::Cash,
            'pay_status' => PayStatus::Paid,
            'status' => 'completed',
        ]);

        Surgery::create([
            'booking_id' => $this->booking->id,
            'dept' => 'surgery',
            'supply_total' => 228,
            'supplies_used' => json_encode([
                ['name' => 'Compono admitto', 'qty' => 1, 'unit_cost' => 228, 'total' => 228],
            ]),
        ]);

        $delegate = Doctor::create(['name' => 'د. delegate', 'fee_type' => 'fixed', 'fee_value' => 0]);

        foreach ([[DelegationRole::Delegate, 77], [DelegationRole::Anesthesia, 780]] as [$role, $amount]) {
            BookingDoctorDelegation::create([
                'booking_id' => $this->booking->id,
                'doctor_id' => $delegate->id,
                'role' => $role,
                'service_name' => 'مساعدة',
                'amount' => $amount,
                'status' => DelegationStatus::Settled,
            ]);
        }
    }

    public function test_the_receipt_payload_carries_every_deduction_it_applies(): void
    {
        $row = collect(app(DoctorClaimsService::class)
            ->calculateClaims($this->doctor->id, '2026-01-01', '2026-12-31')['rows'])
            ->firstWhere('booking_id', $this->booking->id);

        $this->assertNotNull($row);
        $this->assertEquals(10000.0, $row['paid']);
        $this->assertEquals(228.0, $row['supply_total']);
        $this->assertEquals(857.0, $row['delegated_total']);

        // The gross the receipt headlines is net of BOTH deductions, so the
        // two lines it prints now have to reconcile against it exactly.
        $this->assertEquals(
            round($row['paid'] - $row['supply_total'] - $row['delegated_total'], 2),
            $row['dr_share']
        );
    }

    public function test_a_booking_without_delegations_reports_zero_not_a_missing_key(): void
    {
        $row = collect(app(DoctorClaimsService::class)
            ->calculateClaims($this->doctor->id, '2026-01-01', '2026-12-31')['rows'])
            ->firstWhere('booking_id', $this->booking->id);

        // A second case for the same doctor with no delegation lines at all.
        $service = Service::create(['name' => 'عملية بسيطة', 'dept' => 'surgery', 'price' => 5000]);
        $plain = Booking::create([
            'file_no' => 'P-20-565',
            'patient_name' => 'مريض آخر',
            'dept' => 'surgery',
            'doctor_id' => $this->doctor->id,
            'visit_date' => '2026-09-29',
            'service_id' => $service->id,
            'service_name' => $service->name,
            'eye_side' => 'OD',
            'price' => 5000,
            'discount' => 0,
            'ins_amount' => 0,
            'paid_amount' => 5000,
            'pay_method' => PayMethod::Cash,
            'pay_status' => PayStatus::Paid,
            'status' => 'completed',
        ]);

        $plainRow = collect(app(DoctorClaimsService::class)
            ->calculateClaims($this->doctor->id, '2026-01-01', '2026-12-31')['rows'])
            ->firstWhere('booking_id', $plain->id);

        $this->assertNotNull($plainRow);
        $this->assertSame(0.0, $plainRow['delegated_total']);
        $this->assertNotNull($row);
    }

    public function test_the_withheld_fee_is_broken_out_per_role_with_the_doctor_name(): void
    {
        $row = collect(app(DoctorClaimsService::class)
            ->calculateClaims($this->doctor->id, '2026-01-01', '2026-12-31')['rows'])
            ->firstWhere('booking_id', $this->booking->id);

        $lines = $row['delegation_lines'];

        $this->assertCount(2, $lines);
        $this->assertEquals(
            [
                ['role_label' => 'تفويض', 'amount' => 77.0],
                ['role_label' => 'تخدير', 'amount' => 780.0],
            ],
            array_map(fn (array $l): array => ['role_label' => $l['role_label'], 'amount' => $l['amount']], $lines)
        );

        // The parts must add up to the single figure the receipt deducts,
        // otherwise the detailed view would contradict the total.
        $this->assertEquals(
            $row['delegated_total'],
            round(array_sum(array_column($lines, 'amount')), 2)
        );
    }

    public function test_a_voided_delegation_is_excluded_from_the_breakdown(): void
    {
        BookingDoctorDelegation::where('booking_id', $this->booking->id)
            ->where('role', DelegationRole::Anesthesia)
            ->update(['status' => DelegationStatus::Void]);

        $row = collect(app(DoctorClaimsService::class)
            ->calculateClaims($this->doctor->id, '2026-01-01', '2026-12-31')['rows'])
            ->firstWhere('booking_id', $this->booking->id);

        $this->assertEquals(77.0, $row['delegated_total']);
        $this->assertCount(1, $row['delegation_lines']);
        $this->assertEquals('تفويض', $row['delegation_lines'][0]['role_label']);
    }

    public function test_delegation_rows_the_doctor_earns_report_no_withholding(): void
    {
        $delegate = Doctor::where('name', 'د. delegate')->sole();
        $rows = collect(app(DoctorClaimsService::class)
            ->calculateClaims($delegate->id, '2026-01-01', '2026-12-31')['rows']);

        // One row per delegation line, each already the delegate's own earning
        // at its declared amount — nothing is withheld from them, so the key
        // must be present and zero for the receipt template to read blindly.
        $this->assertCount(2, $rows);
        $rows->each(function (array $row): void {
            $this->assertSame(0.0, $row['delegated_total']);
            $this->assertArrayNotHasKey('debt_settled', $row);
        });

        $this->assertEqualsCanonicalizing(
            [77.0, 780.0],
            $rows->pluck('dr_share')->all()
        );
    }
}
