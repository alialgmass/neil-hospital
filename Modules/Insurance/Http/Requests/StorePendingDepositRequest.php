<?php

namespace Modules\Insurance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePendingDepositRequest extends FormRequest
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
            'booking_id' => [
                'required',
                'string',
                Rule::exists('bookings', 'id')->whereNotNull('ins_company_id'),
                Rule::unique('pending_deposits', 'booking_id'),
            ],
            'amount' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
