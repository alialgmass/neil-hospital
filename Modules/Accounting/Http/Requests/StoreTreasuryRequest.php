<?php

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTreasuryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('treasury.write') ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:in,out'],
            'description' => ['required', 'string', 'max:300'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'reference_no' => ['nullable', 'string', 'max:50'],
            'beneficiary' => ['nullable', 'string', 'max:150'],
            'account_id' => ['nullable', 'exists:accounts,id'],
            'booking_id' => ['nullable', 'exists:bookings,id'],
        ];
    }
}
