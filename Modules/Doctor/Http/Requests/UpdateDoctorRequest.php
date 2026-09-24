<?php

namespace Modules\Doctor\Http\Requests;

use App\Enums\Department;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Doctor\Enums\FeeType;

class UpdateDoctorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('doctors.write') ?? false;
    }

    public function rules(): array
    {
        $feeTypes = implode(',', array_column(FeeType::cases(), 'value'));
        $depts = implode(',', array_column(Department::cases(), 'value'));

        return [
            'name' => ['required', 'string', 'max:150'],
            'specialty' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:100'],
            'fee_type' => ['required', "in:{$feeTypes}"],
            'fee_value' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_anesthesiologist' => ['nullable', 'boolean'],
            'dept_fees' => ['nullable', 'array'],
            'dept_fees.*.fee_type' => ['required_with:dept_fees', "in:{$feeTypes}"],
            'dept_fees.*.fee_value' => ['nullable', 'numeric', 'min:0'],
            'departments' => ['nullable', 'array'],
            'departments.*' => ['string', "in:{$depts}"],
            'services' => ['nullable', 'array'],
            'services.*.service_id' => ['required_with:services', 'exists:services,id'],
            'services.*.fee' => ['required_with:services', 'numeric', 'min:0'],
            'delegation_services' => ['nullable', 'array'],
            'delegation_services.*.service_id' => ['required_with:delegation_services', 'exists:services,id'],
            'delegation_services.*.fee' => ['required_with:delegation_services', 'numeric', 'min:0'],
        ];
    }
}
