<?php

namespace Modules\Insurance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'booking_id' => ['required', 'string', 'exists:bookings,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
