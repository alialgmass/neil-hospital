<?php

namespace Modules\Clinic\Actions;

use App\Services\ActivityLogService;
use Illuminate\Validation\ValidationException;
use Modules\Booking\Actions\CreateBookingAction;
use Modules\Booking\DTOs\BookingData;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Booking\Services\ServicePricingService;
use Modules\Clinic\Models\ClinicSheet;
use Modules\Clinic\Repositories\Contracts\ClinicSheetRepositoryInterface;
use Modules\Surgery\Services\SurgeryService;

class ReferPatientAction
{
    public function __construct(
        private readonly ClinicSheetRepositoryInterface $clinicSheetRepository,
        private readonly CreateBookingAction $createBookingAction,
        private readonly ServicePricingService $servicePricing,
        private readonly SurgeryService $surgeryService,
        private readonly ActivityLogService $activityLog,
    ) {}

    /**
     * Set referral on the clinic sheet and optionally create a same-day
     * follow-up booking in the target department. The follow-up gets its
     * own file_no (file_no is unique per booking — it can't share the
     * original's) but its visit_note back-references the original visit,
     * and patient history is still discoverable via patientHistory()'s
     * name/phone match.
     *
     * When a service is chosen, the follow-up booking carries it along with
     * the eye side, and its price is derived server-side from the service's
     * one-eye / both-eyes prices — so an operation referral enters the normal
     * booking flow fully priced (and, for surgery/lasik, already scheduled
     * with the right eye by {@see CreateBookingAction}).
     *
     * @param  bool  $createFollowUp  Whether to auto-create a follow-up booking in target dept.
     * @param  string|null  $serviceId  Service of the target dept to book the follow-up against.
     * @param  string|null  $eyeSide  OD / OS / OU — drives both the price and the operation record.
     */
    public function execute(
        string $bookingId,
        string $referralTo,
        int $referringUserId,
        bool $createFollowUp = false,
        ?string $serviceId = null,
        ?string $eyeSide = null,
    ): void {
        $originalBooking = Booking::findOrFail($bookingId);

        if ($referralTo === $originalBooking->dept->value) {
            throw ValidationException::withMessages([
                'referral_to' => 'لا يمكن توجيه المريض إلى نفس القسم الحالي.',
            ]);
        }

        $sheet = $this->clinicSheetRepository->findByBooking($bookingId);

        $this->clinicSheetRepository->createOrUpdate($bookingId, [
            ...($sheet?->toArray() ?? []),
            'referral_to' => $referralTo,
        ]);

        if ($createFollowUp) {
            $service = $serviceId ? Service::find($serviceId) : null;
            $clinicalSummary = $this->buildClinicalSummary($sheet);

            // An insurance patient stays an insurance patient down the line:
            // the payer follows the referral so the operation is claimed
            // against the same company. Insurance requires a service, so a
            // service-less referral falls back to cash at reception.
            $isInsured = $service && $originalBooking->ins_company_id;

            $followUpData = BookingData::fromArray([
                'patient_name' => $originalBooking->patient_name,
                'patient_phone' => $originalBooking->patient_phone,
                'patient_age' => $originalBooking->patient_age,
                'national_id' => $originalBooking->national_id,
                'gender' => $originalBooking->gender,
                'kinship_degree' => $originalBooking->kinship_degree,
                'dept' => $referralTo,
                'doctor_id' => $originalBooking->doctor_id,
                'service_id' => $service?->id,
                'service_name' => $service?->name,
                'eye_side' => $eyeSide,
                'price' => $this->servicePricing->priceFor($serviceId, $eyeSide) ?? 0,
                'ins_company_id' => $isInsured ? $originalBooking->ins_company_id : null,
                'pay_method' => $isInsured ? 'insurance' : 'cash',
                // Same-day: the patient is being routed to the next stop of
                // *this* visit, not scheduled for a future date.
                'visit_date' => today()->toDateString(),
                'visit_time' => now()->format('H:i'),
                'pay_status' => 'unpaid',
                'status' => 'waiting',
                'visit_note' => trim("إحالة من العيادة — حجز #{$originalBooking->file_no} — {$clinicalSummary}", ' —'),
            ]);

            $followUp = $this->createBookingAction->execute($followUpData, $referringUserId);

            $this->carryClinicalSummaryToOperation($followUp->id, $clinicalSummary);
        }

        $this->activityLog->log(
            action: 'referred',
            module: 'clinic',
            recordId: $bookingId,
            description: "إحالة المريض إلى: {$referralTo}",
        );
    }

    /**
     * The findings the next department actually needs from the clinic visit:
     * diagnosis, both visual acuities, both intraocular pressures and the
     * treatment plan — the eye-exam data set the surgeon reads pre-op.
     */
    private function buildClinicalSummary(?ClinicSheet $sheet): string
    {
        if (! $sheet) {
            return '';
        }

        $parts = array_filter([
            $sheet->diagnosis ? "التشخيص: {$sheet->diagnosis}" : null,
            $sheet->visual_acuity_od || $sheet->visual_acuity_os
                ? 'الإبصار: OD '.($sheet->visual_acuity_od ?: '—').' / OS '.($sheet->visual_acuity_os ?: '—')
                : null,
            $sheet->iop_od !== null || $sheet->iop_os !== null
                ? 'ضغط العين: OD '.($sheet->iop_od ?? '—').' / OS '.($sheet->iop_os ?? '—')
                : null,
            $sheet->plan ? "الخطة: {$sheet->plan}" : null,
        ]);

        return mb_substr(implode(' | ', $parts), 0, 1800);
    }

    /**
     * A surgery/lasik referral is scheduled as an operation by
     * {@see CreateBookingAction}; seed its pre-op notes with the clinic
     * findings so the OR has them without reopening the clinic sheet.
     */
    private function carryClinicalSummaryToOperation(string $followUpBookingId, string $clinicalSummary): void
    {
        if ($clinicalSummary === '') {
            return;
        }

        $this->surgeryService->findByBooking($followUpBookingId)?->update([
            'pre_op_notes' => $clinicalSummary,
        ]);
    }
}
