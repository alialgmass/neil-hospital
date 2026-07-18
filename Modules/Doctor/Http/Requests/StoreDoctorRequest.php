<?php

namespace Modules\Doctor\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Doctor\Enums\FeeType;

class StoreDoctorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('doctors.write') ?? false;
    }

    public function rules(): array
    {
        $feeTypes = implode(',', array_column(FeeType::cases(), 'value'));

        return [
            'name' => ['required', 'string', 'max:150'],
            'specialty' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:100'],
            'fee_type' => ['required', "in:{$feeTypes}"],
            'fee_value' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'dept_fees' => ['nullable', 'array'],
            'dept_fees.*.fee_type' => ['required_with:dept_fees', "in:{$feeTypes}"],
            'dept_fees.*.fee_value' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
