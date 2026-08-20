<?php

namespace Modules\Booking\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Booking\DTOs\BookingData;
use Modules\Booking\DTOs\BookingFilterData;
use Modules\Booking\Enums\PayMethod;
use Modules\Booking\Enums\PayStatus;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\InsuranceCompany;
use Modules\Booking\Models\Service;
use Modules\Booking\Repositories\Contracts\BookingRepositoryInterface;
use Modules\Booking\States\CancelledState;
use Modules\Booking\States\CompletedState;
use Modules\Doctor\Models\Doctor;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\PriceList;
use Modules\Insurance\States\DraftState;

class BookingService
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly MrnGeneratorService $mrnGenerator,
    ) {}

    /** @return array{services: Collection, insuranceCompanies: Collection, priceLists: Collection, doctors: Collection} */
    public function getFormResources(): array
    {
        return [
            'services' => Service::select('id', 'name', 'dept', 'price', 'ins_price')->active()->orderBy('name')->get(),
            'insuranceCompanies' => InsuranceCompany::select('id', 'name', 'coverage_pct')->orderBy('name')->get(),
            'priceLists' => PriceList::select('id', 'name', 'ins_company_id', 'ins_coverage')
                ->where('is_active', true)
                ->with('items:price_list_id,service_id,price')
                ->orderBy('name')
                ->get(),
            'doctors' => Doctor::select('id', 'name', 'departments')->where('is_active', true)->orderBy('name')->get(),
        ];
    }

    public function list(BookingFilterData $filter): LengthAwarePaginator
    {
        return $this->bookingRepository->filterAndPaginate($filter);
    }

    public function findOrFail(string $id): Booking
    {
        return $this->bookingRepository->findOrFail($id);
    }

    public function create(BookingData $data, int $createdBy): Booking
    {
        $fileNo = $this->mrnGenerator->generate($data->nationalId);

        return $this->bookingRepository->create([
            'file_no' => $fileNo,
            'patient_name' => $data->patientName,
            'patient_phone' => $data->patientPhone,
            'patient_age' => $data->patientAge,
            'national_id' => $data->nationalId,
            'gender' => $data->gender,
            'dept' => $data->dept,
            'service_id' => $data->serviceId,
            'service_name' => $data->serviceName,
            'doctor_id' => $data->doctorId,
            'ins_company_id' => $data->insCompanyId,
            'visit_date' => $data->visitDate,
            'visit_time' => $data->visitTime,
            'price' => $data->price,
            'discount' => $data->discount,
            'ins_amount' => $data->insAmount,
            'paid_amount' => $data->paidAmount,
            'pay_method' => $data->payMethod,
            'pay_status' => $data->payStatus,
            'status' => $data->status,
            'visit_note' => $data->visitNote,
            'eye_side' => $data->eyeSide,
            'analysis_type' => $data->analysisType,
            'analysis_notes' => $data->analysisNotes,
            'created_by' => $createdBy,
        ]);
    }

    public function update(string $id, BookingData $data): Booking
    {
        $netDue = max(0.0, $data->price - $data->discount - $data->insAmount);
        $payStatus = $data->paidAmount >= $netDue
            ? PayStatus::Paid
            : ($data->paidAmount > 0 ? PayStatus::Partial : PayStatus::Unpaid);

        return $this->bookingRepository->update($id, [
            'patient_name' => $data->patientName,
            'patient_phone' => $data->patientPhone,
            'patient_age' => $data->patientAge,
            'national_id' => $data->nationalId,
            'gender' => $data->gender,
            'dept' => $data->dept,
            'service_id' => $data->serviceId,
            'service_name' => $data->serviceName,
            'doctor_id' => $data->doctorId,
            'ins_company_id' => $data->insCompanyId,
            'visit_date' => $data->visitDate,
            'visit_time' => $data->visitTime,
            'price' => $data->price,
            'discount' => $data->discount,
            'ins_amount' => $data->insAmount,
            'paid_amount' => $data->paidAmount,
            'pay_method' => $data->payMethod,
            'pay_status' => $payStatus,
            'status' => $data->status,
            'visit_note' => $data->visitNote,
            'eye_side' => $data->eyeSide,
            'analysis_type' => $data->analysisType,
            'analysis_notes' => $data->analysisNotes,
        ]);
    }

    public function getArchive(array $filters = [], int $perPage = 30): LengthAwarePaginator
    {
        return Booking::query()
            ->with('doctor:id,name')
            ->whereIn('status', [CompletedState::$name, CancelledState::$name])
            ->when($filters['search'] ?? null, function ($q, $v) {
                $q->where(function ($iq) use ($v) {
                    $iq->where('patient_name', 'like', "%{$v}%")
                        ->orWhere('file_no', 'like', "%{$v}%");
                });
            })
            ->when($filters['dept'] ?? null, fn ($q, $v) => $q->where('dept', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('visit_date', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('visit_date', '<=', $v))
            ->orderByDesc('visit_date')
            ->paginate($perPage);
    }

    public function getPatientFile(string $fileNo): Collection
    {
        return Booking::query()
            ->with(['doctor', 'clinicSheet', 'diagnosticResults'])
            ->where('file_no', $fileNo)
            ->orderByDesc('visit_date')
            ->get();
    }

    public function recordPayment(string $id, array $data): Booking
    {
        $booking = Booking::findOrFail($id);

        $isInsurance = $data['pay_method'] === PayMethod::Insurance->value;
        $insAmount = $isInsurance ? (float) ($data['ins_amount'] ?? 0) : 0;
        $discount = (float) ($data['discount'] ?? 0);

        $totalPaid = (float) $data['amount_paid'];
        $netDue = max(0.0, $booking->price - $discount - $insAmount);

        $payStatus = $totalPaid >= $netDue
            ? PayStatus::Paid
            : ($totalPaid > 0 ? PayStatus::Partial : PayStatus::Unpaid);

        $booking->update([
            'paid_amount' => $totalPaid,
            'pay_status' => $payStatus,
            'pay_method' => $data['pay_method'],
            'ins_company_id' => $isInsurance ? ($data['ins_company_id'] ?? null) : null,
            'ins_amount' => $insAmount,
            'discount' => $discount,
        ]);

        if ($isInsurance && ! empty($data['ins_company_id'])) {
            $this->upsertInsuranceClaim($booking, $data, $insAmount, $discount);
        }

        return $booking;
    }

    private function upsertInsuranceClaim(Booking $booking, array $data, float $insAmount, float $discount): void
    {
        $patientShare = max(0, $booking->price - $discount - $insAmount);

        $claim = InsuranceClaim::firstOrNew(['booking_id' => $booking->id]);

        $claim->fill([
            'insurance_company_id' => $data['ins_company_id'],
            'service_id' => $booking->service_id,
            'patient_name' => $booking->patient_name,
            'file_no' => $booking->file_no,
            'service_name' => $booking->service_name ?? '',
            'invoice_amount' => $booking->price,
            'discount' => $discount,
            'insurance_share' => $insAmount,
            'patient_share' => $patientShare,
            'service_date' => $booking->visit_date,
        ]);

        if (! $claim->exists) {
            $claim->claim_date = today()->toDateString();
            $claim->status = DraftState::class;
            $claim->created_by = $data['created_by'] ?? null;
        }

        $claim->save();
    }
}
