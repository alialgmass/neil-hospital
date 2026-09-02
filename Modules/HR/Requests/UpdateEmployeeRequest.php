<?php

namespace Modules\HR\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\HR\Enums\ContractType;
use Modules\HR\Enums\EmployeeStatus;
use Modules\HR\Models\Employee;

class UpdateEmployeeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'username' => [
                'nullable', 'string', 'min:3', 'max:100',
                Rule::unique('users', 'username')->ignore($this->linkedUserId(), 'id'),
            ],
            'dept' => ['required', 'string', 'max:50'],
            'position' => ['required', 'string', 'max:100'],
            'hire_date' => ['required', 'date'],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'allowances' => ['nullable', 'numeric', 'min:0'],
            'contract_type' => ['required', Rule::enum(ContractType::class)],
            'status' => ['required', Rule::enum(EmployeeStatus::class)],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * The id of the user account linked to the employee being updated, so the
     * unique-username rule can ignore that row.
     */
    private function linkedUserId(): ?string
    {
        return Employee::find($this->route('id'))?->user_id;
    }
}
