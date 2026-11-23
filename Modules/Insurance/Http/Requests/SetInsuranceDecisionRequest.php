<?php

namespace Modules\Insurance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Insurance\Enums\InsuranceDecision;

class SetInsuranceDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('insurance.collect') ?? false;
    }

    public function rules(): array
    {
        return ['decision' => ['required', 'string', 'in:'.implode(',', array_column(InsuranceDecision::cases(), 'value'))]];
    }
}
