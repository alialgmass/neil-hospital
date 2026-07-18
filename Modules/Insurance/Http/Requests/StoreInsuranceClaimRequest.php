<?php

namespace Modules\Insurance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInsuranceClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('insurance.write') ?? false;
    }

    public function rules(): array
    {
        return [
            'insurance_company_id' => ['required', 'exists:insurance_companies,id'],
            'booking_id' => ['nullable', 'exists:bookings,id'],
            'service_id' => ['nullable', 'exists:services,id'],
            'patient_name' => ['required', 'string', 'max:150'],
            'file_no' => ['nullable', 'string', 'max:50'],
            'service_name' => ['required', 'string', 'max:200'],
            'invoice_amount' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'patient_share' => ['nullable', 'numeric', 'min:0'],
            'insurance_share' => ['nullable', 'numeric', 'min:0'],
            'service_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'insurance_company_id.required' => 'يجب اختيار شركة التأمين.',
            'patient_name.required' => 'اسم المريض مطلوب.',
            'service_name.required' => 'اسم الخدمة مطلوب.',
            'invoice_amount.required' => 'مبلغ الفاتورة مطلوب.',
            'service_date.required' => 'تاريخ الخدمة مطلوب.',
        ];
    }
}
