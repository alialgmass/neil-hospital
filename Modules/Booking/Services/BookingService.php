<?php

namespace Modules\Booking\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Booking\DTOs\BookingData;
use Modules\Booking\DTOs\BookingFilterData;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\InsuranceCompany;
use Modules\Booking\Models\Service;
use Modules\Booking\Repositories\Contracts\BookingRepositoryInterface;

class BookingService
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly MrnGeneratorService $mrnGenerator,
    ) {}

    /** @return array{services: Collection, insuranceCompanies: Collection} */
    public function getFormResources(): array
    {
        return [
            'services' => Service::select('id', 'name', 'dept', 'price', 'ins_price')->orderBy('name')->get(),
            'insuranceCompanies' => InsuranceCompany::select('id', 'name')->orderBy('name')->get(),
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
            'bed_id' => $data->bedId,
            'eye_side' => $data->eyeSide,
            'analysis_type' => $data->analysisType,
            'analysis_notes' => $data->analysisNotes,
            'created_by' => $createdBy,
        ]);
    }

    public function update(string $id, BookingData $data): Booking
    {
        $netDue = max(0.0, $data->price - $data->discount - $data->insAmount);
        $payStatus = $data->paidAmount >= $netDue ? 'paid' : ($data->paidAmount > 0 ? 'partial' : 'unpaid');

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
            'bed_no' => $data->bedNo,
            'eye_side' => $data->eyeSide,
            'analysis_type' => $data->analysisType,
            'analysis_notes' => $data->analysisNotes,
        ]);
    }
}
