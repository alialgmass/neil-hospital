<?php

namespace Modules\HR\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\HR\Enums\ContractType;
use Modules\HR\Enums\EmployeeStatus;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'base_salary' => $this->base_salary ?: 0,
            'allowances' => $this->allowances ?: 0,
        ]);
    }

    public function rules(): array
    {
        return [
            'employee_no' => ['nullable', 'string', 'max:20', 'unique:employees,employee_no'],
            'name' => ['required', 'string', 'max:150'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100', 'unique:users,email'],
            'username' => ['required_with:password', 'nullable', 'string', 'min:3', 'max:100', 'unique:users,username'],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['nullable', 'exists:roles,name'],
            'dept' => ['required', 'string', 'max:50'],
            'position' => ['required', 'string', 'max:100'],
            'hire_date' => ['required', 'date'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'allowances' => ['required', 'numeric', 'min:0'],
            'contract_type' => ['required', Rule::enum(ContractType::class)],
            'status' => ['required', Rule::enum(EmployeeStatus::class)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
