<?php

namespace Modules\HR\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\HR\Enums\AttendanceStatus;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'shift_id' => ['nullable', 'exists:shifts,id'],
            'rows' => ['required', 'array'],
            'rows.*.employee_id' => ['required', 'exists:employees,id'],
            'rows.*.status' => ['nullable', Rule::enum(AttendanceStatus::class)],
            'rows.*.check_in' => ['nullable', 'string'],
            'rows.*.check_out' => ['nullable', 'string'],
            'rows.*.overtime_hours' => ['nullable', 'numeric', 'min:0'],
            'rows.*.notes' => ['nullable', 'string'],
        ];
    }
}
