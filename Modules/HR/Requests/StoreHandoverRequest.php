<?php

namespace Modules\HR\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHandoverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shift_id' => ['required', 'exists:shifts,id'],
            'from_employee_id' => ['required', 'exists:employees,id'],
            'to_employee_id' => ['required', 'exists:employees,id', 'different:from_employee_id'],
            'handover_date' => ['required', 'date'],
            'handover_time' => ['nullable', 'date_format:H:i'],
            'cash_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
