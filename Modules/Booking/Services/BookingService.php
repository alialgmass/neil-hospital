<?php

namespace Modules\Booking\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Booking\DTOs\BookingData;
use Modules\Booking\DTOs\BookingFilterData;
use Modules\Booking\Models\Booking;
use Modules\Booking\Repositories\Contracts\BookingRepositoryInterface;

class BookingService
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly MrnGeneratorService $mrnGenerator,
    ) {}

    public function list(BookingFilterData $filter): LengthAwarePaginator
    {
        return $this->bookingRepository->paginate($filter);
    }

    public function findOrFail(string $id): Booking
    {
        return $this->bookingRepository->findOrFail($id);
    }

    public function create(BookingData $data, int $createdBy): Booking
    {
        $fileNo = $this->mrnGenerator->generate();

        return $this->bookingRepository->create([
            'file_no'        => $fileNo,
            'patient_name'   => $data->patientName,
            'patient_phone'  => $data->patientPhone,
            'patient_age'    => $data->patientAge,
            'national_id'    => $data->nationalId,
            'gender'         => $data->gender,
            'dept'           => $data->dept,
            'service_id'     => $data->serviceId,
            'service_name'   => $data->serviceName,
            'doctor_id'      => $data->doctorId,
            'ins_company_id' => $data->insCompanyId,
            'visit_date'     => $data->visitDate,
            'visit_time'     => $data->visitTime,
            'price'          => $data->price,
            'discount'       => $data->discount,
            'ins_amount'     => $data->insAmount,
            'paid_amount'    => $data->paidAmount,
            'pay_method'     => $data->payMethod,
            'pay_status'     => $data->payStatus,
            'status'         => $data->status,
            'visit_note'     => $data->visitNote,
            'created_by'     => $createdBy,
        ]);
    }

    public function update(string $id, BookingData $data): Booking
    {
        return $this->bookingRepository->update($id, [
            'patient_name'   => $data->patientName,
            'patient_phone'  => $data->patientPhone,
            'patient_age'    => $data->patientAge,
            'national_id'    => $data->nationalId,
            'gender'         => $data->gender,
            'dept'           => $data->dept,
            'service_id'     => $data->serviceId,
            'service_name'   => $data->serviceName,
            'doctor_id'      => $data->doctorId,
            'ins_company_id' => $data->insCompanyId,
            'visit_date'     => $data->visitDate,
            'visit_time'     => $data->visitTime,
            'price'          => $data->price,
            'discount'       => $data->discount,
            'ins_amount'     => $data->insAmount,
            'paid_amount'    => $data->paidAmount,
            'pay_method'     => $data->payMethod,
            'pay_status'     => $data->payStatus,
            'visit_note'     => $data->visitNote,
        ]);
    }
}
