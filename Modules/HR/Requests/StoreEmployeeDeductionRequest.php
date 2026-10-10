<?php

namespace Modules\HR\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\HR\Enums\DeductionType;

class StoreEmployeeDeductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'deduction_date' => ['required', 'date'],
            'type' => ['required', Rule::enum(DeductionType::class)],
            'days' => ['required_if:type,days', 'nullable', 'numeric', 'gt:0', 'max:31'],
            'amount' => ['required_if:type,amount', 'nullable', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
