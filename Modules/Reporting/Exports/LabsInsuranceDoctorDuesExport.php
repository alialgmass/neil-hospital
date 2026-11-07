<?php

namespace Modules\Reporting\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LabsInsuranceDoctorDuesExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly array $data) {}

    public function collection(): Collection
    {
        return collect($this->data['rows']);
    }

    public function headings(): array
    {
        return ['الطبيب', 'رقم الملف', 'المريض', 'تاريخ الزيارة', 'الخدمة', 'شركة التأمين', 'حالة المطالبة', 'مستحق الطبيب', 'الحالة'];
    }

    public function map($row): array
    {
        return [
            $row->doctor_name,
            $row->file_no,
            $row->patient_name,
            $row->visit_date,
            $row->service_name,
            $row->company_name,
            $row->claim_status,
            $row->doctor_due,
            $row->is_recognised ? 'مستحق (تمت التسوية)' : 'معلّق حتى تسوية المطالبة',
        ];
    }
}
