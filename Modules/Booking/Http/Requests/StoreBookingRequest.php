<?php

namespace Modules\Booking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('booking.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'patient_name' => ['required', 'string', 'max:150'],
            'patient_phone' => ['nullable', 'string', 'max:20'],
            'patient_age' => ['nullable', 'integer', 'min:0', 'max:150'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', 'in:male,female'],
            'dept' => ['required', 'in:clinic,labs,surgery,lasik,laser'],
            'service_id' => ['nullable', 'exists:services,id'],
            'service_name' => ['nullable', 'string', 'max:200'],
            'doctor_id' => ['nullable', 'exists:doctors,id'],
            'ins_company_id' => ['nullable', 'exists:insurance_companies,id'],
            'visit_date' => ['required', 'date'],
            'visit_time' => ['nullable', 'date_format:H:i'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'ins_amount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'pay_method' => ['nullable', 'in:cash,card,transfer,insurance'],
            'pay_status' => ['nullable', 'in:unpaid,partial,paid'],
            'visit_note' => ['nullable', 'string', 'max:2000'],
            'bed_id' => ['nullable', 'integer', 'exists:or_beds,id'],
            'eye_side' => ['nullable', 'in:OD,OS,OU'],
            'analysis_type' => ['nullable', 'string', 'max:150'],
            'analysis_notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'patient_name.required' => 'اسم المريض مطلوب.',
            'dept.required' => 'القسم مطلوب.',
            'dept.in' => 'القسم غير صالح.',
            'visit_date.required' => 'تاريخ الزيارة مطلوب.',
            'pay_method.in' => 'طريقة الدفع غير صالحة.',
        ];
    }
}
