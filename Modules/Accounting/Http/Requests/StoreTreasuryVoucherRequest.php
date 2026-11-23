<?php

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTreasuryVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('treasury.write') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:in,out'],
            'beneficiary' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:300'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'reference_no' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'beneficiary.required' => 'اسم الجهة مطلوب في الإيصال.',
        ];
    }
}
