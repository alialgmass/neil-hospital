<?php

namespace Modules\Booking\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Booking\DTOs\BookingData;
use Modules\Booking\DTOs\BookingFilterData;
use Modules\Booking\Enums\PayStatus;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\InsuranceCompany;
use Modules\Booking\Models\Service;
use Modules\Booking\Repositories\Contracts\BookingRepositoryInterface;
use Modules\Booking\States\CancelledState;
use Modules\Booking\States\CompletedState;
use Modules\Doctor\Models\Doctor;
use Modules\Insurance\Models\PriceList;

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
            'insuranceCompanies' => InsuranceCompany::select('id', 'name')->orderBy('name')->get(),
            'priceLists' => PriceList::select('id', 'name', 'ins_company_id', 'ins_coverage')
                ->where('is_active', true)
                ->with('items:price_list_id,service_id,price')
                ->orderBy('name')
                ->get(),
            'doctors' => Doctor::select('id', 'name')->where('is_active', true)->orderBy('name')->get(),
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
        $fileNo = $this->mrnGenerator->generate();

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

        $totalPaid = (float) $data['amount_paid'];
        $netDue = $booking->price - ((float) ($data['discount'] ?? 0));

        $payStatus = $totalPaid >= $netDue
            ? PayStatus::Paid
            : ($totalPaid > 0 ? PayStatus::Partial : PayStatus::Unpaid);

        $booking->update([
            'pay_status' => $payStatus,
            'pay_method' => $data['pay_method'],
            'ins_amount' => $data['ins_amount'] ?? 0,
            'discount' => $data['discount'] ?? 0,
        ]);

        return $booking;
    }
}
