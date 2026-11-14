<?php

namespace Tests\Feature\Doctor;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Models\DoctorPayment;
use Modules\Doctor\Services\DoctorClaimsService;
use Tests\TestCase;

/**
 * The claims report's case list and payment history should show the most
 * recent entries first.
 */
class DoctorClaimsOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_claim_rows_are_ordered_newest_visit_first(): void
    {
        $service = Service::create(['name' => 'كشف عام', 'dept' => 'clinic', 'price' => 100, 'ins_price' => 100]);
        $doctor = Doctor::create(['name' => 'د. ترتيب', 'fee_type' => 'fixed', 'fee_value' => 50]);

        Booking::create([
            'file_no' => 'MRN-ORD-1', 'patient_name' => 'قديم', 'dept' => 'clinic',
            'doctor_id' => $doctor->id, 'visit_date' => '2026-01-01',
            'service_id' => $service->id, 'service_name' => $service->name,
            'price' => 100, 'paid_amount' => 100, 'pay_method' => 'cash', 'pay_status' => 'paid',
            'status' => 'completed',
        ]);
        Booking::create([
            'file_no' => 'MRN-ORD-2', 'patient_name' => 'حديث', 'dept' => 'clinic',
            'doctor_id' => $doctor->id, 'visit_date' => '2026-06-01',
            'service_id' => $service->id, 'service_name' => $service->name,
            'price' => 100, 'paid_amount' => 100, 'pay_method' => 'cash', 'pay_status' => 'paid',
            'status' => 'completed',
        ]);

        $result = app(DoctorClaimsService::class)
            ->calculateClaims($doctor->id, '2026-01-01', '2026-12-31');

        $this->assertSame(['MRN-ORD-2', 'MRN-ORD-1'], collect($result['rows'])->pluck('file_no')->all());
    }

    public function test_payment_history_is_ordered_newest_first(): void
    {
        $doctor = Doctor::create(['name' => 'د. دفعات', 'fee_type' => 'fixed', 'fee_value' => 50]);

        DoctorPayment::create(['doctor_id' => $doctor->id, 'amount' => 100, 'paid_at' => '2026-01-01', 'method' => 'cash']);
        DoctorPayment::create(['doctor_id' => $doctor->id, 'amount' => 200, 'paid_at' => '2026-06-01', 'method' => 'cash']);

        $result = app(DoctorClaimsService::class)
            ->calculateClaims($doctor->id, '2026-01-01', '2026-12-31');

        $this->assertSame([200.0, 100.0], collect($result['payments'])->pluck('amount')->all());
    }
}
