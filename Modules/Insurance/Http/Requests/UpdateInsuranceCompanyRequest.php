<?php

namespace Modules\Insurance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Insurance\Enums\CompanyStatus;

class UpdateInsuranceCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('insurance.write') ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('insurance');

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:20', "unique:insurance_companies,code,{$id}"],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'contract_no' => ['nullable', 'string', 'max:50'],
            'coverage_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'disc_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:100'],
            'status' => ['nullable', 'in:'.implode(',', array_column(CompanyStatus::cases(), 'value'))],
            'notes' => ['nullable', 'string'],
        ];
    }
}
