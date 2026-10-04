<?php

namespace Modules\Booking\Http\Requests;

use App\Enums\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_name' => ['required', 'string', 'max:150'],
            'patient_phone' => ['required', 'string', 'max:30'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'file_no' => ['nullable', 'string', 'max:20'],
            'dept' => ['required', Rule::enum(Department::class)],
            'service_id' => ['nullable', 'string', 'exists:services,id'],
            'doctor_id' => ['nullable', 'string', 'exists:doctors,id'],
            'preferred_date' => ['required', 'date', 'after_or_equal:today'],
            'preferred_time' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'patient_name' => 'اسم المريض',
            'patient_phone' => 'رقم الهاتف',
            'dept' => 'القسم',
            'preferred_date' => 'تاريخ الموعد',
            'preferred_time' => 'وقت الموعد',
        ];
    }
}
