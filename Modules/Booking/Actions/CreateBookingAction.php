<?php

namespace Modules\Booking\Actions;

use App\Enums\Department;
use App\Services\ActivityLogService;
use Modules\Accounting\Actions\AutoPostBookingPaymentAction;
use Modules\Accounting\Actions\AutoPostDevelopmentFeeAction;
use Modules\Booking\DTOs\BookingData;
use Modules\Booking\Enums\PayMethod;
use Modules\Booking\Enums\PayStatus;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Booking\Services\BookingService;
use Modules\Doctor\Actions\SyncDoctorEntitlementAction;
use Modules\Doctor\Models\Doctor;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\States\DraftState;
use Modules\Surgery\DTOs\SurgeryData;
use Modules\Surgery\Services\SurgeryService;

class CreateBookingAction
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly SurgeryService $surgeryService,
        private readonly AutoPostBookingPaymentAction $autoPost,
        private readonly AutoPostDevelopmentFeeAction $autoPostDevelopmentFee,
        private readonly ActivityLogService $activityLog,
        private readonly SyncDoctorEntitlementAction $syncDoctorEntitlement,
    ) {}

    public function execute(BookingData $data, int $createdBy): Booking
    {
        $booking = $this->bookingService->create($data, $createdBy);

        if ($data->insCompanyId) {
            $patientShare = max(0, $data->price - $data->discount - $data->insAmount);

            InsuranceClaim::create([
                'booking_id' => $booking->id,
                'insurance_company_id' => $data->insCompanyId,
                'service_id' => $data->serviceId,
                'patient_name' => $data->patientName,
                'file_no' => $booking->file_no,
                'service_name' => $data->serviceName ?? '',
                'invoice_amount' => $data->price,
                'discount' => $data->discount,
                'insurance_share' => $data->insAmount,
                'patient_share' => $patientShare,
                'approved_amount' => 0,
                'paid_amount' => 0,
                'status' => DraftState::class,
                'service_date' => $data->visitDate,
                'claim_date' => today()->toDateString(),
                'created_by' => $createdBy,
            ]);
        }

        if (in_array($data->dept, [Department::Surgery, Department::Lasik])) {
            $this->surgeryService->schedule(new SurgeryData(
                bookingId: $booking->id,
                dept: $data->dept,
                orBedId: $data->bedId,
                surgeonId: $data->doctorId,
                eye: $data->eyeSide,
                procedure: $data->serviceName,
            ));
        }

        // Automatic Accounting Entry
        if ($booking->pay_status === PayStatus::Paid) {
            $this->autoPost->execute($booking);
            $this->autoPostDevelopmentFee->execute($booking);
        }

        // Automatic doctor entitlement for insurance / contract deals
        $this->syncDoctorEntitlement->execute($booking);

        // Business rule: when the patient pays nothing on a cash booking,
        // the full service price (net of the dev-treasury fee) becomes a
        // debt owed by the doctor, settled out of their future dues.
        $this->recordDoctorDebtIfUnpaid($booking);

        $this->activityLog->log(
            action: 'created',
            module: 'booking',
            recordId: $booking->id,
            description: "حجز جديد: {$booking->patient_name} — {$booking->file_no}",
            newValues: $booking->toArray(),
        );

        return $booking;
    }

    /**
     * When a cash booking with a doctor is created with paid_amount = 0
     * (patient paid nothing), the service price — after the dev-treasury
     * fee deduction, same base as the doctor's fee computation — becomes a
     * debt on the doctor, to be deducted from their future dues.
     *
     * Insurance/contract bookings (pay_method) are excluded: those are
     * already settled through SyncDoctorEntitlementAction regardless of
     * patient payment. An insurance-fee-type doctor (fee_type) on a CASH
     * booking is treated exactly like a fixed-fee doctor — they earn and can
     * owe cash-booking debt the same as any other doctor; see
     * DoctorClaimsService::computeShareForPayment().
     */
    private function recordDoctorDebtIfUnpaid(Booking $booking): void
    {
        if ((float) $booking->paid_amount > 0
            || $booking->pay_method !== PayMethod::Cash
            || ! $booking->doctor_id) {
            return;
        }

        $doctor = Doctor::whereKey($booking->doctor_id)->first();

        if (! $doctor) {
            return;
        }

        $devFee = $booking->service_id
            ? (float) (Service::whereKey($booking->service_id)->value('dev_treasury_fee') ?? 0)
            : 0.0;

        $debt = max(0, (float) $booking->price - $devFee);

        if ($debt > 0) {
            $doctor->incurDebtForBooking($booking->id, $debt);
        }
    }
}
