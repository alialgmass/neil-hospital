<?php

namespace Modules\Insurance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Insurance\Enums\DepositDecision;

class DecidePendingDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('insurance.collect') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in([DepositDecision::Approved->value, DepositDecision::Rejected->value])],
        ];
    }
}
